<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestLogMiddleware
{
    /**
     * 需要记录的敏感字段
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'api_key',
        'secret',
        'token',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // 记录请求开始
        $this->logRequest($request);

        $response = $next($request);

        // 记录响应
        $duration = round((microtime(true) - $startTime) * 1000);
        $this->logResponse($request, $response, $duration);

        return $response;
    }

    /**
     * 记录请求信息
     */
    protected function logRequest(Request $request): void
    {
        $data = $request->all();

        // 过滤敏感字段
        foreach ($this->sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***';
            }
        }

        Log::channel('request')->info('API Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'user_agent' => $request->userAgent(),
            'data' => $data,
        ]);
    }

    /**
     * 记录响应信息
     */
    protected function logResponse(Request $request, Response $response, float $duration): void
    {
        $level = $response->getStatusCode() >= 400 ? 'error' : 'info';
        $content = json_decode($response->getContent(), true);

        // 过滤敏感响应数据
        if (isset($content['data']['token'])) {
            $content['data']['token'] = substr($content['data']['token'], 0, 10) . '***';
        }

        Log::channel('request')->$level('API Response', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'user_id' => $request->user()?->id,
            'response' => $content,
        ]);
    }
}
