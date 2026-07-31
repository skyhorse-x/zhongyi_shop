# 编码规范

> **版本**：v1.0  
> **日期**：2026-07-28  
> **对应 ai.md 阶段**：第十二阶段（编码规范）

---

## 1. 目录规范

### 1.1 前端目录规范

```
h5/src/
├── api/                    # API接口（按模块划分）
│   ├── auth.ts
│   ├── user.ts
│   └── analysis.ts
├── assets/                 # 静态资源
│   ├── images/
│   └── styles/
├── components/             # 公共组件（大写驼峰）
│   ├── NavBar.vue
│   └── TabBar.vue
├── composables/            # 组合式函数（小驼峰）
│   ├── useUser.ts
│   └── useAnalysis.ts
├── router/                 # 路由配置
│   └── index.ts
├── stores/                 # 状态管理（小写）
│   ├── user.ts
│   └── analysis.ts
├── utils/                  # 工具函数
│   ├── request.ts
│   └── auth.ts
└── views/                  # 页面（按模块划分）
    ├── home/
    ├── analysis/
    └── profile/
```

### 1.2 后端目录规范

```
api/app/
├── Http/
│   ├── Controllers/        # 控制器（按版本+模块）
│   │   ├── Api/V1/
│   │   └── Admin/
│   ├── Middleware/         # 中间件
│   ├── Requests/           # 请求验证
│   └── Resources/          # 资源转换
├── Jobs/                   # 队列任务
├── Models/                 # 模型
├── Repositories/           # 仓库
├── Services/               # 服务
└── Traits/                 # 特性
```

---

## 2. 命名规范

### 2.1 通用规范

| 类型 | 规范 | 示例 |
|------|------|------|
| 文件名 | kebab-case（前端）/ PascalCase（后端类） | user-profile.vue, UserController.php |
| 变量名 | camelCase | userId, taskNo |
| 常量名 | UPPER_SNAKE_CASE | MAX_RETRY_TIMES |
| 函数名 | camelCase | getUserInfo() |
| 类名 | PascalCase | UserService |
| 接口名 | 大写I前缀 | IUserService |
| 数据库表 | snake_case | analysis_tasks |
| 数据库字段 | snake_case | user_id, task_no |

### 2.2 前端命名规范

| 类型 | 规范 | 示例 |
|------|------|------|
| 组件文件 | PascalCase | NavBar.vue |
| 页面文件 | kebab-case | user-profile.vue |
| 路由路径 | kebab-case | /user/profile |
| Store名称 | camelCase | useUserStore |
| 事件名称 | kemit-case | @click="handle-submit" |

### 2.3 后端命名规范

| 类型 | 规范 | 示例 |
|------|------|------|
| 控制器 | 名词+Controller | UserController |
| 服务 | 名词+Service | UserService |
| 仓库 | 名词+Repository | UserRepository |
| 模型 | 单数大写 | User |
| 数据表 | 复数小写 | users |
| 迁移 | 日期+描述 | 2026_07_01_000001_create_users_table |

---

## 3. 注释规范

### 3.1 前端注释

```typescript
/**
 * 用户服务
 * 提供用户相关的业务逻辑
 */
export const useUserStore = defineStore('user', {
    /**
     * 用户登录
     * @param mobile - 手机号
     * @param password - 密码
     * @returns 登录结果
     */
    async login(mobile: string, password: string): Promise<LoginResult> {
        // 调用登录接口
        const res = await apiLogin({ mobile, password })
        return res.data
    },
})
```

### 3.2 后端注释

```php
<?php

namespace App\Services;

/**
 * 用户服务
 * 
 * 提供用户相关的业务逻辑
 */
class UserService
{
    /**
     * 用户注册
     *
     * @param array $data 注册数据
     * @return User 用户模型
     * @throws \Exception 注册失败异常
     */
    public function register(array $data): User
    {
        // 创建用户
        $user = User::create([
            'mobile' => $data['mobile'],
            'password' => bcrypt($data['password']),
        ]);

        return $user;
    }
}
```

---

## 4. Git Flow

### 4.1 分支模型

```
main (生产环境)
  ↑
staging (预发布环境)
  ↑
develop (开发环境)
  ↑
feature/* (功能分支)
  ↑
hotfix/* (热修复分支)
```

### 4.2 分支命名

| 分支类型 | 命名规范 | 示例 |
|---------|---------|------|
| 功能分支 | feature/模块-功能 | feature/auth-login |
| 修复分支 | bugfix/模块-问题 | bugfix/login-error |
| 热修复 | hotfix/问题描述 | hotfix/payment-callback |
| 发布分支 | release/版本号 | release/v1.0.0 |

### 4.3 工作流程

```bash
# 1. 创建功能分支
git checkout -b feature/auth-login develop

# 2. 开发并提交
git add .
git commit -m "feat: 添加用户登录功能"

# 3. 合并到develop
git checkout develop
git merge feature/auth-login

# 4. 删除功能分支
git branch -d feature/auth-login
```

