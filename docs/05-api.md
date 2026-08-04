# API 设计

> **版本**：v2.0  
> **日期**：2026-08-04  
> **认证方式**：Laravel Sanctum PersonalAccessToken (Bearer Token)  
> **基础URL**：/api/v1  

---

## 1. API规范

### 1.1 基础规范

| 项目 | 规范 |
|------|------|
| 协议 | HTTPS |
| 格式 | JSON |
| 编码 | UTF-8 |
| 时区 | Asia/Shanghai |
| 认证 | Authorization: Bearer {token} |

### 1.2 统一响应格式

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### 1.3 错误码定义

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| 0 | 成功 | 200 |
| 400 | 请求参数错误 | 400 |
| 401 | 未登录或Token无效 | 401 |
| 403 | 无权限 | 403 |
| 404 | 资源不存在 | 404 |
| 422 | 验证错误 | 422 |
| 429 | 请求过于频繁 | 429 |
| 500 | 系统错误 | 500 |
| 501 | 功能未实现 | 501 |
| 503 | 服务不可用（如AI未配置） | 503 |

---

## 2. 用户认证接口

### 2.1 注册（账号密码）

```
POST /api/v1/auth/register
```

**Request:**
```json
{
    "type": "account",
    "username": "zhangsan",
    "password": "abc123456",
    "password_confirmation": "abc123456",
    "invite_code": "ABC123"
}
```

**Response:**
```json
{
    "code": 0,
    "message": "注册成功",
    "data": {
        "token": "1|laravel_sanctum_token...",
        "user": {
            "id": 10001,
            "username": "zhangsan",
            "nickname": "zhangsan"
        },
        "is_promoter": true,
        "invite_code": "ABC123",
        "invite_url": "https://tcm-health.com?code=ABC123"
    }
}
```

### 2.2 注册（手机号密码）

```
POST /api/v1/auth/register
```

**Request:**
```json
{
    "type": "mobile",
    "mobile": "1*********0",
    "password": "abc123456",
    "password_confirmation": "abc123456",
    "invite_code": "ABC123"
}
```

### 2.3 登录

```
POST /api/v1/auth/login
```

**Request:**
```json
{
    "account": "zhangsan",
    "password": "abc123456"
}
```

> 支持用户名或手机号登录

**Response:**
```json
{
    "code": 0,
    "message": "登录成功",
    "data": {
        "token": "1|laravel_sanctum_token...",
        "user": {
            "id": 10001,
            "username": "zhangsan",
            "nickname": "zhangsan"
        }
    }
}
```

### 2.4 刷新Token

```
POST /api/v1/auth/refresh
```

**Request:**
```json
{
    "refresh_token": "1|laravel_sanctum_token..."
}
```

### 2.5 发送短信验证码

```
POST /api/v1/auth/sms-code
```

**Request:**
```json
{
    "mobile": "1*********0",
    "type": "register"
}
```

### 2.6 微信授权登录

```
POST /api/v1/auth/wechat
```

> ⚠️ **未实现** - 返回501错误

---

## 3. 用户中心接口

### 3.1 获取用户信息

```
GET /api/v1/user/info
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "id": 10001,
        "username": "zhangsan",
        "nickname": "zhangsan",
        "mobile": "138****5678",
        "avatar": "https://xxx.com/avatar.jpg",
        "gender": 1,
        "birthday": "1990-01-01",
        "is_promoter": true,
        "analysis_times": 5,
        "balance": 100.00
    }
}
```

### 3.2 更新用户信息

```
PUT /api/v1/user/info
Authorization: Bearer {token}
```

**Request:**
```json
{
    "nickname": "张三",
    "avatar": "https://xxx.com/avatar.jpg",
    "gender": 1,
    "birthday": "1990-01-01"
}
```

### 3.3 退出登录

```
POST /api/v1/user/logout
Authorization: Bearer {token}
```

### 3.4 我的订单

```
GET /api/v1/user/orders?page=1&limit=10
GET /api/v1/user/orders/{orderNo}
POST /api/v1/user/orders/{orderNo}/cancel
Authorization: Bearer {token}
```

### 3.5 余额明细

```
GET /api/v1/user/balance-logs?page=1&limit=10
Authorization: Bearer {token}
```

