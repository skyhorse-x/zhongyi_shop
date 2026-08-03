<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 统一缓存服务
 *
 * 设计目标：
 *   1. 集中所有缓存 key 命名空间，避免 key 散落
 *   2. 提供按 namespace 一键清空
 *   3. 统一走 file cache（项目不使用 Redis）
 *   4. 统一 TTL 管理
 *   5. 缓存击穿保护（单飞锁）
 *
 * 命名空间约定：
 *   - sys:config:*      系统配置
 *   - risk:rule:*       风控规则
 *   - risk:count:*      风控计数
 *   - llm:config:*      LLM 配置
 *   - user:profile:*    用户档案
 *   - invite:code:*     邀请码
 *   - analytics:*       分析指标
 *   - rate:*            限速计数
 *   - sms:*             短信验证码 / 发送频率
 *   - stats:*           访问量统计（按天）
 */
class CacheService
{
    /** @var string 默认命名空间 */
    protected string $namespace = 'app';

    /** @var array 命名空间 TTL 配置（秒） */
    public const TTL = [
        'sys:config'      => 600,   // 10 分钟
        'risk:rule'       => 60,    // 1 分钟
        'risk:count'      => 600,   // 10 分钟
        'llm:config'      => 300,   // 5 分钟
        'user:profile'    => 1800,  // 30 分钟
        'invite:code'     => 3600,  // 1 小时
        'analytics'       => 300,   // 5 分钟
        'rate'            => 60,    // 1 分钟
        'sms'             => 300,   // 5 分钟
        'stats'           => 86400, // 1 天（按天 key 实际只用到当天）
    ];

    public function __construct(string $namespace = 'app')
    {
        $this->namespace = $namespace;
    }

    /**
     * 静态工厂：按命名空间获取服务
     */
    public static function namespace(string $ns): self
    {
        return new self($ns);
    }

    /**
     * 记住缓存值（带回退）
     */
    public function remember(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        $fullKey = $this->fullKey($key);
        $ttl = $ttl ?? $this->guessTtl($key);

        try {
            return Cache::remember($fullKey, $ttl, $callback);
        } catch (\Throwable $e) {
            // 缓存异常时直接回退到回调
            Log::warning('Cache::remember failed, fallback to direct callback', [
                'key' => $fullKey,
                'error' => $e->getMessage(),
            ]);
            return $callback();
        }
    }

    /**
     * 单飞缓存（防击穿）：同一 key 同时只查一次
     */
    public function rememberSingleFlight(string $key, int $lockTtl, \Closure $callback, ?int $valueTtl = null): mixed
    {
        $fullKey = $this->fullKey($key);

        try {
            $lockKey = "{$fullKey}:lock";
            // 尝试加锁
            $lock = Cache::lock($lockKey, $lockTtl);

            return Cache::lock($lockKey, $lockTtl)->block($lockTtl, function () use ($fullKey, $valueTtl, $callback) {
                $cached = Cache::get($fullKey);
                if ($cached !== null) return $cached;
                $value = $callback();
                Cache::put($fullKey, $value, $valueTtl ?? $this->guessTtl($fullKey));
                return $value;
            });
        } catch (\Throwable $e) {
            Log::warning('SingleFlight cache failed, fallback', ['key' => $fullKey, 'error' => $e->getMessage()]);
            return $callback();
        }
    }

    /**
     * 获取（带 try/catch 降级，失败返回 default）
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($this->fullKey($key), $default);
        } catch (\Throwable $e) {
            Log::warning('Cache::get failed, return default', [
                'key' => $this->fullKey($key),
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

    /**
     * 设置（带 try/catch 降级，失败静默跳过）
     */
    public function put(string $key, mixed $value, ?int $ttl = null): void
    {
        try {
            Cache::put($this->fullKey($key), $value, $ttl ?? $this->guessTtl($key));
        } catch (\Throwable $e) {
            Log::warning('Cache::put failed, skip', [
                'key' => $this->fullKey($key),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 永久（慎用）
     */
    public function forever(string $key, mixed $value): void
    {
        Cache::forever($this->fullKey($key), $value);
    }

    /**
     * 忘记（带 try/catch 降级，失败静默跳过）
     */
    public function forget(string $key): void
    {
        try {
            Cache::forget($this->fullKey($key));
        } catch (\Throwable $e) {
            Log::warning('Cache::forget failed, skip', [
                'key' => $this->fullKey($key),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 清空整个命名空间
     *
     * file driver 下 Cache::flush() 不可用，因此按已知名清理。
     */
    public function flush(): void
    {
        $prefix = "{$this->namespace}:";
        $this->flushFallback($prefix);
    }

    /**
     * 自增（带 try/catch 降级，失败返回 0）
     */
    public function increment(string $key, int $step = 1, ?int $ttl = null): int
    {
        $fullKey = $this->fullKey($key);
        try {
            $count = (int) Cache::increment($fullKey, $step);
            if ($count === 1 && $ttl) {
                Cache::put($fullKey, $count, $ttl);
            }
            return $count;
        } catch (\Throwable $e) {
            Log::warning('Cache::increment failed, return 0', [
                'key' => $fullKey,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    // ===== 内部 =====

    protected function fullKey(string $key): string
    {
        return "{$this->namespace}:{$key}";
    }

    protected function guessTtl(string $key): int
    {
        foreach (self::TTL as $prefix => $ttl) {
            if (str_starts_with($key, $prefix)) return $ttl;
        }
        return 300; // 默认 5 分钟
    }

    /**
     * 把任意 ttl 表达式规范化为秒数
     */
    protected function normalizeTtl(int|\DateTimeInterface|null $ttl): ?int
    {
        if ($ttl === null) return null;
        if (is_int($ttl)) return $ttl;
        $seconds = $ttl->getTimestamp() - now()->getTimestamp();
        return max(1, $seconds);
    }

    protected function flushFallback(string $prefix): void
    {
        // file driver：仅清理约定的 namespace 根 key（保守）
        $patterns = array_keys(self::TTL);
        foreach ($patterns as $p) {
            Cache::forget("{$prefix}{$p}");
        }
    }

    /**
     * 健康检查：报告当前 cache driver
     */
    public function healthCheck(): array
    {
        try {
            $start = microtime(true);
            Cache::put('__healthcheck', 1, 5);
            $val = Cache::get('__healthcheck');
            $latency = round((microtime(true) - $start) * 1000, 2);
            return [
                'driver'   => config('cache.default'),
                'healthy'  => $val === 1,
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'driver' => config('cache.default'),
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
