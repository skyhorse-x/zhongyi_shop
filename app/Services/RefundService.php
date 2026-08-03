<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Package;
use App\Models\Refund;
use App\Models\SystemMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 退款服务
 *
 * 提供：
 *   1. 用户申请退款
 *   2. 管理员审核退款
 *   3. 渠道退款（微信/支付宝/余额）
 *   4. 退款后的次数回退 / 佣金回滚
 */
class RefundService
{
    public function __construct(protected NotificationService $notif) {}

    /**
     * 用户申请退款
     */
    public function apply(int $userId, string $orderNo, string $reason, ?string $description = null): Refund
    {
        $order = Order::where('order_no', $orderNo)
            ->where('user_id', $userId)
            ->firstOrFail();

        if ($order->status !== 1) {
            throw new \Exception('只有已支付订单才能申请退款');
        }

        // 幂等：同一订单已有进行中的退款
        $existing = Refund::where('order_no', $orderNo)
            ->whereIn('status', ['pending', 'processing'])
            ->first();
        if ($existing) {
            return $existing;
        }

        return Refund::create([
            'refund_no'     => $this->generateRefundNo(),
            'order_no'      => $order->order_no,
            'user_id'       => $order->user_id,
            'amount'        => $order->amount,
            'refund_amount' => $order->amount,
            'reason'        => $reason,
            'description'   => $description,
            'status'        => 'pending',
            'pay_type'      => $order->pay_type,
            'transaction_id' => $order->transaction_id,
        ]);
    }

