<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// V1 版本API
Route::prefix('v1')->middleware([\App\Http\Middleware\VisitCounterMiddleware::class])->group(function () {
    
    // 登录路由命名（用于未认证时重定向）
    Route::get('login', function () {
        return redirect('/auth/login');
    })->name('login');
    
    // ===== 公开接口 =====
    Route::prefix('auth')->group(function () {
        Route::post('register', [V1\AuthController::class, 'register']);
        Route::post('login', [V1\AuthController::class, 'login']);
        Route::post('sms-code', [V1\AuthController::class, 'sendSmsCode']);
        Route::post('wechat', [V1\AuthController::class, 'wechatLogin']);        // 微信登录
        Route::post('refresh', [V1\AuthController::class, 'refreshToken']);      // 刷新Token
    });

    // ===== 管理后台公开接口 =====
    Route::prefix('admin')->group(function () {
        Route::post('auth/login', [V1\AdminController::class, 'login']);
    });

    // ===== 需要登录的接口 =====
    Route::middleware('auth:sanctum')->group(function () {

        // 管理员自身管理（普通管理员可用）
        Route::prefix('admin')->group(function () {
            Route::get('auth/info', [V1\AdminController::class, 'adminInfo']);
            Route::post('auth/change-password', [V1\AdminController::class, 'changeOwnPassword']);
        });

        // 用户相关
        Route::prefix('user')->group(function () {
            Route::get('info', [V1\UserController::class, 'info']);
            Route::put('info', [V1\UserController::class, 'update']);
            Route::post('logout', [V1\AuthController::class, 'logout']);
            // 我的订单
            Route::get('orders', [V1\UserController::class, 'orders']);
            Route::get('orders/{orderNo}', [V1\UserController::class, 'orderDetail']);
            Route::post('orders/{orderNo}/cancel', [V1\UserController::class, 'cancelOrder']);

            // 余额明细
            Route::get('balance-logs', [V1\UserController::class, 'balanceLogs']);
        });

        // AI分析
        Route::prefix('analysis')->group(function () {
            Route::get('config', [V1\AnalysisController::class, 'getConfig']);
            Route::post('upload-url', [V1\AnalysisController::class, 'getUploadUrl']);
            Route::post('upload-image', [V1\AnalysisController::class, 'uploadImage']);
            Route::post('submit', [V1\AnalysisController::class, 'submit']);
            Route::get('status/{taskNo}', [V1\AnalysisController::class, 'status']);
            Route::get('report/{taskNo}', [V1\AnalysisController::class, 'report']);
            Route::get('history', [V1\AnalysisController::class, 'history']);
        });

        // 体质测试
        Route::prefix('constitution')->group(function () {
            Route::get('questions', [V1\ConstitutionController::class, 'questions']);
            Route::post('submit', [V1\ConstitutionController::class, 'submit']);
            Route::get('report/{taskNo}', [V1\ConstitutionController::class, 'report']);
        });

        // 健康问答
        Route::prefix('qa')->group(function () {
            Route::post('sessions', [V1\QaController::class, 'createSession']);
            Route::get('sessions', [V1\QaController::class, 'sessions']);
            Route::post('sessions/{sessionNo}/messages', [V1\QaController::class, 'sendMessage']);
            Route::get('sessions/{sessionNo}/messages', [V1\QaController::class, 'messages']);
        });

        // 客服（用户端）
        Route::prefix('customer-service')->group(function () {
            Route::get('session', [V1\CustomerServiceController::class, 'getOrCreateSession']);
            Route::get('sessions', [V1\CustomerServiceController::class, 'sessions']);
            Route::get('sessions/{sessionNo}/messages', [V1\CustomerServiceController::class, 'messages']);
            Route::post('sessions/{sessionNo}/messages', [V1\CustomerServiceController::class, 'sendMessage']);
            Route::post('sessions/{sessionNo}/upload-image', [V1\CustomerServiceController::class, 'uploadImage']);
            Route::post('sessions/{sessionNo}/close', [V1\CustomerServiceController::class, 'closeSession']);
        });
        
        // 系统消息（用户端）
        Route::prefix('system-messages')->group(function () {
            Route::get('/', [V1\SystemMessageController::class, 'index']);
            Route::get('unread-count', [V1\SystemMessageController::class, 'unreadCount']);
            Route::post('{id}/read', [V1\SystemMessageController::class, 'markAsRead']);
        });

        // 次数包
        Route::prefix('packages')->group(function () {
            Route::get('/', [V1\PackageController::class, 'index']);
            Route::post('buy', [V1\PackageController::class, 'buy']);
        });

        // 健康档案
        Route::prefix('health')->group(function () {
            Route::get('history', [V1\HealthController::class, 'history']);
            Route::get('trend', [V1\HealthController::class, 'trend']);
            Route::get('constitution', [V1\HealthController::class, 'constitution']);
        });

        // 支付
        Route::prefix('payment')->group(function () {
            Route::post('create', [V1\PaymentController::class, 'create']);
            Route::get('methods', [V1\PaymentController::class, 'methods']);
            Route::get('order/{orderNo}', [V1\PaymentController::class, 'status']);
        });

        // 推广中心
        Route::prefix('promoter')->group(function () {
            Route::post('activate', [V1\PromoterController::class, 'activate']);
            Route::get('info', [V1\PromoterController::class, 'info']);
            Route::get('poster', [V1\PromoterController::class, 'poster']);
            Route::get('commissions', [V1\PromoterController::class, 'commissions']);
            Route::get('withdraw-history', [V1\PromoterController::class, 'withdrawHistory']);
            Route::post('withdraw', [V1\PromoterController::class, 'withdraw']);

            // 邀请追踪
            Route::post('track-click', [V1\PromoterController::class, 'trackClick']);
            Route::get('invite-records', [V1\PromoterController::class, 'inviteRecords']);
            Route::get('invite-clicks', [V1\PromoterController::class, 'inviteClicks']);
        });

        // 推广海报生成（公开访问，无需登录）
        Route::get('promoter/poster-image', [V1\PromoterController::class, 'posterImage']);

        // 文章
        Route::prefix('articles')->group(function () {
            Route::get('/', [V1\ArticleController::class, 'index']);
            Route::get('/{id}', [V1\ArticleController::class, 'detail']);
        });

        // ===== 管理后台（需管理员登录） =====
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('dashboard', [V1\AdminController::class, 'dashboard']);
            Route::get('invite-marquee', [V1\AdminController::class, 'inviteMarquee']);

            // 系统配置
            Route::prefix('config')->group(function () {
                Route::get('payment', [V1\ConfigController::class, 'paymentConfig']);
                Route::post('payment-toggle', [V1\ConfigController::class, 'togglePayment']);
            });

            // 用户管理
            Route::get('users', [V1\AdminController::class, 'users']);
            Route::get('users/{id}', [V1\AdminController::class, 'userDetail']);
            Route::put('users/{id}', [V1\AdminController::class, 'userUpdate']);
            Route::put('users/{id}/status', [V1\AdminController::class, 'userToggleStatus']);
            Route::post('users/{id}/reset-password', [V1\AdminController::class, 'userResetPassword']);

            // 用户余额：充值 / 扣减 + 流水
            Route::post('users/{id}/balance', [V1\AdminController::class, 'userAdjustBalance']);
            Route::get('users/{id}/balance-logs', [V1\AdminController::class, 'userBalanceLogs']);

            // 管理员管理（需超级管理员权限）
            Route::middleware('super_admin')->group(function () {
                Route::get('admins', [V1\AdminController::class, 'adminList']);
                Route::post('admins', [V1\AdminController::class, 'adminStore']);
                Route::put('admins/{id}', [V1\AdminController::class, 'adminUpdate']);
                Route::post('admins/{id}/reset-password', [V1\AdminController::class, 'adminResetPassword']);
                Route::delete('admins/{id}', [V1\AdminController::class, 'adminDestroy']);
            });

            // 订单管理
            Route::get('orders', [V1\AdminController::class, 'orders']);
            Route::get('orders/{orderNo}', [V1\AdminController::class, 'orderDetail']);

            // AI管理
            Route::get('ai/models', [V1\AdminController::class, 'aiModels']);
            Route::post('ai/models', [V1\AdminController::class, 'aiModelStore']);
            Route::put('ai/models/{id}', [V1\AdminController::class, 'aiModelUpdate']);
            Route::get('ai/logs', [V1\AdminController::class, 'aiLogs']);

            // 推广管理
            Route::get('promoters', [V1\AdminController::class, 'promoters']);

            // 邀请记录 + 反作弊
            Route::get('promoters/invite-records', [V1\PromoterController::class, 'adminInviteRecords']);
            Route::post('promoters/{id}/ban', [V1\PromoterController::class, 'ban']);
            Route::post('promoters/{id}/unban', [V1\PromoterController::class, 'unban']);

            // 提现审核
            Route::get('withdraws', [V1\AdminController::class, 'withdraws']);
            Route::post('withdraws/{id}/audit', [V1\AdminController::class, 'withdrawAudit']);

            // 文章管理
            Route::get('articles', [V1\AdminController::class, 'articles']);
            Route::post('articles', [V1\AdminController::class, 'articleStore']);
            Route::put('articles/{id}', [V1\AdminController::class, 'articleUpdate']);
            Route::delete('articles/{id}', [V1\AdminController::class, 'articleDelete']);

            // 系统配置
            Route::get('configs', [V1\AdminController::class, 'configs']);
            Route::post('configs', [V1\AdminController::class, 'configUpdate']);
            Route::post('test-llm', [V1\AdminController::class, 'testLlmConnection']);

            // 次数包管理
            Route::get('packages', [V1\AdminController::class, 'packages']);
            Route::post('packages', [V1\AdminController::class, 'packageStore']);
            Route::put('packages/{id}', [V1\AdminController::class, 'packageUpdate']);
            Route::delete('packages/{id}', [V1\AdminController::class, 'packageDestroy']);
            Route::post('packages/{id}/toggle', [V1\AdminController::class, 'packageToggle']);

            // 体质题目管理
            Route::get('constitution/questions', [V1\AdminController::class, 'constitutionQuestions']);
            Route::post('constitution/questions', [V1\AdminController::class, 'constitutionQuestionStore']);
            Route::put('constitution/questions/{id}', [V1\AdminController::class, 'constitutionQuestionUpdate']);
            Route::delete('constitution/questions/{id}', [V1\AdminController::class, 'constitutionQuestionDestroy']);

            // 推广员详情/禁用
            Route::get('promoters/{id}', [V1\AdminController::class, 'promoterDetail']);
            Route::post('promoters/{id}/toggle', [V1\AdminController::class, 'promoterToggle']);

            // 客服管理（后台）
            Route::prefix('customer-service')->group(function () {
                Route::get('statistics', [V1\Admin\CustomerServiceController::class, 'statistics']);
                Route::get('sessions', [V1\Admin\CustomerServiceController::class, 'sessions']);
                Route::get('sessions/{sessionNo}/messages', [V1\Admin\CustomerServiceController::class, 'messages']);
                Route::post('sessions/{sessionNo}/messages', [V1\Admin\CustomerServiceController::class, 'sendMessage']);
                Route::post('sessions/{sessionNo}/upload-image', [V1\Admin\CustomerServiceController::class, 'uploadImage']);
                Route::post('sessions/{sessionNo}/close', [V1\Admin\CustomerServiceController::class, 'closeSession']);
                
                // 常用话术管理
                Route::get('phrases', [V1\Admin\CustomerServiceManageController::class, 'phrases']);
                Route::post('phrases', [V1\Admin\CustomerServiceManageController::class, 'phraseStore']);
                Route::put('phrases/{id}', [V1\Admin\CustomerServiceManageController::class, 'phraseUpdate']);
                Route::delete('phrases/{id}', [V1\Admin\CustomerServiceManageController::class, 'phraseDestroy']);
                
                // 系统消息管理
                Route::get('system-messages', [V1\Admin\CustomerServiceManageController::class, 'systemMessages']);
                Route::post('system-messages', [V1\Admin\CustomerServiceManageController::class, 'sendSystemMessage']);
                Route::delete('system-messages/{id}', [V1\Admin\CustomerServiceManageController::class, 'systemMessageDestroy']);
                
                // 余额不足记录
                Route::get('balance-insufficient-logs', [V1\Admin\CustomerServiceManageController::class, 'balanceInsufficientLogs']);
                Route::get('balance-insufficient-stats', [V1\Admin\CustomerServiceManageController::class, 'balanceInsufficientStats']);
                
                // 客服配置
                Route::get('configs', [V1\Admin\CustomerServiceManageController::class, 'configs']);
                Route::post('configs', [V1\Admin\CustomerServiceManageController::class, 'configUpdate']);
                
                // 发送系统消息到客服会话
                Route::post('sessions/{sessionNo}/system-message', [V1\Admin\CustomerServiceManageController::class, 'sendSessionSystemMessage']);
            });
        });
    });

    // 支付回调（无需登录）
    Route::prefix('payment')->group(function () {
        Route::post('notify/wechat', [V1\PaymentController::class, 'wechatNotify']);
        Route::post('notify/alipay', [V1\PaymentController::class, 'alipayNotify']);
    });

    // 推广海报生成（公开访问，无需登录）
    Route::get('promoter/poster-image', [V1\PromoterController::class, 'posterImage']);
});
