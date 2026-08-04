# 性能设计

> **版本**：v2.0  
> **日期**：2026-08-04  
> **对应 ai.md 阶段**：第九阶段（性能设计）

---

## 1. 缓存策略

### 1.1 缓存层级

```
┌─────────────────────────────────────────────────────────┐
│                   浏览器缓存                             │
│              静态资源、LocalStorage                      │
├─────────────────────────────────────────────────────────┤
│                  应用缓存（文件缓存）                     │
│              配置缓存、路由缓存、数据缓存                 │
├─────────────────────────────────────────────────────────┤
│                  数据库缓存                              │
│              查询缓存、连接池                            │
└─────────────────────────────────────────────────────────┘
```

> **说明**：当前使用 **文件缓存驱动**（`CACHE_DRIVER=file`），未使用 Redis。后续可根据需要切换为 Redis。

### 1.2 缓存数据清单

| 数据 | 缓存方式 | 过期时间 | 更新策略 |
|------|---------|---------|---------|
| 系统配置 | 文件缓存 | 永久 | 更新时覆盖 |
| 用户信息 | 文件缓存 | 10分钟 | 更新时清除 |
| 分析报告 | 文件缓存 | 24小时 | 生成时写入 |
| 体质题目 | 文件缓存 | 永久 | 更新时清除 |

### 1.3 缓存配置

```env
# .env
CACHE_DRIVER=file
# 可选：redis, memcached, database
```

---

## 2. 数据库优化

### 2.1 索引优化

| 表名 | 索引 | 场景 |
|------|------|------|
| users | users_mobile_unique | 手机号登录 |
| users | users_username_unique | 用户名登录 |
| users | idx_status | 状态筛选 |
| users | idx_parent_id | 推广关系查询 |
| analysis_tasks | idx_user_id_created_at | 历史记录查询 |
| orders | idx_user_id | 订单查询 |

### 2.2 数据库配置

```env
# .env（开发环境）
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database.sqlite

# .env（生产环境）
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=traditional_chinese_medicine
DB_USERNAME=root
DB_PASSWORD=
```

### 2.3 查询优化

- 使用 Laravel Debugbar 监控查询性能
- 使用 `select()` 指定字段，避免 `SELECT *`
- 大数据量查询使用分页
- 关联查询使用 `with()` 预加载

---

## 3. 队列配置

### 3.1 队列驱动

```env
# .env
QUEUE_CONNECTION=database
```

> **说明**：当前使用 **数据库队列驱动**，未使用 Redis 队列。后续高并发场景可切换为 Redis。

### 3.2 队列使用场景

| 场景 | 说明 |
|------|------|
| 支付回调通知 | 异步处理支付成功后续操作 |
| 佣金结算 | 定时结算冻结佣金 |

### 3.3 队列处理命令

```bash
# 处理队列（前台）
php artisan queue:work

# 处理队列（后台守护）
php artisan queue:work --daemon
```

> **注意**：生产环境建议使用 Supervisor 或 systemd 管理队列进程（当前未配置）。

---

## 4. 文件存储优化

### 4.1 文件存储

```env
# .env
FILESYSTEM_DISK=local
# 可选：public, s3, cos
```

> **说明**：当前使用 **本地存储**，文件存储在 `storage/app/public` 目录。

### 4.2 图片处理建议

| 操作 | 建议 |
|------|------|
| 压缩 | 前端压缩至500KB以下再上传 |
| 格式 | 支持 JPG、PNG、WebP |
| 大小限制 | 单文件最大 5MB |

---

## 5. 性能目标

### 5.1 接口性能

| 接口 | 目标响应时间 | 说明 |
|------|-------------|------|
| 首页 | ≤ 500ms | - |
| 登录 | ≤ 300ms | - |
| 提交分析 | ≤ 30s | 同步AI分析（取决于AI服务响应） |
| 查看报告 | ≤ 200ms | - |
| 创建订单 | ≤ 300ms | - |
| 支付回调 | ≤ 500ms | - |

### 5.2 系统性能

| 指标 | 目标 |
|------|------|
| 首页加载 | ≤ 3s |
| 接口响应（P95） | ≤ 1s |
| AI分析 | ≤ 30s |
| 并发支持 | ≥ 100 QPS |
| 系统可用性 | ≥ 99% |

---

## 6. 容量规划

### 6.1 初期（0-1万用户）

| 资源 | 配置 | 月成本 |
|------|------|--------|
| 应用服务器 | 2核4G × 1 | ¥200 |
| 数据库 | SQLite / MySQL 2核4G | ¥0-300 |
| 对象存储 | 本地 50GB | ¥0 |
| CDN | 可选 | ¥0-50 |
| **合计** | | **¥200-550** |

### 6.2 成长建议

当用户量增长时，可考虑以下优化：

| 优化项 | 说明 |
|--------|------|
| Redis缓存 | 切换 `CACHE_DRIVER=redis` |
| Redis队列 | 切换 `QUEUE_CONNECTION=redis` |
| 对象存储 | 使用云存储（COS/OSS） |
| CDN加速 | 静态资源使用CDN |
| 数据库 | 升级MySQL配置，考虑读写分离 |

---

## 7. Laravel 优化

### 7.1 推荐优化命令

```bash
# 配置缓存
php artisan config:cache

# 路由缓存
php artisan route:cache

# 视图缓存
php artisan view:cache

# 自动加载优化
composer dump-autoload --optimize
```

### 7.2 PHP 优化

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  ; 生产环境设为0
```

---

## 8. 前端性能优化

### 8.1 构建优化

| 优化项 | 说明 |
|--------|------|
| 代码分割 | 路由懒加载 |
| 资源压缩 | Vite 自动压缩 JS/CSS |
| Tree Shaking | 移除未使用代码 |

### 8.2 运行时优化

| 优化项 | 说明 |
|--------|------|
| 组件懒加载 | `defineAsyncComponent` |
| 图片懒加载 | `loading="lazy` |
| 数据缓存 | Pinia 状态缓存 |
| 请求防抖 | 搜索输入防抖 |

---

## 9. 监控建议

### 9.1 日志监控

```bash
# Laravel 日志
tail -f storage/logs/laravel.log

# Nginx 访问日志
tail -f /var/log/nginx/access.log

# PHP-FPM 慢日志
tail -f /var/log/php-fpm/slow.log
```

### 9.2 可选监控工具

| 工具 | 用途 | 状态 |
|------|------|------|
| Laravel Debugbar | 开发调试 | ✅ 已安装（开发环境） |
| Laravel Telescope | 应用监控 | ❌ 未安装 |
| Sentry | 错误追踪 | ❌ 未安装 |
| Prometheus | 系统监控 | ❌ 未安装 |

---

## 10. 性能优化清单

### 10.1 已完成

- [x] Laravel 配置缓存
- [x] Laravel 路由缓存
- [x] 路由懒加载（前端）
- [x] 前端资源压缩（Vite）
- [x] 数据库索引优化
- [x] 文件上传大小限制

### 10.2 待优化（按需）

- [ ] 切换 Redis 缓存
- [ ] 切换 Redis 队列
- [ ] 接入云存储（COS/OSS）
- [ ] 接入 CDN
- [ ] 安装 Telescope 监控
- [ ] 接入 Sentry 错误追踪
- [ ] 数据库读写分离
- [ ] 图片处理服务（缩略图、压缩）

---

> **相关文档**：
> - [系统架构设计](03-architecture.md)
> - [后端设计](08-backend.md)