    /**
     * 管理员审核通过 + 渠道退款
     */
    public function approve(Refund $refund, ?int $adminId = null, ?string $note = null): bool
    {
        if ($refund->status !== 'pending') {
            throw new \Exception('该退款单已处理');
        }

        try {
            DB::beginTransaction();

            $refund->update([
                'status'       => 'processing',
                'admin_note'   => $note,
                'processed_by' => $adminId,
                'processed_at' => now(),
            ]);

            // 1. 渠道退款
            $ok = $this->doChannelRefund($refund);

            if (!$ok) {
                $refund->update(['status' => 'failed']);
                DB::commit();
                return false;
            }

            // 2. 业务补偿：恢复次数 / 回退佣金 / 关闭订单
            $this->compensate($refund->order);

            $refund->update([
                'status'         => 'success',
                'refunded_at'    => now(),
            ]);

            DB::commit();

            // 3. 通知用户
            $this->notif->sendSystemMessage(
                $refund->user_id,
                '退款成功',
                "您的订单 {$refund->order_no} 已成功退款 ¥{$refund->refund_amount}，款项将原路返回。" . ($note ? "\n备注：{$note}" : ''),
                ['type' => 'refund', 'target_url' => '/member/orders']
            );

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('退款失败', ['refund_no' => $refund->refund_no, 'error' => $e->getMessage()]);
            $refund->update(['status' => 'failed', 'admin_note' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 管理员驳回退款
     */
    public function reject(Refund $refund, ?int $adminId = null, ?string $note = null): void
    {
        $refund->update([
            'status'       => 'cancelled',
            'admin_note'   => $note,
            'processed_by' => $adminId,
            'processed_at' => now(),
        ]);

        $this->notif->sendSystemMessage(
            $refund->user_id,
            '退款被拒',
            "您的订单 {$refund->order_no} 退款申请未通过" . ($note ? "，原因：{$note}" : '。如有疑问请联系客服。'),
            ['type' => 'refund', 'target_url' => '/customer-service']
        );
    }

    /**
     * 业务补偿
     */
    public function compensate(Order $order): void
    {
        // 1. 订单状态置为已退款
        $order->update(['status' => 3]); // 3=已退款

        // 2. 套餐订单：扣回已加的次数
        if ($order->type === 'package' && $order->relation_id) {
            $this->refundAnalysisTimes($order);
        }

        // 3. 推广佣金回滚
        $this->rollbackCommission($order);
    }

    /**
     * 扣回 AI 次数
     */
    protected function refundAnalysisTimes(Order $order): void
    {
        $package = Package::find($order->relation_id);
        if (!$package) return;
        $times = (int) ($package->analysis_times ?? 0);
        if ($times <= 0) return;

        $user = User::find($order->user_id);
        if (!$user) return;

        // 不直接扣到负数，最少 0
        User::where('id', $user->id)->update([
            'analysis_times' => DB::raw("GREATEST(analysis_times - {$times}, 0)"),
        ]);

        // 写流水
        \App\Models\UserBalanceLog::create([
            'user_id'    => $user->id,
            'type'       => 'refund',
            'amount'     => -$times,
            'balance'    => max(0, (int) $user->analysis_times - $times),
            'remark'     => "订单 {$order->order_no} 退款，扣回次数",
        ]);
    }

    /**
     * 推广佣金回滚
     */
    protected function rollbackCommission(Order $order): void
    {
        $commissions = \App\Models\Commission::where('source_order_id', $order->id)
            ->where('status', 1) // 仅回滚已结算的
            ->get();

        foreach ($commissions as $c) {
            $c->update(['status' => 3]); // 3=已撤销

            // 减少推广员余额（如果是已提现的，要从冻结中扣）
            $promoter = \App\Models\Promoter::where('user_id', $c->promoter_id)->first();
            if (!$promoter) continue;

            if ($c->withdraw_id) {
                // 已被提现：从已提现总额中扣
                $promoter->update([
                    'withdrawn_commission' => DB::raw("GREATEST(withdrawn_commission - {$c->amount}, 0)"),
                ]);
            } else {
                // 未提现：从可提现中扣
                $promoter->update([
                    'available_commission' => DB::raw("GREATEST(available_commission - {$c->amount}, 0)"),
                    'total_commission'     => DB::raw("GREATEST(total_commission - {$c->amount}, 0)"),
                ]);
            }

            // 通知推广员
            $this->notif->sendSystemMessage(
                $c->promoter_id,
                '佣金已撤销',
                "由于订单 {$order->order_no} 退款，¥{$c->amount} 佣金已被撤销",
                ['type' => 'commission']
            );
        }
    }

    /**
     * 渠道退款（实际对接微信/支付宝）
     */
    protected function doChannelRefund(Refund $refund): bool
    {
        switch ($refund->pay_type) {
            case 'balance':
                // 余额支付：直接退回余额
                User::where('id', $refund->user_id)->update([
                    'balance' => DB::raw("balance + {$refund->refund_amount}"),
                ]);
                \App\Models\UserBalanceLog::create([
                    'user_id' => $refund->user_id,
                    'type'    => 'refund',
                    'amount'  => $refund->refund_amount,
                    'balance' => 0,
                    'remark'  => "退款：{$refund->refund_no}",
                ]);
                return true;

            case 'wechat':
                return $this->wechatRefund($refund);

            case 'alipay':
                return $this->alipayRefund($refund);

            default:
                Log::warning('未知支付渠道', ['pay_type' => $refund->pay_type]);
                return false;
        }
    }

    protected function wechatRefund(Refund $refund): bool
    {
        try {
            $resp = app(PaymentService::class)->refundWechat(
                $refund->transaction_id, $refund->refund_no, (float) $refund->refund_amount
            );
            $refund->update([
                'refund_transaction_id' => $resp['refund_id'] ?? null,
                'response' => $resp,
            ]);
            return ($resp['result_code'] ?? '') === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::error('微信退款失败', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function alipayRefund(Refund $refund): bool
    {
        try {
            $resp = app(PaymentService::class)->refundAlipay(
                $refund->order_no, (float) $refund->refund_amount
            );
            $refund->update(['response' => $resp]);
            return true;
        } catch (\Throwable $e) {
            Log::error('支付宝退款失败', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function generateRefundNo(): string
    {
        return 'RF' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
