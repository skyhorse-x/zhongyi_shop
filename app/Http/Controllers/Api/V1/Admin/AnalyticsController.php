<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;

/**
 * 管理后台 - 数据分析 BI
 */
class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analytics) {}

    public function overview()
    {
        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $this->analytics->overviewWithComparison(),
        ]);
    }

    public function funnel(Request $request)
    {
        $days = (int) $request->integer('days', 30);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $this->analytics->funnel($days)]);
    }

    public function retention(Request $request)
    {
        $days = (int) $request->integer('days', 7);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $this->analytics->retention($days)]);
    }

    public function revenue(Request $request)
    {
        $days = (int) $request->integer('days', 30);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $this->analytics->revenueTrend($days)]);
    }

    public function userGrowth(Request $request)
    {
        $days = (int) $request->integer('days', 30);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $this->analytics->userGrowthTrend($days)]);
    }

    public function topPromoters(Request $request)
    {
        $limit = (int) $request->integer('limit', 10);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $this->analytics->topPromoters($limit)]);
    }

    public function analysisDistribution(Request $request)
    {
        $days = (int) $request->integer('days', 30);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $this->analytics->analysisTypeDistribution($days)]);
    }

    public function refundRate(Request $request)
    {
        $days = (int) $request->integer('days', 30);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $this->analytics->refundRate($days)]);
    }

    public function packageSales(Request $request)
    {
        $days = (int) $request->integer('days', 30);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $this->analytics->packageSales($days)]);
    }

    public function promotionConversion(Request $request)
    {
        $days = (int) $request->integer('days', 30);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $this->analytics->promotionConversion($days)]);
    }

    public function satisfaction(Request $request)
    {
        $days = (int) $request->integer('days', 30);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $this->analytics->customerServiceSatisfaction($days)]);
    }
}
