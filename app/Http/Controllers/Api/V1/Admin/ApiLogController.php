<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiLogController extends Controller
{
    /**
     * 获取API请求日志列表
     */
    public function index(Request $request)
    {
        $query = ApiLog::query();

        // 筛选条件
        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'success') {
                $query->where('success', true);
            } elseif ($status === 'failed') {
                $query->where('success', false);
            } elseif (is_numeric($status)) {
                $query->where('response_status', (int) $status);
            }
        }

        if ($request->filled('method')) {
            $query->where('method', strtoupper($request->input('method')));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('url', 'like', "%{$keyword}%")
                    ->orWhere('route_name', 'like', "%{$keyword}%")
                    ->orWhere('ip', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('requested_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('requested_at', '<=', $request->input('date_to'));
        }

        // 排序
        $query->orderBy('requested_at', 'desc');

        // 分页
        $limit = $request->input('limit', 20);
        $limit = min($limit, 100);

        $logs = $query->paginate($limit);

        // 统计信息
        $stats = [
            'total_requests' => ApiLog::count(),
            'today_requests' => ApiLog::whereDate('requested_at', today())->count(),
            'success_rate' => $this->getSuccessRate(),
            'avg_duration' => round(ApiLog::avg('duration_ms') ?? 0, 2),
        ];

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $logs,
            'stats' => $stats,
        ]);
    }

    /**
     * 获取单条日志详情
     */
    public function show($id)
    {
        $log = ApiLog::findOrFail($id);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $log,
        ]);
    }

    /**
     * 删除日志
     */
    public function destroy($id)
    {
        ApiLog::destroy($id);

        return response()->json([
            'code' => 0,
            'message' => '删除成功',
        ]);
    }

    /**
     * 批量删除日志
     */
    public function batchDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json([
                'code' => 1,
                'message' => '请选择要删除的记录',
            ], 422);
        }

        ApiLog::destroy($ids);

        return response()->json([
            'code' => 0,
            'message' => '批量删除成功',
        ]);
    }

    /**
     * 清空所有日志
     */
    public function clean()
    {
        ApiLog::query()->delete();

        return response()->json([
            'code' => 0,
            'message' => '清空成功',
        ]);
    }

    /**
     * 获取模块列表
     */
    public function modules()
    {
        $modules = ApiLog::select('module')
            ->distinct()
            ->pluck('module');

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $modules,
        ]);
    }

    /**
     * 获取统计数据
     */
    public function stats(Request $request)
    {
        $days = $request->input('days', 7);

        $stats = ApiLog::select(
            DB::raw('DATE(requested_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('AVG(duration_ms) as avg_duration'),
            DB::raw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count')
        )
            ->where('requested_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * 计算成功率
     */
    private function getSuccessRate(): float
    {
        $total = ApiLog::count();
        if ($total === 0) {
            return 100.0;
        }

        $success = ApiLog::where('success', true)->count();

        return round(($success / $total) * 100, 2);
    }
}
