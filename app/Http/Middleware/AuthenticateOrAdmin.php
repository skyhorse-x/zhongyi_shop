<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOrAdmin
{
    /**
     * 允许用户或管理员通过 token 认证访问
     *
     * - User token:  auth()->user()  返回 User 实例
     * - Admin token: auth()->user()  返回 Admin 实例
     *
     * 注意：本中间件不再做"管理员关联到 User"的隐式创建，
     * 调用方需要根据 auth()->user() 的实际类型分别处理。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json([
                'code' => 401,
                'message' => '请先登录',
            ], 401);
        }

        $token = PersonalAccessToken::findToken($bearerToken);

        if (!$token) {
            return response()->json([
                'code' => 401,
                'message' => '请先登录',
            ], 401);
        }

        $tokenable = $token->tokenable;

        if (!$tokenable) {
            return response()->json([
                'code' => 401,
                'message' => '请先登录',
            ], 401);
        }

        // 仅允许 User 或 Admin 通过
        if (!($tokenable instanceof User) && !($tokenable instanceof Admin)) {
            return response()->json([
                'code' => 401,
                'message' => '无效的认证身份',
            ], 401);
        }

        // 直接将 token 关联的实例设置为当前认证用户
        auth()->setUser($tokenable);

        return $next($request);
    }
}
