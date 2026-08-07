<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\AnalysisTask;
use App\Models\SystemConfig;
use App\Services\AiService;
use App\Services\AnalysisTimesService;
use App\Services\NotificationService;
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
     * 提交分析任务（立即返回任务号，后台异步处理AI分析）
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

        // 初始状态为未支付，积分将在报告生成后扣除
        $isPaid = 0;

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
            'status' => 1, // 处理中
            'is_paid' => $isPaid,
            'started_at' => now(),
        ]);

        // 立即返回任务号，后台异步处理AI分析
        $taskNo = $task->task_no;
        $taskId = $task->id;

        // 使用 fastcgi_finish_request 实现异步处理
        if (function_exists('fastcgi_finish_request')) {
            // 立即返回响应给前端
            $response = response()->json([
                'code' => 0,
                'message' => '分析任务已提交',
                'data' => [
                    'task_no' => $taskNo,
                    'status' => 1, // 处理中
                    'type' => $task->type,
                    'created_at' => $task->created_at,
                ],
            ]);

            // 发送响应并关闭连接
            $response->send();
            fastcgi_finish_request();

            // 后台继续处理AI分析
            $this->processAnalysisAsync($taskId, $validated, $hasImages, $hasText);
        } else {
            // 非 FastCGI 环境（如 artisan serve），同步处理但快速返回
            try {
                $result = $this->processAnalysisSync($task, $validated, $hasImages, $hasText);
                return response()->json([
                    'code' => 0,
                    'message' => '分析完成',
                    'data' => [
                        'task_no' => $taskNo,
                        'status' => 2, // 已完成
                        'type' => $task->type,
                        'health_score' => $result['health_score'],
                        'summary' => $result['summary'],
                        'result' => $task->result,
                        'created_at' => $task->created_at,
                    ],
                ]);
            } catch (\Exception $e) {
                // 更新任务状态为失败
                $task->update([
                    'status' => 3,
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);

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
                    'data' => [
                        'task_no' => $taskNo,
                        'status' => 3, // 失败
                    ],
                ], $isConfigError ? 503 : 500);
            }
        }

        // FastCGI 环境下已经返回了响应，这里不会执行到
        exit;
    }

    /**
     * 异步处理AI分析（FastCGI 环境）
     */
    private function processAnalysisAsync(int $taskId, array $validated, bool $hasImages, bool $hasText): void
    {
        try {
            $task = AnalysisTask::find($taskId);
            if (!$task) {
                Log::error('Task not found for async processing', ['task_id' => $taskId]);
                return;
            }

            $result = $this->processAnalysisSync($task, $validated, $hasImages, $hasText);

            Log::info('Async analysis completed', [
                'task_no' => $task->task_no,
                'type' => $task->type,
            ]);
        } catch (\Exception $e) {
            $task = AnalysisTask::find($taskId);
            if ($task) {
                $task->update([
                    'status' => 3,
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);

                // 通知用户
                try {
                    app(NotificationService::class)->sendSystemMessage(
                        $task->user_id,
                        'AI 分析失败',
                        "您的分析任务（{$task->task_no}）失败，请稍后重试。",
                        ['type' => 'reminder']
                    );
                } catch (\Throwable $ne) {}
            }

            Log::error('Async analysis failed', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 同步处理AI分析
     */
    private function processAnalysisSync($task, array $validated, bool $hasImages, bool $hasText): array
    {
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

        // 计算健康评分
        $content = $result['content'] ?? '';
        $healthScore = $this->calculateHealthScore($content);
        $summary = $this->extractSummary($content);

        // 更新任务为已完成
        $task->update([
            'status' => 2, // 已完成
            'result' => [
                'content' => $content,
                'summary' => $summary,
                'health_score' => $healthScore,
                'model' => $result['model'] ?? '',
                'usage' => $result['usage'] ?? [],
                'mode' => $hasImages ? 'image' : 'text',
            ],
            'completed_at' => now(),
        ]);

        // 创建健康档案报告
        $reportData = [
            'task_id' => $task->id,
            'user_id' => $task->user_id,
            'type' => $task->type,
            'gender' => $task->gender,
            'age' => $task->age,
            'health_score' => $healthScore,
            'summary' => $summary,
            'content' => ['text' => $content],
            'is_paid' => $task->is_paid ?? false,
        ];

        // 根据类型提取特定的分析字段
        if ($task->type === 'tongue') {
            $reportData['tongue_color'] = $this->extractField($content, '舌色');
            $reportData['tongue_shape'] = $this->extractField($content, '舌形');
            $reportData['tongue_coating'] = $this->extractField($content, '舌苔');
            $reportData['sublingual_vein'] = $this->extractField($content, '舌下');
            $reportData['tongue_analysis'] = $content;
        } elseif ($task->type === 'face') {
            $reportData['face_color'] = $this->extractField($content, '面色');
            $reportData['lip_color'] = $this->extractField($content, '唇色');
            $reportData['eye_analysis'] = $this->extractField($content, '眼部');
            $reportData['face_analysis'] = $content;
        } elseif ($task->type === 'palm') {
            $reportData['life_line'] = $this->extractField($content, '生命线');
            $reportData['career_line'] = $this->extractField($content, '事业线');
            $reportData['marriage_line'] = $this->extractField($content, '婚姻线');
            $reportData['palm_analysis'] = $content;
        } elseif ($task->type === 'eye') {
            $reportData['eye_color'] = $this->extractField($content, '眼色');
            $reportData['eye_white'] = $this->extractField($content, '眼白');
            $reportData['dark_circle'] = $this->extractField($content, '黑眼圈');
            $reportData['eye_analysis'] = $content;
        }

        AnalysisReport::create($reportData);

        return [
            'health_score' => $healthScore,
            'summary' => $summary,
        ];
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

        // 报告已完成，扣除积分（仅首次查看时扣除）
        if ($task->is_paid === 0) {
            $timesService = app(AnalysisTimesService::class);
            $analysisType = match($task->type) {
                'tongue' => '舌诊分析',
                'face' => '面部分析',
                'palm' => '手相分析',
                'eye' => '眼部分析',
                default => 'AI分析',
            };
            
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
                    'data' => [
                        'task_no' => $task->task_no,
                        'status' => 2,
                    ],
                ], 402);
            }

            // 标记为已支付
            $task->update(['is_paid' => 1]);
        }

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
