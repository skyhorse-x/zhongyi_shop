# 后端设计

> **版本**：v1.1  
> **日期**：2026-07-28  
> **对应 ai.md 阶段**：第六阶段（后端设计）  
> **变更说明**：Laravel 13 + Laravel Queue（Redis驱动）替代RabbitMQ

---

## 1. 分层架构

```
┌─────────────────────────────────────────────────────────┐
│                      Controller 层                       │
│              接收请求、参数验证、调用Service              │
├─────────────────────────────────────────────────────────┤
│                       Service 层                         │
│              业务逻辑、事务管理、事件触发                 │
├─────────────────────────────────────────────────────────┤
│                     Repository 层                        │
│              数据访问、查询构建、模型操作                 │
├─────────────────────────────────────────────────────────┤
│                       Model 层                           │
│              数据模型、关系定义、访问器                   │
├─────────────────────────────────────────────────────────┤
│                      数据库                              │
└─────────────────────────────────────────────────────────┘
```

---

## 2. Controller层

### 2.1 用户端控制器（Api/V1/）

| Controller | 路径 | 职责 |
|------------|------|------|
| AuthController | Api/V1/AuthController.php | 注册、登录、验证码、微信授权 |
| UserController | Api/V1/UserController.php | 用户信息查询和更新 |
| TongueAnalysisController | Api/V1/TongueAnalysisController.php | 舌诊分析提交、状态查询 |
| FaceAnalysisController | Api/V1/FaceAnalysisController.php | 面诊分析提交、状态查询 |
| ConstitutionController | Api/V1/ConstitutionController.php | 体质测试题库、提交分析 |
| QaController | Api/V1/QaController.php | 健康问答会话、消息 |
| ReportController | Api/V1/ReportController.php | 分析报告获取 |
| HealthController | Api/V1/HealthController.php | 健康档案、历史记录、趋势 |
| PaymentController | Api/V1/PaymentController.php | 订单创建、支付回调 |
| PromoterController | Api/V1/PromoterController.php | 推广员开通、推广信息、佣金、提现 |
| PackageController | Api/V1/PackageController.php | 次数包列表、购买 |
| ArticleController | Api/V1/ArticleController.php | 健康资讯文章 |

### 2.2 管理端控制器（Admin/）

| Controller | 路径 | 职责 |
|------------|------|------|
| DashboardController | Admin/DashboardController.php | 数据概览 |
| UserController | Admin/UserController.php | 用户管理 |
| OrderController | Admin/OrderController.php | 订单管理 |
| AiController | Admin/AiController.php | AI模型管理、Prompt管理 |
| ConstitutionController | Admin/ConstitutionController.php | 体质测试题库管理 |
| PromoterController | Admin/PromoterController.php | 推广员管理、提现审核 |
| PackageController | Admin/PackageController.php | 次数包管理 |
| ArticleController | Admin/ArticleController.php | 文章管理 |
| SystemController | Admin/SystemController.php | 系统配置 |

### 2.3 控制器示例

```php
// app/Http/Controllers/Api/V1/TongueAnalysisController.php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TongueAnalysisRequest;
use App\Http\Resources\AnalysisResource;
use App\Services\Analysis\TongueAnalysisService;
use Illuminate\Http\JsonResponse;

class TongueAnalysisController extends Controller
{
    public function __construct(
        private TongueAnalysisService $analysisService
    ) {}

    /**
     * 提交舌诊分析任务
     */
    public function submit(TongueAnalysisRequest $request): JsonResponse
    {
        $task = $this->analysisService->submit(
            $request->user()->id,
            $request->validated('image_url')
        );

        return $this->success(new AnalysisResource($task));
    }

    /**
     * 查询分析状态
     */
    public function status(string $taskNo): JsonResponse
    {
        $task = $this->analysisService->getStatus($taskNo);
        return $this->success(new AnalysisResource($task));
    }
}
```

---

## 3. Service层

### 3.1 服务清单

| Service | 路径 | 职责 |
|---------|------|------|
| AuthService | Services/Auth/AuthService.php | 注册、登录逻辑、JWT生成 |
| UserService | Services/User/UserService.php | 用户信息管理 |
| TongueAnalysisService | Services/Analysis/TongueAnalysisService.php | 舌诊分析任务管理 |
| FaceAnalysisService | Services/Analysis/FaceAnalysisService.php | 面诊分析任务管理 |
| ConstitutionService | Services/Analysis/ConstitutionService.php | 体质测试分析 |
| QaService | Services/Qa/QaService.php | 健康问答服务 |
| ReportService | Services/Report/ReportService.php | 分析报告服务 |
| HealthRecordService | Services/Health/HealthRecordService.php | 健康档案服务 |
| PaymentService | Services/Payment/PaymentService.php | 支付订单、退款处理 |
| PromoterService | Services/Promote/PromoterService.php | 推广员开通、推广关系、佣金计算 |
| SmsService | Services/Notification/SmsService.php | 短信发送 |
| WechatService | Services/Wechat/WechatService.php | 微信授权、支付、消息 |
| AiService | Services/Ai/AiService.php | AI模型调用、结果解析 |

