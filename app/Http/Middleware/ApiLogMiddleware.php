<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiLogMiddleware
{
    /**
     * 需要排除的路径（不记录日志）
     */
    private array $excludePaths = [
        'api/v1/admin/api-logs',
        'api/v1/admin/operation-logs',
        'api/v1/admin/queue-monitor',
        'api/v1/health',
    ];

    /**
     * 需要排除的方法
     */
    private array $excludeMethods = ['OPTIONS'];

    /**
     * 最大记录的响应体大小（字节）
     */
    private int $maxBodySize = 65536; // 64KB

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 记录请求开始时间
        $startTime = microtime(true);

        // 执行请求
        $response = $next($request);

        // 记录日志
        $this->logRequest($request, $response, $startTime);

        return $response;
    }

    /**
     * 记录请求日志
     */
    private function logRequest(Request $request, Response $response, float $startTime): void
    {
        try {
            // 检查是否需要排除
            if ($this->shouldExclude($request)) {
                return;
            }

            $durationMs = round((microtime(true) - $startTime) * 1000);

            // 获取用户信息
            $userInfo = $this->getUserInfo($request);

            // 构建日志数据
            $logData = [
                'method' => $request->getMethod(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'module' => $this->getModule($request),
                'request_headers' => $this->filterHeaders($request->headers->all()),
                'request_params' => $request->query(),
                'request_body' => $this->getRequestBody($request),
                'response_status' => $response->getStatusCode(),
                'response_headers' => $this->filterHeaders($response->headers->all()),
                'response_body' => $this->getResponseBody($response),
                'success' => $response->getStatusCode() < 400,
                'error_message' => $response->getStatusCode() >= 400 ? $this->getErrorMessage($response) : null,
                'duration_ms' => $durationMs,
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                'user_id' => $userInfo['id'],
                'user_type' => $userInfo['type'],
                'token' => $this->getTokenIdentifier($request),
                'requested_at' => now(),
            ];

            ApiLog::create($logData);
        } catch (\Exception $e) {
            // 日志记录失败不影响主流程
            Log::error('API日志记录失败: ' . $e->getMessage(), [
                'url' => $request->fullUrl(),
                'method' => $request->getMethod(),
            ]);
        }
    }

    /**
     * 检查是否应该排除该请求
     */
    private function shouldExclude(Request $request): bool
    {
        if (in_array($request->getMethod(), $this->excludeMethods)) {
            return true;
        }

        $path = $request->path();
        foreach ($this->excludePaths as $excludePath) {
            if (str_starts_with($path, $excludePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取模块分类
     */
    private function getModule(Request $request): string
    {
        $path = $request->path();

        return match (true) {
            str_starts_with($path, 'api/v1/admin') => 'admin',
            str_starts_with($path, 'api/v1/auth') => 'auth',
            str_starts_with($path, 'api/v1/analysis') => 'analysis',
            str_starts_with($path, 'api/v1/constitution') => 'constitution',
            str_starts_with($path, 'api/v1/chat') => 'chat',
            str_starts_with($path, 'api/v1/orders') => 'orders',
            str_starts_with($path, 'api/v1/payments') => 'payments',
            str_starts_with($path, 'api/v1/packages') => 'packages',
            str_starts_with($path, 'api/v1/users') => 'users',
            str_starts_with($path, 'api/v1/products') => 'products',
            str_starts_with($path, 'api/v1/articles') => 'articles',
            str_starts_with($path, 'api/v1/promoters') => 'promoters',
            str_starts_with($path, 'api/v1/withdraws') => 'withdraws',
            str_starts_with($path, 'api/v1/recharge') => 'recharge',
            default => 'other',
        };
    }

    /**
     * 获取用户信息
     */
    private function getUserInfo(Request $request): array
    {
        try {
            // 检查是否为管理员
            if (Auth::guard('admin')->check()) {
                $admin = Auth::guard('admin')->user();
                return ['id' => $admin?->id, 'type' => 'admin'];
            }
        } catch (\Exception $e) {
            // admin guard 不存在时忽略
        }

        try {
            // 检查是否为普通用户
            if (Auth::check()) {
                $user = Auth::user();
                return ['id' => $user?->id, 'type' => 'user'];
            }
        } catch (\Exception $e) {
            // guard 不存在时忽略
        }

        return ['id' => null, 'type' => null];
    }

    /**
     * 获取请求体
     */
    private function getRequestBody(Request $request): ?string
    {
        $content = $request->getContent();

        if (empty($content)) {
            return null;
        }

        // 过滤敏感字段
        $data = json_decode($content, true);
        if (is_array($data)) {
            $data = $this->filterSensitiveData($data);
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return substr($content, 0, $this->maxBodySize);
    }

    /**
     * 获取响应体
     */
    private function getResponseBody(Response $response): ?string
    {
        $content = $response->getContent();

        if (empty($content)) {
            return null;
        }

        // 限制记录大小
        if (strlen($content) > $this->maxBodySize) {
            return substr($content, 0, $this->maxBodySize) . '...(truncated)';
        }

        return $content;
    }

    /**
     * 获取错误信息
     */
    private function getErrorMessage(Response $response): ?string
    {
        $content = $response->getContent();

        try {
            $data = json_decode($content, true);
            return $data['message'] ?? $data['error'] ?? substr($content, 0, 500);
        } catch (\Exception $e) {
            return substr($content, 0, 500);
        }
    }

    /**
     * 过滤请求头（移除敏感信息）
     */
    private function filterHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-csrf-token', 'x-xsrf-token'];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['***'];
            }
        }

        return $headers;
    }

    /**
     * 过滤敏感数据
     */
    private function filterSensitiveData(array $data): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'secret', 'credit_card', 'cvv'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***';
            }
        }

        return $data;
    }

    /**
     * 获取令牌标识（用于追踪）
     */
    private function getTokenIdentifier(Request $request): ?string
    {
        $token = $request->bearerToken();

        if ($token) {
            return substr($token, 0, 10) . '...';
        }

        return null;
    }
}
