<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * 超级管理员权限检查
     *
     * 只有 id=1 的管理员或 role_id=1 的管理员可以执行敏感操作
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'code' => 401,
                'message' => '未登录',
            ], 401);
        }

        // id=1 为超级管理员，或 role_id=1
        $isSuperAdmin = $admin->id === 1 || $admin->role_id === 1;

        if (!$isSuperAdmin) {
            return response()->json([
                'code' => 403,
                'message' => '权限不足，需要超级管理员权限',
            ], 403);
        }

        return $next($request);
    }
}
