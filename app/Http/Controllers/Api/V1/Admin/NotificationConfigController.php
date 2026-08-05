<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\SystemConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 消息推送配置管理
 */
class NotificationConfigController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    /**
     * 获取推送配置
     *
     * GET /api/v1/admin/notification-config
     */
    public function index(): JsonResponse
    {
        $config = [
            // 短信配置
            'sms_enabled'           => SystemConfigService::get('push_sms_enabled', '0'),
            'sms_access_key_id'     => SystemConfigService::get('sms_access_key_id', ''),
            'sms_access_key_secret' => $this->maskSecret(SystemConfigService::get('sms_access_key_secret', '')),
            'sms_sign_name'         => SystemConfigService::get('sms_sign_name', ''),

            // 短信模板
            'sms_template_payment'    => SystemConfigService::get('sms_template_payment', ''),
            'sms_template_commission' => SystemConfigService::get('sms_template_commission', ''),
            'sms_template_withdraw'    => SystemConfigService::get('sms_template_withdraw', ''),

            // 微信配置
            'wechat_enabled'        => SystemConfigService::get('push_wechat_enabled', '0'),
            'wechat_app_id'         => SystemConfigService::get('wechat_app_id', ''),
            'wechat_app_secret'     => $this->maskSecret(SystemConfigService::get('wechat_app_secret', '')),

            // 微信模板
            'wechat_template_payment'    => SystemConfigService::get('wechat_template_payment', ''),
            'wechat_template_commission' => SystemConfigService::get('wechat_template_commission', ''),
            'wechat_template_withdraw'    => SystemConfigService::get('wechat_template_withdraw', ''),
        ];

        return response()->json(['code' => 0, 'message' => '获取成功', 'data' => $config]);
    }

    /**
     * 保存推送配置
     *
     * POST /api/v1/admin/notification-config
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // 短信配置
            'sms_enabled'           => 'nullable|in:0,1',
            'sms_access_key_id'     => 'nullable|string|max:100',
            'sms_access_key_secret' => 'nullable|string|max:200',
            'sms_sign_name'         => 'nullable|string|max:50',

            // 短信模板
            'sms_template_payment'    => 'nullable|string|max:50',
            'sms_template_commission' => 'nullable|string|max:50',
            'sms_template_withdraw'    => 'nullable|string|max:50',

            // 微信配置
            'wechat_enabled'        => 'nullable|in:0,1',
            'wechat_app_id'         => 'nullable|string|max:100',
            'wechat_app_secret'     => 'nullable|string|max:200',

            // 微信模板
            'wechat_template_payment'    => 'nullable|string|max:50',
            'wechat_template_commission' => 'nullable|string|max:50',
            'wechat_template_withdraw'    => 'nullable|string|max:50',
        ]);

        // 保存配置
        foreach ($validated as $key => $value) {
            // 如果敏感字段被掩码（***），则不更新
            if (is_string($value) && str_starts_with($value, '***')) {
                continue;
            }
            SystemConfigService::set($key, $value);
        }

        // 清除推送配置缓存
        $this->notificationService->clearConfigCache();

        return response()->json(['code' => 0, 'message' => '保存成功']);
    }

    /**
     * 测试短信发送
     *
     * POST /api/v1/admin/notification-config/test-sms
     */
    public function testSms(Request $request): JsonResponse
    {
        $request->validate([
            'phone'         => 'required|string|max:20',
            'template_code' => 'required|string|max:50',
            'params'        => 'nullable|array',
        ]);

        $config = $this->notificationService->getPushConfig();
        $result = $this->notificationService->sendAliyunSms(
            $request->input('phone'),
            $request->input('template_code'),
            $request->input('params', []),
            $config
        );

        if ($result['success']) {
            return response()->json(['code' => 0, 'message' => '短信发送成功']);
        }

        return response()->json(['code' => 500, 'message' => $result['message'] ?? '发送失败'], 500);
    }

    /**
     * 测试微信模板消息
     *
     * POST /api/v1/admin/notification-config/test-wechat
     */
    public function testWechat(Request $request): JsonResponse
    {
        $request->validate([
            'openid'      => 'required|string|max:100',
            'template_id' => 'required|string|max:100',
            'data'        => 'required|array',
            'url'         => 'nullable|string|max:500',
        ]);

        $config = $this->notificationService->getPushConfig();
        $accessToken = $this->notificationService->getWechatAccessToken($config);

        if (!$accessToken) {
            return response()->json(['code' => 500, 'message' => '获取微信AccessToken失败'], 500);
        }

        $result = $this->notificationService->sendWechatTemplateMessage(
            $accessToken,
            $request->input('openid'),
            $request->input('template_id'),
            $request->input('data'),
            $request->input('url', '')
        );

        if ($result['success']) {
            return response()->json(['code' => 0, 'message' => '微信消息发送成功']);
        }

        return response()->json(['code' => 500, 'message' => $result['message'] ?? '发送失败'], 500);
    }

    /**
     * 脱敏处理密钥
     */
    private function maskSecret(string $secret): string
    {
        if (empty($secret)) {
            return '';
        }
        $length = strlen($secret);
        if ($length <= 8) {
            return '********';
        }
        return substr($secret, 0, 4) . '********' . substr($secret, -4);
    }
}
