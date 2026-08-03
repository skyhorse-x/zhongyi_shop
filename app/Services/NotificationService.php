<?php

namespace App\Services;

use App\Models\SystemMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 消息触达中心
 *
 * 提供：
 *   - 系统消息（站内）
 *   - 短信（接口已存在时启用）
 *   - 邮件（占位）
 *   - 模板化触发
 *
 * 关键事件触达：
 *   - 支付成功 → 通知订单状态
 *   - 次数不足 → 引导购买
 *   - 推广佣金到账 → 通知推广员
 *   - 提现审核结果 → 通知
 *   - AI 申诉结果 → 通知
 *   - 用户注册 → 欢迎消息
 */
class NotificationService
{
    /**
     * 发送站内消息
     */
    public function sendSystemMessage(int $userId, string $title, string $content, array $options = []): SystemMessage
    {
        return SystemMessage::create([
            'user_id'    => $userId,
            'title'      => $title,
            'content'    => $content,
            'type'       => $options['type'] ?? 'system',
            'target_url' => $options['target_url'] ?? null,
            'is_read'    => false,
        ]);
    }

    /**
     * 批量发送
     */
    public function sendToMany(array $userIds, string $title, string $content, array $options = []): int
    {
        $now = now();
        $rows = [];
        foreach ($userIds as $uid) {
            $rows[] = [
                'user_id'    => $uid,
                'title'      => $title,
                'content'    => $content,
                'type'       => $options['type'] ?? 'system',
                'target_url' => $options['target_url'] ?? null,
                'is_read'    => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        return SystemMessage::insert($rows) ? count($rows) : 0;
    }

    /**
     * 发送给所有用户（用于系统公告）
     */
    public function sendToAll(string $title, string $content, array $options = []): int
    {
        $userIds = User::pluck('id')->toArray();
        return $this->sendToMany($userIds, $title, $content, $options);
    }

    // ============ 业务触达点 ============

    /**
     * 支付成功
     */
    public function paymentSuccess(int $userId, string $orderNo, float $amount, string $packageName): void
    {
        $this->sendSystemMessage($userId, '支付成功', "您购买的「{$packageName}」已到账（订单号：{$orderNo}，金额：¥{$amount}）", [
            'type' => 'payment',
            'target_url' => '/member/orders',
        ]);
    }

    /**
     * 次数不足
     */
    public function balanceInsufficient(int $userId, int $remaining): void
    {
        $this->sendSystemMessage($userId, '次数不足', "您的 AI 分析次数仅剩 {$remaining} 次，请及时充值以免影响使用", [
            'type' => 'reminder',
            'target_url' => '/packages',
        ]);
    }

    /**
     * 推广佣金到账
     */
    public function commissionEarned(int $userId, float $amount, string $fromUserName): void
    {
        $this->sendSystemMessage($userId, '获得佣金', "您的好友「{$fromUserName}」消费，您获得佣金 ¥{$amount}", [
            'type' => 'commission',
            'target_url' => '/promoter/commissions',
        ]);
    }

    /**
     * 提现审核结果
     */
    public function withdrawResult(int $userId, float $amount, bool $approved, string $reason = ''): void
    {
        $title = $approved ? '提现已通过' : '提现被拒';
        $content = $approved
            ? "您的提现申请 ¥{$amount} 已通过审核，款项将在 1-3 个工作日内到账"
            : "您的提现申请 ¥{$amount} 未通过审核" . ($reason ? "，原因：{$reason}" : '');
        $this->sendSystemMessage($userId, $title, $content, [
            'type' => 'withdraw',
            'target_url' => '/promoter/withdraw-history',
        ]);
    }

    /**
     * AI 申诉结果
     */
    public function appealResult(int $userId, bool $approved, string $reply = ''): void
    {
        $title = $approved ? 'AI 申诉已采纳' : 'AI 申诉未通过';
        $content = $approved
            ? "您的 AI 诊断申诉已采纳{$reply}，感谢您的反馈"
            : "您的 AI 诊断申诉未通过{$reply}";
        $this->sendSystemMessage($userId, $title, $content, [
            'type' => 'appeal',
        ]);
    }

    /**
     * 反馈已回复
     */
    public function feedbackReplied(int $userId, string $reply): void
    {
        $this->sendSystemMessage($userId, '反馈已回复', "您的反馈已收到回复：{$reply}", [
            'type' => 'feedback',
        ]);
    }

    /**
     * 欢迎新用户
     */
    public function welcome(int $userId, string $nickname = ''): void
    {
        $name = $nickname ?: '用户';
        $this->sendSystemMessage($userId, '欢迎加入', "{$name}，欢迎使用 AI 中医健康管理！首次注册即赠送 3 次 AI 分析次数。", [
            'type' => 'welcome',
        ]);
    }
}
