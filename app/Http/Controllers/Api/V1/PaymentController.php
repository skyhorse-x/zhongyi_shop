<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SystemConfig;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    /**
     * GET /api/v1/payment/methods
     * 前台拉取当前可用的支付方式 + 当前用户余额
     */
    public function methods(Request $request): JsonResponse
    {
        $balance = 0.0;
        if ($request->user()) {
            $balance = (float) $request->user()->balance;
        }
        $list = $this->paymentService->getAvailablePayTypes();

        return response()->json([
            'code' => 0,
            'data' => [
                'list' => $list,
                'user_balance' => $balance,
            ],
        ]);
    }

    /**
     * POST /api/v1/payment/create
     * 创建支付（兼容旧接口）
     */
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_no' => 'required|string',
            'pay_type' => 'required|in:wechat,alipay,balance',
        ]);

        $order = Order::where('order_no', $data['order_no'])
            ->where('user_id', $request->user()->id)
            ->first();
        if (!$order) {
            return response()->json(['code' => 404, 'message' => '订单不存在'], 404);
        }
        if ($order->status === 1) {
            return response()->json(['code' => 400, 'message' => '订单已支付'], 400);
        }
        if ($order->status === 2) {
            return response()->json(['code' => 400, 'message' => '订单已取消'], 400);
        }

        try {
            if ($data['pay_type'] === 'balance') {
                $result = $this->paymentService->createOrder(
                    $request->user(),
                    $order->type,
                    (string) $order->relation_id,
                    'balance',
                    (float) $order->amount
                );
            } else {
                // 重新生成第三方支付参数（不创建新订单）
                $result = match ($data['pay_type']) {
                    'wechat' => ['order_no' => $order->order_no, 'pay_amount' => (float) $order->amount, 'pay_params' => $this->paymentService->regenerateWechatParams($order)],
                    'alipay' => ['order_no' => $order->order_no, 'pay_amount' => (float) $order->amount, 'pay_params' => $this->paymentService->regenerateAlipayParams($order)],
                };
            }
        } catch (\Throwable $e) {
            return response()->json(['code' => 400, 'message' => $e->getMessage() ?: '创建支付失败'], 400);
        }

        return response()->json(['code' => 0, 'data' => $result]);
    }

    /**
     * GET /api/v1/payment/order/{orderNo}
     * 订单支付状态
     */
    public function status(string $orderNo, Request $request): JsonResponse
    {
        $order = Order::where('order_no', $orderNo)
            ->where('user_id', $request->user()->id)
            ->first();
        if (!$order) {
            return response()->json(['code' => 404, 'message' => '订单不存在'], 404);
        }
        return response()->json([
            'code' => 0,
            'data' => [
                'order_no' => $order->order_no,
                'status'   => (int) $order->status,
                'amount'   => (float) $order->amount,
                'pay_type' => $order->pay_type,
                'paid_at'  => $order->paid_at,
            ],
        ]);
    }

    /**
     * POST /api/v1/payment/notify/wechat
     * 微信支付异步回调
     */
    public function wechatNotify(Request $request): Response
    {
        Log::info('Wechat pay notify received', [
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
        ]);

        try {
            // 获取回调数据
            $notifyData = $request->toArray();

            // 如果是 V3 加密回调，从 header 获取验签信息
            if (empty($notifyData['out_trade_no']) && $request->getContent()) {
                $notifyData = json_decode($request->getContent(), true) ?: [];
            }

            $result = $this->paymentService->handleWechatNotify($notifyData);

            if ($result) {
                // 返回微信成功的 XML 格式
                return response('<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>', 200)
                    ->header('Content-Type', 'text/xml');
            }

            return response('<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[处理失败]]></return_msg></xml>', 200)
                ->header('Content-Type', 'text/xml');
        } catch (\Throwable $e) {
            Log::error('Wechat notify handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[' . $e->getMessage() . ']]></return_msg></xml>', 200)
                ->header('Content-Type', 'text/xml');
        }
    }

    /**
     * POST /api/v1/payment/notify/alipay
     * 支付宝支付异步回调
     */
    public function alipayNotify(Request $request): Response
    {
        Log::info('Alipay pay notify received', [
            'data' => $request->toArray(),
        ]);

        try {
            $notifyData = $request->toArray();

            $result = $this->paymentService->handleAlipayNotify($notifyData);

            if ($result) {
                // 返回支付宝成功的标识
                return response('success', 200);
            }

            return response('fail', 200);
        } catch (\Throwable $e) {
            Log::error('Alipay notify handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('fail', 200);
        }
    }
}
