<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * 验证管理员身份
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'code' => 401,
                'message' => '未授权访问',
            ], 401);
        }

        // 验证 token 并获取管理员
        $admin = $this->validateToken($token);

        if (!$admin) {
            return response()->json([
                'code' => 401,
                'message' => '登录已过期，请重新登录',
            ], 401);
        }

        // 检查管理员状态
        if ($admin->status !== 1) {
            return response()->json([
                'code' => 403,
                'message' => '账号已被禁用',
            ], 403);
        }

        // 将管理员信息注入请求
        $request->merge(['admin' => $admin]);
        $request->setUserResolver(function () use ($admin) {
            return $admin;
        });

        return $next($request);
    }

    /**
     * 验证 Token
     */
    private function validateToken(string $token): ?Admin
    {
        // 使用 Sanctum 验证
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if (!$accessToken || !$accessToken->tokenable) {
            return null;
        }

        // 检查是否过期
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        $tokenable = $accessToken->tokenable;

        // 确保是管理员
        if (!$tokenable instanceof Admin) {
            return null;
        }

        return $tokenable;
    }
}
