# API 设计

> **版本**：v1.0  
> **日期**：2026-07-28  
> **对应 ai.md 阶段**：第四阶段（API 设计）

---

## 1. API规范

### 1.1 基础规范

| 项目 | 规范 |
|------|------|
| 协议 | HTTPS |
| 域名 | api.tcm-health.com |
| 前缀 | /api/v1 |
| 格式 | JSON |
| 编码 | UTF-8 |
| 时区 | Asia/Shanghai |

### 1.2 请求头

| Header | 必填 | 说明 |
|--------|------|------|
| Authorization | 是 | Bearer {token} |
| Content-Type | 是 | application/json |
| X-Request-ID | 否 | 请求追踪ID |

### 1.3 统一响应格式

```json
{
    "code": 0,
    "message": "success",
    "data": {},
    "timestamp": 1721234567890,
    "request_id": "req_abc123"
}
```

### 1.4 错误码定义

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| 0 | 成功 | 200 |
| 1001 | 参数错误 | 400 |
| 1002 | 未登录 | 401 |
| 1003 | 无权限 | 403 |
| 1004 | 资源不存在 | 404 |
| 1005 | 请求过于频繁 | 429 |
| 2001 | 手机号已注册 | 400 |
| 2002 | 验证码错误 | 400 |
| 2003 | 账号或密码错误 | 400 |
| 3001 | 订单不存在 | 404 |
| 3002 | 订单已支付 | 400 |
| 4001 | AI分析失败 | 500 |
| 9999 | 系统错误 | 500 |

---

## 2. 用户认证接口

### 2.1 发送验证码

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

**Response:**
```json
{
    "code": 0,
    "message": "验证码已发送",
    "data": {
        "expire_in": 300
    }
}
```

### 2.2 注册

```
POST /api/v1/auth/register
```

**Request:**
```json
{
    "mobile": "1*********0",
    "code": "123456",
    "password": "abc123456",
    "invite_code": "ABC123"
}
```

**Response:**
```json
{
    "code": 0,
    "message": "注册成功",
    "data": {
        "user_id": 10001,
        "token": "eyJhbGciOiJIUzI1NiIs...",
        "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
        "expire_in": 7200
    }
}
```

### 2.3 登录

```
POST /api/v1/auth/login
```

**Request:**
```json
{
    "mobile": "1*********0",
    "password": "abc123456"
}
```