### 3.2 AI服务示例

```php
// app/Services/Ai/AiService.php
<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\AiLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * AI分析调用
     */
    public function analyze(string $imageUrl, string $type): array
    {
        $model = AiModel::where('type', 'vision')
            ->where('analysis_type', 'like', "%{$type}%")
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->first();

        if (!$model) {
            throw new \Exception('未找到可用的AI模型');
        }

        $startTime = microtime(true);

        try {
            $response = Http::timeout($model->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $model->api_key,
                    'Content-Type' => 'application/json',
                ])
                ->post($model->api_url, $this->buildVisionPayload($model, $imageUrl, $type));

            $result = $response->json();

            // 记录调用日志
            AiLog::create([
                'model_id' => $model->id,
                'type' => $type,
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $result['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $result['usage']['total_tokens'] ?? 0,
                'cost' => $this->calculateCost($model, $result['usage'] ?? []),
                'response_time' => intval((microtime(true) - $startTime) * 1000),
                'status' => 1,
            ]);

            return $this->parseResult($result, $type);

        } catch (\Exception $e) {
            AiLog::create([
                'model_id' => $model->id,
                'type' => $type,
                'status' => 0,
                'error' => $e->getMessage(),
                'response_time' => intval((microtime(true) - $startTime) * 1000),
            ]);
            throw $e;
        }
    }

    /**
     * 健康问答调用
     */
    public function chat(string $message, array $history = []): array
    {
        $model = AiModel::where('type', 'chat')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->first();

        $messages = $this->buildChatMessages($message, $history);

        $response = Http::timeout($model->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $model->api_key,
            ])
            ->post($model->api_url, [
                'model' => $model->model,
                'messages' => $messages,
                'stream' => false,
            ]);

        return $response->json();
    }
}
```

### 3.3 舌诊分析服务示例

```php
// app/Services/Analysis/TongueAnalysisService.php
<?php

namespace App\Services\Analysis;

use App\Jobs\Analysis\TongueAnalysisJob;
use App\Models\AnalysisTask;
use App\Models\AnalysisReport;
use App\Services\Ai\AiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TongueAnalysisService
{
    public function __construct(
        private AiService $aiService
    ) {}

    /**
     * 提交舌诊分析任务
     */
    public function submit(int $userId, string $imageUrl): AnalysisTask
    {
        $imageMd5 = md5_file($imageUrl);

        // 检查是否有相同图片的缓存结果
        $cacheKey = 'analysis:tongue:' . $imageMd5;
        if (Cache::has($cacheKey)) {
            $cachedResult = Cache::get($cacheKey);
            return $this->createTaskWithResult($userId, $imageUrl, $cachedResult);
        }

        // 创建新任务
        $task = AnalysisTask::create([
            'task_no' => 'TK' . date('Ymd') . Str::random(8),
            'user_id' => $userId,
            'type' => 'tongue',
            'image_url' => $imageUrl,
            'image_md5' => $imageMd5,
            'status' => 0,
        ]);

        // 分发队列任务
        TongueAnalysisJob::dispatch($task);

        return $task;
    }

    /**
     * 获取分析状态
     */
    public function getStatus(string $taskNo): AnalysisTask
    {
        return AnalysisTask::where('task_no', $taskNo)
            ->with('report')
            ->firstOrFail();
    }

    /**
     * 获取报告
     */
    public function getReport(int $userId, string $taskNo): AnalysisReport
    {
        $report = AnalysisReport::whereHas('task', function ($query) use ($taskNo, $userId) {
            $query->where('task_no', $taskNo)->where('user_id', $userId);
        })->firstOrFail();

        if (!$report->is_paid) {
            throw new \Exception('请先支付后查看完整报告');
        }

        return $report;
    }

    private function createTaskWithResult(int $userId, string $imageUrl, array $result): AnalysisTask
    {
        $task = AnalysisTask::create([
            'task_no' => 'TK' . date('Ymd') . Str::random(8),
            'user_id' => $userId,
            'type' => 'tongue',
            'image_url' => $imageUrl,
            'image_md5' => md5_file($imageUrl),
            'status' => 2,
            'result' => $result,
            'model' => $result['model'],
            'tokens' => $result['tokens'],
            'cost' => $result['cost'],
            'completed_at' => now(),
        ]);

        // 创建报告
        AnalysisReport::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'type' => 'tongue',
            ...$this->extractReportData($result),
        ]);

        return $task;
    }
}
```

