<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\AnalysisTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HealthArchiveController extends Controller
{
    /**
     * 健康档案列表
     * GET /api/v1/admin/health-archives
     */
    public function index(Request $request)
    {
        $query = AnalysisReport::with(['user:id,username,email,mobile,gender,birthday', 'task:task_no,type,created_at,image_urls,text'])
            ->orderBy('id', 'desc');

        // 按类型筛选
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // 按用户搜索
        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->whereHas('user', function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%")
                  ->orWhere('mobile', 'like', "%{$keyword}%");
            });
        }

        // 按日期范围筛选
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $perPage = $request->input('per_page', 15);
        $reports = $query->paginate($perPage);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $reports,
        ]);
    }

    /**
     * 查看健康档案详情
     * GET /api/v1/admin/health-archives/{id}
     */
    public function show($id)
    {
        $report = AnalysisReport::with(['user:id,username,email,mobile,avatar', 'task:task_no,type,gender,age,image_url,image_urls,text,prompt,result,created_at,completed_at'])
            ->findOrFail($id);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $report,
        ]);
    }

    /**
     * 删除健康档案
     * DELETE /api/v1/admin/health-archives/{id}
     */
    public function destroy(Request $request, $id)
    {
        $report = AnalysisReport::findOrFail($id);
        $report->delete();

        Log::info('Health archive deleted', [
            'id' => $id,
            'admin_id' => $request->user()->id ?? null,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '删除成功',
        ]);
    }

    /**
     * 健康档案统计数据
     * GET /api/v1/admin/health-archives/stats
     */
    public function stats()
    {
        $totalReports = AnalysisReport::count();
        $todayReports = AnalysisReport::whereDate('created_at', today())->count();
        $weekReports = AnalysisReport::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();

        $typeStats = AnalysisReport::selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        $avgScore = AnalysisReport::avg('health_score');

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'total_reports' => $totalReports,
                'today_reports' => $todayReports,
                'week_reports' => $weekReports,
                'avg_health_score' => round($avgScore, 1),
                'type_stats' => $typeStats,
            ],
        ]);
    }
}