**Response:**
```json
{
    "code": 0,
    "message": "登录成功",
    "data": {
        "user_id": 10001,
        "token": "eyJhbGciOiJIUzI1NiIs...",
        "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
        "expire_in": 7200
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
    "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

### 2.5 微信授权登录

```
POST /api/v1/auth/wechat
```

**Request:**
```json
{
    "code": "wx_auth_code",
    "invite_code": "ABC123"
}
```

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
        "user_id": 10001,
        "mobile": "138****5678",
        "nickname": "张三",
        "avatar": "https://xxx.com/avatar.jpg",
        "is_vip": false,
        "analysis_count": 5,
        "is_promoter": true,
        "invite_code": "ABC123"
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

---

## 4. AI分析接口

### 4.1 获取上传预签名URL

```
POST /api/v1/analysis/upload-url
Authorization: Bearer {token}
```

**Request:**
```json
{
    "filename": "photo.jpg",
    "content_type": "image/jpeg"
}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "upload_url": "https://cos.xxx.com/upload?sign=xxx",
        "file_url": "https://cos.xxx.com/images/xxx.jpg",
        "expire_in": 300
    }
}
```

### 4.2 提交分析任务

```
POST /api/v1/analysis/submit
Authorization: Bearer {token}
```

**Request:**
```json
{
    "type": "tongue",
    "image_url": "https://cos.xxx.com/images/xxx.jpg"
}
```

**Response:**
```json
{
    "code": 0,
    "message": "任务已提交",
    "data": {
        "task_no": "TK202607280001",
        "status": 0,
        "estimated_time": 30
    }
}
```

### 4.3 查询分析状态

```
GET /api/v1/analysis/status/{task_no}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "task_no": "TK202607280001",
        "status": 2,
        "summary": "您的舌象显示...",
        "is_paid": false
    }
}
```

### 4.4 获取完整报告

```
GET /api/v1/analysis/report/{task_no}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "task_no": "TK202607280001",
        "health_score": 85,
        "tongue_analysis": "舌质淡红，苔薄白...",
        "constitution_analysis": "平和质倾向...",
        "life_advice": "保持规律作息...",
        "diet_advice": "多吃清淡食物...",
        "exercise_advice": "适度运动...",
        "precautions": "避免熬夜..."
    }
}
```

### 4.5 获取分析历史

```
GET /api/v1/analysis/history?page=1&limit=10
Authorization: Bearer {token}
```

---

## 5. 支付接口

### 5.1 创建支付订单

```
POST /api/v1/payment/create
Authorization: Bearer {token}
```

**Request:**
```json
{
    "type": "analysis",
    "relation_id": "TK202607280001",
    "pay_type": "wechat",
    "amount": 9.90
}
```

**Response:**
```json
{
    "code": 0,
    "message": "订单创建成功",
    "data": {
        "order_no": "ORD202607280001",
        "pay_amount": 9.90,
        "pay_params": {
            "prepay_id": "wx202607280001"
        }
    }
}
```

### 5.2 支付回调（微信）

```
POST /api/v1/payment/notify/wechat
```

### 5.3 查询订单状态

```
GET /api/v1/payment/order/{order_no}
Authorization: Bearer {token}
```

---

## 6. 推广中心接口

### 6.1 开通推广员

```
POST /api/v1/promoter/activate
Authorization: Bearer {token}
```

**说明**：注册用户直接开通推广员，无需审核，立即生效。

**Response:**
```json
{
    "code": 0,
    "message": "开通成功",
    "data": {
        "invite_code": "ABC123",
        "invite_url": "https://tcm-health.com?code=ABC123",
        "level": 1,
        "commission_rate": 15.00,
        "activated_at": "2026-07-28 10:00:00"
    }
}
```

### 6.2 获取推广信息

```
GET /api/v1/promoter/info
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "invite_code": "ABC123",
        "invite_url": "https://tcm-health.com?code=ABC123",
        "level": 1,
        "commission_rate": 15.00,
        "total_invite": 50,
        "total_consume": 20,
        "total_commission": 298.00,
        "available_commission": 200.00
    }
}
```

### 6.3 获取佣金记录

```
GET /api/v1/promoter/commissions?page=1&limit=10
Authorization: Bearer {token}
```

### 6.4 申请提现

```
POST /api/v1/promoter/withdraw
Authorization: Bearer {token}
```

**Request:**
```json
{
    "amount": 100.00,
    "pay_type": "wechat",
    "pay_account": "openid_xxx"
}
```

### 6.5 获取推广海报

```
GET /api/v1/promoter/poster
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "poster_url": "https://cos.xxx.com/poster/abc123.jpg",
        "share_link": "https://tcm-health.com?code=ABC123"
    }
}
```

---

## 7. 体质测试接口

### 7.1 获取体质测试题目

```
GET /api/v1/constitution/questions
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "total": 30,
        "questions": [
            {
                "id": 1,
                "category": "躯体",
                "question": "您是否经常感到疲乏无力？",
                "type": "single",
                "options": [
                    {"key": "A", "text": "没有", "scores": {"pinghe": 2, "qixu": 0}},
                    {"key": "B", "text": "很少", "scores": {"pinghe": 1, "qixu": 1}},
                    {"key": "C", "text": "有时", "scores": {"pinghe": 0, "qixu": 2}},
                    {"key": "D", "text": "总是", "scores": {"pinghe": 0, "qixu": 3}}
                ]
            }
        ]
    }
}
```

### 7.2 提交体质测试答案

```
POST /api/v1/constitution/submit
Authorization: Bearer {token}
```

**Request:**
```json
{
    "answers": [
        {"question_id": 1, "answer": "A"},
        {"question_id": 2, "answer": "B"},
        {"question_id": 3, "answer": "C"}
    ]
}
```

**Response:**
```json
{
    "code": 0,
    "message": "提交成功",
    "data": {
        "task_no": "CS202607280001",
        "constitution_type": "气虚质",
        "scores": {
            "pinghe": 12,
            "qixu": 25,
            "yangxu": 8,
            "yinxu": 5,
            "tanshi": 3,
            "shire": 2,
            "xueyu": 4,
            "qiyu": 6,
            "tebing": 1
        },
        "summary": "您的体质类型为气虚质...",
        "is_paid": false
    }
}
```

### 7.3 获取体质测试报告

```
GET /api/v1/constitution/report/{task_no}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "task_no": "CS202607280001",
        "constitution_type": "气虚质",
        "features": "容易疲乏，声音低弱，气短懒言...",
        "tendency": "易患感冒、内脏下垂等病...",
        "diet_advice": "宜食益气健脾食物，如黄豆、白扁豆...",
        "exercise_advice": "不宜剧烈运动，宜散步、慢跑...",
        "life_advice": "起居宜规律，避免熬夜...",
        "emotion_advice": "保持乐观心态..."
    }
}
```

---

## 8. 健康问答接口

### 8.1 创建问答会话

```
POST /api/v1/qa/sessions
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "创建成功",
    "data": {
        "session_no": "QA202607280001",
        "title": "失眠调理方法",
        "created_at": "2026-07-28 10:00:00"
    }
}
```

### 8.2 获取会话列表

```
GET /api/v1/qa/sessions?page=1&limit=10
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "total": 5,
        "list": [
            {
                "session_no": "QA202607280001",
                "title": "失眠调理方法",
                "message_count": 8,
                "last_question_at": "2026-07-28 10:05:00",
                "status": 1
            }
        ]
    }
}
```

### 8.3 发送消息

```
POST /api/v1/qa/sessions/{session_no}/messages
Authorization: Bearer {token}
```

**Request:**
```json
{
    "content": "我最近经常失眠，有什么好的调理方法？"
}
```

**Response:**
```json
{
    "code": 0,
    "message": "发送成功",
    "data": {
        "message_id": 10001,
        "role": "assistant",
        "content": "失眠在中医中称为不寐，多由情志、饮食内伤...",
        "tokens": 156,
        "created_at": "2026-07-28 10:00:05"
    }
}
```

### 8.4 获取消息列表

```
GET /api/v1/qa/sessions/{session_no}/messages?page=1&limit=20
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "total": 8,
        "list": [
            {
                "id": 1,
                "role": "user",
                "content": "我最近经常失眠...",
                "created_at": "2026-07-28 10:00:00"
            },
            {
                "id": 2,
                "role": "assistant",
                "content": "失眠在中医中称为不寐...",
                "tokens": 156,
                "created_at": "2026-07-28 10:00:05"
            }
        ]
    }
}
```

---

## 9. 次数包接口

### 9.1 获取次数包列表

```
GET /api/v1/packages
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": [
        {
            "id": 1,
            "name": "舌诊10次包",
            "type": "tongue",
            "times": 10,
            "days": 30,
            "price": 69.00,
            "original_price": 99.00,
            "is_recommend": true
        },
        {
            "id": 2,
            "name": "面诊10次包",
            "type": "face",
            "times": 10,
            "days": 30,
            "price": 69.00,
            "original_price": 99.00,
            "is_recommend": false
        },
        {
            "id": 3,
            "name": "月度会员",
            "type": "all",
            "times": 999,
            "days": 30,
            "price": 39.00,
            "original_price": 99.00,
            "is_recommend": true
        }
    ]
}
```

### 9.2 购买次数包

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

**Response:**
```json
{
    "code": 0,
    "message": "订单创建成功",
    "data": {
        "order_no": "PKG202607280001",
        "pay_amount": 69.00,
        "pay_params": {
            "prepay_id": "wx202607280001"
        }
    }
}
```

---

## 10. 健康档案接口

### 10.1 获取分析历史

```
GET /api/v1/health/history?page=1&limit=10&type=
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "total": 15,
        "list": [
            {
                "task_no": "TK202607280001",
                "type": "tongue",
                "type_text": "舌诊",
                "health_score": 85,
                "summary": "舌质淡红，苔薄白...",
                "is_paid": true,
                "created_at": "2026-07-28 10:00:00"
            },
            {
                "task_no": "FC202607270001",
                "type": "face",
                "type_text": "面诊",
                "health_score": 82,
                "summary": "面色红润，唇色正常...",
                "is_paid": true,
                "created_at": "2026-07-27 14:00:00"
            }
        ]
    }
}
```

### 10.2 获取健康趋势

```
GET /api/v1/health/trend?days=30
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "dates": ["2026-06-28", "2026-07-05", "2026-07-12", "2026-07-28"],
        "scores": [78, 80, 82, 85],
        "constitution_changes": [
            {"date": "2026-06-28", "type": "气虚质"},
            {"date": "2026-07-28", "type": "平和质"}
        ]
    }
}
```

### 10.3 获取体质档案

```
GET /api/v1/health/constitution
Authorization: Bearer {token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "constitution_type": "平和质",
        "test_count": 3,
        "last_test_at": "2026-07-28 10:00:00",
        "history": [
            {
                "task_no": "CS202607280001",
                "constitution_type": "平和质",
                "scores": {"pinghe": 25, "qixu": 3},
                "created_at": "2026-07-28 10:00:00"
            }
        ]
    }
}
```

---

## 11. 管理端接口

### 11.1 管理员登录

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

### 11.2 数据概览

```
GET /api/v1/admin/dashboard
Authorization: Bearer {admin_token}
```

**Response:**
```json
{
    "code": 0,
    "message": "success",
    "data": {
        "today_visit": 1250,
        "today_register": 86,
        "today_paid": 23,
        "today_income": 227.70,
        "today_ai_calls": 156,
        "today_ai_cost": 78.50,
        "today_profit": 115.05,
        "total_users": 5680
    }
}
```

### 11.3 用户列表

```
GET /api/v1/admin/users?page=1&limit=20&keyword=&status=
Authorization: Bearer {admin_token}
```

### 11.4 AI模型配置

```
GET /api/v1/admin/ai-models
POST /api/v1/admin/ai-models
PUT /api/v1/admin/ai-models/{id}
DELETE /api/v1/admin/ai-models/{id}
Authorization: Bearer {admin_token}
```

### 11.5 AI调用日志

```
GET /api/v1/admin/ai-logs?page=1&limit=20&type=&model_id=
Authorization: Bearer {admin_token}
```

### 11.6 推广员列表

```
GET /api/v1/admin/promoters?page=1&limit=20
Authorization: Bearer {admin_token}
```

### 11.7 提现审核

```
GET /api/v1/admin/withdraws?status=0
POST /api/v1/admin/withdraws/{id}/audit
Authorization: Bearer {admin_token}
```

### 11.8 体质题目管理

```
GET /api/v1/admin/constitution/questions
POST /api/v1/admin/constitution/questions
PUT /api/v1/admin/constitution/questions/{id}
DELETE /api/v1/admin/constitution/questions/{id}
Authorization: Bearer {admin_token}
```

### 11.9 次数包管理

```
GET /api/v1/admin/packages
POST /api/v1/admin/packages
PUT /api/v1/admin/packages/{id}
DELETE /api/v1/admin/packages/{id}
Authorization: Bearer {admin_token}
```

### 11.10 文章管理

```
GET /api/v1/admin/articles
POST /api/v1/admin/articles
PUT /api/v1/admin/articles/{id}
DELETE /api/v1/admin/articles/{id}
Authorization: Bearer {admin_token}
```

### 11.11 系统配置

```
GET /api/v1/admin/system-configs
POST /api/v1/admin/system-configs
PUT /api/v1/admin/system-configs/{key}
Authorization: Bearer {admin_token}
```

### 11.12 订单列表

```
GET /api/v1/admin/orders?page=1&limit=20&status=&type=
Authorization: Bearer {admin_token}
```

### 11.13 订单退款

```
POST /api/v1/admin/orders/{order_no}/refund
Authorization: Bearer {admin_token}
```

---

## 12. 接口权限矩阵

| 接口 | 游客 | 用户 | 推广员 | 管理员 |
|------|------|------|--------|--------|
| 注册/登录 | ✅ | - | - | - |
| AI分析提交 | - | ✅ | ✅ | - |
| 支付订单 | - | ✅ | ✅ | - |
| 推广中心 | - | - | ✅ | - |
| 用户管理 | - | - | - | ✅ |
| 订单管理 | - | - | - | ✅ |
| AI模型配置 | - | - | - | ✅ |

---

> **相关文档**：
> - [数据库设计](04-database.md)
> - [前端设计 - 用户端H5](06-frontend-web.md)
> - [后端设计](08-backend.md)
> - [安全设计](10-security.md)
