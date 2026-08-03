<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
                'status' => 0,
                'is_paid' => $isPaid,
            ]);

            // 同步执行AI分析（生产环境建议使用队列）
            $this->processAnalysis($task);

            return response()->json([
                'code' => 0,
                'message' => '任务已提交',
                'data' => [
                    'task_no' => $task->task_no,
                    'status' => $task->fresh()->status,
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
     * 处理AI分析
     *
     * @param AnalysisTask $task
     * @return void
     */
    protected function processAnalysis(AnalysisTask $task): void
    {
        // 幂等：已完成的任务不再处理（防止队列重试重复扣次数）
        if ($task->status === 2) {
            Log::info('Analysis task already completed, skip', ['task_no' => $task->task_no]);
            return;
        }

        try {
            $task->update(['status' => 1]); // 处理中

            $hasImages = !empty($task->image_url);

            // 调用AI服务（根据是否上传图片选择不同分析方法）
            $result = match ($task->type) {
                'tongue' => $hasImages
                    ? $this->aiService->analyzeTongue($task->image_urls ?: [$task->image_url], $task->gender, $task->age)
                    : $this->aiService->analyzeTongueByText($task->text ?? '', $task->gender, $task->age),
                'face' => $hasImages
                    ? $this->aiService->analyzeFace($task->image_url, $task->gender, $task->age)
                    : $this->aiService->analyzeFaceByText($task->text ?? '', $task->gender, $task->age),
                default => throw new \InvalidArgumentException("Unknown analysis type: {$task->type}"),
            };

            // 更新任务结果
            $task->update([
                'status' => 2, // 已完成
                'result' => [
                    'content' => $result['content'] ?? '',
                    'summary' => $this->extractSummary($result['content'] ?? ''),
                    'health_score' => $this->calculateHealthScore($result['content'] ?? ''),
                    'model' => $result['model'] ?? '',
                    'usage' => $result['usage'] ?? [],
                    'mode' => $hasImages ? 'image' : 'text',
                ],
                'completed_at' => now(),
            ]);

            Log::info('Analysis task completed', [
                'task_no' => $task->task_no,
                'type' => $task->type,
                'mode' => $hasImages ? 'image' : 'text',
            ]);
        } catch (\Exception $e) {
            $task->update(['status' => 3]); // 失败

            // 失败/超时/异常：返还次数（避免用户资金损失）
            try {
                app(\App\Services\AnalysisTimesService::class)->refundTimes(
                    $task->user,
                    1,
                    'refund',
                    "AI分析失败返还：{$e->getMessage()}"
                );
                Log::info('Analysis times refunded due to failure', [
                    'task_no' => $task->task_no,
                    'user_id' => $task->user_id,
                ]);
            } catch (\Throwable $refundErr) {
                Log::error('退还分析次数失败', [
                    'task_no' => $task->task_no,
                    'refund_error' => $refundErr->getMessage(),
                ]);
            }

            // 通知用户
            try {
                app(\App\Services\NotificationService::class)->sendSystemMessage(
                    $task->user_id,
                    'AI 分析失败',
                    "您的分析任务（{$task->task_no}）失败，已自动返还 1 次分析次数。",
                    ['type' => 'reminder']
                );
            } catch (\Throwable $ne) {}

            Log::error('Analysis task failed', [
                'task_no' => $task->task_no,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 提取摘要
     *
     * @param string $content
     * @return string
     */
    protected function extractSummary(string $content): string
    {
        // 提取第一行非空内容作为摘要
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !str_starts_with($line, '#')) {
                return mb_substr($line, 0, 100);
            }
        }
        return '分析完成';
    }

    /**
     * 计算健康评分
     *
     * @param string $content
     * @return int
     */
    protected function calculateHealthScore(string $content): int
    {
        // 简单的评分逻辑，实际应用中可以更复杂
        $score = 75;

        if (str_contains($content, '平和质')) {
            $score = 90;
        } elseif (str_contains($content, '气虚') || str_contains($content, '阳虚')) {
            $score = 65;
        } elseif (str_contains($content, '湿热') || str_contains($content, '痰湿')) {
            $score = 60;
        }

        return $score;
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
