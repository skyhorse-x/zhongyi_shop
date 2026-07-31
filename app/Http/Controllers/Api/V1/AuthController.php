<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Promoter;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * 用户注册
     */
    public function register(Request $request)
    {
        $type = $request->input('type', 'mobile'); // mobile 或 account

        if ($type === 'account') {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/|unique:users',
                'password' => 'required|min:6|confirmed',
            ], [
                'username.required' => '账号不能为空',
                'username.min' => '账号至少3位字符',
                'username.max' => '账号不能超过50位字符',
                'username.regex' => '账号只能包含字母、数字和下划线',
                'username.unique' => '账号已被注册',
                'password.required' => '密码不能为空',
                'password.min' => '密码至少6位',
                'password.confirmed' => '两次密码不一致',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            // 处理推荐人关系
            $parentId = $this->getParentIdFromCode($request->input('invite_code'));

            // 注册 + 开通推广员在一个事务内，保证数据一致性
            try {
                DB::beginTransaction();

                $user = User::create([
                    'name' => $request->username,
                    'email' => $request->username . '@user.local',
                    'username' => $request->username,
                    'nickname' => $request->username,
                    'password' => Hash::make($request->password),
                    'parent_id' => $parentId,
                ]);
                $this->autoActivatePromoter($user);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Register failed', ['error' => $e->getMessage()]);
                return response()->json([
                    'code' => 500,
                    'message' => '注册失败，请稍后重试',
                ], 500);
            }
        } else {
            $validator = Validator::make($request->all(), [
                'mobile' => 'required|regex:/^1[3-9]\d{9}$/|unique:users',
                'password' => 'required|min:6|confirmed',
            ], [
                'mobile.required' => '手机号不能为空',
                'mobile.regex' => '手机号格式不正确',
                'mobile.unique' => '手机号已注册',
                'password.required' => '密码不能为空',
                'password.min' => '密码至少6位',
                'password.confirmed' => '两次密码不一致',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 422,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            // 处理推荐人关系
            $parentId = $this->getParentIdFromCode($request->input('invite_code'));

            // 注册 + 开通推广员在一个事务内，保证数据一致性
            try {
                DB::beginTransaction();

                $user = User::create([
                    'name' => '用户' . substr($request->mobile, -4),
                    'email' => $request->mobile . '@mobile.local',
                    'mobile' => $request->mobile,
                    'password' => Hash::make($request->password),
                    'nickname' => '用户' . substr($request->mobile, -4),
                    'parent_id' => $parentId,
                ]);
                $this->autoActivatePromoter($user);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Register failed', ['error' => $e->getMessage()]);
                return response()->json([
                    'code' => 500,
                    'message' => '注册失败，请稍后重试',
                ], 500);
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // 获取推广员信息（事务内已创建）
        $promoter = Promoter::where('user_id', $user->id)->first();

        return response()->json([
            'code' => 0,
            'message' => '注册成功',
            'data' => [
                'token' => $token,
                'user' => $user,
                'is_promoter' => true,
                'invite_code' => $promoter->invite_code ?? null,
                'invite_url' => $promoter ? (env('FRONTEND_URL', 'http://localhost:5173') . '?code=' . $promoter->invite_code) : null,
            ],
        ]);
    }

    /**
     * 自动开通推广员（注册时调用）
     * 幂等：已存在则跳过
     */
    private function autoActivatePromoter(User $user): ?Promoter
    {
        try {
            $existing = Promoter::where('user_id', $user->id)->first();
            if ($existing) {
                return $existing;
            }

            $commissionRate = (float) (SystemConfig::getValue('commission_rate', 15));

            $promoter = Promoter::create([
                'user_id' => $user->id,
                'invite_code' => $this->generateUniqueInviteCode(),
                'level' => 1,
                'commission_rate' => $commissionRate,
                'status' => 1,
                'activated_at' => now(),
            ]);

            $user->update(['is_promoter' => 1]);

            return $promoter;
        } catch (\Exception $e) {
            Log::warning('Auto-activate promoter failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 生成唯一推广码
     */
    private function generateUniqueInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Promoter::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * 根据邀请码获取推荐人ID
     * 防止自我推荐
     */
    private function getParentIdFromCode(?string $inviteCode, ?int $currentUserId = null): ?int
    {
        if (empty($inviteCode)) {
            return null;
        }

        $promoter = Promoter::where('invite_code', $inviteCode)->first();

        if (!$promoter) {
            return null;
        }

        // 防止自我推荐
        if ($currentUserId && $promoter->user_id === $currentUserId) {
            return null;
        }

        return $promoter->user_id;
    }

    /**
     * 用户登录（支持账号或手机号）
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account' => 'required',
            'password' => 'required',
        ], [
            'account.required' => '账号或手机号不能为空',
            'password.required' => '密码不能为空',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        // 支持用户名或手机号登录
        $account = $request->account;
        $user = User::where('mobile', $account)
                    ->orWhere('username', $account)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'code' => 401,
                'message' => '账号或密码错误',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'code' => 0,
            'message' => '登录成功',
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ]);
    }

    /**
     * 发送短信验证码
     */
    public function sendSmsCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|regex:/^1[3-9]\d{9}$/',
            'type' => 'required|in:register,login,reset',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $mobile = $request->mobile;
        $type = $request->type;

        // 频率限制：同一手机号1分钟内只能发一次
        $rateKey = 'sms_rate_' . $mobile;
        if (\Illuminate\Support\Facades\Cache::has($rateKey)) {
            return response()->json([
                'code' => 429,
                'message' => '请求过于频繁，请稍后再试',
            ], 429);
        }

        // 生成6位验证码
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // 存储验证码到缓存（5分钟过期）
        \Illuminate\Support\Facades\Cache::put('sms_code_' . $mobile, [
            'code' => $code,
            'type' => $type,
        ], 300);

        // 设置发送频率限制（60秒）
        \Illuminate\Support\Facades\Cache::put($rateKey, 1, 60);

        // 发送短信（接入短信宝/阿里云短信服务）
        $smsResult = $this->sendSmsViaProvider($mobile, $code, $type);

        if (!$smsResult['success']) {
            \Illuminate\Support\Facades\Log::warning('SMS send failed', [
                'mobile' => $mobile,
                'error' => $smsResult['error'],
            ]);
            // 短信发送失败时，开发环境返回验证码以便测试
            if (config('app.debug')) {
                return response()->json([
                    'code' => 0,
                    'message' => '验证码已发送（开发模式）',
                    'data' => [
                        'expire_in' => 300,
                        'debug_code' => $code, // 仅开发模式返回
                    ],
                ]);
            }
            return response()->json([
                'code' => 500,
                'message' => '短信发送失败，请稍后重试',
            ], 500);
        }

        return response()->json([
            'code' => 0,
            'message' => '验证码已发送',
            'data' => [
                'expire_in' => 300,
            ],
        ]);
    }

    /**
     * 调用短信服务商发送验证码
     * 配置从后台「系统设置」中读取，键：sms_provider, sms_bao_user, sms_bao_pass
     */
    private function sendSmsViaProvider(string $mobile, string $code, string $type): array
    {
        $provider = \App\Models\SystemConfig::where('key', 'sms_provider')->value('value') ?: 'smsbao';
        $userName = \App\Models\SystemConfig::where('key', 'sms_bao_user')->value('value') ?: '';
        $password = \App\Models\SystemConfig::where('key', 'sms_bao_pass')->value('value') ?: '';

        if (empty($userName) || empty($password)) {
            return [
                'success' => false,
                'error' => '短信服务未配置，请在后台【系统设置】中配置短信宝账号',
            ];
        }

        $content = "【中医健康】您的验证码是{$code}，5分钟内有效，请勿泄露。";

        try {
            if ($provider === 'smsbao') {
                // 短信宝 API
                $url = 'https://api.smsbao.com/sms';
                $params = [
                    'u' => $userName,
                    'p' => md5($password),
                    'm' => $mobile,
                    'c' => $content,
                ];

                $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url, $params);
                $result = trim($response->body());

                // 短信宝返回 "0" 表示成功
                if ($result === '0') {
                    return ['success' => true];
                }

                return [
                    'success' => false,
                    'error' => '短信宝返回错误码: ' . $result,
                ];
            }

            return [
                'success' => false,
                'error' => '不支持的短信服务商: ' . $provider,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 退出登录
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'code' => 0,
            'message' => '退出成功',
        ]);
    }

    /**
     * 微信授权登录
     */
    public function wechatLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['code' => 422, 'message' => '参数错误'], 422);
        }

        return response()->json([
            'code' => 501,
            'message' => '微信登录功能尚未实现，请使用手机号 + 密码登录',
            'data' => null,
        ], 501);
    }

    /**
     * 刷新Token
     */
    public function refreshToken(Request $request)
    {
        $request->validate(['refresh_token' => 'required']);

        return response()->json([
            'code' => 501,
            'message' => 'Token 刷新功能尚未实现，请重新登录',
            'data' => null,
        ], 501);
    }
}
