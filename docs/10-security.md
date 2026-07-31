# 安全设计

> **版本**：v1.0  
> **日期**：2026-07-28  
> **对应 ai.md 阶段**：第八阶段（安全设计）

---

## 1. 认证与鉴权

### 1.1 JWT认证

| 项目 | 方案 |
|------|------|
| 算法 | HS256 |
| Access Token有效期 | 2小时 |
| Refresh Token有效期 | 30天（用户端）/ 7天（管理端） |
| Token存储 | localStorage（前端） |
| 传输方式 | Authorization: Bearer {token} |

### 1.2 JWT实现

```php
// 生成Token
public function generateTokens(User $user): array
{
    $accessToken = JWTAuth::customClaims([
        'sub' => $user->id,
        'iat' => time(),
        'exp' => time() + 7200,
    ])->fromUser($user);

    $refreshToken = JWTAuth::customClaims([
        'sub' => $user->id,
        'iat' => time(),
        'exp' => time() + 2592000,
        'type' => 'refresh',
    ])->fromUser($user);

    return [
        'token' => $accessToken,
        'refresh_token' => $refreshToken,
        'expire_in' => 7200,
    ];
}
```

---

## 2. RBAC权限模型

### 2.1 角色定义

| 角色 | 权限范围 |
|------|---------|
| 超级管理员 | 全部权限 |
| 运营管理员 | 用户管理、订单管理、内容管理 |
| 财务管理员 | 财务管理、提现审核 |
| 客服 | 用户查看、订单查看 |

### 2.2 权限矩阵

| 权限点 | 超级管理员 | 运营 | 财务 | 客服 |
|--------|-----------|------|------|------|
| dashboard | ✅ | ✅ | ✅ | ❌ |
| user_view | ✅ | ✅ | ❌ | ✅ |
| user_edit | ✅ | ✅ | ❌ | ❌ |
| order_view | ✅ | ✅ | ✅ | ✅ |
| order_refund | ✅ | ❌ | ✅ | ❌ |
| ai_manage | ✅ | ❌ | ❌ | ❌ |
| promoter_view | ✅ | ✅ | ❌ | ❌ |
| withdraw_audit | ✅ | ❌ | ✅ | ❌ |
| system_config | ✅ | ❌ | ❌ | ❌ |

---

## 3. 数据安全

### 3.1 密码安全

| 项目 | 方案 |
|------|------|
| 哈希算法 | bcrypt |
| Cost因子 | 12 |
| 最小长度 | 8位 |
| 复杂度要求 | 字母+数字 |

### 3.2 敏感信息脱敏

| 数据类型 | 脱敏规则 | 示例 |
|---------|---------|------|
| 手机号 | 中间4位星号 | 138****5678 |
| 身份证 | 前3后4 | 110***********1234 |
| 银行卡 | 前4后4 | 6222************1234 |
| 邮箱 | 前2+星号+@ | ab****@qq.com |

### 3.3 传输安全

| 项目 | 方案 |
|------|------|
| 协议 | HTTPS |
| TLS版本 | 1.3 |
| HSTS | 启用 |
| 证书 | 腾讯云免费SSL |

---

## 4. 接口安全

### 4.1 SQL注入防护

```php
// 使用Eloquent ORM参数化查询（安全）
User::where('mobile', $mobile)->first();

// 避免原生SQL拼接（危险）
// DB::select("SELECT * FROM users WHERE mobile = '$mobile'");
```

### 4.2 XSS防护

```php
// Blade模板自动转义（安全）
{{ $user->nickname }}

// 输出JSON时转义
return response()->json([
    'nickname' => e($user->nickname),
]);
```

### 4.3 CSRF防护

```php
// API使用JWT验证，无需CSRF Token
// 管理端表单使用CSRF Token
<meta name="csrf-token" content="{{ csrf_token() }}">
```

### 4.4 重放攻击防护

