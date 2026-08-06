<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
use App\Services\AdminOperationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 操作日志管理
 */
class OperationLogController extends Controller
{
    public function __construct(
        private readonly AdminOperationLogService $logService
    ) {
        // 路由已使用 admin 中间件，无需重复添加
    }

    /**
     * 获取操作日志列表
     *
     * GET /api/v1/admin/operation-logs
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'admin_id',
            'module',
            'action',
            'status',
            'start_date',
            'end_date',
            'keyword',
        ]);

        $perPage = (int) $request->get('per_page', 20);
        $perPage = min($perPage, 100);

        $logs = $this->logService->query($filters, $perPage);

        return response()->json(['code' => 0, 'message' => '获取成功', 'data' => $logs]);
    }

    /**
     * 获取操作日志详情
     *
     * GET /api/v1/admin/operation-logs/{id}
     */
    public function show(int $id): JsonResponse
    {
        $logItem = AdminOperationLog::with('admin')->find($id);

        if (!$logItem) {
            return response()->json(['code' => 404, 'message' => '日志不存在'], 404);
        }

        return response()->json(['code' => 0, 'message' => '获取成功', 'data' => $logItem]);
    }

    /**
     * 获取操作模块列表
     *
     * GET /api/v1/admin/operation-logs/modules
     */
    public function modules(): JsonResponse
    {
        $modules = $this->logService->getModules();

        return response()->json(['code' => 0, 'message' => '获取成功', 'data' => $modules]);
    }

    /**
     * 获取操作统计
     *
     * GET /api/v1/admin/operation-logs/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateTimeString());
        $endDate = $request->get('end_date', now()->toDateTimeString());

        $stats = $this->logService->getStatistics($startDate, $endDate);

        return response()->json(['code' => 0, 'message' => '获取成功', 'data' => $stats]);
    }

    /**
     * 清理过期日志
     *
     * DELETE /api/v1/admin/operation-logs/clean
     */
    public function clean(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 90);
        $days = max($days, 7); // 最少保留7天

        $count = $this->logService->cleanOldLogs($days);

        return response()->json(['code' => 0, 'message' => "已清理 {$count} 条过期日志"]);
    }
}
