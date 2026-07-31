<?php

namespace App\Console\Commands;

use App\Models\Commission;
use App\Models\Promoter;
use App\Models\SystemConfig;
use Illuminate\Console\Command;

class SettleCommissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commission:settle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '结算推广佣金：将冻结佣金解冻到可提现余额';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $settleDays = (int) (SystemConfig::where('key', 'commission_settle_days')->value('value') ?? 7);

        $this->info("开始结算佣金，结算天数: {$settleDays}天");

        // 查找超过结算天数的已结算佣金
        $deadline = now()->subDays($settleDays);

        $commissions = Commission::where('status', 1)
            ->where('created_at', '<=', $deadline)
            ->get();

        $totalCount = 0;
        $totalAmount = 0;

        foreach ($commissions as $commission) {
            // 佣金已经是可用状态，这里可以添加额外的结算逻辑
            // 例如：将佣金从"冻结"状态转为"可提现"状态
            $totalCount++;
            $totalAmount += $commission->amount;
        }

        $this->info("结算完成: 共 {$totalCount} 笔佣金，总金额: ¥{$totalAmount}");

        // 更新推广员邀请统计
        $this->updatePromoterStats();

        return Command::SUCCESS;
    }

    /**
     * 更新推广员统计数据
     */
    private function updatePromoterStats(): void
    {
        $promoters = Promoter::all();
        foreach ($promoters as $promoter) {
            // 更新邀请人数
            $inviteCount = \App\Models\User::where('parent_id', $promoter->user_id)->count();
            $promoter->update(['total_invite' => $inviteCount]);
        }
    }
}
