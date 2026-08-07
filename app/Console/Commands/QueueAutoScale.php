<?php

namespace App\Console\Commands;

use App\Services\QueueManagerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueueAutoScale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:auto-scale
                            {--min=1 : 最小工作者进程数}
                            {--max=8 : 最大工作者进程数}
                            {--check-interval=30 : 检查间隔(秒)}
                            {--threshold=50 : 扩容阈值(排队任务数)}
                            {--scale-down-threshold=10 : 缩容阈值(排队任务数)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根据队列负载自动扩缩容工作者进程';

    /**
     * The queue manager service.
     */
    protected QueueManagerService $queueManager;

    /**
     * Current child PIDs.
     */
    protected array $childPids = [];

    /**
     * Execute the console command.
     */
    public function handle(QueueManagerService $queueManager): int
    {
        $this->queueManager = $queueManager;

        $minWorkers = (int) $this->option('min');
        $maxWorkers = (int) $this->option('max');
        $checkInterval = (int) $this->option('check-interval');
        $scaleUpThreshold = (int) $this->option('threshold');
        $scaleDownThreshold = (int) $this->option('scale-down-threshold');

        $this->info("========================================");
        $this->info("  队列自动扩缩容管理器");
        $this->info("========================================");
        $this->info("最小工作者: {$minWorkers}");
        $this->info("最大工作者: {$maxWorkers}");
        $this->info("检查间隔: {$checkInterval}秒");
        $this->info("扩容阈值: {$scaleUpThreshold}个排队任务");
        $this->info("缩容阈值: {$scaleDownThreshold}个排队任务");
        $this->info("----------------------------------------");

        // 注册信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }

        // 初始启动最小数量的工作者
        $currentWorkers = $minWorkers;
        $this->scaleWorkers($currentWorkers);

        $this->info("初始启动 {$currentWorkers} 个工作者进程");

        while (true) {
            sleep($checkInterval);

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // 清理已退出的子进程
            $this->reapChildren();

            // 获取当前队列状态
            $health = $queueManager->getHealthReport();
            $pendingJobs = $health['jobs']['pending'];
            $activeWorkers = count($this->childPids);

            $this->info("[" . now()->format('Y-m-d H:i:s') . "] 排队: {$pendingJobs}, 活跃工作者: {$activeWorkers}");

            // 扩容判断
            if ($pendingJobs > $scaleUpThreshold && $currentWorkers < $maxWorkers) {
                $newWorkers = min(
                    $currentWorkers + ceil($pendingJobs / $scaleUpThreshold),
                    $maxWorkers
                );
                $toAdd = $newWorkers - $currentWorkers;
                $this->info("↗ 扩容: 增加 {$toAdd} 个工作者 ({$currentWorkers} → {$newWorkers})");
                $this->scaleWorkers($toAdd);
                $currentWorkers = $newWorkers;
            }

            // 缩容判断
            if ($pendingJobs < $scaleDownThreshold && $currentWorkers > $minWorkers) {
                $newWorkers = max(
                    $currentWorkers - 1,
                    $minWorkers
                );
                $toRemove = $currentWorkers - $newWorkers;
                $this->info("↘ 缩容: 减少 {$toRemove} 个工作者 ({$currentWorkers} → {$newWorkers})");
                $this->killWorkers($toRemove);
                $currentWorkers = $newWorkers;
            }
        }

        return 0;
    }

    /**
     * 启动指定数量的工作者
     */
    protected function scaleWorkers(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                Log::error('无法 fork 子进程');
                continue;
            } elseif ($pid === 0) {
                // 子进程运行工作者
                $this->runWorker();
                exit(0);
            } else {
                $this->childPids[] = $pid;
            }
        }
    }

    /**
     * 停止指定数量的工作者
     */
    protected function killWorkers(int $count): void
    {
        $toKill = array_slice($this->childPids, 0, $count);

        foreach ($toKill as $pid) {
            posix_kill($pid, SIGTERM);
            $this->childPids = array_diff($this->childPids, [$pid]);
        }
    }

    /**
     * 清理已退出的子进程
     */
    protected function reapChildren(): void
    {
        foreach ($this->childPids as $key => $pid) {
            $result = pcntl_waitpid($pid, $status, WNOHANG);
            if ($result === $pid || $result === -1) {
                unset($this->childPids[$key]);
            }
        }
        $this->childPids = array_values($this->childPids);
    }

    /**
     * 运行单个工作进程
     */
    protected function runWorker(): void
    {
        $connection = config('queue.default', 'database');
        $queue = config("queue.connections.{$connection}.queue", 'default');

        $command = [
            PHP_BINARY,
            base_path('artisan'),
            'queue:work',
            $connection,
            '--queue=' . $queue,
            '--tries=1',
            '--timeout=60',
            '--memory=256',
            '--sleep=0',
            '--max-time=600', // 每10分钟重启一次，防止内存泄漏
        ];

        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (is_resource($process)) {
            proc_close($process);
        }
    }

    /**
     * 处理信号
     */
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->info("接收到信号 {$signal}，正在停止所有工作者...");

        foreach ($this->childPids as $pid) {
            posix_kill($pid, SIGTERM);
        }

        // 等待子进程退出
        foreach ($this->childPids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $this->info("所有工作者已停止。");
        exit(0);
    }
}
