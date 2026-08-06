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
     * 提交分析任务（直接调用豆包API，同步返回结果）
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:tongue,face,palm',
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

        // 按次数计费模式：扣除次数即视为已授权查看报告
        $isPaid = 1;

        // 扣除分析次数（事务内行级锁，含流水记录）
        $timesService = app(AnalysisTimesService::class);
        $analysisType = match($validated['type']) {
            'tongue' => '舌诊分析',
            'face' => '面诊分析',
            'palm' => '手相分析',
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
            'status' => 1, // 直接设为处理中
            'is_paid' => $isPaid,
            'started_at' => now(),
        ]);

        try {
            // ========== 直接调用豆包API（同步） ==========
            $aiService = app(AiService::class);

            $result = match ($validated['type']) {
                'tongue' => $hasImages
                    ? $aiService->analyzeTongue($imageUrls, (int) $validated['gender'], (int) $validated['age'])
                    : $aiService->analyzeTongueByText($validated['text'] ?? '', (int) $validated['gender'], (int) $validated['age']),
                'face' => $hasImages
                    ? $aiService->analyzeFace($imageUrls[0] ?? '', (int) $validated['gender'], (int) $validated['age'])
                    : $aiService->analyzeFaceByText($validated['text'] ?? '', (int) $validated['gender'], (int) $validated['age']),
                'palm' => $aiService->analyzeTongueByText($validated['text'] ?? '', (int) $validated['gender'], (int) $validated['age']), // 手相暂用文本分析
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
            }

            AnalysisReport::create($reportData);

            Log::info('Analysis task completed (direct API call)', [
                'task_no' => $task->task_no,
                'type' => $task->type,
                'mode' => $hasImages ? 'image' : 'text',
            ]);

            // ========== 直接返回完整结果给前端 ==========
            return response()->json([
                'code' => 0,
                'message' => '分析完成',
                'data' => [
                    'task_no' => $task->task_no,
                    'status' => 2, // 已完成
                    'type' => $task->type,
                    'health_score' => $healthScore,
                    'summary' => $summary,
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

            // 失败时返还次数
            try {
                app(AnalysisTimesService::class)->refundTimes(
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
                app(NotificationService::class)->sendSystemMessage(
                    $task->user_id,
                    'AI 分析失败',
                    "您的分析任务（{$task->task_no}）失败，已自动返还 1 次分析次数。",
                    ['type' => 'reminder']
                );
            } catch (\Throwable $ne) {}

            Log::error('Analysis task failed (direct API call)', [
                'task_no' => $task->task_no,
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
                    : '分析失败，已自动返还分析次数，请稍后重试',
                'error_type' => $isConfigError ? 'ai_not_configured' : 'internal_error',
                'data' => [
                    'task_no' => $task->task_no,
                    'status' => 3, // 失败
                ],
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
