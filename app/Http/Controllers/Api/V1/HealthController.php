<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalysisTask;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    /**
     * 获取分析历史
     */
    public function history(Request $request)
    {
        $query = AnalysisTask::where('user_id', $request->user()->id);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $tasks = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 10));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $tasks,
        ]);
    }

    /**
     * 获取健康趋势
     */
    public function trend(Request $request)
    {
        $days = $request->get('days', 30);

        $tasks = AnalysisTask::where('user_id', $request->user()->id)
            ->where('status', 2)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get();

        $defaultScore = (int) (\App\Models\SystemConfig::where('key', 'default_health_score')->value('value') ?: 80);

        $dates = [];
        $scores = [];

        foreach ($tasks as $task) {
            $dates[] = $task->created_at->format('Y-m-d');
            $scores[] = $task->result['health_score'] ?? $defaultScore;
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
        $tasks = AnalysisTask::where('user_id', $request->user()->id)
            ->where('type', 'constitution')
            ->where('status', 2)
            ->orderBy('created_at', 'desc')
            ->get();

        $latestType = $tasks->first()?->result['constitution_type'] ?? '未知';

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'constitution_type' => $latestType,
                'test_count' => $tasks->count(),
                'last_test_at' => $tasks->first()?->created_at,
                'history' => $tasks->map(function ($task) {
                    return [
                        'task_no' => $task->task_no,
                        'constitution_type' => $task->result['constitution_type'] ?? '',
                        'scores' => $task->result['scores'] ?? [],
                        'created_at' => $task->created_at,
                    ];
                }),
            ],
        ]);
    }
}
