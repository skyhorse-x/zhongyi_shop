<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\XianyuProduct;
use App\Services\SystemConfigService;
use Illuminate\Http\Request;

class XianyuProductController extends Controller
{
    /**
     * 前台：获取启用的闲鱼充值商品列表（公开）
     * system_link：后台【系统设置 → 基本设置】配置的闲鱼商品链接，作为无商品时的兜底入口
     */
    public function index(Request $request)
    {
        $products = XianyuProduct::where('is_enabled', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'title', 'link', 'amount', 'times', 'description']);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'system_link' => SystemConfigService::get('xianyu_product_link', ''),
                'products' => $products,
            ],
        ]);
    }
}
