<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * 创建支付订单
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:analysis,package',
            'relation_id' => 'required|string',
            'pay_type' => 'required|in:wechat,alipay',
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $result = $this->paymentService->createOrder(
                $request->user(),
                $validated['type'],
                $validated['relation_id'],
                $validated['pay_type'],
                $validated['amount']
            );

            return response()->json([
                'code' => 0,
                'message' => '订单创建成功',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Invalid payment request', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'code' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment order creation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'code' => 500,
                'message' => '订单创建失败，请稍后重试',
            ], 500);
        }
    }

    /**
     * 支付宝支付回调
     */
    public function alipayNotify(Request $request)
    {
        try {
            Log::info('Alipay notify received', $request->all());

            $result = $this->paymentService->handleAlipayNotify($request->all());

            if ($result) {
                return 'success';
            }

            return 'fail';
        } catch (\Exception $e) {
            Log::error('Alipay notify processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            return 'fail';
        }
    }

    /**
     * 微信支付回调
     */
    public function wechatNotify(Request $request)
    {
        try {
            Log::info('Wechat notify received', $request->all());

            $result = $this->paymentService->handleWechatNotify($request->all());

            if ($result) {
                return response()->json(['code' => 'SUCCESS', 'message' => 'OK']);
            }

            return response()->json(['code' => 'FAIL', 'message' => '处理失败']);
        } catch (\Exception $e) {
            Log::error('Wechat notify processing failed', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            return response()->json(['code' => 'FAIL', 'message' => $e->getMessage()]);
        }
    }

    /**
     * 查询订单状态
     */
    public function status(Request $request, string $orderNo)
    {
        try {
            $order = $this->paymentService->queryOrderStatus($orderNo);

            if (!$order || $order->user_id !== $request->user()->id) {
                return response()->json([
                    'code' => 404,
                    'message' => '订单不存在',
                ], 404);
            }

            return response()->json([
                'code' => 0,
                'message' => 'success',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('Order status query failed', [
                'order_no' => $orderNo,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'code' => 500,
                'message' => '查询失败',
            ], 500);
        }
    }
}
