<?php

namespace App\Http\Middleware;

use App\Services\CacheService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VisitCounterMiddleware
{
    /**
     * 访问量统计中间件
     * - 按天自增访问量（走 stats 命名空间，存于 Redis）
     * - 排除 API 内部调用、静态资源
     * - 失败自动降级到 file/database（CacheService 内部处理）
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 只统计 H5/小程序端访问（不统计 admin 后台）
        $path = $request->path();
        $shouldCount = !str_starts_with($path, 'api/v1/admin')
            && !str_contains($path, 'notify')   // 支付回调
            && $request->method() === 'GET';      // 只统计 GET

        if ($shouldCount) {
            $key = 'visits:' . date('Ymd');
            $statsCache = CacheService::namespace('stats');

            // 当前值 +1
            $count = $statsCache->increment($key);

            // 如果是首次写入（count=1），补上当天结束前的过期时间
            if ($count === 1) {
                $statsCache->put($key, $count, now()->endOfDay());
            }
        }

        return $next($request);
    }
}
