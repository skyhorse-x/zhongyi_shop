<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\AnalysisTask;
use App\Models\SystemConfig;
use App\Services\AiService;
use App\Services\AnalysisTimesService;
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
        $wechatService = SystemConfig::getValue('wechat_service', '');
        $siteName = SystemConfig::getValue('site_name', 'AI 中医健康助手');
        $siteUrl = SystemConfig::getValue('site_url', config('app.url'));
        $disableMobileAuth = SystemConfig::getValue('disable_mobile_auth', '0');
        $showXianyuRecharge = SystemConfig::getValue('show_xianyu_recharge', '1');

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'analysis_mode' => $analysisMode,
                'analysis_price' => floatval($analysisPrice),
                'wechat_service' => $wechatService,
                'site_name' => $siteName,
                'site_url' => $siteUrl,
                'disable_mobile_auth' => $disableMobileAuth,
                'show_xianyu_recharge' => $showXianyuRecharge,
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
     * 提交分析（直接请求AI接口，完成后扣除积分）
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:tongue,face,palm,eye',
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

        // 先检查积分是否充足
        $timesService = app(AnalysisTimesService::class);
        $user = $request->user();
        $checkResult = $timesService->checkTimes($user, $validated['type']);
        if (!$checkResult) {
            return response()->json([
                'code' => 402,
                'message' => '积分不足，请先充值',
                'error_type' => 'insufficient_times',
            ], 402);
        }

        // 直接调用AI接口
        try {
            $aiService = app(AiService::class);
            $result = match ($validated['type']) {
                'tongue' => $hasImages
                    ? $aiService->analyzeTongue($validated['image_urls'] ?? [], (int) $validated['gender'], (int) $validated['age'])
                    : $aiService->analyzeTongueByText($validated['text'] ?? '', (int) $validated['gender'], (int) $validated['age']),
                'face' => $hasImages
                    ? $aiService->analyzeFace($validated['image_urls'][0] ?? '', (int) $validated['gender'], (int) $validated['age'])
                    : $aiService->analyzeFaceByText($validated['text'] ?? '', (int) $validated['gender'], (int) $validated['age']),
                'palm' => $hasImages
                    ? $aiService->analyzePalm($validated['image_urls'] ?? [], (int) $validated['gender'], (int) $validated['age'])
                    : $aiService->analyzePalmByText($validated['text'] ?? '', (int) $validated['gender'], (int) $validated['age']),
                'eye' => $hasImages
                    ? $aiService->analyzeEye($validated['image_urls'] ?? [], (int) $validated['gender'], (int) $validated['age'])
                    : $aiService->analyzeEyeByText($validated['text'] ?? '', (int) $validated['gender'], (int) $validated['age']),
                default => throw new \InvalidArgumentException("未知的分析类型: {$validated['type']}"),
            };

            // 扣除积分
            $analysisType = match($validated['type']) {
                'tongue' => '舌诊分析',
                'face' => '面部分析',
                'palm' => '手相分析',
                'eye' => '眼部分析',
                default => 'AI分析',
            };
            $deductResult = $timesService->deductTimes(
                $user,
                1,
                'use',
                "AI{$analysisType}"
            );
            if (!$deductResult) {
                throw new \RuntimeException('积分扣除失败');
            }

            // 计算健康评分
            $content = $result['content'] ?? '';
            $healthScore = $this->calculateHealthScore($content);
            $summary = $this->extractSummary($content);

            return response()->json([
                'code' => 0,
                'message' => '分析完成',
                'data' => [
                    'type' => $validated['type'],
                    'health_score' => $healthScore,
                    'summary' => $summary,
                    'result' => [
                        'content' => $content,
                        'summary' => $summary,
                        'health_score' => $healthScore,
                        'model' => $result['model'] ?? '',
                        'usage' => $result['usage'] ?? [],
                        'mode' => $hasImages ? 'image' : 'text',
                    ],
                    'created_at' => now(),
                ],
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $isConfigError = str_contains($message, 'API Key')
                || str_contains($message, 'API key')
                || str_contains($message, '未配置');

            return response()->json([
                'code' => $isConfigError ? 503 : 500,
                'message' => $isConfigError
                    ? $message
                    : '分析失败，请稍后重试',
                'error_type' => $isConfigError ? 'ai_not_configured' : 'internal_error',
            ], $isConfigError ? 503 : 500);
        }
    }

    /**
     * 提取摘要
     */
    protected function extractSummary(string $content): string
    {
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
     */
    protected function calculateHealthScore(string $content): int
    {
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
     * 从AI分析内容中提取指定字段的值
     */
    protected function extractField(string $content, string $fieldName): string
    {
        // 匹配 "- 字段名：值" 或 "- 字段名:值" 格式
        $pattern = '/[-•]\s*' . preg_quote($fieldName, '/') . '[：:]\s*(.+)/u';
        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }
        // 匹配 "字段名：值" 格式
        $pattern2 = '/' . preg_quote($fieldName, '/') . '[：:]\s*(.+)/u';
        if (preg_match($pattern2, $content, $matches)) {
            return trim($matches[1]);
        }
        return '';
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

        // 任务仍在处理中：返回 code=1，前端继续轮询
        if ($task->status === 1) {
            return response()->json([
                'code' => 1,
                'message' => '分析中，请稍候',
                'data' => [
                    'task_no' => $task->task_no,
                    'status' => 1,
                    'type' => $task->type,
                    'created_at' => $task->created_at,
                ],
            ]);
        }

        // 任务失败
        if ($task->status === 3) {
            return response()->json([
                'code' => 500,
                'message' => $task->error_message ?: '分析失败，请稍后重试',
                'data' => [
                    'task_no' => $task->task_no,
                    'status' => 3,
                ],
            ], 500);
        }

        // 报告已完成，直接返回结果（积分已在分析完成时扣除）
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

    /**
     * 提交分析反馈（有用/无用）
     */
    public function feedback(Request $request, string $taskNo)
    {
        $validated = $request->validate([
            'type' => 'required|in:useful,useless',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        $task = AnalysisTask::where('task_no', $taskNo)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$task) {
            return response()->json([
                'code' => 404,
                'message' => '分析任务不存在',
            ], 404);
        }

        // 保存或更新反馈
        \App\Models\AnalysisFeedback::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'task_id' => $task->id,
            ],
            [
                'type' => $validated['type'],
                'rating' => $validated['rating'] ?? null,
            ]
        );

        return response()->json([
            'code' => 0,
            'message' => '反馈已提交',
        ]);
    }
}
