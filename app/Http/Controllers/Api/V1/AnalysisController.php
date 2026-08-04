<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAnalysisJob;
use App\Models\AnalysisTask;
use App\Models\SystemConfig;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnalysisController extends Controller
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * 获取上传预签名URL
     */
    public function getUploadUrl(Request $request)
    {
        $validated = $request->validate([
            'filename' => 'required|string',
            'content_type' => 'required|string',
        ]);

        // 优先使用本地存储（开发环境），生产环境可配置对象存储
        $storageDriver = config('filesystems.default', 'public');
        $fileName = md5($validated['filename'] . time() . uniqid()) . '.' . pathinfo($validated['filename'], PATHINFO_EXTENSION);
        $path = 'analysis/' . date('Ymd') . '/' . $fileName;

        if ($storageDriver === 'local' || $storageDriver === 'public') {
            // 本地存储：返回直接上传的API端点
            $fileUrl = url('storage/' . $path);
            $uploadUrl = url('api/v1/analysis/upload-direct');
        } else {
            // 对象存储：生成预签名URL（需要对应SDK）
            $fileUrl = url('storage/' . $path);
            $uploadUrl = url('api/v1/analysis/upload-direct');
            Log::info('Object storage driver detected, using local fallback', ['driver' => $storageDriver]);
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'upload_url' => $uploadUrl,
                'file_url' => $fileUrl,
                'expire_in' => 300,
                'method' => 'POST',
                'headers' => [],
            ],
        ]);
    }

    /**
     * 获取分析配置
     */
    public function getConfig(Request $request)
    {
        $analysisMode = SystemConfig::getValue('analysis_mode', 'paid');
        $analysisPrice = SystemConfig::getValue('analysis_price', '9.99');

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'analysis_mode' => $analysisMode,
                'analysis_price' => floatval($analysisPrice),
            ],
        ]);
    }

    /**
     * 上传图片文件
     */
    public function uploadImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:10240', // 最大10MB
        ]);

        try {
            $file = $request->file('image');
            $path = $file->store('analysis', 'public');
            $url = asset('storage/' . $path);

            return response()->json([
                'code' => 0,
                'message' => '上传成功',
                'data' => [
                    'image_url' => $url,
                    'path' => $path,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'code' => 500,
                'message' => '图片上传失败',
            ], 500);
        }
    }

    /**
     * 提交分析任务
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:tongue,face',
            'gender' => 'required|in:1,2',
            'age' => 'required|integer|min:1|max:150',
            'image_urls' => 'nullable|array',
            'image_urls.*' => 'nullable|url',
            'text' => 'nullable|string|max:1000',
        ]);

        // 至少需要图片或文字描述
        $hasImages = !empty($validated['image_urls']);
        $hasText = !empty(trim($validated['text'] ?? ''));
        if (!$hasImages && !$hasText) {
            return response()->json([
                'code' => 400,
                'message' => '请上传图片或输入症状描述',
            ], 400);
        }

        try {
            // 按次数计费模式：扣除次数即视为已授权查看报告
            $isPaid = 1;

            // 扣除分析次数（事务内行级锁，含流水记录）
            $timesService = app(\App\Services\AnalysisTimesService::class);
            $analysisType = $validated['type'] === 'tongue' ? '舌诊分析' : '面诊分析';
            $deducted = $timesService->deductTimes(
                $request->user(),
                1,
                'use',
                "AI{$analysisType}"
            );

            if (!$deducted) {
                return response()->json([
                    'code' => 402,
                    'message' => '分析次数不足，请先购买次数包',
                    'error_type' => 'insufficient_times',
                ], 402);
            }

            $imageUrls = $validated['image_urls'] ?? [];
            $task = AnalysisTask::create([
                'task_no' => 'TK' . date('Ymd') . substr(md5(uniqid()), 0, 8),
                'user_id' => $request->user()->id,
                'type' => $validated['type'],
                'gender' => (int) $validated['gender'],
                'age' => (int) $validated['age'],
                'image_url' => $imageUrls[0] ?? null,
                'image_urls' => $imageUrls ?: null,
                'text' => $validated['text'] ?? null,
                'image_md5' => $hasImages ? md5(implode(',', $imageUrls)) : null,
                'status' => 0, // 排队中
                'is_paid' => $isPaid,
            ]);

            // 异步派发 AI 分析任务（立即返回，前端跳转结果页轮询）
            ProcessAnalysisJob::dispatch($task);

            return response()->json([
                'code' => 0,
                'message' => '分析任务已提交，正在处理中',
                'data' => [
                    'task_no' => $task->task_no,
                    'status' => 0,
                    'estimated_time' => 30,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Analysis task submission failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 把"缺 API Key"等可操作错误以更友好的方式透出
            $message = $e->getMessage();
            $isConfigError = str_contains($message, 'API Key')
                || str_contains($message, 'API key')
                || str_contains($message, '未配置');

            return response()->json([
                'code' => $isConfigError ? 503 : 500,
                'message' => $isConfigError
                    ? $message  // 配置类问题：透出真实提示，便于排查
                    : '任务提交失败，请稍后重试',
                'error_type' => $isConfigError ? 'ai_not_configured' : 'internal_error',
            ], $isConfigError ? 503 : 500);
        }
    }

    /**
     * 查询分析状态
     */
    public function status(Request $request, string $taskNo)
    {
        $task = AnalysisTask::where('task_no', $taskNo)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$task) {
            return response()->json([
                'code' => 404,
                'message' => '任务不存在',
            ], 404);
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'task_no' => $task->task_no,
                'status' => $task->status,
                'summary' => $task->result['summary'] ?? null,
                'is_paid' => $task->is_paid,
            ],
        ]);
    }

    /**
     * 获取完整报告
     */
    public function report(Request $request, string $taskNo)
    {
        $task = AnalysisTask::where('task_no', $taskNo)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$task) {
            return response()->json([
                'code' => 404,
                'message' => '任务不存在',
            ], 404);
        }

        // 按次数计费模式：次数已扣除，直接返回完整报告
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'task_no' => $task->task_no,
                'type' => $task->type,
                'health_score' => $task->result['health_score'] ?? 85,
                'result' => $task->result,
                'created_at' => $task->created_at,
            ],
        ]);
    }

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
}
