<?php

namespace App\Services;

use App\Models\RiskBlacklist;
use App\Models\RiskEvent;
use App\Models\RiskRule;
use Illuminate\Support\Facades\Log;

/**
 * 风控引擎
 *
 * 提供能力：
 *   1. 黑名单快速检查
 *   2. 规则引擎（按 type 加载规则、按条件匹配）
 *   3. 实时计数（窗口期内某维度出现次数）
 *   4. 风控事件记录与告警
 *
 * 使用：
 *   $result = $risk->check('register', [
 *       'user_id' => null,
 *       'ip' => $ip,
 *       'mobile' => $mobile,
 *   ]);
 *   if ($result['action'] === 'deny') {
 *       abort(429, $result['message']);
 *   }
 *
 * 缓存策略：
 *   - 规则列表走 risk:rule 命名空间（TTL 60s）
 *   - 窗口计数走 risk:count 命名空间（TTL = window/10）
 *   - 通过 CacheService 统一走 Redis，失败自动降级
 */
class RiskControlService
{
    /**
     * 检查某动作是否可放行
     */
    public function check(string $type, array $context): array
    {
        // 1) 黑名单预检
        $blacklist = $this->checkBlacklist($context);
        if ($blacklist) {
            return [
                'action' => 'deny',
                'risk_level' => 'critical',
                'message' => '操作被拒绝：触发黑名单',
                'reason' => "blacklist:{$blacklist['type']}",
            ];
        }

        // 2) 加载该类型的所有启用的规则
        $rules = $this->loadRules($type);
        if ($rules->isEmpty()) {
            return $this->allow('no_rules');
        }

        // 3) 按优先级逐条匹配
        foreach ($rules as $rule) {
            $hit = $this->matchRule($rule, $context);
            if ($hit) {
                $this->recordEvent($rule, $type, $context, $hit);
                return [
                    'action' => $rule->action,
                    'risk_level' => $hit['risk_level'],
                    'message' => $this->actionMessage($rule, $hit),
                    'reason' => "rule:{$rule->code}",
                ];
            }
        }

        return $this->allow('pass');
    }

    /**
     * 显式加入黑名单
     */
    public function block(string $type, string $value, string $reason = '', ?int $createdBy = null, ?int $ttlSeconds = null): RiskBlacklist
    {
        return RiskBlacklist::updateOrCreate(
            ['type' => $type, 'value' => $value],
            [
                'reason' => $reason,
                'created_by' => $createdBy,
                'expires_at' => $ttlSeconds ? now()->addSeconds($ttlSeconds) : null,
            ]
        );
    }

    /**
     * 解禁
     */
    public function unblock(string $type, string $value): bool
    {
        return RiskBlacklist::where('type', $type)->where('value', $value)->delete() > 0;
    }

    // ============ 内部方法 ============

    /**
     * 黑名单预检
     */
    protected function checkBlacklist(array $context): ?array
    {
        $checks = [
            'ip'     => $context['ip'] ?? null,
            'mobile' => $context['mobile'] ?? null,
            'device' => $context['device_id'] ?? null,
        ];

        foreach ($checks as $type => $value) {
            if (!$value) continue;
            $exists = RiskBlacklist::where('type', $type)
                ->where('value', $value)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();
            if ($exists) {
                return ['type' => $type, 'reason' => $exists->reason];
            }
        }
        return null;
    }

    /**
     * 加载规则
     */
    protected function loadRules(string $type)
    {
        return CacheService::namespace('risk:rule')->remember("rules:{$type}", function () use ($type) {
            return RiskRule::where('type', $type)
                ->where('enabled', true)
                ->orderBy('priority')
                ->get();
        });
    }

    /**
     * 匹配单条规则
     *
     * 支持的条件类型：
     *   - dimension: ip / mobile / user_id
     *   - window: 时间窗口（秒）
     *   - max_count: 窗口期内最大允许次数
     *   - max_amount: 最大允许金额（仅 payment/withdraw）
     */
    protected function matchRule(RiskRule $rule, array $context): ?array
    {
        $conds = $rule->conditions;
        $dimension = $conds['dimension'] ?? 'ip';
        $window = (int) ($conds['window'] ?? 3600);
        $maxCount = (int) ($conds['max_count'] ?? 5);

        $value = $context[$dimension] ?? null;
        if (!$value) return null;

        $count = $this->countInWindow($rule->type, $dimension, $value, $window);

        if ($count >= $maxCount) {
            $this->clearCache($rule->type, $dimension, $value, $window);
            return [
                'risk_level' => $count >= $maxCount * 2 ? 'critical' : 'high',
                'count' => $count,
                'window' => $window,
                'dimension' => $dimension,
                'value' => $value,
            ];
        }
        return null;
    }

    /**
     * 统计窗口期内某维度出现次数（基于 risk_events 表）
     */
    protected function countInWindow(string $type, string $dimension, string $value, int $window): int
    {
        $countCache = CacheService::namespace('risk:count');
        $cacheKey = "{$type}:{$dimension}:{$value}:{$window}";

        $count = $countCache->get($cacheKey, null);
        if ($count !== null) {
            return (int) $count;
        }

        $since = now()->subSeconds($window);
        $count = RiskEvent::where('type', $type)
            ->where('created_at', '>=', $since)
            ->where("context->{$dimension}", $value)
            ->count();

        // 缓存时长：窗口的 1/10，至少 60s
        $countCache->put($cacheKey, $count, max(60, intdiv($window, 10)));

        return (int) $count;
    }

    protected function clearCache(string $type, string $dimension, string $value, int $window): void
    {
        // 命中后清缓存，强制下一请求重新读 DB
        CacheService::namespace('risk:count')->forget("{$type}:{$dimension}:{$value}:{$window}");
    }

    /**
     * 记录风控事件
     */
    protected function recordEvent(RiskRule $rule, string $type, array $context, array $hit): void
    {
        try {
            RiskEvent::create([
                'user_id'    => $context['user_id'] ?? null,
                'rule_code'  => $rule->code,
                'type'       => $type,
                'action'     => $rule->action,
                'risk_level' => $hit['risk_level'],
                'context'    => $context,
                'ip'         => $context['ip'] ?? null,
                'note'       => "count={$hit['count']}/{$hit['window']}s",
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('风控事件记录失败', ['error' => $e->getMessage()]);
        }
    }

    protected function allow(string $reason): array
    {
        return [
            'action' => 'allow',
            'risk_level' => 'low',
            'message' => 'OK',
            'reason' => $reason,
        ];
    }

    protected function actionMessage(RiskRule $rule, array $hit): string
    {
        return match ($rule->action) {
            'deny'   => "操作被拒绝（{$rule->name}）",
            'review' => "操作已提交审核（{$rule->name}）",
            default  => 'OK',
        };
    }

    /**
     * 重新加载规则缓存
     */
    public function flushRulesCache(): void
    {
        CacheService::namespace('risk:rule')->flush();
        CacheService::namespace('risk:count')->flush();
    }
}
