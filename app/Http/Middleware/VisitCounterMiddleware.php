<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VisitCounterMiddleware
{
    /**
     * 访问量统计中间件
     * - 按天自增访问量（存于 cache，过期时间 7 天）
     * - 排除 API 内部调用、静态资源
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 只统计 H5/小程序端访问（不统计 admin 后台）
        $path = $request->path();
        $shouldCount = !str_starts_with($path, 'api/v1/admin')
            && !str_contains($path, 'notify')   // 支付回调
            && $request->method() === 'GET';      // 只统计 GET

        if ($shouldCount) {
            $key = 'stats:visits:' . date('Ymd');
            // 当前值 +1（永久保存到当天 23:59:59 后）
            Cache::increment($key);
            // 如果 key 不存在会被置为 1，再确保有过期时间
            if (Cache::has($key)) {
                Cache::put($key, Cache::get($key, 1), now()->endOfDay());
            }
        }

        return $next($request);
    }
}
