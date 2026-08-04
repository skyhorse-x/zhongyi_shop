# 后端设计

> **版本**：v2.0  
> **日期**：2026-08-04  
> **对应 ai.md 阶段**：第六阶段（后端设计）  
> **变更说明**：根据实际代码修正（移除Repository/Jobs/Events/Horizon，修正Controller/Service/Middleware列表，Sanctum认证）

---

## 1. 分层架构

```
┌─────────────────────────────────────────────────────────┐
│                      Controller 层                       │
│              接收请求、参数验证、调用Service              │
├─────────────────────────────────────────────────────────┤
│                       Service 层                         │
│              业务逻辑、事务管理                          │
├─────────────────────────────────────────────────────────┤
│                       Model 层                           │
│              数据模型、关系定义、查询构建                 │
├─────────────────────────────────────────────────────────┤
│                      数据库                              │
└─────────────────────────────────────────────────────────┘
```

**说明**：
- **无 Repository 层**：直接使用 Laravel Eloquent Model
- **无 FormRequest**：验证逻辑内联在 Controller 中
- **无 Resource 类**：直接返回数组或 Model->toArray()
- **AI 分析为同步处理**：不使用队列 Jobs
- **无 Events/Listeners**：业务逻辑直接在 Service 中处理

---

## 2. Controller 层

### 2.1 用户端控制器（Api/V1/）

| Controller | 文件路径 | 职责 |
|------------|---------|------|
| AuthController | Api/V1/AuthController.php | 注册、登录、验证码、微信授权、Token刷新 |
| UserController | Api/V1/UserController.php | 用户信息、订单、余额明细 |
| AnalysisController | Api/V1/AnalysisController.php | 舌诊/面诊分析提交、状态查询、报告获取、历史记录 |
| ConstitutionController | Api/V1/ConstitutionController.php | 体质测试题库、提交分析、获取报告 |
| QaController | Api/V1/QaController.php | 健康问答会话、消息 |
| HealthController | Api/V1/HealthController.php | 健康档案、历史记录、趋势、体质档案 |
| PaymentController | Api/V1/PaymentController.php | 订单创建、支付回调、支付方式 |
| PromoterController | Api/V1/PromoterController.php | 推广员开通、推广信息、佣金、提现、邀请追踪 |
| PackageController | Api/V1/PackageController.php | 次数包列表、购买 |
| ArticleController | Api/V1/ArticleController.php | 健康资讯文章 |
| CustomerServiceController | Api/V1/CustomerServiceController.php | 客服会话、消息 |
| CustomerServiceRatingController | Api/V1/CustomerServiceRatingController.php | 客服评价 |
| SystemMessageController | Api/V1/SystemMessageController.php | 系统消息 |
| FeedbackController | Api/V1/FeedbackController.php | 用户反馈 |
| AppealController | Api/V1/AppealController.php | AI申诉 |
| RefundController | Api/V1/RefundController.php | 退款 |
| ConfigController | Api/V1/ConfigController.php | 系统配置 |
| XianyuProductController | Api/V1/XianyuProductController.php | 闲鱼商品（用户端） |

### 2.2 管理端控制器

| Controller | 文件路径 | 职责 |
|------------|---------|------|
| AdminController | Api/V1/AdminController.php | **统一管理后台**（仪表盘、用户/订单/AI/推广/文章/系统配置等所有后台管理功能） |
| AnalyticsController | Api/V1/Admin/AnalyticsController.php | 数据分析 BI |
| AppealController | Api/V1/Admin/AppealController.php | 申诉审核 |
| CustomerServiceController | Api/V1/Admin/CustomerServiceController.php | 客服会话查看、消息发送 |
| CustomerServiceManageController | Api/V1/Admin/CustomerServiceManageController.php | 客服管理（话术、系统消息、配置） |
| CustomerServiceRatingController | Api/V1/Admin/CustomerServiceRatingController.php | 客服评价管理 |
| FeedbackController | Api/V1/Admin/FeedbackController.php | 用户反馈管理 |
| RefundController | Api/V1/Admin/RefundController.php | 退款审核 |
| RiskController | Api/V1/Admin/RiskController.php | 风控规则、事件、黑名单管理 |
| XianyuProductController | Api/V1/Admin/XianyuProductController.php | 闲鱼商品管理 |

