<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAnalysisLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalysisTimesService
{
    /**
     * 增加分析次数（购买套餐）
     */
    public function addTimes(User $user, int $times, string $orderNo): bool
    {
        if ($times <= 0) {
            return false;
        }

        return DB::transaction(function () use ($user, $times, $orderNo) {
            $before = (int) $user->analysis_times;
            $after  = $before + $times;

            $user->update(['analysis_times' => $after]);

            UserAnalysisLog::create([
                'user_id' => $user->id,
                'change'  => $times,
                'before'  => $before,
                'after'   => $after,
                'type'    => 'purchase',
                'remark'  => "购买套餐 +{$times}次 (订单: {$orderNo})",
            ]);

            return true;
        });
    }

    /**
     * 扣除分析次数（使用 AI 分析）
     *
     * @return bool 成功返回 true，次数不足返回 false
     */
    public function deductTimes(User $user, int $count, string $type, string $remark = ''): bool
    {
        if ($count <= 0) {
            return false;
        }

        return DB::transaction(function () use ($user, $count, $type, $remark) {
            // 行级锁，防止并发超扣
            $fresh = User::lockForUpdate()->find($user->id);
            $before = (int) $fresh->analysis_times;

            if ($before < $count) {
                Log::info('Analysis times insufficient', [
                    'user_id' => $user->id,
                    'required' => $count,
                    'available' => $before,
                ]);
                return false;
            }

            $after = $before - $count;
            $fresh->update(['analysis_times' => $after]);

            UserAnalysisLog::create([
                'user_id' => $user->id,
                'change'  => -$count,
                'before'  => $before,
                'after'   => $after,
                'type'    => $type,
                'remark'  => $remark ?: "AI分析消耗 -{$count}次",
            ]);

            return true;
        });
    }

    /**
     * 查询用户剩余次数
     */
    public function getRemaining(User $user): int
    {
        return (int) $user->analysis_times;
    }

    /**
     * 检查用户是否有足够的分析次数
     */
    public function checkTimes(User $user, string $type): bool
    {
        $required = $this->getRequiredTimes($type);
        $remaining = $this->getRemaining($user);
        return $remaining >= $required;
    }

    /**
     * 根据分析类型获取所需次数
     */
    private function getRequiredTimes(string $type): int
    {
        return match ($type) {
            'tongue', 'face', 'palm', 'eye' => 1,
            default => 1,
        };
    }

    /**
     * 返还分析次数（AI 失败 / 退款 / 异常补偿）
     *
     * @return bool
     */
    public function refundTimes(User $user, int $count, string $type, string $remark = ''): bool
    {
        if ($count <= 0) {
            return false;
        }

        return DB::transaction(function () use ($user, $count, $type, $remark) {
            $fresh = User::lockForUpdate()->find($user->id);
            $before = (int) $fresh->analysis_times;
            $after  = $before + $count;

            $fresh->update(['analysis_times' => $after]);

            UserAnalysisLog::create([
                'user_id' => $user->id,
                'change'  => $count,
                'before'  => $before,
                'after'   => $after,
                'type'    => $type, // refund / compensate / admin
                'remark'  => $remark ?: "返还 +{$count}次",
            ]);

            return true;
        });
    }
}
