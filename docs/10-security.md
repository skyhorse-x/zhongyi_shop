# 安全设计

> **版本**：v2.0  
> **日期**：2026-08-04  
> **对应 ai.md 阶段**：第八阶段（安全设计）  
> **变更说明**：根据实际代码修正（Sanctum认证替代JWT，移除不存在的中间件/Trait引用，修正权限模型描述）

---

## 1. 认证与鉴权

### 1.1 Sanctum 认证

| 项目 | 方案 |
|------|------|
| 认证方式 | Laravel Sanctum PersonalAccessToken |
| Token 生成 | `$user->createToken('app')->plainTextToken` |
| Token 传输 | Authorization: Bearer {token} |
| Token 存储 | localStorage（前端） |
| Token 过期 | 默认无过期（可配置 `tokens_expire_at`） |

### 1.2 Sanctum 实现

```php
// 生成 Token
public function generateToken(User $user): string
{
    return $user->createToken('app', ['*'])->plainTextToken;
}

// 验证 Token（通过中间件）
Route::middleware('auth:sanctum')->group(function () {
    // 需要登录的接口
});

// 刷新 Token
public function refreshToken(Request $request): JsonResponse
{
    $user = $request->user();
    $user->tokens()->delete(); // 删除旧 Token
    $token = $user->createToken('app')->plainTextToken;
    return $this->success(['token' => $token]);
}

// 退出登录
public function logout(Request $request): JsonResponse
{
    $request->user()->currentAccessToken()->delete();
    return $this->success(null, '退出成功');
}
```

---

## 2. 权限模型

### 2.1 实际权限实现

| 角色 | 判断逻辑 | 权限范围 |
|------|---------|---------|
| 普通用户 | 已登录 | 访问用户端接口 |
| 管理员 | `is_admin = 1` | 访问管理后台接口 |
| 超级管理员 | `id === 1` 或 `role_id === 1` | 全部权限（包括管理员管理） |

### 2.2 权限中间件

```php
// AdminMiddleware - 验证管理员身份
public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    if (!$user || !$user->is_admin) {
        return response()->json(['code' => 1003, 'message' => '无权限'], 403);
    }
    return $next($request);
}

// SuperAdminMiddleware - 验证超级管理员身份
public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    if (!$user || ($user->id !== 1 && $user->role_id !== 1)) {
        return response()->json(['code' => 1003, 'message' => '需要超级管理员权限'], 403);
    }
    return $next($request);
}
```

### 2.3 风控中间件

```php
// RiskControlMiddleware - 风控检查
// 使用方式：->middleware('risk:withdraw')
public function handle(Request $request, Closure $next, string $action)
{
    $user = $request->user();
    
    // 检查黑名单
    if (RiskBlacklist::isBlacklisted('user_id', $user->id)) {
        return response()->json(['code' => 1003, 'message' => '账号已被限制'], 403);
    }
    
    // 检查风控规则
    $rule = RiskRule::where('action', $action)->where('is_enabled', 1)->first();
    if ($rule && $this->checkRule($rule, $user)) {
        return response()->json(['code' => 1005, 'message' => '操作过于频繁'], 429);
    }
    
    return $next($request);
}
```

---

## 3. 数据安全

### 3.1 密码安全

| 项目 | 方案 |
|------|------|
| 哈希算法 | bcrypt（Laravel 默认） |
| Cost 因子 | 10（Laravel 默认） |
| 最小长度 | 6 位 |

### 3.2 敏感信息脱敏

| 数据类型 | 脱敏规则 | 示例 |
|---------|---------|------|
| 手机号 | 中间 4 位星号 | 138****5678 |
| 身份证 | 前 3 后 4 | 110***********1234 |
| 银行卡 | 前 4 后 4 | 6222************1234 |
| 邮箱 | 前 2 + 星号 + @ | ab****@qq.com |

### 3.3 传输安全

| 项目 | 方案 |
|------|------|
| 协议 | HTTPS |
| TLS 版本 | 1.2+ |
| HSTS | 建议启用 |

---

## 4. 接口安全

### 4.1 SQL 注入防护

```php
// 使用 Eloquent ORM 参数化查询（安全）
User::where('mobile', $mobile)->first();

// 避免原生 SQL 拼接（危险）
// DB::select("SELECT * FROM users WHERE mobile = '$mobile'");
```

### 4.2 XSS 防护

```php
// Blade 模板自动转义（安全）
{{ $user->nickname }}

// 输出 JSON 时转义
return response()->json([
    'nickname' => e($user->nickname),
]);
```

### 4.3 CSRF 防护

- API 使用 Sanctum Token 验证，无需 CSRF Token
- 前端请求携带 `Authorization: Bearer {token}`

---

## 5. 限流策略

### 5.1 风控限流

通过 `RiskControlMiddleware` 实现：

| 操作 | 限流规则 |
|------|---------|
| 提现 | 通过风控规则配置 |
| 登录 | 通过风控规则配置 |
| 注册 | 通过风控规则配置 |

### 5.2 Nginx 限流

```nginx
# nginx.conf
limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;

server {
    location /api/ {
        limit_req zone=api burst=20 nodelay;
    }
}
```

---

## 6. 上传安全

### 6.1 文件验证

| 验证项 | 规则 |
|--------|------|
| 文件类型 | jpg, jpeg, png, webp |
| MIME 类型 | image/jpeg, image/png, image/webp |
| 文件大小 | ≤ 2MB |
| 文件头 | 验证真实图片格式 |

### 6.2 上传实现

```php
// 直接上传文件到服务器本地存储
public function uploadImage(Request $request): JsonResponse
{
    $request->validate([
        'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    $file = $request->file('image');
    $path = $file->store('analysis/' . date('Ymd'), 'public');
    $url = asset('storage/' . $path);

    return $this->success([
        'image_url' => $url,
        'image_path' => $path,
    ]);
}
```

---

## 7. 日志审计

### 7.1 操作日志

| 操作类型 | 记录内容 |
|---------|---------|
| 用户登录 | 时间、IP、设备、结果 |
| 管理员操作 | 时间、管理员、操作、参数 |
| 订单操作 | 时间、订单号、操作、金额 |
| 提现操作 | 时间、提现号、金额、状态 |

### 7.2 日志记录

通过 `RequestLogMiddleware` 自动记录所有请求日志。

---

## 8. 安全测试清单

| 测试项 | 测试方法 | 预期结果 |
|--------|---------|---------|
| SQL 注入 | 输入 `' OR 1=1 --` | 不返回异常数据 |
| XSS 攻击 | 输入 `<script>alert(1)</script>` | 输出转义 |
| Token 过期 | 使用无效 Token | 返回 401 |
| 权限越权 | 普通用户访问管理接口 | 返回 403 |
| 文件上传 | 上传 php 文件 | 拒绝上传 |
| 黑名单 | 黑名单用户访问接口 | 返回 403 |

---

> **相关文档**：
> - [后端设计](08-backend.md)
> - [API 设计](05-api.md)
> - [系统架构设计](03-architecture.md)