### 2.3 控制器示例

```php
// app/Http/Controllers/Api/V1/AuthController.php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Promoter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * 用户注册（账号+密码 或 手机号+密码）
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:account,mobile',
            'username' => 'required_if:type,account|unique:users',
            'mobile' => 'required_if:type,mobile|unique:users',
            'password' => 'required|min:6|confirmed',
            'invite_code' => 'nullable|exists:promoters,invite_code',
        ]);

        $user = User::create([
            'username' => $request->username,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
            'nickname' => '用户' . substr($request->mobile ?? $request->username, -4),
            'parent_id' => $this->getInviterId($request->invite_code),
        ]);

        // 自动开通推广员
        Promoter::create([
            'user_id' => $user->id,
            'invite_code' => $this->generateInviteCode(),
            'level' => 1,
            'commission_rate' => 15.00,
            'status' => 1,
        ]);
        $user->update(['is_promoter' => 1]);

        $token = $user->createToken('app')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => $user,
            'is_promoter' => true,
            'invite_code' => $promoter->invite_code ?? null,
            'invite_url' => url('?code=' . ($promoter->invite_code ?? '')),
        ]);
    }

    /**
     * 登录（账号或手机号 + 密码）
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'account' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->account)
            ->orWhere('mobile', $request->account)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('账号或密码错误', 401);
        }

        $token = $user->createToken('app')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * 微信登录（暂未实现）
     */
    public function wechatLogin(Request $request): JsonResponse
    {
        return $this->error('微信登录功能尚未实现，请使用账号+密码登录', 501);
    }

    /**
     * 刷新Token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $token = $user->createToken('app')->plainTextToken;
        return $this->success(['token' => $token]);
    }

    /**
     * 退出登录
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, '退出成功');
    }
}
```

---

## 3. Service 层

### 3.1 服务清单

| Service | 文件路径 | 职责 |
|---------|---------|------|
| AiService.php | Services/AiService.php | AI模型调用、结果解析、视觉分析 |
| AnalysisTimesService.php | Services/AnalysisTimesService.php | 分析次数管理（消耗、赠送、查询） |
| AnalyticsService.php | Services/AnalyticsService.php | 数据统计（漏斗、留存、收入、增长等） |
| CacheService.php | Services/CacheService.php | 统一缓存服务（File驱动） |
| LlmService.php | Services/LlmService.php | 大模型调用封装 |
| NotificationService.php | Services/NotificationService.php | 消息触达中心 |
| PaymentService.php | Services/PaymentService.php | 支付订单、支付成功处理 |
| RefundService.php | Services/RefundService.php | 退款服务 |
| RiskControlService.php | Services/RiskControlService.php | 风控引擎（规则检查、黑名单） |
| SystemConfigService.php | Services/SystemConfigService.php | 系统配置服务 |

### 3.2 AI 服务示例

