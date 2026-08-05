<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VisitCounterMiddleware
{
    /**
     * 访问量统计中间件
     * - 按天自增访问量（走 stats 命名空间，存于 file cache）
     * - 排除 API 内部调用、静态资源
     * - 失败自动降级（CacheService 内部处理）
     * - 记录已登录用户的最后访问时间和IP
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
                $ttl = max(60, now()->endOfDay()->getTimestamp() - time());
                $statsCache->put($key, $count, $ttl);
            }
        }

        // 记录已登录用户的最后访问时间和IP（非admin接口）
        if (Auth::check() && !str_starts_with($path, 'api/v1/admin')) {
            $user = Auth::user();
            if ($user && $user instanceof User) {
                // 每5分钟更新一次，避免频繁写数据库
                $cacheKey = 'user_last_visit:' . $user->id;
                if (!CacheService::get($cacheKey)) {
                    $user->last_visit_at = now();
                    $user->last_visit_ip = $request->ip();
                    $user->save();
                    CacheService::put($cacheKey, true, 300); // 5分钟缓存
                }
            }
        }

        return $next($request);
    }
}
