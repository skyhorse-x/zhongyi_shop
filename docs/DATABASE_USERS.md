# 数据库基线 - users 表

> 上线前必读。本文档描述 `users` 表的完整结构、字段含义、索引、外键约束和迁移历史。

---

## 字段总览

| 字段 | 类型 | 必填 | 默认 | 说明 |
|------|------|------|------|------|
| `id` | bigint unsigned | ✅ | auto | 主键 |
| `name` | varchar(255) | ✅ | - | Laravel 默认（保留） |
| `email` | varchar(255) | ✅ | - | Laravel 默认（可空，建议改为 mobile 登录） |
| `email_verified_at` | timestamp | ❌ | NULL | Laravel 默认 |
| `password` | varchar(255) | ✅ | - | 密码 hash |
| `remember_token` | varchar(100) | ❌ | NULL | Sanctum 兼容 |
| `created_at` / `updated_at` | timestamp | ✅ | - | Laravel 时间戳 |
| `nickname` | varchar(255) | ❌ | "用户" | 用户昵称（前端展示） |
| `mobile` | varchar(20) | ❌ | NULL | 手机号（登录） |
| `username` | varchar(50) | ❌ | NULL | 用户名（备用登录） |
| `avatar` | varchar(255) | ❌ | NULL | 头像 URL |
| `gender` | tinyint | ❌ | 0 | 0未知 1男 2女 |
| `birthday` | date | ❌ | NULL | 生日 |
| `is_promoter` | tinyint | ❌ | 0 | 是否为推广员 |
| `status` | tinyint | ❌ | 1 | 1正常 0禁用 |
| `parent_id` | bigint unsigned | ❌ | NULL | 推荐人 ID（FK→users.id, set null） |
| `parent_locked` | boolean | ❌ | 0 | 邀请关系是否锁定（防止后续被修改） |
| `parent_locked_at` | timestamp | ❌ | NULL | 锁定时间 |
| `analysis_times` | int | ❌ | 0 | 剩余分析次数（**核心业务字段**） |
| `balance` | decimal(10,2) | ❌ | 0.00 | 账户余额（元）（**核心业务字段**） |
| `user_registered_granted` | boolean | ❌ | 0 | 是否已发放过注册试用次数（防重复） |

---

## 索引

| 索引名 | 列 | 类型 | 备注 |
|--------|----|----|------|
| PRIMARY | id | 主键 | - |
| `users_email_unique` | email | unique | Laravel 默认 |
| `users_mobile_unique` | mobile | unique | 登录 |
| `users_username_unique` | username | unique | 备用 |
| `idx_status` | status | index | 后台筛选 |
| `idx_parent_id` | parent_id | index | 推广关系 |
| `idx_is_promoter` | is_promoter | index | 推广员筛选 |
| `idx_created_at` | created_at | index | 时间查询 |
| `idx_parent_created` | parent_id, created_at | composite | 团队数据 |

## 外键

| 名称 | 字段 | 引用 | onDelete |
|------|------|------|----------|
| `users_parent_id_foreign` | parent_id | users.id | SET NULL |

---

## 迁移历史

| 序号 | 文件 | 说明 | 类型 |
|------|------|------|------|
| 1 | `0001_01_01_000000_create_users_table.php` | 创建基础 users 表 | CREATE |
| 2 | `2024_01_01_000001_create_users_table.php` | 加 nickname/mobile/avatar/gender/birthday/is_promoter/parent_id（注：文件命名误导，实际是 `Schema::table`） | EXTEND |
| 3 | `2026_07_28_060000_add_username_to_users_table.php` | 加 username | EXTEND |
| 4 | `2026_07_30_020117_add_status_to_users_table.php` | 加 status + 索引 | EXTEND |
| 5 | `2026_07_30_120000_add_analysis_times_and_logs_table.php` | 加 analysis_times | EXTEND |
| 6 | `2026_08_01_120000_add_balance_to_users_table.php` | 加 balance | EXTEND |
| 7 | `2026_08_01_150000_add_registered_grant_to_users_table.php` | 加 user_registered_granted | EXTEND |
| 8 | `2026_08_03_130000_create_refunds_table.php` | 加 parent_locked / parent_locked_at | EXTEND |
| 9 | **`2026_08_04_000000_users_unified_schema.php`** | **统一基线（兜底）** | **BASELINE** |

---

## 基线 Migration 说明

**第 9 号 migration 是"统一基线"**，作用：

1. **字段兜底**：所有未运行历史 migration 的环境（全新部署），可一次性跑通
2. **索引统一**：补齐所有缺失的索引（外键、状态、手机号、parent_id）
3. **外键补齐**：parent_id → users.id 的外键约束

**安全保证**：
- 全部操作使用 `Schema::hasColumn / hasIndex` 守卫，**完全幂等**
- 生产环境运行也是安全的（只添加不删除）
- 没有提供 `down()`：基线不可逆

---

## 部署检查清单

```bash
# 1. 查看所有 users 相关 migrations
ls -la database/migrations | grep users

# 2. 确认 migrations 状态
php artisan migrate:status

# 3. 运行迁移（会自动应用基线 migration）
php artisan migrate

# 4. 验证表结构
php artisan db:show users
```

---

## 关联表

| 表名 | 关联 | 说明 |
|------|------|------|
| `user_analysis_logs` | user_id | 分析次数流水（审计） |
| `user_balance_logs` | user_id | 余额流水（审计） |
| `orders` | user_id | 订单 |
| `refunds` | user_id | 退款单 |
| `analysis_tasks` | user_id | 分析任务 |
| `commissions` | user_id (promoter_id) | 推广佣金 |
| `promoters` | user_id | 推广员 |
| `withdraws` | user_id | 提现 |
| `feedbacks` | user_id | 用户反馈 |
| `analysis_appeals` | user_id | AI 申诉 |
| `system_messages` | user_id | 系统消息 |
| `risk_events` | user_id | 风控事件 |
| `risk_blacklists` | value=user_id | 黑名单 |
| `customer_service_sessions` | user_id | 客服会话 |
| `customer_service_ratings` | user_id | 客服评价 |
| `customer_service_messages` | sender_id=user_id | 客服消息 |
