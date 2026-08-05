<?php

namespace App\Services;

use App\Models\AdminOperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 管理员操作日志服务
 */
class AdminOperationLogService
{
    /**
     * 敏感字段（记录时脱敏）
     */
    private array $sensitiveFields = [
        'password',
        'password_confirmation',
        'old_password',
        'new_password',
        'token',
        'api_key',
        'secret',
    ];

    /**
     * 记录操作日志
     *
     * @param string $module 模块名称
     * @param string $action 操作动作
     * @param Request|null $request 请求对象
     * @param array $extra 额外数据
     * @param int|null $startTime 开始时间（用于计算耗时）
     * @param bool $status 操作状态
     * @param string|null $errorMessage 错误信息
     * @return AdminOperationLog|null
     */
    public function log(
        string $module,
        string $action,
        ?Request $request = null,
        array $extra = [],
        ?int $startTime = null,
        bool $status = true,
        ?string $errorMessage = null
    ): ?AdminOperationLog {
        try {
            $admin = Auth::user();
            $request = $request ?? request();

            $params = $this->getParams($request, $extra);
            $duration = $startTime ? (int) ((microtime(true) - $startTime) * 1000) : 0;

            return AdminOperationLog::create([
                'admin_id'       => $admin?->id ?? 0,
                'admin_name'     => $admin?->name ?? '系统',
                'module'         => $module,
                'action'         => $action,
                'method'         => $request->method(),
                'url'            => $request->fullUrl(),
                'params'         => $params,
                'ip'             => $request->ip(),
                'user_agent'     => substr($request->userAgent() ?? '', 0, 500),
                'response_code'  => null,
                'response_data'  => null,
                'duration_ms'    => $duration,
                'status'         => $status ? 1 : 0,
                'error_message'  => $errorMessage ? substr($errorMessage, 0, 1000) : null,
            ]);
        } catch (Throwable $e) {
            Log::error('记录操作日志失败: ' . $e->getMessage(), [
                'module' => $module,
                'action' => $action,
            ]);
            return null;
        }
    }

    /**
     * 记录操作日志（带响应）
     */
    public function logWithResponse(
        string $module,
        string $action,
        int $responseCode,
        ?array $responseData,
        ?Request $request = null,
        array $extra = [],
        ?int $startTime = null
    ): ?AdminOperationLog {
        $status = $responseCode >= 200 && $responseCode < 400;
        $errorMessage = !$status ? ($responseData['message'] ?? "HTTP {$responseCode}") : null;

        return $this->log(
            $module,
            $action,
            $request,
            $extra,
            $startTime,
            $status,
            $errorMessage
        );
    }

    /**
     * 获取请求参数（脱敏处理）
     */
    private function getParams(Request $request, array $extra = []): array
    {
        $params = $request->except($this->sensitiveFields);

        // 合并额外数据
        if (!empty($extra)) {
            $params = array_merge(['_extra' => $extra], $params);
        }

        // 截断过长内容
        foreach ($params as $key => $value) {
            if (is_string($value) && strlen($value) > 1000) {
                $params[$key] = substr($value, 0, 1000) . '... [已截断]';
            }
        }

        return $params;
    }

    /**
     * 查询操作日志
     */
    public function query(array $filters = [], int $perPage = 20)
    {
        $query = AdminOperationLog::query()->orderByDesc('id');

        // 按管理员筛选
        if (!empty($filters['admin_id'])) {
            $query->byAdmin((int) $filters['admin_id']);
        }

        // 按模块筛选
        if (!empty($filters['module'])) {
            $query->byModule($filters['module']);
        }

        // 按操作动作筛选
        if (!empty($filters['action'])) {
            $query->where('action', 'like', "%{$filters['action']}%");
        }

        // 按状态筛选
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->byStatus((int) $filters['status']);
        }

        // 按时间范围筛选
        if (!empty($filters['start_date'])) {
            $startDate = $filters['start_date'];
            $endDate = $filters['end_date'] ?? now()->toDateTimeString();
            $query->betweenDates($startDate, $endDate);
        }

        // 按关键词搜索
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('admin_name', 'like', "%{$keyword}%")
                    ->orWhere('action', 'like', "%{$keyword}%")
                    ->orWhere('url', 'like', "%{$keyword}%")
                    ->orWhere('ip', 'like', "%{$keyword}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * 获取操作模块列表
     */
    public function getModules(): array
    {
        return AdminOperationLog::query()
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->toArray();
    }

    /**
     * 获取操作统计
     */
    public function getStatistics(string $startDate, string $endDate): array
    {
        $query = AdminOperationLog::query()
            ->betweenDates($startDate, $endDate);

        return [
            'total_operations' => (clone $query)->count(),
            'success_count'    => (clone $query)->byStatus(1)->count(),
            'fail_count'       => (clone $query)->byStatus(0)->count(),
            'active_admins'    => (clone $query)->distinct('admin_id')->count('admin_id'),
            'avg_duration_ms'  => (int) ((clone $query)->avg('duration_ms') ?? 0),
            'modules'          => (clone $query)->selectRaw('module, count(*) as count')
                ->groupBy('module')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->toArray(),
        ];
    }

    /**
     * 清理过期日志
     */
    public function cleanOldLogs(int $days = 90): int
    {
        $cutoffDate = now()->subDays($days);
        return AdminOperationLog::query()
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }
}
