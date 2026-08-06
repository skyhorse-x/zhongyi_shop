<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAnalysisJob;
use App\Models\AnalysisTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QueueController extends Controller
{
    /**
     * 获取任务列表
     */
    public function index(Request $request)
    {
        $query = AnalysisTask::with('user');

        // 状态筛选
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // 类型筛选
        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        // 搜索（任务号或用户昵称）
        if ($request->has('keyword') && !empty($request->keyword)) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('task_no', 'like', "%{$keyword}%")
                    ->orWhereHas('user', function ($userQuery) use ($keyword) {
                        $userQuery->where('nickname', 'like', "%{$keyword}%")
                            ->orWhere('mobile', 'like', "%{$keyword}%");
                    });
            });
        }

        // 时间范围筛选
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $tasks = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $tasks,
        ]);
    }

    /**
     * 获取任务详情
     */
    public function show(string $taskNo)
    {
        $task = AnalysisTask::with('user')->where('task_no', $taskNo)->first();

        if (!$task) {
            return response()->json([
                'code' => 404,
                'message' => '任务不存在',
            ], 404);
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $task,
        ]);
    }

    /**
     * 获取队列统计信息
     */
    public function statistics()
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();

        $stats = [
            // 今日统计
            'today' => [
                'total' => AnalysisTask::where('created_at', '>=', $today)->count(),
                'pending' => AnalysisTask::where('created_at', '>=', $today)->where('status', 0)->count(),
                'processing' => AnalysisTask::where('created_at', '>=', $today)->where('status', 1)->count(),
                'completed' => AnalysisTask::where('created_at', '>=', $today)->where('status', 2)->count(),
                'failed' => AnalysisTask::where('created_at', '>=', $today)->where('status', 3)->count(),
            ],
            // 昨日统计
            'yesterday' => [
                'total' => AnalysisTask::whereBetween('created_at', [$yesterday, $today])->count(),
                'completed' => AnalysisTask::whereBetween('created_at', [$yesterday, $today])->where('status', 2)->count(),
                'failed' => AnalysisTask::whereBetween('created_at', [$yesterday, $today])->where('status', 3)->count(),
            ],
            // 总体统计
            'overall' => [
                'total' => AnalysisTask::count(),
                'pending' => AnalysisTask::where('status', 0)->count(),
                'processing' => AnalysisTask::where('status', 1)->count(),
                'completed' => AnalysisTask::where('status', 2)->count(),
                'failed' => AnalysisTask::where('status', 3)->count(),
            ],
            // 失败率
            'failure_rate' => $this->calculateFailureRate(),
        ];

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * 重试失败任务
     */
    public function retry(Request $request, string $taskNo)
    {
        $task = AnalysisTask::where('task_no', $taskNo)->first();

        if (!$task) {
            return response()->json([
                'code' => 404,
                'message' => '任务不存在',
            ], 404);
        }

        // 只允许重试失败的任务
        if ($task->status !== 3) {
            return response()->json([
                'code' => 400,
                'message' => '只能重试失败的任务',
            ], 400);
        }

        try {
            // 重置任务状态为待处理
            $task->update([
                'status' => 0,
                'error_message' => null,
            ]);

            // 重新派发任务
            ProcessAnalysisJob::dispatch($task);

            Log::info('Task manually retried by admin', [
                'task_no' => $task->task_no,
                'admin_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'code' => 0,
                'message' => '任务已重新派发',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retry task', [
                'task_no' => $task->task_no,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => 500,
                'message' => '重试失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 批量重试失败任务
     */
    public function retryAll(Request $request)
    {
        $tasks = AnalysisTask::where('status', 3)->get();

        $retried = 0;
        $failed = 0;

        foreach ($tasks as $task) {
            try {
                $task->update([
                    'status' => 0,
                    'error_message' => null,
                ]);
                ProcessAnalysisJob::dispatch($task);
                $retried++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to retry task in batch', [
                    'task_no' => $task->task_no,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Batch retry completed by admin', [
            'retried' => $retried,
            'failed' => $failed,
            'admin_id' => $request->user()->id ?? null,
        ]);

        return response()->json([
            'code' => 0,
            'message' => "批量重试完成：成功 {$retried} 个，失败 {$failed} 个",
            'data' => [
                'retried' => $retried,
                'failed' => $failed,
            ],
        ]);
    }

    /**
     * 获取失败任务列表
     */
    public function failedJobs(Request $request)
    {
        $tasks = AnalysisTask::with('user')
            ->where('status', 3)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $tasks,
        ]);
    }

    /**
     * 计算失败率
     */
    private function calculateFailureRate(): float
    {
        $total = AnalysisTask::where('status', '!=', 0)->where('status', '!=', 1)->count();
        $failed = AnalysisTask::where('status', 3)->count();

        if ($total === 0) {
            return 0;
        }

        return round(($failed / $total) * 100, 2);
    }

    /**
     * 队列工作者状态监控
     * 检查 queue:work 进程是否在运行
     */
    public function monitor()
    {
        $queueConnection = config('queue.default', 'database');
        $queueName = config('queue.connections.' . $queueConnection . '.queue', 'default');

        // 获取工作者进程状态
        $workerStatus = $this->checkWorkerStatus();

        // 获取任务统计
        $now = now();
        $last5Minutes = $now->copy()->subMinutes(5);
        $last1Hour = $now->copy()->subHour();

        $stats = [
            // 队列连接信息
            'connection' => $queueConnection,
            'queue_name' => $queueName,

            // 工作者状态
            'worker' => $workerStatus,

            // 任务统计（全部）
            'jobs' => [
                'pending' => AnalysisTask::where('status', 0)->count(),
                'processing' => AnalysisTask::where('status', 1)->count(),
                'completed' => AnalysisTask::where('status', 2)->count(),
                'failed' => AnalysisTask::where('status', 3)->count(),
                'total' => AnalysisTask::count(),
            ],

            // 最近5分钟处理速率
            'throughput' => [
                'completed_5min' => AnalysisTask::where('status', 2)
                    ->where('completed_at', '>=', $last5Minutes)->count(),
                'failed_5min' => AnalysisTask::where('status', 3)
                    ->where('updated_at', '>=', $last5Minutes)->count(),
                'avg_duration_seconds' => $this->calculateAverageDuration(),
            ],

            // 最近1小时统计
            'hourly' => [
                'total' => AnalysisTask::where('created_at', '>=', $last1Hour)->count(),
                'completed' => AnalysisTask::where('status', 2)
                    ->where('completed_at', '>=', $last1Hour)->count(),
                'failed' => AnalysisTask::where('status', 3)
                    ->where('updated_at', '>=', $last1Hour)->count(),
            ],

            // 数据库队列特有：jobs表状态
            'queue_table' => $this->getQueueTableStats(),

            // 更新时间
            'updated_at' => $now->toDateTimeString(),
        ];

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * 检查队列工作者进程状态
     */
    private function checkWorkerStatus(): array
    {
        $isRunning = false;
        $processCount = 0;
        $processes = [];

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows 系统
                $output = [];
                exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV /NH 2>&1', $output);
                foreach ($output as $line) {
                    if (stripos($line, 'php.exe') !== false) {
                        $processCount++;
                    }
                }
                // 检查是否有 queue:work 进程
                exec('wmic process where "name=\'php.exe\'" get commandline 2>&1', $cmdOutput);
                foreach ($cmdOutput as $line) {
                    if (stripos($line, 'queue:work') !== false || stripos($line, 'queue:listen') !== false) {
                        $isRunning = true;
                        $processes[] = trim($line);
                    }
                }
            } else {
                // Linux/Unix 系统
                $output = [];
                exec('ps aux | grep "queue:work\|queue:listen" | grep -v grep 2>&1', $output);
                $processCount = count($output);
                $isRunning = $processCount > 0;
                $processes = array_map('trim', $output);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check worker status: ' . $e->getMessage());
        }

        return [
            'is_running' => $isRunning,
            'process_count' => $processCount,
            'processes' => array_slice($processes, 0, 5), // 最多显示5个进程
        ];
    }

    /**
     * 获取数据库队列的 jobs 表统计
     */
    private function getQueueTableStats(): array
    {
        $connection = config('queue.default', 'database');

        if ($connection !== 'database') {
            return [
                'available' => false,
                'message' => '当前使用 ' . $connection . ' 队列驱动',
            ];
        }

        try {
            $pendingJobs = \DB::table('jobs')
                ->where('queue', config('queue.connections.database.queue', 'default'))
                ->count();

            $reservedJobs = \DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->where('queue', config('queue.connections.database.queue', 'default'))
                ->count();

            return [
                'available' => true,
                'pending_in_table' => $pendingJobs,
                'reserved_in_table' => $reservedJobs,
                'table_name' => 'jobs',
            ];
        } catch (\Exception $e) {
            return [
                'available' => false,
                'message' => 'jobs 表不存在或无法访问',
            ];
        }
    }

    /**
     * 计算平均处理时长（秒）
     */
    private function calculateAverageDuration(): int
    {
        $avgSeconds = AnalysisTask::where('status', 2)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subHour())
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration')
            ->value('avg_duration');

        return (int) round($avgSeconds ?: 0);
    }

    /**
     * 获取队列健康报告
     */
    public function health()
    {
        $queueManager = app(\App\Services\QueueManagerService::class);
        $report = $queueManager->getHealthReport();

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $report,
        ]);
    }

    /**
     * 批量重试失败任务
     */
    public function batchRetryFailed(Request $request)
    {
        $batchSize = $request->get('batch_size', 100);
        $queueManager = app(\App\Services\QueueManagerService::class);
        $result = $queueManager->batchRetryFailed($batchSize);

        return response()->json([
            'code' => 0,
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * 启动工作者进程
     */
    public function startWorkers(Request $request)
    {
        $count = $request->get('count', 4);
        $count = max(1, min($count, 16)); // 限制在 1-16 之间

        $queueManager = app(\App\Services\QueueManagerService::class);
        $result = $queueManager->startWorkers($count);

        return response()->json([
            'code' => 0,
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * 停止所有工作者进程
     */
    public function stopWorkers()
    {
        $queueManager = app(\App\Services\QueueManagerService::class);
        $result = $queueManager->stopAllWorkers();

        return response()->json([
            'code' => 0,
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * 清理历史已完成任务
     */
    public function cleanup(Request $request)
    {
        $days = $request->get('days', 7);
        $days = max(1, min($days, 90));

        $queueManager = app(\App\Services\QueueManagerService::class);
        $deleted = $queueManager->cleanupOldTasks($days);

        return response()->json([
            'code' => 0,
            'message' => "已清理 {$deleted} 条 {$days} 天前的已完成任务",
            'data' => ['deleted' => $deleted],
        ]);
    }
}
