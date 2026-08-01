<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductPackage;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PackageController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * 获取次数包列表
     */
    public function index(Request $request)
    {
        $packages = ProductPackage::where('is_enabled', 1)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($pkg) {
                // 图标从数据库配置读取，便于后台自定义
                $iconKey = $pkg->is_recommend ? 'package_icon_recommend' : 'package_icon_normal';
                $icon = \App\Models\SystemConfig::where('key', $iconKey)->value('value');
                if (empty($icon)) {
                    $icon = $pkg->is_recommend ? 'Trophy' : 'Star';
                }

                return [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'type' => $pkg->type,
                    'times' => (int) $pkg->times,
                    'days' => (int) $pkg->days,
                    'price' => (float) $pkg->price,
                    'original_price' => (float) $pkg->original_price,
                    'is_recommend' => (bool) $pkg->is_recommend,
                    'icon' => $icon,
                    'discount' => $pkg->original_price > $pkg->price
                        ? (int) round((1 - $pkg->price / $pkg->original_price) * 100)
                        : 0,
                    'sort_order' => (int) $pkg->sort_order,
                ];
            });

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $packages,
        ]);
    }

    /**
     * 购买次数包（创建订单 + 生成支付参数）
     */
    public function buy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|integer|exists:product_packages,id',
            'pay_type' => 'required|in:wechat,alipay,balance',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $package = ProductPackage::find($request->package_id);

        if (!$package->is_enabled) {
            return response()->json([
                'code' => 400,
                'message' => '该套餐已下架',
            ], 400);
        }

        try {
            // 通过 PaymentService 统一处理：创建订单 + 生成支付参数
            $result = $this->paymentService->createOrder(
                $request->user(),
                'package',
                (string) $package->id,
                $request->pay_type,
                (float) $package->price
            );
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 400,
                'message' => $e->getMessage() ?: '订单创建失败',
            ], 400);
        }

        return response()->json([
            'code' => 0,
            'message' => $result['paid'] ?? false ? '支付成功' : '订单创建成功',
            'data' => $result,
        ]);
    }
}