---

## 4. AI分析接口

### 4.1 获取分析配置

```
GET /api/v1/analysis/config
Authorization: Bearer {token}
```

### 4.2 上传图片文件

```
POST /api/v1/analysis/upload-image
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request:**
```
image: File (jpg, jpeg, png, max: 10MB)
```

**Response:**
```json
{
    "code": 0,
    "message": "上传成功",
    "data": {
        "image_url": "https://xxx.com/storage/analysis/20260804/xxx.jpg",
        "path": "analysis/20260804/xxx.jpg"
    }
}
```

### 4.3 获取上传URL（兼容旧版）

```
POST /api/v1/analysis/upload-url
Authorization: Bearer {token}
```

### 4.4 提交分析任务

```
POST /api/v1/analysis/submit
Authorization: Bearer {token}
```

**Request:**
```json
{
    "type": "tongue",
    "gender": 1,
    "age": 30,
    "image_urls": ["https://xxx.com/image1.jpg"],
    "text": "最近感觉疲劳..."
}
```

> 至少需要图片或文字描述

**Response:**
```json
{
    "code": 0,
    "message": "任务已提交",
    "data": {
        "task_no": "TK20260804abc1234",
        "status": 2,
        "estimated_time": 30
    }
}
```

### 4.5 查询分析状态

```
GET /api/v1/analysis/status/{taskNo}
Authorization: Bearer {token}
```

### 4.6 获取完整报告

```
GET /api/v1/analysis/report/{taskNo}
Authorization: Bearer {token}
```

### 4.7 获取分析历史

```
GET /api/v1/analysis/history?page=1&limit=10&type=tongue
Authorization: Bearer {token}
```

---

## 5. 体质测试接口

### 5.1 获取体质测试题目

```
GET /api/v1/constitution/questions
Authorization: Bearer {token}
```

### 5.2 提交体质测试答案

```
POST /api/v1/constitution/submit
Authorization: Bearer {token}
```

**Request:**
```json
{
    "answers": [
        {"question_id": 1, "answer": "A"},
        {"question_id": 2, "answer": "B"}
    ]
}
```

### 5.3 获取体质测试报告

```
GET /api/v1/constitution/report/{taskNo}
Authorization: Bearer {token}
```

---

## 6. 健康问答接口

### 6.1 创建问答会话

```
POST /api/v1/qa/sessions
Authorization: Bearer {token}
```

### 6.2 获取会话列表

```
GET /api/v1/qa/sessions?page=1&limit=10
Authorization: Bearer {token}
```

### 6.3 发送消息

```
POST /api/v1/qa/sessions/{sessionNo}/messages
Authorization: Bearer {token}
```

**Request:**
```json
{
    "content": "我最近经常失眠，有什么好的调理方法？"
}
```

### 6.4 获取消息列表

```
GET /api/v1/qa/sessions/{sessionNo}/messages?page=1&limit=20
Authorization: Bearer {token}
```

---

## 7. 客服系统接口

### 7.1 获取或创建会话

```
GET /api/v1/customer-service/session
Authorization: Bearer {token}
```

### 7.2 获取会话列表

```
GET /api/v1/customer-service/sessions
Authorization: Bearer {token}
```

### 7.3 发送消息

```
POST /api/v1/customer-service/sessions/{sessionNo}/messages
Authorization: Bearer {token}
```

### 7.4 上传图片

```
POST /api/v1/customer-service/sessions/{sessionNo}/upload-image
Authorization: Bearer {token}
```

### 7.5 关闭会话

```
POST /api/v1/customer-service/sessions/{sessionNo}/close
Authorization: Bearer {token}
```

### 7.6 评价客服

```
POST /api/v1/customer-service/sessions/{sessionNo}/rate
Authorization: Bearer {token}
```

**Request:**
```json
{
    "score": 5,
    "attitude": "good",
    "solved": "yes",
    "comment": "服务很好"
}
```

---

## 8. 消息中心接口

### 8.1 获取系统消息列表

```
GET /api/v1/system-messages
Authorization: Bearer {token}
```

### 8.2 获取未读消息数

```
GET /api/v1/system-messages/unread-count
Authorization: Bearer {token}
```

### 8.3 标记消息已读

```
POST /api/v1/system-messages/{id}/read
Authorization: Bearer {token}
```

---

## 9. 反馈申诉接口

### 9.1 用户反馈

```
GET /api/v1/feedback
POST /api/v1/feedback
GET /api/v1/feedback/{id}
Authorization: Bearer {token}
```

**POST Request:**
```json
{
    "type": "bug",
    "title": "发现问题",
    "content": "详细描述...",
    "images": ["url1", "url2"],
    "contact": "1*********0"
}
```

### 9.2 AI申诉

```
GET /api/v1/appeals
POST /api/v1/appeals
GET /api/v1/appeals/{id}
Authorization: Bearer {token}
```

### 9.3 退款申请

```
GET /api/v1/refunds
POST /api/v1/refunds
GET /api/v1/refunds/{id}
Authorization: Bearer {token}
```

---

## 10. 次数包接口

### 10.1 获取次数包列表

```
GET /api/v1/packages
Authorization: Bearer {token}
```

### 10.2 购买次数包

```
POST /api/v1/packages/buy
Authorization: Bearer {token}
```

**Request:**
```json
{
    "package_id": 1,
    "pay_type": "wechat"
}
```

---

## 11. 支付接口

### 11.1 创建支付订单

```
POST /api/v1/payment/create
Authorization: Bearer {token}
```

**Request:**
```json
{
    "type": "analysis",
    "relation_id": "TK20260804abc1234",
    "pay_type": "wechat"
}
```

### 11.2 获取支付方式

```
GET /api/v1/payment/methods
Authorization: Bearer {token}
```

### 11.3 查询订单状态

```
GET /api/v1/payment/order/{orderNo}
Authorization: Bearer {token}
```

### 11.4 支付回调（无需登录）

```
POST /api/v1/payment/notify/wechat
POST /api/v1/payment/notify/alipay
```

---

## 12. 推广中心接口

### 12.1 开通推广员

```
POST /api/v1/promoter/activate
Authorization: Bearer {token}
```

> 注册时自动激活，此接口用于查询状态

### 12.2 获取推广信息

```
GET /api/v1/promoter/info
Authorization: Bearer {token}
```

### 12.3 获取佣金记录

```
GET /api/v1/promoter/commissions?page=1&limit=10
Authorization: Bearer {token}
```

### 12.4 申请提现

```
POST /api/v1/promoter/withdraw
Authorization: Bearer {token}
Middleware: risk:withdraw
```

**Request:**
```json
{
    "amount": 100.00,
    "pay_type": "wechat",
    "pay_account": "openid_xxx"
}
```

### 12.5 提现历史

```
GET /api/v1/promoter/withdraw-history
Authorization: Bearer {token}
```

### 12.6 获取推广海报

```
GET /api/v1/promoter/poster
GET /api/v1/promoter/poster-image
Authorization: Bearer {token}
```

### 12.7 邀请追踪

```
POST /api/v1/promoter/track-click
GET /api/v1/promoter/invite-records
GET /api/v1/promoter/invite-clicks
Authorization: Bearer {token}
```

---

## 13. 健康档案接口

### 13.1 获取分析历史

```
GET /api/v1/health/history?page=1&limit=10&type=
Authorization: Bearer {token}
```

### 13.2 获取健康趋势

```
GET /api/v1/health/trend?days=30
Authorization: Bearer {token}
```

### 13.3 获取体质档案

```
GET /api/v1/health/constitution
Authorization: Bearer {token}
```

---

## 14. 文章接口

### 14.1 文章列表

```
GET /api/v1/articles?page=1&limit=10&category=
Authorization: Bearer {token}
```

### 14.2 文章详情

```
GET /api/v1/articles/{id}
Authorization: Bearer {token}
```

---

## 15. 闲鱼商品接口

### 15.1 获取闲鱼商品列表

```
GET /api/v1/xianyu/products
```

> 公开接口，无需登录

---

## 16. 管理后台接口

### 16.1 管理员登录

```
POST /api/v1/admin/auth/login
```

**Request:**
```json
{
    "username": "admin",
    "password": "admin123"
}
```

### 16.2 管理员信息

```
GET /api/v1/admin/auth/info
Authorization: Bearer {admin_token}
```

### 16.3 修改密码

```
POST /api/v1/admin/auth/change-password
Authorization: Bearer {admin_token}
```

### 16.4 数据概览

```
GET /api/v1/admin/dashboard
Authorization: Bearer {admin_token}
```

### 16.5 用户管理

```
GET /api/v1/admin/users
POST /api/v1/admin/users
GET /api/v1/admin/users/{id}
PUT /api/v1/admin/users/{id}
PUT /api/v1/admin/users/{id}/status
POST /api/v1/admin/users/{id}/reset-password
POST /api/v1/admin/users/{id}/balance
GET /api/v1/admin/users/{id}/balance-logs
Authorization: Bearer {admin_token}
```

### 16.6 管理员管理（需超级管理员权限）

```
GET /api/v1/admin/admins
POST /api/v1/admin/admins
PUT /api/v1/admin/admins/{id}
POST /api/v1/admin/admins/{id}/reset-password
DELETE /api/v1/admin/admins/{id}
Authorization: Bearer {admin_token}
Middleware: super_admin
```

### 16.7 订单管理

```
GET /api/v1/admin/orders
GET /api/v1/admin/orders/{orderNo}
Authorization: Bearer {admin_token}
```

### 16.8 AI管理

```
GET /api/v1/admin/ai/models
POST /api/v1/admin/ai/models
PUT /api/v1/admin/ai/models/{id}
GET /api/v1/admin/ai/logs
Authorization: Bearer {admin_token}
```

### 16.9 推广管理

```
GET /api/v1/admin/promoters
GET /api/v1/admin/promoters/{id}
POST /api/v1/admin/promoters/{id}/toggle
GET /api/v1/admin/promoters/invite-records
POST /api/v1/admin/promoters/{id}/ban
POST /api/v1/admin/promoters/{id}/unban
Authorization: Bearer {admin_token}
```

### 16.10 提现审核

```
GET /api/v1/admin/withdraws?status=0
POST /api/v1/admin/withdraws/{id}/audit
Authorization: Bearer {admin_token}
```

### 16.11 文章管理

```
GET /api/v1/admin/articles
POST /api/v1/admin/articles
PUT /api/v1/admin/articles/{id}
DELETE /api/v1/admin/articles/{id}
Authorization: Bearer {admin_token}
```

### 16.12 系统配置

```
GET /api/v1/admin/configs
POST /api/v1/admin/configs
POST /api/v1/admin/test-llm
Authorization: Bearer {admin_token}
```

### 16.13 次数包管理

```
GET /api/v1/admin/packages
POST /api/v1/admin/packages
PUT /api/v1/admin/packages/{id}
DELETE /api/v1/admin/packages/{id}
POST /api/v1/admin/packages/{id}/toggle
Authorization: Bearer {admin_token}
```

### 16.14 闲鱼商品管理

```
GET /api/v1/admin/xianyu-products
POST /api/v1/admin/xianyu-products
PUT /api/v1/admin/xianyu-products/{id}
DELETE /api/v1/admin/xianyu-products/{id}
Authorization: Bearer {admin_token}
```

### 16.15 体质题目管理

```
GET /api/v1/admin/constitution/questions
POST /api/v1/admin/constitution/questions
PUT /api/v1/admin/constitution/questions/{id}
DELETE /api/v1/admin/constitution/questions/{id}
Authorization: Bearer {admin_token}
```

### 16.16 客服管理

```
GET /api/v1/admin/customer-service/statistics
GET /api/v1/admin/customer-service/sessions
GET /api/v1/admin/customer-service/sessions/{sessionNo}/messages
POST /api/v1/admin/customer-service/sessions/{sessionNo}/messages
POST /api/v1/admin/customer-service/sessions/{sessionNo}/close
Authorization: Bearer {admin_token}
```

### 16.17 客服话术管理

```
GET /api/v1/admin/customer-service/phrases
POST /api/v1/admin/customer-service/phrases
PUT /api/v1/admin/customer-service/phrases/{id}
DELETE /api/v1/admin/customer-service/phrases/{id}
Authorization: Bearer {admin_token}
```

### 16.18 系统消息管理

```
GET /api/v1/admin/customer-service/system-messages
POST /api/v1/admin/customer-service/system-messages
DELETE /api/v1/admin/customer-service/system-messages/{id}
Authorization: Bearer {admin_token}
```

### 16.19 数据分析BI

```
GET /api/v1/admin/analytics/overview
GET /api/v1/admin/analytics/funnel
GET /api/v1/admin/analytics/retention
GET /api/v1/admin/analytics/revenue
GET /api/v1/admin/analytics/user-growth
GET /api/v1/admin/analytics/top-promoters
GET /api/v1/admin/analytics/analysis-distribution
GET /api/v1/admin/analytics/refund-rate
GET /api/v1/admin/analytics/package-sales
GET /api/v1/admin/analytics/promotion-conversion
GET /api/v1/admin/analytics/satisfaction
Authorization: Bearer {admin_token}
```

### 16.20 退款管理

```
GET /api/v1/admin/refunds
GET /api/v1/admin/refunds/{id}
POST /api/v1/admin/refunds/{id}/approve
POST /api/v1/admin/refunds/{id}/reject
Authorization: Bearer {admin_token}
```

### 16.21 客服评价管理

```
GET /api/v1/admin/customer-service/ratings
GET /api/v1/admin/customer-service/ratings-stats
Authorization: Bearer {admin_token}
```

### 16.22 风控管理

```
GET /api/v1/admin/risk/rules
POST /api/v1/admin/risk/rules
PUT /api/v1/admin/risk/rules/{id}
DELETE /api/v1/admin/risk/rules/{id}
GET /api/v1/admin/risk/events
GET /api/v1/admin/risk/blacklists
POST /api/v1/admin/risk/blacklists
DELETE /api/v1/admin/risk/blacklists/{type}/{value}
GET /api/v1/admin/risk/statistics
Authorization: Bearer {admin_token}
```

### 16.23 用户反馈管理

```
GET /api/v1/admin/feedback
GET /api/v1/admin/feedback/{id}
POST /api/v1/admin/feedback/{id}/reply
POST /api/v1/admin/feedback/{id}/close
Authorization: Bearer {admin_token}
```

### 16.24 AI申诉管理

```
GET /api/v1/admin/appeals
GET /api/v1/admin/appeals/{id}
POST /api/v1/admin/appeals/{id}/audit
Authorization: Bearer {admin_token}
```

---

## 17. 支付配置管理

### 17.1 获取支付配置

```
GET /api/v1/admin/config/payment
Authorization: Bearer {admin_token}
```

### 17.2 切换支付方式

```
POST /api/v1/admin/config/payment-toggle
Authorization: Bearer {admin_token}
```

---

## 18. 接口权限矩阵

| 模块 | 接口 | 游客 | 用户 | 管理员 | 超级管理员 |
|------|------|------|------|--------|-----------|
| 认证 | 注册/登录 | ✅ | - | - | - |
| 认证 | 微信登录 | ✅(501) | - | - | - |
| 用户 | 用户信息 | - | ✅ | - | - |
| AI分析 | 提交分析 | - | ✅ | - | - |
| 体质测试 | 获取题目/提交 | - | ✅ | - | - |
| 健康问答 | 所有接口 | - | ✅ | - | - |
| 客服 | 所有接口 | - | ✅ | ✅ | - |
| 消息 | 系统消息 | - | ✅ | - | - |
| 反馈 | 提交反馈 | - | ✅ | - | - |
| 申诉 | 提交申诉 | - | ✅ | - | - |
| 退款 | 申请退款 | - | ✅ | - | - |
| 次数包 | 购买 | - | ✅ | - | - |
| 支付 | 创建订单 | - | ✅ | - | - |
| 推广 | 提现 | - | ✅(风控) | - | - |
| 管理 | 仪表盘 | - | - | ✅ | - |
| 管理 | 用户管理 | - | - | ✅ | - |
| 管理 | 管理员管理 | - | - | - | ✅ |
| 管理 | 风控管理 | - | - | ✅ | - |
| 管理 | 数据分析 | - | - | ✅ | - |

---

> **相关文档**：
> - [数据库设计](04-database.md)
> - [前端设计 - 用户端](06-frontend-web.md)
> - [前端设计 - 管理端](07-frontend-admin.md)
> - [后端设计](08-backend.md)
> - [安全设计](10-security.md)
