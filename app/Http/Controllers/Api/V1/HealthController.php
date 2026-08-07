<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    /**
     * 获取健康档案历史
     */
    public function history(Request $request)
    {
        $query = AnalysisReport::where('user_id', $request->user()->id)
            ->where('is_paid', true);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $reports = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $reports,
        ]);
    }

    /**
     * 获取健康趋势
     */
    public function trend(Request $request)
    {
        $days = $request->get('days', 30);

        $reports = AnalysisReport::where('user_id', $request->user()->id)
            ->where('is_paid', true)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get();

        $defaultScore = (int) (\App\Models\SystemConfig::where('key', 'default_health_score')->value('value') ?: 80);

        $dates = [];
        $scores = [];

        foreach ($reports as $report) {
            $dates[] = $report->created_at->format('Y-m-d');
            $scores[] = $report->health_score ?? $defaultScore;
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'dates' => $dates,
                'scores' => $scores,
            ],
        ]);
    }

    /**
     * 获取体质档案
     */
    public function constitution(Request $request)
    {
        $reports = AnalysisReport::where('user_id', $request->user()->id)
            ->where('type', 'constitution')
            ->where('is_paid', true)
            ->orderBy('created_at', 'desc')
            ->get();

        $latestType = $reports->first()?->constitution_type ?? '未知';

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'constitution_type' => $latestType,
                'test_count' => $reports->count(),
                'last_test_at' => $reports->first()?->created_at,
                'history' => $reports->map(function ($report) {
                    $content = $report->content ?? [];
                    return [
                        'id' => $report->id,
                        'constitution_type' => $report->constitution_type ?? $content['constitution_type'] ?? '',
                        'scores' => $content['scores'] ?? [],
                        'created_at' => $report->created_at,
                    ];
                }),
            ],
        ]);
    }
}
