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
     * 允许用户或管理员访问（通过 token 判断）
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

        // 查找 token 记录
        $token = PersonalAccessToken::findToken($bearerToken);

        if (!$token) {
            return response()->json([
                'code' => 401,
                'message' => '请先登录',
            ], 401);
        }

        // 获取关联的用户（User 或 Admin）
        $tokenable = $token->tokenable;

        if (!$tokenable) {
            return response()->json([
                'code' => 401,
                'message' => '请先登录',
            ], 401);
        }

        // 根据 tokenable 类型设置对应的 guard
        if ($tokenable instanceof Admin) {
            // 管理员 token - 创建或获取关联的用户
            $user = User::where('phone', $tokenable->phone)->first();
            if (!$user) {
                // 如果没有关联的用户，使用管理员的基本信息创建一个用户记录
                $user = User::firstOrCreate(
                    ['phone' => $tokenable->phone],
                    [
                        'nickname' => $tokenable->name,
                        'password' => bcrypt(uniqid()),
                    ]
                );
            }
            // 设置当前认证用户
            auth()->setUser($user);
        } else {
            // 普通用户 token
            auth()->setUser($tokenable);
        }

        return $next($request);
    }
}