### 3.3 推广员服务示例

```php
// app/Services/Promote/PromoterService.php
<?php

namespace App\Services\Promote;

use App\Models\Promoter;
use App\Models\User;
use Illuminate\Support\Str;

class PromoterService
{
    /**
     * 开通推广员（注册用户直接开通，无需审核）
     */
    public function activate(int $userId): Promoter
    {
        // 检查是否已是推广员
        $promoter = Promoter::where('user_id', $userId)->first();
        if ($promoter) {
            throw new \Exception('您已是推广员');
        }

        // 创建推广员记录
        $promoter = Promoter::create([
            'user_id' => $userId,
            'invite_code' => $this->generateInviteCode(),
            'level' => 1,
            'commission_rate' => 15.00,
            'status' => 1,
            'activated_at' => now(),
        ]);

        // 更新用户信息
        User::where('id', $userId)->update(['is_promoter' => 1]);

        return $promoter;
    }

    /**
     * 生成唯一推广码
     */
    private function generateInviteCode(): string
    {
        do {
            $code = 'code' . strtoupper(Str::random(6));
        } while (Promoter::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * 获取推广员信息
     */
    public function getInfo(int $userId): ?Promoter
    {
        return Promoter::where('user_id', $userId)->first();
    }

    /**
     * 计算佣金
     */
    public function calculateCommission(int $promoterId, int $orderAmount): float
    {
        $promoter = Promoter::find($promoterId);
        return round($orderAmount * $promoter->commission_rate / 100, 2);
    }
}
```

---

## 4. Repository层

### 4.1 仓库清单

| Repository | 路径 | 职责 |
|------------|------|------|
| UserRepository | Repositories/User/UserRepository.php | 用户数据访问 |
| AnalysisRepository | Repositories/Analysis/AnalysisRepository.php | 分析数据访问 |
| ConstitutionRepository | Repositories/Constitution/ConstitutionRepository.php | 体质测试数据访问 |
| QaRepository | Repositories/Qa/QaRepository.php | 问答数据访问 |
| OrderRepository | Repositories/Order/OrderRepository.php | 订单数据访问 |
| PromoterRepository | Repositories/Promote/PromoterRepository.php | 推广数据访问 |

---

## 5. Model层

### 5.1 模型清单

| Model | 表名 | 说明 |
|-------|------|------|
| User | users | 用户模型 |
| UserProfile | user_profiles | 用户详情 |
| AnalysisTask | analysis_tasks | 分析任务 |
| AnalysisReport | analysis_reports | 分析报告 |
| ConstitutionQuestion | constitution_questions | 体质测试题目 |
| ConstitutionAnswer | constitution_answers | 答题记录 |
| QaSession | health_qa_sessions | 问答会话 |
| QaMessage | health_qa_messages | 问答消息 |
| Order | orders | 订单 |
| Payment | payments | 支付记录 |
| Promoter | promoters | 推广员 |
| Commission | commissions | 佣金记录 |
| Withdraw | withdraws | 提现记录 |
| Package | product_packages | 次数包 |
| Article | articles | 文章 |
| AiModel | ai_models | AI模型配置 |
| AiLog | ai_logs | AI调用日志 |
| Admin | admins | 管理员 |

---

## 6. 中间件

### 6.1 中间件清单

| Middleware | 路径 | 职责 |
|------------|------|------|
| JwtAuth | Middleware/JwtAuth.php | JWT认证验证 |
| RateLimit | Middleware/RateLimit.php | 接口限流 |
| LogRequest | Middleware/LogRequest.php | 请求日志记录 |
| Cors | Middleware/Cors.php | 跨域处理 |
| AdminAuth | Middleware/AdminAuth.php | 管理员权限验证 |
| PromoterAuth | Middleware/PromoterAuth.php | 推广员权限验证 |

---

## 7. Laravel Queue（队列任务）

### 7.1 队列配置

```php
// config/queue.php
return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => true,
        ],
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
```

### 7.2 队列任务清单

