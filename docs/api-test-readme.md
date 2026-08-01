# API 端到端测试执行说明

## 测试文件

| 文件 | 用途 | 运行方式 |
|------|------|---------|
| `docs/api-test-collection.json` | Postman 测试集合 | 导入 Postman 后运行 |
| `tests/Feature/Api/ApiEndToEndTest.php` | PHPUnit 自动化测试 | `php artisan test --filter=ApiEndToEndTest` |

---

## 一、Postman 测试（推荐用于生产环境）

### 1. 导入集合

1. 打开 Postman
2. File → Import → 选择 `docs/api-test-collection.json`
3. 导入后会看到 13 个模块文件夹

### 2. 配置环境变量

创建 Postman Environment，设置以下变量：

| 变量 | 示例值 | 说明 |
|------|--------|------|
| `base_url` | `http://zhongyi.qilewl.net` | 你的域名 |
| `token` | （自动设置） | 用户 Token |
| `admin_token` | （自动设置） | 管理员 Token |
| `invite_code` | （可选） | 测试用邀请码 |

### 3. 运行测试

1. 先运行 **1.4 用户登录** → 自动设置 `token`
2. 再运行 **8.1 管理员登录** → 自动设置 `admin_token`
3. 选择集合 → Run → 按顺序执行全部测试

### 4. 验证每个接口

每个接口都有自动验证：
- HTTP 状态码
- 返回 JSON 结构
- 业务 code = 0

---

## 二、PHPUnit 测试（推荐用于开发环境）

### 1. 运行全部测试

```bash
cd /www/wwwroot/zhongyi.qilewl.net
php artisan test --filter=ApiEndToEndTest
```

### 2. 运行单个模块

```bash
# 只测用户认证
php artisan test --filter='test_register_with_account or test_login_success or test_get_user_info'

# 只测购买支付
php artisan test --filter='test_buy_package_with_balance or test_buy_package_balance_insufficient'

# 只测推广分销
php artisan test --filter='test_activate_promoter or test_withdraw_request or test_invite_records'

# 只测管理后台
php artisan test --filter='test_admin_login or test_admin_users_list or test_admin_adjust_balance'
```

### 3. 运行异常场景

```bash
php artisan test --filter='test_not_found_route or test_validation_error or test_invalid_token'
```

### 4. 运行并发安全测试

```bash
php artisan test --filter='test_concurrent_analysis_deduction or test_concurrent_balance_deduction'
```

---

## 三、测试覆盖矩阵

| 模块 | 测试数 | Postman | PHPUnit |
|------|--------|---------|---------|
| 1. 用户认证 | 6 | ✅ | ✅ |
| 2. AI 分析 | 5 | ✅ | ✅ |
| 3. 体质测试 | 3 | ✅ | ✅ |
| 4. 健康问答 | 4 | ✅ | ✅ |
| 5. 次数包与购买 | 6 | ✅ | ✅ |
| 6. 用户中心 | 6 | ✅ | ✅ |
| 7. 推广分销 | 9 | ✅ | ✅ |
| 8. 管理后台认证 | 4 | ✅ | ✅ |
| 9. 用户管理 | 5 | ✅ | ✅ |
| 10. 推广管理 | 5 | ✅ | ✅ |
| 11. 订单与提现 | 3 | ✅ | ✅ |
| 12. 系统配置 | 5 | ✅ | ✅ |
| 13. 文章模块 | 2 | ✅ | ✅ |
| 14. 异常场景 | 5 | - | ✅ |
| 15. 并发安全 | 2 | - | ✅ |
| 16. 反作弊 | 2 | - | ✅ |
| 17. 幂等性 | 2 | - | ✅ |
| **合计** | **74** | **60** | **74** |

---

## 四、测试检查清单

### 每个接口解耦验证点

| 验证项 | 说明 |
|--------|------|
| HTTP 状态码 | 200/401/404/422/500 正确返回 |
| JSON 结构 | 包含 code/message/data |
| 业务 code | 0 = 成功，非 0 = 错误 |
| 数据完整性 | 返回字段与接口文档一致 |
| 权限隔离 | A 用户不能访问 B 用户数据 |
| 参数校验 | 空值/非法值返回 422 |
| 业务规则 | 余额不足/次数不足有正确提示 |
| 副作用 | 操作后数据变更正确（余额/次数/流水） |

### 常见失败排查

| 错误 | 原因 | 修复 |
|------|------|------|
| 401 Unauthorized | Token 失效或未传 | 重新登录获取 Token |
| 404 Not Found | 路由未注册或 ID 不存在 | 检查 routes/api.php |
| 422 Validation Error | 参数格式错误 | 检查请求参数 |
| 500 Server Error | 服务器内部错误 | 检查 storage/logs/laravel.log |
| cURL error 28 | 请求超时 | 检查网络或增加超时时间 |

---

## 五、生产环境测试注意事项

1. **备份数据库** 前运行
2. **使用独立测试账号**，避免污染真实用户数据
3. **支付相关测试** 使用沙箱环境
4. **短信测试** 避免频繁发送（有频率限制）
5. **AI 分析测试** 会消耗 API 额度

---

## 六、新增接口后如何添加测试

### Postman
1. 在对应模块文件夹下新建 Request
2. 设置 Method/URL/Headers/Body
3. 在 Tests 标签页添加验证脚本

### PHPUnit
1. 在 `ApiEndToEndTest.php` 添加 `test_xxx` 方法
2. 使用 `$this->withHeader('Authorization', ...)` 设置认证
3. 调用 `$this->getJson()` / `$this->postJson()` 等
4. 使用 `->assertStatus()` / `->assertJson()` 验证
