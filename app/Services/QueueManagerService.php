<?php

namespace App\Services;

use App\Models\AnalysisTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class QueueManagerService
{
    /**
     * 队列连接类型
     */
    protected string $connection;

    /**
     * 队列名称
     */
    protected string $queueName;

    public function __construct()
    {
        $this->connection = config('queue.default', 'database');
        $this->queueName = config("queue.connections.{$this->connection}.queue", 'default');
    }

    /**
     * 获取队列健康状态报告
     */
    public function getHealthReport(): array
    {
        $pendingCount = AnalysisTask::where('status', 0)->count();
        $processingCount = AnalysisTask::where('status', 1)->count();
        $failedCount = AnalysisTask::where('status', 3)->count();

        $workerStatus = $this->checkWorkerStatus();
        $avgDuration = $this->getAverageDuration();

        // 预估完成时间（秒）
        $estimatedSeconds = $pendingCount > 0 && $workerStatus['process_count'] > 0
            ? ($pendingCount * $avgDuration) / $workerStatus['process_count']
            : 0;

        return [
            'worker' => $workerStatus,
            'jobs' => [
                'pending' => $pendingCount,
                'processing' => $processingCount,
                'failed' => $failedCount,
            ],
            'performance' => [
                'avg_duration_seconds' => $avgDuration,
                'estimated_completion_seconds' => (int) $estimatedSeconds,
                'recommended_workers' => $this->recommendWorkerCount($pendingCount, $avgDuration),
            ],
            'connection' => $this->connection,
            'queue_name' => $this->queueName,
        ];
    }

    /**
     * 检查工作者进程状态
     */
    public function checkWorkerStatus(): array
    {
        $isRunning = false;
        $processCount = 0;
        $processes = [];

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows: 使用 tasklist 和 wmic
                $output = [];
                @exec('wmic process where "name=\'php.exe\'" get commandline 2>&1', $cmdOutput);
                foreach ($cmdOutput as $line) {
                    if (stripos($line, 'queue:work') !== false || stripos($line, 'queue:listen') !== false) {
                        $isRunning = true;
                        $processCount++;
                        $processes[] = trim($line);
                    }
                }
            } else {
                // Linux/Unix: 使用 ps
                $output = [];
                @exec('ps aux | grep "queue:work\|queue:listen" | grep -v grep 2>&1', $output);
                $processCount = count($output);
                $isRunning = $processCount > 0;
                $processes = array_map('trim', $output);
            }
        } catch (\Exception $e) {
            Log::warning('检查队列工作者状态失败: ' . $e->getMessage());
        }

        return [
            'is_running' => $isRunning,
            'process_count' => $processCount,
            'processes' => array_slice($processes, 0, 10),
        ];
    }

    /**
     * 获取平均处理时长（秒）
     */
    public function getAverageDuration(): int
    {
        $avg = AnalysisTask::where('status', 2)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subHours(2))
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration')
            ->value('avg_duration');

        return (int) round($avg ?: 30); // 默认30秒
    }

    /**
     * 推荐工作者数量
     */
    public function recommendWorkerCount(int $pendingJobs, int $avgDuration): int
    {
        if ($pendingJobs === 0) {
            return 1;
        }

        // 目标：在30分钟内完成所有任务
        $targetSeconds = 1800;
        $neededWorkers = ceil(($pendingJobs * $avgDuration) / $targetSeconds);

        // 限制在合理范围内
        return max(1, min($neededWorkers, 16));
    }

    /**
     * 启动多个工作者进程
     */
    public function startWorkers(int $count = 4): array
    {
        $results = [];
        $started = 0;

        for ($i = 0; $i < $count; $i++) {
            if ($this->startSingleWorker()) {
                $started++;
            }
        }

        $results['started'] = $started;
        $results['total_requested'] = $count;
        $results['message'] = "已启动 {$started}/{$count} 个队列工作者进程";

        return $results;
    }

    /**
     * 启动单个工作进程
     */
    protected function startSingleWorker(): bool
    {
        try {
            $command = sprintf(
                'php %s/artisan queue:work %s --queue=%s --tries=1 --timeout=60 --memory=256 > /dev/null 2>&1 &',
                base_path(),
                $this->connection === 'database' ? 'database' : $this->connection,
                $this->queueName
            );

            if (PHP_OS_FAMILY === 'Windows') {
                $command = sprintf(
                    'start /B php %s/artisan queue:work %s --queue=%s --tries=1 --timeout=60 --memory=256',
                    base_path(),
                    $this->connection === 'database' ? 'database' : $this->connection,
                    $this->queueName
                );
            }

            Log::info('启动队列工作者: ' . $command);

            if (PHP_OS_FAMILY === 'Windows') {
                pclose(popen($command, 'r'));
            } else {
                exec($command);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('启动队列工作者失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 停止所有工作者进程
     */
    public function stopAllWorkers(): array
    {
        $killed = 0;

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows: 使用 taskkill
                $output = [];
                @exec('wmic process where "name=\'php.exe\' and commandline like \'%queue:work%\'" get processid 2>&1', $pids);
                foreach ($pids as $pid) {
                    $pid = trim($pid);
                    if (is_numeric($pid) && $pid > 0) {
                        @exec("taskkill /PID {$pid} /F 2>&1");
                        $killed++;
                    }
                }
            } else {
                // Linux/Unix: 使用 pkill
                $output = [];
                @exec('pkill -f "queue:work" 2>&1', $output, $returnCode);
                $killed = count($output);
            }
        } catch (\Exception $e) {
            Log::error('停止队列工作者失败: ' . $e->getMessage());
        }

        return [
            'killed' => $killed,
            'message' => "已停止 {$killed} 个队列工作者进程",
        ];
    }

    /**
     * 批量重试失败任务
     */
    public function batchRetryFailed(int $batchSize = 100): array
    {
        $tasks = AnalysisTask::where('status', 3)
            ->limit($batchSize)
            ->get();

        $retried = 0;
        foreach ($tasks as $task) {
            try {
                $task->update([
                    'status' => 0,
                    'error_message' => null,
                ]);
                \App\Jobs\ProcessAnalysisJob::dispatch($task);
                $retried++;
            } catch (\Exception $e) {
                Log::error("批量重试任务失败: {$task->task_no}", ['error' => $e->getMessage()]);
            }
        }

        return [
            'retried' => $retried,
            'remaining' => AnalysisTask::where('status', 3)->count(),
            'message' => "已重试 {$retried} 个失败任务",
        ];
    }

    /**
     * 清理已完成的历史任务
     */
    public function cleanupOldTasks(int $days = 7): int
    {
        $cutoff = now()->subDays($days);
        $deleted = AnalysisTask::where('status', 2)
            ->where('completed_at', '<', $cutoff)
            ->delete();

        return $deleted;
    }

    /**
     * 获取队列表统计（database 驱动时）
     */
    public function getQueueTableStats(): array
    {
        if ($this->connection !== 'database') {
            return ['available' => false];
        }

        try {
            $pending = DB::table('jobs')
                ->where('queue', $this->queueName)
                ->count();

            $reserved = DB::table('jobs')
                ->where('queue', $this->queueName)
                ->whereNotNull('reserved_at')
                ->count();

            return [
                'available' => true,
                'pending' => $pending,
                'reserved' => $reserved,
            ];
        } catch (\Exception $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }
}
