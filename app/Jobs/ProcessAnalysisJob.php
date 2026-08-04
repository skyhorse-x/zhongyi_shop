<?php

namespace App\Jobs;

use App\Models\AnalysisTask;
use App\Services\AiService;
use App\Services\AnalysisTimesService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务最大重试次数
     */
    public int $tries = 2;

    /**
     * 任务超时时间（秒）
     */
    public int $timeout = 120;

    /**
     * 分析任务模型
     */
    protected AnalysisTask $task;

    /**
     * Create a new job instance.
     */
    public function __construct(AnalysisTask $task)
    {
        $this->task = $task;
    }

    /**
     * Execute the job.
     */
    public function handle(AiService $aiService): void
    {
        $task = $this->task;

        // 幂等：已完成的任务不再处理（防止重试重复扣次数）
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
                    ? $aiService->analyzeTongue($task->image_urls ?: [$task->image_url], $task->gender, $task->age)
                    : $aiService->analyzeTongueByText($task->text ?? '', $task->gender, $task->age),
                'face' => $hasImages
                    ? $aiService->analyzeFace($task->image_url, $task->gender, $task->age)
                    : $aiService->analyzeFaceByText($task->text ?? '', $task->gender, $task->age),
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

            Log::error('Analysis task failed', [
                'task_no' => $task->task_no,
                'error' => $e->getMessage(),
            ]);

            // 重新抛出异常，让队列系统处理重试
            throw $e;
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
}