---

## 5. Commit 规范

### 5.1 提交格式

```
<type>(<scope>): <subject>

<body>

<footer>
```

### 5.2 类型说明

| 类型 | 说明 |
|------|------|
| feat | 新功能 |
| fix | 修复Bug |
| docs | 文档变更 |
| style | 代码格式调整 |
| refactor | 重构 |
| perf | 性能优化 |
| test | 测试 |
| chore | 构建/工具变更 |

### 5.3 提交示例

```
feat(auth): 添加微信授权登录功能

1. 新增微信授权API
2. 新增前端微信登录组件
3. 新增微信绑定逻辑

Closes #123
```

---

## 6. 代码风格

### 6.1 前端代码风格

```typescript
// 使用Composition API
import { ref, computed, onMounted } from 'vue'

// 响应式数据
const count = ref(0)

// 计算属性
const doubleCount = computed(() => count.value * 2)

// 方法
function increment() {
    count.value++
}

// 生命周期
onMounted(() => {
    console.log('组件已挂载')
})
```

### 6.2 后端代码风格

```php
<?php

// 严格类型声明
declare(strict_types=1);

namespace App\Services;

// 导入排序：系统类 → 框架类 → 应用类
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class UserService
{
    // 常量定义
    const CACHE_TTL = 3600;

    // 依赖注入
    public function __construct(
        private UserRepository $userRepository
    ) {}

    // 方法可见性
    public function getUser(int $id): ?User
    {
        // 空行分隔逻辑块
        $cacheKey = "user:{$id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            return $this->userRepository->find($id);
        });
    }
}
```

---

## 7. 异常处理规范

### 7.1 前端异常处理

```typescript
// 统一错误处理
async function fetchData() {
    try {
        const res = await apiGetUser()
        return res.data
    } catch (error: any) {
        // 根据错误码处理
        if (error.code === 1002) {
            // 未登录，跳转登录页
            router.push('/auth/login')
        } else {
            // 显示错误提示
            showToast(error.message || '请求失败')
        }
        throw error
    }
}
```

### 7.2 后端异常处理

```php
// 自定义异常类
class BusinessException extends \Exception
{
    public function __construct(int $code, string $message)
    {
        parent::__construct($message, $code);
    }
}

// 全局异常处理
// app/Exceptions/Handler.php
public function render($request, \Throwable $e)
{
    if ($e instanceof BusinessException) {
        return response()->json([
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
        ]);
    }

    return parent::render($request, $e);
}
```

---

## 8. 日志规范

### 8.1 日志级别

| 级别 | 场景 | 示例 |
|------|------|------|
| debug | 调试信息 | 请求参数、响应结果 |
| info | 正常信息 | 用户登录、订单创建 |
| warning | 警告信息 | 重试、降级 |
| error | 错误信息 | 异常、失败 |

### 8.2 日志格式

```php
// 结构化日志
Log::info('用户登录', [
    'user_id' => $user->id,
    'mobile' => $user->mobile,
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
]);
```

---

## 9. API 规范

### 9.1 接口命名

| 操作 | HTTP方法 | 路径 | 说明 |
|------|---------|------|------|
| 列表 | GET | /api/v1/users | 获取用户列表 |
| 详情 | GET | /api/v1/users/{id} | 获取用户详情 |
| 创建 | POST | /api/v1/users | 创建用户 |
| 更新 | PUT | /api/v1/users/{id} | 更新用户 |
| 删除 | DELETE | /api/v1/users/{id} | 删除用户 |

### 9.2 响应格式

```json
{
    "code": 0,
    "message": "success",
    "data": {},
    "timestamp": 1721234567890,
    "request_id": "req_abc123"
}
```

---

## 10. 数据库规范

### 10.1 表命名

- 使用复数形式：users, orders
- 使用下划线分隔：analysis_tasks
- 前缀一致：同项目不使用前缀

### 10.2 字段命名

- 主键：id
- 外键：{表}_id（user_id, order_id）
- 状态：status
- 时间：created_at, updated_at, deleted_at
- 金额：amount, price

### 10.3 索引命名

- 唯一索引：uk_{字段}（uk_mobile）
- 普通索引：idx_{字段}（idx_user_id）

---

## 11. 安全规范

| 规范 | 说明 |
|------|------|
| 密码存储 | 必须使用bcrypt哈希 |
| SQL查询 | 必须使用参数化查询 |
| 输出转义 | 所有输出必须转义 |
| 文件上传 | 验证MIME类型和文件头 |
| 接口限流 | 所有接口必须限流 |
| 敏感日志 | 禁止记录密码、Token |

---

> **相关文档**：
> - [后端设计](08-backend.md)
> - [前端设计 - 用户端H5](06-frontend-web.md)
> - [安全设计](10-security.md)
> - [开发计划与路线图](13-roadmap.md)