```php
// app/Services/AiService.php
<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\AiLog;
use Illuminate\Support\Facades\Http;

class AiService
{
    /**
     * 舌诊分析
     */
    public function analyzeTongue(array $imageUrls, int $gender, int $age): array
    {
        $model = $this->getVisionModel('tongue');
        $result = $this->callVisionApi($model, $imageUrls, 'tongue', $gender, $age);
        $this->logCall($model, 'tongue', $result);
        return $result;
    }

    /**
     * 面诊分析
     */
    public function analyzeFace(array $imageUrls, int $gender, int $age): array
    {
        $model = $this->getVisionModel('face');
        $result = $this->callVisionApi($model, $imageUrls, 'face', $gender, $age);
        $this->logCall($model, 'face', $result);
        return $result;
    }

    /**
     * 健康问答
     */
    public function chat(string $message, array $history = []): array
    {
        $model = $this->getChatModel();
        $result = $this->callChatApi($model, $message, $history);
        $this->logCall($model, 'qa', $result);
        return $result;
    }

    /**
     * 获取视觉模型
     */
    private function getVisionModel(string $type): AiModel
    {
        return AiModel::where('type', 'vision')
            ->where('analysis_type', 'like', "%{$type}%")
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->firstOrFail();
    }

    /**
     * 调用视觉API
     */
    private function callVisionApi(AiModel $model, array $imageUrls, string $type, int $gender, int $age): array
    {
        $response = Http::timeout($model->timeout)
            ->withToken($model->api_key)
            ->post($model->api_url, [
                'model' => $model->model,
                'messages' => $this->buildVisionMessages($imageUrls, $type, $gender, $age),
            ]);

        return $response->json();
    }
}
```

### 3.3 缓存服务示例

```php
// app/Services/CacheService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * 统一走 file cache（项目不使用 Redis）
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $value = $callback();
        Cache::put($key, $value, $ttl);
        return $value;
    }

    /**
     * 清除缓存
     */
    public function forget(string $key): void
    {
        Cache::forget($key);
    }
}
```

---

## 4. Model 层

### 4.1 模型清单

| Model | 表名 | 说明 |
|-------|------|------|
| User | users | 用户模型 |
| UserProfile | user_profiles | 用户详情 |
| Admin | admins | 管理员 |
| AnalysisTask | analysis_tasks | 分析任务 |
| AnalysisReport | analysis_reports | 分析报告 |
| ConstitutionQuestion | constitution_questions | 体质测试题目 |
| HealthQaSession | health_qa_sessions | 问答会话 |
| HealthQaMessage | health_qa_messages | 问答消息 |
| Order | orders | 订单 |
| Payment | payments | 支付记录 |
| Refund | refunds | 退款单 |
| Promoter | promoters | 推广员 |
| Commission | commissions | 佣金记录 |
| Withdraw | withdraws | 提现记录 |
| ProductPackage | product_packages | 次数包 |
| Article | articles | 文章 |
| AiModel | ai_models | AI模型配置 |
| AiLog | ai_logs | AI调用日志 |
| SystemConfig | system_configs | 系统配置 |
| SystemMessage | system_messages | 系统消息 |
| Feedback | feedbacks | 用户反馈 |
| AnalysisAppeal | analysis_appeals | AI诊断申诉 |
| CustomerServiceSession | customer_service_sessions | 客服会话 |
| CustomerServiceMessage | customer_service_messages | 客服消息 |
| CustomerServicePhrase | customer_service_phrases | 客服常用话术 |
| CustomerServiceRating | customer_service_ratings | 客服评价 |
| CustomerServiceConfig | customer_service_configs | 客服配置 |
| RiskRule | risk_rules | 风控规则 |
| RiskEvent | risk_events | 风控事件 |
| RiskBlacklist | risk_blacklists | 风控黑名单 |
| XianyuProduct | xianyu_products | 闲鱼商品 |
| UserAnalysisLog | user_analysis_logs | 分析次数流水 |
| UserBalanceLog | user_balance_logs | 余额流水 |
| BalanceInsufficientLog | balance_insufficient_logs | 余额不足记录 |
| InviteClick | invite_clicks | 邀请点击记录 |
| InviteRegistration | invite_registrations | 邀请注册记录 |

---

## 5. 中间件

### 5.1 中间件清单

