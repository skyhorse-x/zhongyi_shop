<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /**
     * 支付方式开关配置
     *
     * GET /api/v1/admin/config/payment
     */
    public function paymentConfig(): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'data' => $this->loadPaymentConfig(),
        ]);
    }

    /**
     * 切换某个支付方式开关
     *
     * POST /api/v1/admin/config/payment-toggle
     * body: { type: 'wechat'|'alipay'|'balance', enabled: 0|1 }
     */
    public function togglePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'    => 'required|in:wechat,alipay,balance',
            'enabled' => 'required|in:0,1',
        ]);

        $key = 'payment_' . $data['type'] . '_enabled';
        SystemConfig::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $data['enabled']]
        );
        SystemConfig::clearCache();

        return response()->json([
            'code'    => 0,
            'message' => $data['enabled'] === '1' ? '已开启' : '已关闭',
            'data'    => $this->loadPaymentConfig(),
        ]);
    }

    protected function loadPaymentConfig(): array
    {
        return [
            'balance' => [
                'name'  => '余额支付',
                'enabled' => (bool) SystemConfig::getValue('payment_balance_enabled', '1'),
            ],
            'wechat'  => [
                'name'  => '微信支付',
                'enabled' => (bool) SystemConfig::getValue('payment_wechat_enabled', '1'),
            ],
            'alipay'  => [
                'name'  => '支付宝',
                'enabled' => (bool) SystemConfig::getValue('payment_alipay_enabled', '1'),
            ],
        ];
    }
}
