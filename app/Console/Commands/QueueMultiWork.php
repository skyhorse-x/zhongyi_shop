<?php

namespace App\Console\Commands;

use App\Services\QueueManagerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QueueMultiWork extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:multi-work
                            {--workers=4 : 启动的工作者进程数量}
                            {--queue=default : 队列名称}
                            {--tries=1 : 任务最大重试次数}
                            {--timeout=60 : 单个任务超时时间(秒)}
                            {--memory=256 : 单个进程内存限制(MB)}
                            {--daemon : 守护进程模式}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '启动多个队列工作者进程以提高处理速度';

    /**
     * The queue manager service.
     */
    protected QueueManagerService $queueManager;

    /**
     * Child process PIDs.
     */
    protected array $childPids = [];

    /**
     * Execute the console command.
     */
    public function handle(QueueManagerService $queueManager): int
    {
        $this->queueManager = $queueManager;

        $workerCount = (int) $this->option('workers');
        $queue = $this->option('queue');
        $tries = (int) $this->option('tries');
        $timeout = (int) $this->option('timeout');
        $memory = (int) $this->option('memory');
        $daemon = $this->option('daemon');

        $this->info("========================================");
        $this->info("  多进程队列工作者管理器");
        $this->info("========================================");
        $this->info("进程数量: {$workerCount}");
        $this->info("队列名称: {$queue}");
        $this->info("重试次数: {$tries}");
        $this->info("超时时间: {$timeout}秒");
        $this->info("内存限制: {$memory}MB");
        $this->info("----------------------------------------");

        // 检查当前队列状态
        $health = $queueManager->getHealthReport();
        $this->info("当前排队任务: {$health['jobs']['pending']}");
        $this->info("处理中任务: {$health['jobs']['processing']}");
        $this->info("失败任务: {$health['jobs']['failed']}");
        $this->info("平均处理时长: {$health['performance']['avg_duration_seconds']}秒");
        $this->info("推荐工作者数: {$health['performance']['recommended_workers']}");
        $this->info("----------------------------------------");

        if ($daemon) {
            return $this->runDaemonMode($workerCount, $queue, $tries, $timeout, $memory);
        }

        return $this->runForegroundMode($workerCount, $queue, $tries, $timeout, $memory);
    }

    /**
     * 运行前台模式（适用于开发环境）
     */
    protected function runForegroundMode(int $workerCount, string $queue, int $tries, int $timeout, int $memory): int
    {
        $this->info("正在启动 {$workerCount} 个工作者进程...");

        // 使用 Laravel 的 Process  facade 来管理子进程
        $processes = [];

        for ($i = 0; $i < $workerCount; $i++) {
            $process = $this->createWorkerProcess($queue, $tries, $timeout, $memory);
            if ($process) {
                $processes[] = $process;
                $this->info("  ✓ 工作者 #" . ($i + 1) . " 已启动 (PID: {$process->getPid()})");
            } else {
                $this->error("  ✗ 工作者 #" . ($i + 1) . " 启动失败");
            }
        }

        if (empty($processes)) {
            $this->error("没有成功启动任何工作者进程！");
            return 1;
        }

        $this->info("----------------------------------------");
        $this->info("已启动 " . count($processes) . " 个工作者进程，按 Ctrl+C 停止...");

        // 等待所有子进程
        foreach ($processes as $process) {
            $process->wait();
        }

        $this->info("所有工作者进程已停止。");
        return 0;
    }

    /**
     * 运行守护进程模式（适用于生产环境）
     */
    protected function runDaemonMode(int $workerCount, string $queue, int $tries, int $timeout, int $memory): int
    {
        $this->info("正在以守护进程模式启动...");

        // 注册信号处理
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }

        $processes = [];

        for ($i = 0; $i < $workerCount; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->error("无法 fork 子进程 #{$i}");
                continue;
            } elseif ($pid === 0) {
                // 子进程
                $this->runWorker($queue, $tries, $timeout, $memory);
                exit(0);
            } else {
                // 父进程
                $this->childPids[] = $pid;
                $processes[] = $pid;
                $this->info("  ✓ 工作者 #" . ($i + 1) . " 已启动 (PID: {$pid})");
            }
        }

        $this->info("----------------------------------------");
        $this->info("守护进程模式已启动，工作者 PIDs: " . implode(', ', $processes));
        $this->info("使用 'kill " . implode(' ', $processes) . "' 停止所有工作者");

        // 等待子进程
        while (count($this->childPids) > 0) {
            $pid = pcntl_wait($status);
            if ($pid > 0) {
                $this->childPids = array_diff($this->childPids, [$pid]);
                Log::info("工作者进程已退出: PID {$pid}");
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            // 检查是否需要重启退出的进程
            if (count($this->childPids) < $workerCount) {
                $this->info("检测到进程退出，正在重启...");
                $pid = pcntl_fork();
                if ($pid === 0) {
                    $this->runWorker($queue, $tries, $timeout, $memory);
                    exit(0);
                } elseif ($pid > 0) {
                    $this->childPids[] = $pid;
                }
            }

            sleep(1);
        }

        return 0;
    }

    /**
     * 运行单个工作进程
     */
    protected function runWorker(string $queue, int $tries, int $timeout, int $memory): void
    {
        $connection = config('queue.default', 'database');

        $command = [
            PHP_BINARY,
            base_path('artisan'),
            'queue:work',
            $connection,
            '--queue=' . $queue,
            '--tries=' . $tries,
            '--timeout=' . $timeout,
            '--memory=' . $memory,
            '--sleep=0',
            '--max-time=3600',
        ];

        proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        // 子进程会一直运行
        while (true) {
            sleep(1);
        }
    }

    /**
     * 创建工作者进程
     */
    protected function createWorkerProcess(string $queue, int $tries, int $timeout, int $memory)
    {
        try {
            $connection = config('queue.default', 'database');

            $process = new \Symfony\Component\Process\Process([
                PHP_BINARY,
                base_path('artisan'),
                'queue:work',
                $connection,
                '--queue=' . $queue,
                '--tries=' . $tries,
                '--timeout=' . $timeout,
                '--memory=' . $memory,
                '--sleep=0',
            ]);

            $process->setTimeout(null);
            $process->start();

            return $process;
        } catch (\Exception $e) {
            Log::error('创建工作者进程失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 处理信号
     */
    public function handleSignal(int $signal): void
    {
        $this->info("接收到信号 {$signal}，正在停止所有工作者...");

        foreach ($this->childPids as $pid) {
            posix_kill($pid, SIGTERM);
        }

        // 等待子进程退出
        foreach ($this->childPids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        exit(0);
    }
}