| Job | 路径 | 队列 | 职责 |
|-----|------|------|------|
| TongueAnalysisJob | Jobs/Analysis/TongueAnalysisJob.php | analysis | 执行舌诊AI分析 |
| FaceAnalysisJob | Jobs/Analysis/FaceAnalysisJob.php | analysis | 执行面诊AI分析 |
| ConstitutionAnalysisJob | Jobs/Analysis/ConstitutionAnalysisJob.php | analysis | 执行体质分析 |
| PaymentNotifyJob | Jobs/Payment/PaymentNotifyJob.php | payment | 支付成功通知 |
| CommissionJob | Jobs/Promote/CommissionJob.php | promote | 佣金结算 |
| SmsJob | Jobs/Notification/SmsJob.php | sms | 短信发送 |
| WithdrawJob | Jobs/Promote/WithdrawJob.php | promote | 提现处理 |
| QaJob | Jobs/Qa/QaJob.php | qa | 健康问答处理 |

### 7.3 AI分析任务（舌诊）

```php
// app/Jobs/Analysis/TongueAnalysisJob.php
<?php

namespace App\Jobs\Analysis;

use App\Models\AnalysisTask;
use App\Models\AnalysisReport;
use App\Services\Ai\AiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TongueAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 最大尝试次数
     */
    public int $tries = 3;

    /**
     * 超时时间（秒）
     */
    public int $timeout = 60;

    /**
     * 重试间隔（秒）
     */
    public int $backoff = 5;

    public function __construct(
        private AnalysisTask $task
    ) {
        $this->onQueue('analysis');
    }

    public function handle(AiService $aiService): void
    {
        $this->task->update(['status' => 1, 'started_at' => now()]);

        try {
            $result = $aiService->analyze(
                $this->task->image_url,
                'tongue'
            );

            // 更新任务状态
            $this->task->update([
                'status' => 2,
                'result' => $result,
                'model' => $result['model'],
                'tokens' => $result['tokens'],
                'cost' => $result['cost'],
                'completed_at' => now(),
            ]);

            // 创建报告
            $reportData = $this->extractReportData($result);
            AnalysisReport::create(array_merge($reportData, [
                'task_id' => $this->task->id,
                'user_id' => $this->task->user_id,
                'type' => 'tongue',
            ]));

            // 缓存结果（7天）
            Cache::put('analysis:tongue:' . $this->task->image_md5, $result, 604800);

        } catch (\Exception $e) {
            $this->task->update([
                'status' => 3,
                'error_msg' => $e->getMessage(),
            ]);
            Log::error('舌诊AI分析失败', [
                'task_no' => $this->task->task_no,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 任务失败处理
     */
    public function failed(\Throwable $exception): void
    {
        $this->task->update([
            'status' => 3,
            'error_msg' => '分析失败：' . $exception->getMessage(),
        ]);
        
        Log::error('舌诊分析任务最终失败', [
            'task_no' => $this->task->task_no,
            'error' => $exception->getMessage(),
        ]);
    }

    private function extractReportData(array $result): array
    {
        return [
            'health_score' => $result['health_score'] ?? null,
            'tongue_color' => $result['tongue_color'] ?? null,
            'tongue_shape' => $result['tongue_shape'] ?? null,
            'tongue_coating' => $result['tongue_coating'] ?? null,
            'sublingual_vein' => $result['sublingual_vein'] ?? null,
            'tongue_analysis' => $result['tongue_analysis'] ?? null,
            'life_advice' => $result['life_advice'] ?? null,
            'diet_advice' => $result['diet_advice'] ?? null,
            'exercise_advice' => $result['exercise_advice'] ?? null,
            'precautions' => $result['precautions'] ?? null,
            'summary' => $result['summary'] ?? null,
            'content' => $result,
        ];
    }
}
```

### 7.4 健康问答任务

```php
// app/Jobs/Qa/QaJob.php
<?php

namespace App\Jobs\Qa;

use App\Models\QaSession;
use App\Models\QaMessage;
use App\Services\Ai\AiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        private QaSession $session,
        private string $question,
        private array $history = []
    ) {
        $this->onQueue('qa');
    }

    public function handle(AiService $aiService): void
    {
        try {
            $result = $aiService->chat($this->question, $this->history);

            // 保存AI回复
            QaMessage::create([
                'session_id' => $this->session->id,
                'user_id' => $this->session->user_id,
                'role' => 'assistant',
                'content' => $result['choices'][0]['message']['content'] ?? '',
                'tokens' => $result['usage']['total_tokens'] ?? 0,
                'cost' => $this->calculateCost($result['usage'] ?? []),
                'model' => $result['model'] ?? null,
            ]);

            // 更新会话
            $this->session->increment('message_count');
            $this->session->update(['last_question_at' => now()]);

        } catch (\Exception $e) {
            Log::error('健康问答处理失败', [
                'session_no' => $this->session->session_no,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

### 7.5 队列工作进程配置

```ini
# docker/supervisor/supervisord.conf
[supervisord]
nodaemon=true
user=root

