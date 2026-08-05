<?php

namespace App\Services;

use App\Models\SystemMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * 消息触达中心
 *
 * 提供：
 *   - 系统消息（站内）
 *   - 短信推送（阿里云SMS）
 *   - 微信模板消息推送
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
     * 推送配置缓存键
     */
    private const CONFIG_KEY = 'notification_push_config';

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
     * 批量发送站内消息
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

    // ============ 多渠道推送 ============

    /**
     * 多渠道推送消息
     *
     * @param int $userId 用户ID
     * @param string $title 标题
     * @param string $content 内容
     * @param array $options 选项
     *   - channels: array 推送渠道 ['system', 'sms', 'wechat']
     *   - type: string 消息类型
     *   - target_url: string 跳转链接
     *   - template_id: string 微信模板ID
     *   - template_data: array 微信模板数据
     *   - sms_template_code: string 短信模板CODE
     *   - sms_params: array 短信模板参数
     * @return array 各渠道发送结果
     */
    public function push(int $userId, string $title, string $content, array $options = []): array
    {
        $channels = $options['channels'] ?? ['system'];
        $results = [];

        foreach ($channels as $channel) {
            $results[$channel] = match ($channel) {
                'system' => $this->pushSystem($userId, $title, $content, $options),
                'sms'    => $this->pushSms($userId, $content, $options),
                'wechat' => $this->pushWechat($userId, $options),
                default  => ['success' => false, 'message' => "未知渠道: {$channel}"],
            };
        }

        return $results;
    }

    /**
     * 推送站内消息
     */
    private function pushSystem(int $userId, string $title, string $content, array $options): array
    {
        try {
            $this->sendSystemMessage($userId, $title, $content, $options);
            return ['success' => true, 'message' => '发送成功'];
        } catch (\Throwable $e) {
            Log::error('站内消息推送失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 推送短信消息
     */
    private function pushSms(int $userId, string $content, array $options): array
    {
        $config = $this->getPushConfig();
        if (!$config['sms_enabled']) {
            return ['success' => false, 'message' => '短信推送未启用'];
        }

        try {
            $user = User::find($userId);
            if (!$user || !$user->phone) {
                return ['success' => false, 'message' => '用户未绑定手机号'];
            }

            $templateCode = $options['sms_template_code'] ?? '';
            $smsParams = $options['sms_params'] ?? [];

            if (empty($templateCode)) {
                return ['success' => false, 'message' => '缺少短信模板CODE'];
            }

            // 调用阿里云SMS接口
            $result = $this->sendAliyunSms(
                $user->phone,
                $templateCode,
                $smsParams,
                $config
            );

            return $result;
        } catch (\Throwable $e) {
            Log::error('短信推送失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 推送微信模板消息
     */
    private function pushWechat(int $userId, array $options): array
    {
        $config = $this->getPushConfig();
        if (!$config['wechat_enabled']) {
            return ['success' => false, 'message' => '微信推送未启用'];
        }

        try {
            $user = User::find($userId);
            if (!$user) {
                return ['success' => false, 'message' => '用户不存在'];
            }

            $templateId = $options['template_id'] ?? '';
            $templateData = $options['template_data'] ?? [];
            $url = $options['target_url'] ?? '';

            if (empty($templateId)) {
                return ['success' => false, 'message' => '缺少微信模板ID'];
            }

            // 获取微信 AccessToken
            $accessToken = $this->getWechatAccessToken($config);
            if (!$accessToken) {
                return ['success' => false, 'message' => '获取微信AccessToken失败'];
            }

            // 调用微信模板消息接口
            $result = $this->sendWechatTemplateMessage(
                $accessToken,
                $user->wechat_openid ?? '',
                $templateId,
                $templateData,
                $url
            );

            return $result;
        } catch (\Throwable $e) {
            Log::error('微信推送失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 发送阿里云短信
     */
    private function sendAliyunSms(string $phone, string $templateCode, array $params, array $config): array
    {
        try {
            $accessKeyId = $config['sms_access_key_id'] ?? '';
            $accessKeySecret = $config['sms_access_key_secret'] ?? '';
            $signName = $config['sms_sign_name'] ?? '';

            if (empty($accessKeyId) || empty($accessKeySecret)) {
                return ['success' => false, 'message' => 'SMS配置不完整'];
            }

            // 使用阿里云SMS SDK或HTTP接口
            $params = [
                'PhoneNumbers'  => $phone,
                'SignName'      => $signName,
                'TemplateCode'  => $templateCode,
                'TemplateParam' => json_encode($params),
            ];

            // 这里使用 HTTP 调用阿里云 SMS API
            // 实际项目中建议使用官方 SDK: alibabacloud/dysmsapi-20170525
            $response = Http::timeout(10)->post('https://dysmsapi.aliyuncs.com', $params);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['Code'] === 'OK') {
                    return ['success' => true, 'message' => '发送成功', 'biz_id' => $data['BizId'] ?? ''];
                }
                return ['success' => false, 'message' => $data['Message'] ?? '发送失败'];
            }

            return ['success' => false, 'message' => 'HTTP请求失败'];
        } catch (\Throwable $e) {
            Log::error('阿里云SMS发送失败', ['phone' => $phone, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取微信 AccessToken
     */
    private function getWechatAccessToken(array $config): ?string
    {
        $cacheKey = 'wechat_access_token';
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        try {
            $appId = $config['wechat_app_id'] ?? '';
            $appSecret = $config['wechat_app_secret'] ?? '';

            if (empty($appId) || empty($appSecret)) {
                return null;
            }

            $response = Http::timeout(10)->get('https://api.weixin.qq.com/cgi-bin/token', [
                'grant_type' => 'client_credential',
                'appid' => $appId,
                'secret' => $appSecret,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['access_token'])) {
                    // 缓存7000秒（有效期7200秒）
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $data['access_token'], 7000);
                    return $data['access_token'];
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('获取微信AccessToken失败', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 发送微信模板消息
     */
    private function sendWechatTemplateMessage(
        string $accessToken,
        string $openid,
        string $templateId,
        array $data,
        string $url = ''
    ): array {
        if (empty($openid)) {
            return ['success' => false, 'message' => '用户未绑定微信'];
        }

        try {
            $sendData = [
                'touser' => $openid,
                'template_id' => $templateId,
                'data' => $data,
            ];

            if (!empty($url)) {
                $sendData['url'] = $url;
            }

            $response = Http::timeout(10)->post(
                "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$accessToken}",
                $sendData
            );

            if ($response->successful()) {
                $result = $response->json();
                if ($result['errcode'] === 0) {
                    return ['success' => true, 'message' => '发送成功', 'msgid' => $result['msgid'] ?? ''];
                }
                return ['success' => false, 'message' => $result['errmsg'] ?? '发送失败'];
            }

            return ['success' => false, 'message' => 'HTTP请求失败'];
        } catch (\Throwable $e) {
            Log::error('微信模板消息发送失败', ['openid' => $openid, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取推送配置
     */
    public function getPushConfig(): array
    {
        return \Illuminate\Support\Facades\Cache::remember(self::CONFIG_KEY, 3600, function () {
            return [
                'sms_enabled'           => \App\Services\SystemConfigService::get('push_sms_enabled', '0') === '1',
                'wechat_enabled'        => \App\Services\SystemConfigService::get('push_wechat_enabled', '0') === '1',
                'sms_access_key_id'     => \App\Services\SystemConfigService::get('sms_access_key_id', ''),
                'sms_access_key_secret' => \App\Services\SystemConfigService::get('sms_access_key_secret', ''),
                'sms_sign_name'         => \App\Services\SystemConfigService::get('sms_sign_name', ''),
                'wechat_app_id'         => \App\Services\SystemConfigService::get('wechat_app_id', ''),
                'wechat_app_secret'     => \App\Services\SystemConfigService::get('wechat_app_secret', ''),
            ];
        });
    }

    /**
     * 清除推送配置缓存
     */
    public function clearConfigCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget(self::CONFIG_KEY);
    }

    // ============ 业务触达点 ============

    /**
     * 支付成功 - 多渠道推送
     */
    public function paymentSuccess(int $userId, string $orderNo, float $amount, string $packageName): void
    {
        $title = '支付成功';
        $content = "您购买的「{$packageName}」已到账（订单号：{$orderNo}，金额：¥{$amount}）";

        // 站内消息
        $this->sendSystemMessage($userId, $title, $content, [
            'type' => 'payment',
            'target_url' => '/member/orders',
        ]);

        // 多渠道推送
        $this->push($userId, $title, $content, [
            'channels' => ['sms', 'wechat'],
            'type' => 'payment',
            'target_url' => '/member/orders',
            'sms_template_code' => \App\Services\SystemConfigService::get('sms_template_payment', ''),
            'sms_params' => ['package' => $packageName, 'amount' => number_format($amount, 2)],
            'template_id' => \App\Services\SystemConfigService::get('wechat_template_payment', ''),
            'template_data' => [
                'thing1' => ['value' => $packageName],
                'amount2' => ['value' => '¥' . number_format($amount, 2)],
                'time3' => ['value' => now()->format('Y-m-d H:i')],
            ],
        ]);
    }

    /**
     * 次数不足
     */
    public function balanceInsufficient(int $userId, int $remaining): void
    {
        $title = '次数不足';
        $content = "您的 AI 分析次数仅剩 {$remaining} 次，请及时充值以免影响影响使用";

        $this->sendSystemMessage($userId, $title, $content, [
            'type' => 'reminder',
            'target_url' => '/packages',
        ]);
    }

    /**
     * 推广佣金到账 - 多渠道推送
     */
    public function commissionEarned(int $userId, float $amount, string $fromUserName): void
    {
        $title = '获得佣金';
        $content = "您的好友「{$fromUserName}」消费，您获得佣金 ¥{$amount}";

        $this->sendSystemMessage($userId, $title, $content, [
            'type' => 'commission',
            'target_url' => '/promoter/commissions',
        ]);

        // 佣金到账推送
        $this->push($userId, $title, $content, [
            'channels' => ['sms', 'wechat'],
            'type' => 'commission',
            'target_url' => '/promoter/commissions',
            'sms_template_code' => \App\Services\SystemConfigService::get('sms_template_commission', ''),
            'sms_params' => ['amount' => number_format($amount, 2)],
            'template_id' => \App\Services\SystemConfigService::get('wechat_template_commission', ''),
            'template_data' => [
                'amount1' => ['value' => '¥' . number_format($amount, 2)],
                'thing2' => ['value' => '好友' . $fromUserName . '消费'],
            ],
        ]);
    }

    /**
     * 提现审核结果 - 多渠道推送
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

        // 提现结果推送
        $this->push($userId, $title, $content, [
            'channels' => ['sms', 'wechat'],
            'type' => 'withdraw',
            'target_url' => '/promoter/withdraw-history',
            'sms_template_code' => \App\Services\SystemConfigService::get('sms_template_withdraw', ''),
            'sms_params' => [
                'result' => $approved ? '通过' : '拒绝',
                'amount' => number_format($amount, 2),
            ],
            'template_id' => \App\Services\SystemConfigService::get('wechat_template_withdraw', ''),
            'template_data' => [
                'result1' => ['value' => $approved ? '已通过' : '未通过'],
                'amount2' => ['value' => '¥' . number_format($amount, 2)],
            ],
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