| Middleware | 文件路径 | 职责 |
|------------|---------|------|
| AdminMiddleware | Middleware/AdminMiddleware.php | 管理员权限验证（检查Token和admin标记） |
| SuperAdminMiddleware | Middleware/SuperAdminMiddleware.php | 超级管理员权限验证（id===1 或 role_id===1） |
| AuthenticateOrAdmin | Middleware/AuthenticateOrAdmin.php | 用户或管理员均可访问 |
| RequestLogMiddleware | Middleware/RequestLogMiddleware.php | 请求日志记录 |
| RiskControlMiddleware | Middleware/RiskControlMiddleware.php | 风控检查（支持规则：withdraw等） |
| VisitCounterMiddleware | Middleware/VisitCounterMiddleware.php | 访问计数 |

### 5.2 中间件示例

```php
// app/Http/Middleware/AdminMiddleware.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user || !$user->is_admin) {
            return response()->json(['code' => 1003, 'message' => '无权限'], 403);
        }

        return $next($request);
    }
}

// app/Http/Middleware/SuperAdminMiddleware.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        // 超级管理员：id===1 或 role_id===1
        if (!$user || ($user->id !== 1 && $user->role_id !== 1)) {
            return response()->json(['code' => 1003, 'message' => '需要超级管理员权限'], 403);
        }

        return $next($request);
    }
}
```

---

## 6. 命令行（Console Commands）

### 6.1 命令清单

| Command | 文件路径 | 职责 |
|---------|---------|------|
| SettleCommissions | Console/Commands/SettleCommissions.php | 结算冻结佣金 |
| CleanDuplicateData | Console/Commands/CleanDuplicateData.php | 清理重复数据 |
| ClearPlaceholderApiKeys | Console/Commands/ClearPlaceholderApiKeys.php | 清理占位API Keys |
| ResetAdminPassword | Console/Commands/ResetAdminPassword.php | 重置管理员密码 |

### 6.2 调度任务

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// 每天凌晨结算佣金
Schedule::command('commission:settle')->dailyAt('02:00');
```

---

## 7. 认证机制（Sanctum）

### 7.1 认证流程

```
用户登录 → AuthController::login() → User::createToken() → 返回 plainTextToken
                                                     ↓
请求接口 → Authorization: Bearer {token} → Sanctum 中间件验证 → 获取用户
```

### 7.2 Token 生成

```php
// 用户端登录
$token = $user->createToken('app', ['*'])->plainTextToken;

// 管理端登录
$token = $admin->createToken('admin', ['*'])->plainTextToken;
```

### 7.3 Token 验证

```php
// 通过 auth:sanctum 中间件
Route::middleware('auth:sanctum')->group(function () {
    // 需要登录的接口
});

// 或自定义中间件
Route::middleware('auth.or.admin')->group(function () {
    // 用户或管理员均可
});
```

---

## 8. 统一响应格式

```php
// app/Http/Controllers/Controller.php
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use ValidatesRequests;

    /**
     * 成功响应
     */
    protected function success($data = null, string $message = 'success'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * 错误响应
     */
    protected function error(string $message = 'error', int $code = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => null,
        ], $code >= 400 ? $code : 400);
    }
}
```

---

## 9. 文件上传

### 9.1 上传方式

- **直接上传文件**：前端通过 `POST /api/v1/analysis/upload-image` 上传图片文件
- **存储位置**：`storage/app/public/` 目录
- **访问方式**：通过 `storage` 符号链接公开访问

### 9.2 上传示例

```php
// app/Http/Controllers/Api/V1/AnalysisController.php
public function uploadImage(Request $request): JsonResponse
{
    $request->validate([
        'image' => 'required|image|max:2048', // 最大2MB
    ]);

    $file = $request->file('image');
    $path = $file->store('analysis/' . date('Ymd'), 'public');
    $url = asset('storage/' . $path);

    return $this->success([
        'image_url' => $url,
        'image_path' => $path,
    ]);
}
```

---

> **相关文档**：
> - [系统架构设计](03-architecture.md)
> - [数据库设计](04-database.md)
> - [API 设计](05-api.md)
> - [安全设计](10-security.md)