```php
// 请求头携带时间戳和Nonce
$timestamp = $request->header('X-Timestamp');
$nonce = $request->header('X-Nonce');

// 验证时间戳（5分钟内有效）
if (abs(time() - $timestamp) > 300) {
    return $this->error('请求已过期');
}

// 验证Nonce（防止重复）
if (Cache::has('nonce:' . $nonce)) {
    return $this->error('请求重复');
}
Cache::put('nonce:' . $nonce, true, 300);
```

---

## 5. 限流策略

### 5.1 全局限流

| 接口 | 限流规则 |
|------|---------|
| 全部接口 | 100次/分钟/IP |
| 发送短信 | 1次/分钟，5次/小时/手机号 |
| 登录 | 5次/分钟/IP |
| AI分析 | 10次/分钟/用户 |
| 注册 | 3次/小时/IP |

### 5.2 限流实现

```php
// app/Http/Middleware/RateLimit.php
public function handle(Request $request, Closure $next, $maxAttempts = 100, $decayMinutes = 1)
{
    $key = 'rate_limit:' . $request->ip() . ':' . $request->path();
    
    if (Cache::has($key . ':block')) {
        return response()->json([
            'code' => 1005,
            'message' => '请求过于频繁，请稍后再试',
        ], 429);
    }

    $hits = Cache::increment($key);
    
    if ($hits == 1) {
        Cache::put($key, 1, $decayMinutes * 60);
    }

    if ($hits > $maxAttempts) {
        Cache::put($key . ':block', true, $decayMinutes * 60);
        return response()->json([
            'code' => 1005,
            'message' => '请求过于频繁，请稍后再试',
        ], 429);
    }

    return $next($request);
}
```

---

## 6. 上传安全

### 6.1 文件验证

| 验证项 | 规则 |
|--------|------|
| 文件类型 | jpg, jpeg, png, webp |
| MIME类型 | image/jpeg, image/png, image/webp |
| 文件大小 | ≤ 2MB |
| 图片尺寸 | 最小200x200，最大4096x4096 |
| 文件头 | 验证真实图片格式 |

### 6.2 上传实现

```php
// 使用预签名URL上传（不经过服务器）
public function getUploadUrl(Request $request): JsonResponse
{
    $filename = $request->input('filename');
    $contentType = $request->input('content_type');
    
    // 验证文件类型
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($contentType, $allowedTypes)) {
        return $this->error('不支持的文件类型');
    }

    // 生成预签名URL
    $key = 'images/' . date('Ymd') . '/' . Str::random(32) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
    $uploadUrl = $this->cosClient->createPresignedUrl($key, 300, 'put', [
        'Content-Type' => $contentType,
    ]);

    return $this->success([
        'upload_url' => $uploadUrl,
        'file_url' => 'https://' . config('cos.domain') . '/' . $key,
        'expire_in' => 300,
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

### 7.2 日志实现

```php
// app/Traits/LogOperation.php
trait LogOperation
{
    public function logOperation(string $action, array $data = []): void
    {
        OperationLog::create([
            'admin_id' => auth('admin')->id(),
            'action' => $action,
            'data' => json_encode($data),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
```

---

## 8. 安全测试清单

| 测试项 | 测试方法 | 预期结果 |
|--------|---------|---------|
| SQL注入 | 输入`' OR 1=1 --` | 不返回异常数据 |
| XSS攻击 | 输入`<script>alert(1)</script>` | 输出转义 |
| CSRF攻击 | 跨域提交表单 | 验证失败 |
| 暴力破解 | 连续登录10次 | 触发限流 |
| Token过期 | 使用过期Token | 返回401 |
| 权限越权 | 普通用户访问管理接口 | 返回403 |
| 文件上传 | 上传php文件 | 拒绝上传 |

---

> **相关文档**：
> - [后端设计](08-backend.md)
> - [API 设计](05-api.md)
> - [测试设计](12-test.md)
