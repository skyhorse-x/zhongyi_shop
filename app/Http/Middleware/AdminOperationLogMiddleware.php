<?php

namespace App\Http\Middleware;

use App\Services\AdminOperationLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理员操作日志中间件
 *
 * 自动记录敏感操作的日志
 */
class AdminOperationLogMiddleware
{
    /**
     * 需要记录日志的路由模式
     * 格式: 'method@uri' => ['module' => '模块名', 'action' => '操作描述']
     */
    private array $loggableRoutes = [
        // 用户管理
        'POST@api/v1/admin/users/{id}/balance' => ['module' => '用户管理', 'action' => '调整用户余额'],
        'POST@api/v1/admin/users/{id}/credits' => ['module' => '用户管理', 'action' => '调整用户次数'],
        'PUT@api/v1/admin/users/{id}' => ['module' => '用户管理', 'action' => '更新用户信息'],
        'DELETE@api/v1/admin/users/{id}' => ['module' => '用户管理', 'action' => '删除用户'],

        // 订单管理
        'POST@api/v1/admin/orders/{id}/refund' => ['module' => '订单管理', 'action' => '订单退款'],
        'PUT@api/v1/admin/orders/{id}' => ['module' => '订单管理', 'action' => '更新订单'],

        // 推广管理
        'POST@api/v1/admin/promoters/{id}/approve' => ['module' => '推广管理', 'action' => '审核推广员'],
        'POST@api/v1/admin/promoters/{id}/reject' => ['module' => '推广管理', 'action' => '拒绝推广员'],
        'POST@api/v1/admin/withdrawals/{id}/approve' => ['module' => '推广管理', 'action' => '审核提现通过'],
        'POST@api/v1/admin/withdrawals/{id}/reject' => ['module' => '推广管理', 'action' => '审核提现拒绝'],

        // 内容管理
        'POST@api/v1/admin/articles' => ['module' => '内容管理', 'action' => '创建文章'],
        'PUT@api/v1/admin/articles/{id}' => ['module' => '内容管理', 'action' => '更新文章'],
        'DELETE@api/v1/admin/articles/{id}' => ['module' => '内容管理', 'action' => '删除文章'],

        // 系统配置
        'PUT@api/v1/admin/system-configs' => ['module' => '系统配置', 'action' => '更新系统配置'],
        'PUT@api/v1/admin/payment-configs' => ['module' => '系统配置', 'action' => '更新支付配置'],

        // AI管理
        'PUT@api/v1/admin/ai-models/{id}' => ['module' => 'AI管理', 'action' => '更新AI模型'],
        'POST@api/v1/admin/ai-models' => ['module' => 'AI管理', 'action' => '创建AI模型'],
        'DELETE@api/v1/admin/ai-models/{id}' => ['module' => 'AI管理', 'action' => '删除AI模型'],

        // 体质管理
        'POST@api/v1/admin/constitution-questions' => ['module' => '体质管理', 'action' => '创建体质题目'],
        'PUT@api/v1/admin/constitution-questions/{id}' => ['module' => '体质管理', 'action' => '更新体质题目'],
        'DELETE@api/v1/admin/constitution-questions/{id}' => ['module' => '体质管理', 'action' => '删除体质题目'],

        // 风控管理
        'POST@api/v1/admin/risk-rules' => ['module' => '风控管理', 'action' => '创建风控规则'],
        'PUT@api/v1/admin/risk-rules/{id}' => ['module' => '风控管理', 'action' => '更新风控规则'],
        'DELETE@api/v1/admin/risk-rules/{id}' => ['module' => '风控管理', 'action' => '删除风控规则'],
        'POST@api/v1/admin/blacklist' => ['module' => '风控管理', 'action' => '添加黑名单'],
        'DELETE@api/v1/admin/blacklist/{id}' => ['module' => '风控管理', 'action' => '移除黑名单'],

        // 退款管理
        'POST@api/v1/admin/refunds/{id}/approve' => ['module' => '退款管理', 'action' => '审核退款通过'],
        'POST@api/v1/admin/refunds/{id}/reject' => ['module' => '退款管理', 'action' => '审核退款拒绝'],

        // 申诉管理
        'POST@api/v1/admin/appeals/{id}/handle' => ['module' => '申诉管理', 'action' => '处理申诉'],

        // 反馈管理
        'POST@api/v1/admin/feedbacks/{id}/reply' => ['module' => '反馈管理', 'action' => '回复反馈'],

        // 管理员管理
        'POST@api/v1/admin/admins' => ['module' => '管理员管理', 'action' => '创建管理员'],
        'PUT@api/v1/admin/admins/{id}' => ['module' => '管理员管理', 'action' => '更新管理员'],
        'DELETE@api/v1/admin/admins/{id}' => ['module' => '管理员管理', 'action' => '删除管理员'],
    ];

    public function handle(Request $request, Closure $next, ?string $module = null, ?string $action = null): Response
    {
        $startTime = microtime(true);

        // 执行请求
        $response = $next($request);

        // 如果中间件参数指定了模块和动作，直接记录
        if ($module && $action) {
            $this->recordLog($module, $action, $request, $response, $startTime);
            return $response;
        }

        // 根据路由模式匹配记录
        $routeKey = $this->getRouteKey($request);
        if (isset($this->loggableRoutes[$routeKey])) {
            $routeConfig = $this->loggableRoutes[$routeKey];
            $this->recordLog($routeConfig['module'], $routeConfig['action'], $request, $response, $startTime);
        }

        return $response;
    }

    /**
     * 记录日志
     */
    private function recordLog(string $module, string $action, Request $request, Response $response, int $startTime): void
    {
        try {
            $responseCode = $response->getStatusCode();
            $responseData = [];

            // 尝试解析 JSON 响应
            $content = $response->getContent();
            if ($content) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $responseData = $decoded;
                }
            }

            app(AdminOperationLogService::class)->logWithResponse(
                $module,
                $action,
                $responseCode,
                $responseData,
                $request,
                [],
                $startTime
            );
        } catch (\Throwable $e) {
            // 记录日志失败不应影响主流程
            \Log::warning('操作日志记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取路由标识
     */
    private function getRouteKey(Request $request): string
    {
        $method = $request->method();
        $uri = $request->getPathInfo();

        // 替换数字 ID 为 {id}
        $uri = preg_replace('/\/\d+/', '/{id}', $uri);

        return "{$method}@{uri}";
    }
}
