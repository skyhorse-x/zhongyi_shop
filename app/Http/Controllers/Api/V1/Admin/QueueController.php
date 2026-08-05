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
}