# 舌诊/面诊/体质分析队列
[program:analysis-worker]
command=php /var/www/api/artisan queue:work redis --queue=analysis --sleep=3 --tries=3 --max-time=3600 --memory=256
numprocs=5
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/analysis-worker.log

# 健康问答队列
[program:qa-worker]
command=php /var/www/api/artisan queue:work redis --queue=qa --sleep=2 --tries=3 --max-time=3600 --memory=128
numprocs=3
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/qa-worker.log

# 支付通知队列
[program:payment-worker]
command=php /var/www/api/artisan queue:work redis --queue=payment --sleep=3 --tries=3 --max-time=3600
numprocs=3
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/payment-worker.log

# 推广结算队列
[program:promote-worker]
command=php /var/www/api/artisan queue:work redis --queue=promote --sleep=3 --tries=3 --max-time=3600
numprocs=2
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/promote-worker.log

# 短信通知队列
[program:sms-worker]
command=php /var/www/api/artisan queue:work redis --queue=sms --sleep=3 --tries=3 --max-time=3600
numprocs=2
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/sms-worker.log
```

---

## 8. Laravel Horizon（队列监控）

### 8.1 安装配置

```bash
composer require laravel/horizon
php artisan horizon:install
```

### 8.2 配置

```php
// config/horizon.php
return [
    'use' => 'redis',

    'waits' => [
        'redis:analysis' => 60,
        'redis:qa' => 30,
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['analysis', 'qa', 'payment', 'promote', 'sms'],
                'balance' => 'auto',
                'processes' => 10,
                'tries' => 3,
                'memory' => 256,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'analysis', 'qa'],
                'balance' => 'simple',
                'processes' => 3,
                'tries' => 3,
            ],
        ],
    ],
];
```

### 8.3 监控面板访问

```
URL: /horizon
权限: 管理员权限
功能: 实时监控队列状态、任务吞吐量、失败任务、延迟任务
```

---

## 9. 事件与监听

### 9.1 事件清单

| Event | 路径 | 说明 |
|-------|------|------|
| OrderPaid | Events/Order/OrderPaid.php | 订单支付成功 |
| AnalysisCompleted | Events/Analysis/AnalysisCompleted.php | 分析完成 |
| CommissionEarned | Events/Promote/CommissionEarned.php | 佣金产生 |

### 9.2 监听器清单

| Listener | 路径 | 监听事件 |
|----------|------|---------|
| UnlockReport | Listeners/Analysis/UnlockReport.php | OrderPaid |
| NotifyUser | Listeners/Notification/NotifyUser.php | OrderPaid |
| CalculateCommission | Listeners/Promote/CalculateCommission.php | OrderPaid |

---

## 10. 调度任务

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // 每5分钟处理超时订单
    $schedule->command('orders:expire')->everyFiveMinutes();

    // 每天凌晨结算佣金
    $schedule->command('commissions:settle')->dailyAt('02:00');

    // 每天清理过期缓存
    $schedule->command('cache:clear-expired')->dailyAt('03:00');

    // 每周清理AI日志
    $schedule->command('ai-logs:clean')->weekly();

    // 监控队列状态
    $schedule->command('horizon:snapshot')->everyFiveMinutes();
}
```

---

## 11. 请求验证

```php
// app/Http/Requests/TongueAnalysisRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TongueAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image_url' => 'required|url|max:500',
        ];
    }
}

// app/Http/Requests/ConstitutionSubmitRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConstitutionSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => 'required|array|min:30',
            'answers.*.question_id' => 'required|integer|exists:constitution_questions,id',
            'answers.*.answer' => 'required|string|in:A,B,C,D',
        ];
    }
}
```

---

## 12. 资源转换

```php
// app/Http/Resources/AnalysisResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'task_no' => $this->task_no,
            'type' => $this->type,
            'status' => $this->status,
            'status_text' => $this->getStatusText(),
            'summary' => $this->status == 2 ? $this->result['summary'] ?? '' : null,
            'is_paid' => $this->report?->is_paid ?? false,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function getStatusText(): string
    {
        return match($this->status) {
            0 => '待处理',
            1 => '处理中',
            2 => '已完成',
            3 => '失败',
            default => '未知',
        };
    }
}
```

---

> **相关文档**：
> - [系统架构设计](03-architecture.md)
> - [数据库设计](04-database.md)
> - [API 设计](05-api.md)
> - [安全设计](10-security.md)
> - [DevOps](15-devops.md)
