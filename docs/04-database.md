# 数据库设计

> **版本**：v2.0  
> **日期**：2026-08-04  
> **数据库**：SQLite（开发环境）/ MySQL（生产环境）  
> **字符集**：utf8mb4  

---

## 1. ER图（核心实体关系）

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│   users     │───────│user_profiles│       │user_login_  │
│             │  1:1  │             │       │    logs     │
│ 用户基础信息 │       │ 用户详细信息 │       │ 登录日志    │
└──────┬──────┘       └─────────────┘       └─────────────┘
       │
       │ 1:N
       ▼
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│analysis_    │───────│analysis_    │       │   orders    │
│   tasks     │  1:1  │   reports   │       │             │
│ 分析任务    │       │ 分析报告    │       │ 支付订单    │
└─────────────┘       └─────────────┘       └──────┬──────┘
                                                   │
       ┌───────────────────────────────────────────┤
       │                                           │
       ▼                                           ▼
┌─────────────┐                           ┌─────────────┐
│  promoters  │                           │  payments   │
│ 推广员      │                           │ 支付记录    │
└──────┬──────┘                           └─────────────┘
       │
       │ 1:N
       ▼
┌─────────────┐       ┌─────────────┐
│ commissions │───────│  withdraws  │
│ 佣金记录    │  1:N  │ 提现记录    │
└─────────────┘       └─────────────┘

┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│constitution_│       │constitution_│       │constitution_│
│  questions  │───────│   answers   │       │   reports   │
│ 体质测试题目 │  1:N  │ 用户答题记录 │       │ (同analysis_│
└─────────────┘       └─────────────┘       │   reports)  │
                                            └─────────────┘

┌─────────────┐       ┌─────────────┐
│  health_    │       │  health_    │
│  qa_sessions│───────│  qa_messages│
│ 问答会话    │  1:N  │ 问答消息    │
└─────────────┘       └─────────────┘

┌─────────────┐       ┌─────────────┐
│customer_    │───────│customer_    │
│service_     │  1:N  │service_     │
│sessions     │       │messages     │
│客服会话    │       │客服消息    │
└─────────────┘       └─────────────┘

┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│  feedbacks  │       │analysis_    │       │   refunds   │
│ 用户反馈    │       │  appeals    │       │ 退款记录    │
└─────────────┘       │ AI申诉      │       └─────────────┘
                      └─────────────┘

┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│ risk_rules  │       │ risk_events │       │risk_        │
│ 风控规则    │       │ 风控事件    │       │blacklists   │
└─────────────┘       └─────────────┘       │ 黑名单      │
                                            └─────────────┘

┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│   admins    │       │   roles     │       │permissions  │
│ 管理员      │  N:1  │ 角色        │  N:M  │ 权限        │
└─────────────┘       └─────────────┘       └─────────────┘

┌─────────────┐       ┌─────────────┐
│invite_clicks│       │invite_      │
│ 邀请点击    │       │registrations│
└─────────────┘       │ 邀请注册    │
                      └─────────────┘

┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│  ai_models  │       │   ai_logs   │       │system_      │
│ AI模型配置  │  1:N  │ AI调用日志  │       │configs      │
└─────────────┘       └─────────────┘       │ 系统配置    │
                                            └─────────────┘

┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│  articles   │       │xianyu_      │       │system_      │
│ 文章CMS     │       │products     │       │messages     │
└─────────────┘       │ 闲鱼商品    │       │ 系统消息    │
                      └─────────────┘       └─────────────┘
```

---

## 2. 数据表清单

| 表名 | 说明 | 记录数估算 |
|------|------|-----------|
| users | 用户基础信息 | 100K+ |
| user_profiles | 用户详细信息 | 100K+ |
| user_analysis_logs | 分析次数流水 | 500K+ |
| user_balance_logs | 余额流水 | 500K+ |
| user_login_logs | 登录日志 | 1M+ |
| password_reset_tokens | 密码重置令牌 | 临时 |
| sessions | Laravel会话 | 临时 |
| analysis_tasks | AI分析任务 | 500K+ |
| analysis_reports | AI分析报告 | 500K+ |
| orders | 订单 | 200K+ |
| payments | 支付记录 | 200K+ |
| refunds | 退款记录 | 少量 |
| promoters | 推广员 | 10K+ |
| commissions | 佣金记录 | 100K+ |
| withdraws | 提现记录 | 10K+ |
| constitution_questions | 体质测试题目 | 固定(50+) |
| constitution_answers | 体质答题记录 | 100K+ |
| health_qa_sessions | 健康问答会话 | 50K+ |
| health_qa_messages | 健康问答消息 | 500K+ |
| product_packages | 次数包 | 固定(10+) |
| ai_models | AI模型配置 | 固定(5+) |
| ai_logs | AI调用日志 | 1M+ |
| admins | 管理员 | 少量 |
| roles | 角色 | 固定(5+) |
| permissions | 权限 | 固定(50+) |
| role_permissions | 角色权限关联 | 固定 |
| operation_logs | 操作日志 | 100K+ |
| system_configs | 系统配置 | 固定(20+) |
| system_messages | 系统消息 | 10K+ |
| invite_clicks | 邀请点击记录 | 500K+ |
| invite_registrations | 邀请注册记录 | 100K+ |
| customer_service_sessions | 客服会话 | 50K+ |
| customer_service_messages | 客服消息 | 500K+ |
| customer_service_phrases | 客服常用话术 | 固定 |
| customer_service_ratings | 客服评价 | 10K+ |
| customer_service_configs | 客服配置 | 固定 |
| feedbacks | 用户反馈 | 1K+ |
| analysis_appeals | AI申诉 | 1K+ |
| risk_rules | 风控规则 | 固定(10+) |
| risk_events | 风控事件 | 100K+ |
| risk_blacklists | 黑名单 | 1K+ |
| xianyu_products | 闲鱼商品 | 固定(10+) |
| articles | 文章CMS | 100+ |

---

## 3. 核心数据表结构

### 3.1 用户表（users）

```sql
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Laravel默认字段',
    `nickname` VARCHAR(255) NOT NULL DEFAULT '用户' COMMENT '昵称',
    `email` VARCHAR(255) NOT NULL COMMENT '邮箱(Laravel默认)',
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `mobile` VARCHAR(20) DEFAULT NULL COMMENT '手机号(登录)',
    `username` VARCHAR(50) DEFAULT NULL COMMENT '用户名(备用登录)',
    `password` VARCHAR(255) NOT NULL COMMENT '密码(bcrypt)',
    `remember_token` VARCHAR(100) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像URL',
    `gender` TINYINT NOT NULL DEFAULT 0 COMMENT '性别:0未知 1男 2女',
    `birthday` DATE DEFAULT NULL COMMENT '生日',
    `is_promoter` TINYINT NOT NULL DEFAULT 0 COMMENT '是否推广员:0否 1是',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1正常 0禁用',
    `parent_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '推荐人ID',
    `parent_locked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '邀请关系是否锁定',
    `parent_locked_at` TIMESTAMP NULL DEFAULT NULL COMMENT '锁定时间',
    `analysis_times` INT NOT NULL DEFAULT 0 COMMENT '剩余分析次数',
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '账户余额(元)',
    `user_registered_granted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已发放注册试用次数',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    UNIQUE KEY `users_mobile_unique` (`mobile`),
    UNIQUE KEY `users_username_unique` (`username`),
    KEY `idx_status` (`status`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_is_promoter` (`is_promoter`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_parent_created` (`parent_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';
```

### 3.2 用户详情表（user_profiles）

```sql
CREATE TABLE `user_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `height` DECIMAL(5,2) DEFAULT NULL COMMENT '身高(cm)',
    `weight` DECIMAL(5,2) DEFAULT NULL COMMENT '体重(kg)',
    `blood_type` VARCHAR(10) DEFAULT NULL COMMENT '血型',
    `medical_history` JSON DEFAULT NULL COMMENT '病史',
    `allergies` JSON DEFAULT NULL COMMENT '过敏史',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_profiles_user_id_unique` (`user_id`),
    CONSTRAINT `user_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户详情表';
```

### 3.3 分析任务表（analysis_tasks）

```sql
CREATE TABLE `analysis_tasks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_no` VARCHAR(32) NOT NULL COMMENT '任务编号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` VARCHAR(20) NOT NULL COMMENT '类型:tongue舌诊 face面诊 constitution体质',
    `image_url` VARCHAR(500) DEFAULT NULL COMMENT '原始图片URL',
    `image_urls` JSON DEFAULT NULL COMMENT '多张图片URL',
    `text` TEXT DEFAULT NULL COMMENT '用户输入的描述文本',
    `image_md5` VARCHAR(32) DEFAULT NULL COMMENT '图片MD5(用于缓存)',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待处理 1处理中 2完成 3失败',
    `model` VARCHAR(50) DEFAULT NULL COMMENT '使用的AI模型',
    `prompt` TEXT DEFAULT NULL COMMENT '使用的Prompt',
    `tokens` INT NOT NULL DEFAULT 0 COMMENT '消耗Token数',
    `cost` DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'AI调用成本(元)',
    `result` JSON DEFAULT NULL COMMENT 'AI返回结果',
    `error_msg` VARCHAR(500) DEFAULT NULL COMMENT '错误信息',
    `is_paid` TINYINT NOT NULL DEFAULT 0 COMMENT '是否已支付',
    `started_at` TIMESTAMP NULL DEFAULT NULL COMMENT '开始处理时间',
    `completed_at` TIMESTAMP NULL DEFAULT NULL COMMENT '完成时间',
    `gender` TINYINT DEFAULT NULL COMMENT '用户性别(冗余)',
    `age` INT DEFAULT NULL COMMENT '用户年龄(冗余)',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `analysis_tasks_task_no_unique` (`task_no`),
    KEY `analysis_tasks_user_id_index` (`user_id`),
    KEY `analysis_tasks_status_index` (`status`),
    KEY `analysis_tasks_image_md5_index` (`image_md5`),
    KEY `analysis_tasks_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分析任务表';
```

### 3.4 分析报告表（analysis_reports）

```sql
CREATE TABLE `analysis_reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id` BIGINT UNSIGNED NOT NULL COMMENT '任务ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` VARCHAR(20) NOT NULL COMMENT '报告类型:tongue face constitution',
    `health_score` TINYINT DEFAULT NULL COMMENT '健康评分(0-100)',
    `tongue_color` VARCHAR(50) DEFAULT NULL COMMENT '舌色',
    `tongue_shape` VARCHAR(100) DEFAULT NULL COMMENT '舌形',
    `tongue_coating` VARCHAR(100) DEFAULT NULL COMMENT '舌苔',
    `sublingual_vein` VARCHAR(100) DEFAULT NULL COMMENT '舌下络脉',
    `tongue_analysis` TEXT DEFAULT NULL COMMENT '舌象分析详情',
    `face_color` VARCHAR(50) DEFAULT NULL COMMENT '面色',
    `lip_color` VARCHAR(50) DEFAULT NULL COMMENT '唇色',
    `eye_analysis` VARCHAR(200) DEFAULT NULL COMMENT '眼部分析',
    `skin_analysis` VARCHAR(200) DEFAULT NULL COMMENT '皮肤分析',
    `face_analysis` TEXT DEFAULT NULL COMMENT '面诊分析详情',
    `constitution_type` VARCHAR(20) DEFAULT NULL COMMENT '体质类型',
    `constitution_analysis` TEXT DEFAULT NULL COMMENT '体质分析详情',
    `life_advice` TEXT DEFAULT NULL COMMENT '生活建议',
    `diet_advice` TEXT DEFAULT NULL COMMENT '饮食建议',
    `exercise_advice` TEXT DEFAULT NULL COMMENT '运动建议',
    `precautions` TEXT DEFAULT NULL COMMENT '注意事项',
    `summary` VARCHAR(500) DEFAULT NULL COMMENT '摘要(未付费可见)',
    `content` JSON DEFAULT NULL COMMENT '完整报告内容(付费可见)',
    `is_paid` TINYINT NOT NULL DEFAULT 0 COMMENT '是否已付费:0否 1是',
    `viewed_at` DATETIME DEFAULT NULL COMMENT '查看时间',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `analysis_reports_task_id_unique` (`task_id`),
    KEY `analysis_reports_type_index` (`type`),
    KEY `analysis_reports_is_paid_index` (`is_paid`),
    KEY `analysis_reports_constitution_type_index` (`constitution_type`),
    CONSTRAINT `analysis_reports_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `analysis_tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `analysis_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分析报告表';
```

### 3.5 订单表（orders）

```sql
CREATE TABLE `orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单编号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` VARCHAR(20) NOT NULL COMMENT '类型:analysis分析 package次数包',
    `relation_id` VARCHAR(32) NOT NULL COMMENT '关联ID',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '金额',
    `pay_type` VARCHAR(20) NOT NULL COMMENT '支付方式:wechat alipay',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待支付 1已支付 2已取消 3已退款',
    `transaction_id` VARCHAR(64) DEFAULT NULL COMMENT '第三方支付流水号',
    `paid_at` TIMESTAMP NULL DEFAULT NULL COMMENT '支付时间',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `orders_order_no_unique` (`order_no`),
    KEY `orders_user_id_index` (`user_id`),
    KEY `orders_status_index` (`status`),
    KEY `orders_created_at_index` (`created_at`),
    CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单表';
```

### 3.6 支付记录表（payments）

```sql
CREATE TABLE `payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单编号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `pay_type` VARCHAR(20) NOT NULL COMMENT '支付方式',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '支付金额',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待支付 1支付中 2成功 3失败',
    `trade_no` VARCHAR(100) DEFAULT NULL COMMENT '第三方流水号',
    `pay_response` JSON DEFAULT NULL COMMENT '支付响应',
    `notify_response` JSON DEFAULT NULL COMMENT '回调响应',
    `paid_at` DATETIME DEFAULT NULL COMMENT '支付完成时间',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `payments_order_no_index` (`order_no`),
    KEY `payments_user_id_index` (`user_id`),
    KEY `payments_trade_no_index` (`trade_no`),
    CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付记录表';
```

### 3.7 退款记录表（refunds）

```sql
CREATE TABLE `refunds` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `refund_no` VARCHAR(64) NOT NULL COMMENT '退款单号',
    `order_no` VARCHAR(64) NOT NULL COMMENT '原订单号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '退款金额',
    `refund_amount` DECIMAL(10,2) NOT NULL COMMENT '实际退款金额',
    `reason` ENUM('user_request','admin_refund','order_timeout','service_failure','duplicate_payment','other') NOT NULL COMMENT '退款原因',
    `description` TEXT DEFAULT NULL,
    `status` ENUM('pending','processing','success','failed','cancelled') NOT NULL DEFAULT 'pending',
    `pay_type` ENUM('wechat','alipay','balance') DEFAULT NULL,
    `transaction_id` VARCHAR(128) DEFAULT NULL COMMENT '支付流水号',
    `refund_transaction_id` VARCHAR(128) DEFAULT NULL COMMENT '退款流水号',
    `response` JSON DEFAULT NULL COMMENT '渠道返回',
    `admin_note` TEXT DEFAULT NULL,
    `processed_by` INT UNSIGNED DEFAULT NULL COMMENT '处理人',
    `processed_at` TIMESTAMP NULL DEFAULT NULL,
    `refunded_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `refunds_refund_no_unique` (`refund_no`),
    KEY `refunds_status_created_at_index` (`status`, `created_at`),
    KEY `refunds_user_id_created_at_index` (`user_id`, `created_at`),
    KEY `refunds_order_no_index` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退款记录表';
```

### 3.8 推广员表（promoters）

```sql
CREATE TABLE `promoters` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `invite_code` VARCHAR(20) NOT NULL COMMENT '推广码',
    `level` TINYINT NOT NULL DEFAULT 1 COMMENT '等级:1初级 2高级',
    `commission_rate` DECIMAL(5,2) NOT NULL DEFAULT 15.00 COMMENT '佣金比例(%)',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1正常 0禁用',
    `total_invite` INT NOT NULL DEFAULT 0 COMMENT '累计邀请人数',
    `total_consume` INT NOT NULL DEFAULT 0 COMMENT '累计消费人数',
    `total_commission` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计佣金',
    `frozen_commission` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '冻结佣金',
    `withdrawn_commission` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '已提现佣金',
    `activated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '开通时间',
    `fraud_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '被标记作弊次数',
    `is_banned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否被禁止推广',
    `banned_at` TIMESTAMP NULL DEFAULT NULL COMMENT '封禁时间',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `promoters_user_id_unique` (`user_id`),
    UNIQUE KEY `promoters_invite_code_unique` (`invite_code`),
    KEY `promoters_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广员表';
```

### 3.9 佣金记录表（commissions）

```sql
CREATE TABLE `commissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `commission_no` VARCHAR(32) NOT NULL COMMENT '佣金编号',
    `promoter_id` BIGINT UNSIGNED NOT NULL COMMENT '推广员ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '消费用户ID',
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '订单ID',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '佣金金额',
    `rate` DECIMAL(5,2) NOT NULL COMMENT '佣金比例',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1有效 0无效',
    `withdraw_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联提现ID',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `commissions_commission_no_unique` (`commission_no`),
    KEY `commissions_promoter_id_index` (`promoter_id`),
    KEY `commissions_user_id_index` (`user_id`),
    CONSTRAINT `commissions_promoter_id_foreign` FOREIGN KEY (`promoter_id`) REFERENCES `promoters` (`id`) ON DELETE CASCADE,
    CONSTRAINT `commissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `commissions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='佣金记录表';
```

### 3.10 提现记录表（withdraws）

```sql
CREATE TABLE `withdraws` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `withdraw_no` VARCHAR(32) NOT NULL COMMENT '提现编号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `promoter_id` BIGINT UNSIGNED NOT NULL COMMENT '推广员ID',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '提现金额',
    `pay_type` VARCHAR(20) NOT NULL COMMENT '收款方式',
    `pay_account` VARCHAR(100) NOT NULL COMMENT '收款账号',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待审核 1已通过 2已拒绝 3已打款',
    `remark` VARCHAR(500) DEFAULT NULL COMMENT '用户备注',
    `audit_remark` VARCHAR(500) DEFAULT NULL COMMENT '审核备注',
    `audited_at` TIMESTAMP NULL DEFAULT NULL COMMENT '审核时间',
    `paid_at` TIMESTAMP NULL DEFAULT NULL COMMENT '打款时间',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `withdraws_withdraw_no_unique` (`withdraw_no`),
    KEY `withdraws_user_id_index` (`user_id`),
    KEY `withdraws_status_index` (`status`),
    CONSTRAINT `withdraws_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `withdraws_promoter_id_foreign` FOREIGN KEY (`promoter_id`) REFERENCES `promoters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提现记录表';
```

### 3.11 管理员表（admins）

```sql
CREATE TABLE `admins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码(bcrypt)',
    `name` VARCHAR(50) DEFAULT NULL COMMENT '姓名',
    `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
    `avatar` VARCHAR(500) DEFAULT NULL COMMENT '头像',
    `role_id` INT UNSIGNED DEFAULT NULL COMMENT '角色ID',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1正常 0禁用',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(50) DEFAULT NULL COMMENT '最后登录IP',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `admins_username_unique` (`username`),
    KEY `admins_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';
```

### 3.12 角色权限表

```sql
-- 角色表
CREATE TABLE `roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL COMMENT '角色名称',
    `code` VARCHAR(50) NOT NULL COMMENT '角色编码:super_admin运营管理员 finance_admin客服',
    `description` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '角色描述',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态:1正常 0禁用',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `roles_code_unique` (`code`),
    KEY `roles_code_index` (`code`),
    KEY `roles_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色表';

-- 权限表
CREATE TABLE `permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT '权限名称',
    `code` VARCHAR(100) NOT NULL COMMENT '权限编码',
    `module` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '所属模块',
    `description` VARCHAR(200) NOT NULL DEFAULT '' COMMENT '权限描述',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `permissions_code_unique` (`code`),
    KEY `permissions_module_index` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限表';

-- 角色权限关联表
CREATE TABLE `role_permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色ID',
    `permission_id` BIGINT UNSIGNED NOT NULL COMMENT '权限ID',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `role_permissions_role_id_permission_id_unique` (`role_id`, `permission_id`),
    KEY `role_permissions_role_id_index` (`role_id`),
    KEY `role_permissions_permission_id_index` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色权限关联表';
```

### 3.13 AI模型配置表（ai_models）

```sql
CREATE TABLE `ai_models` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL COMMENT '模型名称',
    `provider` VARCHAR(50) NOT NULL COMMENT '提供商:doubao deepseek openai',
    `model` VARCHAR(50) NOT NULL COMMENT '模型标识',
    `api_url` VARCHAR(500) NOT NULL COMMENT 'API地址',
    `api_key` VARCHAR(255) NOT NULL COMMENT 'API密钥',
    `type` VARCHAR(20) NOT NULL COMMENT '类型:vision视觉 chat文本',
    `analysis_type` VARCHAR(50) DEFAULT NULL COMMENT '分析类型(JSON)',
    `prompt` TEXT DEFAULT NULL COMMENT '默认Prompt',
    `tokens_price` DECIMAL(10,6) NOT NULL DEFAULT 0.000000 COMMENT '每Token价格(元)',
    `timeout` INT NOT NULL DEFAULT 30 COMMENT '超时时间(秒)',
    `retry_times` TINYINT NOT NULL DEFAULT 3 COMMENT '重试次数',
    `is_enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用:0否 1是',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序(优先级)',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ai_models_is_enabled_index` (`is_enabled`),
    KEY `ai_models_type_index` (`type`),
    KEY `ai_models_analysis_type_index` (`analysis_type`),
    KEY `ai_models_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI模型配置表';
```

### 3.14 AI调用日志表（ai_logs）

```sql
CREATE TABLE `ai_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `model_id` INT NOT NULL COMMENT '模型ID',
    `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '用户ID',
    `task_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '任务ID',
    `type` VARCHAR(20) DEFAULT NULL COMMENT '调用类型',
    `prompt_tokens` INT NOT NULL DEFAULT 0 COMMENT '提示词Token数',
    `completion_tokens` INT NOT NULL DEFAULT 0 COMMENT '完成Token数',
    `total_tokens` INT NOT NULL DEFAULT 0 COMMENT '总Token数',
    `cost` DECIMAL(10,4) NOT NULL DEFAULT 0.0000 COMMENT '成本(元)',
    `response_time` INT NOT NULL DEFAULT 0 COMMENT '响应时间(ms)',
    `duration` INT DEFAULT NULL COMMENT '总耗时(ms)',
    `request` TEXT DEFAULT NULL COMMENT '请求内容',
    `response` TEXT DEFAULT NULL COMMENT '响应内容',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1成功 0失败',
    `error` TEXT DEFAULT NULL COMMENT '错误信息',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ai_logs_model_id_index` (`model_id`),
    KEY `ai_logs_user_id_index` (`user_id`),
    KEY `ai_logs_task_id_index` (`task_id`),
    KEY `ai_logs_type_index` (`type`),
    KEY `ai_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI调用日志表';
```

### 3.15 客服会话表（customer_service_sessions）

```sql
CREATE TABLE `customer_service_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_no` VARCHAR(32) NOT NULL COMMENT '会话编号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `admin_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客服管理员ID(0表示未分配)',
    `staff_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '客服人员ID',
    `title` VARCHAR(100) NOT NULL DEFAULT '咨询会话' COMMENT '会话标题',
    `status` VARCHAR(20) NOT NULL DEFAULT 'waiting' COMMENT '状态:waiting待接待 active进行中 resolved已解决 closed已关闭',
    `source` VARCHAR(20) NOT NULL DEFAULT 'web' COMMENT '来源:web网页 mobile移动端',
    `user_ip` VARCHAR(45) DEFAULT NULL COMMENT '用户IP',
    `user_agent` VARCHAR(500) DEFAULT NULL COMMENT '用户浏览器UA',
    `welcome_sent` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '欢迎消息是否已发送',
    `user_message_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户消息计数',
    `staff_message_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客服消息计数',
    `user_unread` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '用户未读数',
    `admin_unread` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '客服未读数',
    `user_last_message_at` TIMESTAMP NULL DEFAULT NULL COMMENT '用户最后消息时间',
    `staff_last_message_at` TIMESTAMP NULL DEFAULT NULL COMMENT '客服最后消息时间',
    `last_message_at` TIMESTAMP NULL DEFAULT NULL COMMENT '最后消息时间',
    `assigned_at` TIMESTAMP NULL DEFAULT NULL COMMENT '分配客服时间',
    `resolved_at` TIMESTAMP NULL DEFAULT NULL COMMENT '解决时间',
    `closed_at` TIMESTAMP NULL DEFAULT NULL COMMENT '关闭时间',
    `satisfaction_score` TINYINT DEFAULT NULL COMMENT '满意度评分',
    `rated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已评价',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `customer_service_sessions_session_no_unique` (`session_no`),
    KEY `customer_service_sessions_user_id_index` (`user_id`),
    KEY `customer_service_sessions_admin_id_index` (`admin_id`),
    KEY `customer_service_sessions_status_index` (`status`),
    KEY `customer_service_sessions_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服会话表';
```

### 3.16 客服消息表（customer_service_messages）

```sql
CREATE TABLE `customer_service_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` BIGINT UNSIGNED NOT NULL COMMENT '会话ID',
    `sender_id` BIGINT UNSIGNED NOT NULL COMMENT '发送者ID',
    `sender_type` VARCHAR(20) NOT NULL COMMENT '发送者类型:user用户 admin客服 system系统',
    `content` TEXT NOT NULL COMMENT '消息内容',
    `msg_type` VARCHAR(20) NOT NULL DEFAULT 'text' COMMENT '消息类型:text文本 image图片 file文件',
    `message_type` VARCHAR(20) DEFAULT 'text' COMMENT '消息类型(兼容)',
    `file_url` VARCHAR(500) DEFAULT '' COMMENT '文件URL',
    `file_name` VARCHAR(255) DEFAULT '' COMMENT '文件名称',
    `file_path` VARCHAR(500) DEFAULT NULL COMMENT '文件存储路径',
    `file_size` INT UNSIGNED DEFAULT 0 COMMENT '文件大小(字节)',
    `file_mime` VARCHAR(100) DEFAULT NULL COMMENT '文件MIME类型',
    `link_url` VARCHAR(500) DEFAULT NULL COMMENT '链接地址',
    `link_title` VARCHAR(200) DEFAULT NULL COMMENT '链接标题',
    `thumbnail_url` VARCHAR(500) DEFAULT NULL COMMENT '缩略图URL',
    `status` VARCHAR(20) NOT NULL DEFAULT 'sent' COMMENT '状态:sent已发送 delivered已送达 read已读',
    `read_at` TIMESTAMP NULL DEFAULT NULL COMMENT '已读时间',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `customer_service_messages_session_id_index` (`session_id`),
    KEY `customer_service_messages_sender_id_index` (`sender_id`),
    KEY `customer_service_messages_created_at_index` (`created_at`),
    KEY `customer_service_messages_session_id_created_at_index` (`session_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服消息表';
```

### 3.17 反馈表（feedbacks）

```sql
CREATE TABLE `feedbacks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` ENUM('bug','suggestion','complaint','other') NOT NULL DEFAULT 'other' COMMENT '类型',
    `title` VARCHAR(200) NOT NULL COMMENT '标题',
    `content` TEXT NOT NULL COMMENT '内容',
    `images` JSON DEFAULT NULL COMMENT '截图URL数组',
    `contact` VARCHAR(100) DEFAULT NULL COMMENT '联系方式',
    `status` ENUM('pending','processing','replied','closed') NOT NULL DEFAULT 'pending' COMMENT '状态',
    `reply` TEXT DEFAULT NULL COMMENT '回复内容',
    `replied_at` TIMESTAMP NULL DEFAULT NULL COMMENT '回复时间',
    `replied_by` INT UNSIGNED DEFAULT NULL COMMENT '回复人',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `feedbacks_status_created_at_index` (`status`, `created_at`),
    KEY `feedbacks_user_id_created_at_index` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户反馈表';
```

### 3.18 AI申诉表（analysis_appeals）

```sql
CREATE TABLE `analysis_appeals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `analysis_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '分析任务ID',
    `task_no` VARCHAR(64) DEFAULT NULL COMMENT '冗余字段',
    `reason` VARCHAR(200) NOT NULL COMMENT '申诉原因分类',
    `description` TEXT NOT NULL COMMENT '详细说明',
    `attachments` JSON DEFAULT NULL COMMENT '附件',
    `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' COMMENT '状态',
    `audit_note` TEXT DEFAULT NULL COMMENT '审核意见',
    `audited_by` INT UNSIGNED DEFAULT NULL COMMENT '审核人',
    `audited_at` TIMESTAMP NULL DEFAULT NULL COMMENT '审核时间',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `analysis_appeals_status_created_at_index` (`status`, `created_at`),
    KEY `analysis_appeals_user_id_created_at_index` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI申诉表';
```

### 3.19 风控规则表（risk_rules）

```sql
CREATE TABLE `risk_rules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL COMMENT '规则唯一编码',
    `name` VARCHAR(255) NOT NULL COMMENT '规则名称',
    `type` ENUM('register','login','payment','promotion','analysis','withdraw') NOT NULL COMMENT '适用场景',
    `action` ENUM('allow','deny','review') NOT NULL DEFAULT 'deny' COMMENT '命中后动作',
    `conditions` JSON NOT NULL COMMENT 'JSON条件',
    `priority` TINYINT NOT NULL DEFAULT 100 COMMENT '优先级',
    `enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `risk_rules_code_unique` (`code`),
    KEY `risk_rules_type_enabled_index` (`type`, `enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='风控规则表';
```

### 3.20 风控事件表（risk_events）

```sql
CREATE TABLE `risk_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '用户ID',
    `rule_code` VARCHAR(64) DEFAULT NULL COMMENT '命中的规则code',
    `type` ENUM('register','login','payment','promotion','analysis','withdraw') NOT NULL COMMENT '类型',
    `action` ENUM('allow','deny','review') NOT NULL COMMENT '动作',
    `risk_level` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low' COMMENT '风险等级',
    `context` JSON NOT NULL COMMENT '触发上下文',
    `ip` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    `note` TEXT DEFAULT NULL COMMENT '备注',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `risk_events_user_id_created_at_index` (`user_id`, `created_at`),
    KEY `risk_events_type_created_at_index` (`type`, `created_at`),
    KEY `risk_events_rule_code_index` (`rule_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='风控事件表';
```

### 3.21 黑名单表（risk_blacklists）

```sql
CREATE TABLE `risk_blacklists` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` ENUM('ip','mobile','device','user_id') NOT NULL COMMENT '黑名单类型',
    `value` VARCHAR(128) NOT NULL COMMENT '具体值',
    `reason` TEXT DEFAULT NULL COMMENT '原因',
    `created_by` INT UNSIGNED DEFAULT NULL COMMENT '创建人',
    `expires_at` TIMESTAMP NULL DEFAULT NULL COMMENT '到期时间(null=永久)',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `risk_blacklists_type_value_unique` (`type`, `value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黑名单表';
```

### 3.22 闲鱼商品表（xianyu_products）

```sql
CREATE TABLE `xianyu_products` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(100) NOT NULL COMMENT '商品名称',
    `link` VARCHAR(500) NOT NULL COMMENT '闲鱼商品链接',
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '售价(元)',
    `times` INT NOT NULL DEFAULT 0 COMMENT '赠送分析次数',
    `description` VARCHAR(255) DEFAULT NULL COMMENT '商品说明',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序权重',
    `is_enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用:0否 1是',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='闲鱼商品表';
```

### 3.23 其他表（简要）

#### articles（文章CMS）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 主键 |
| title | varchar(200) | 标题 |
| cover | varchar(500) | 封面图 |
| summary | varchar(500) | 摘要 |
| content | longtext | 内容 |
| category | varchar(50) | 分类 |
| views | int | 浏览量 |
| status | tinyint | 状态:1发布 0草稿 |
| sort_order | int | 排序 |

#### constitution_questions（体质测试题目）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | int unsigned PK | 主键 |
| category | varchar(50) | 题目分类 |
| question | varchar(200) | 题目内容 |
| type | varchar(20) | 题目类型:single multiple |
| options | json | 选项JSON |
| sort_order | int | 排序 |
| is_enabled | tinyint | 是否启用 |

#### constitution_answers（体质答题记录）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 主键 |
| task_id | bigint unsigned FK | 分析任务ID |
| user_id | bigint unsigned FK | 用户ID |
| question_id | bigint unsigned FK | 题目ID |
| answer | varchar(20) | 答案 |
| scores | json | 各体质得分 |

#### health_qa_sessions（健康问答会话）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 主键 |
| session_no | varchar(32) UNIQUE | 会话编号 |
| user_id | bigint unsigned FK | 用户ID |
| title | varchar(100) | 会话标题 |
| status | tinyint | 状态:1进行中 0已结束 |

#### health_qa_messages（健康问答消息）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 主键 |
| session_id | bigint unsigned FK | 会话ID |
| role | varchar(20) | 角色:user assistant |
| content | text | 消息内容 |
| tokens | int | Token消耗 |

#### product_packages（次数包）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | int unsigned PK | 主键 |
| name | varchar(50) | 套餐名称 |
| type | varchar(20) | 类型:tongue face all |
| times | int | 次数 |
| days | int | 有效期(天) |
| price | decimal(10,2) | 价格 |
| original_price | decimal(10,2) | 原价 |
| is_recommend | tinyint | 是否推荐 |
| is_enabled | tinyint | 是否启用 |
| sort_order | int | 排序 |

#### system_configs（系统配置）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | int unsigned PK | 主键 |
| key | varchar(100) UNIQUE | 配置键 |
| value | text | 配置值 |
| name | varchar(100) | 配置名称 |
| group_name | varchar(50) | 配置分组 |
| type | varchar(20) | 类型 |
| remark | varchar(500) | 备注 |

#### system_messages（系统消息）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 主键 |
| user_id | bigint unsigned | 接收用户ID(0=广播) |
| title | varchar(200) | 消息标题 |
| content | text | 消息内容 |
| type | varchar(30) | 类型:notice activity system balance |
| target_url | varchar(500) | 跳转链接 |
| is_read | tinyint | 是否已读 |
| read_at | timestamp | 阅读时间 |

#### invite_clicks（邀请点击记录）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 主键 |
| promoter_id | bigint unsigned | 推广员ID |
| invite_code | varchar(20) | 邀请码 |
| ip | varchar(45) | 访问者IP |
| user_agent | varchar(500) | User-Agent |
| device_type | varchar(20) | 设备类型 |
| is_duplicate_ip | tinyint | 是否重复IP |
| is_suspicious | tinyint | 是否可疑 |
| fingerprint | varchar(64) | 浏览器指纹 |
| clicked_at | timestamp | 点击时间 |

#### invite_registrations（邀请注册记录）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 主键 |
| promoter_id | bigint unsigned | 推广员ID |
| user_id | bigint unsigned UNIQUE | 注册用户ID |
| invite_code | varchar(20) | 邀请码 |
| ip | varchar(45) | 注册IP |
| is_fraud | tinyint | 是否作弊 |
| fraud_reason | varchar(200) | 作弊原因 |
| risk_score | tinyint unsigned | 风险分数0-100 |

---

## 4. 数据生命周期

| 表名 | 保留策略 | 说明 |
|------|---------|------|
| users | 永久保留 | 用户基础数据 |
| user_profiles | 永久保留 | 用户详情 |
| user_analysis_logs | 永久保留 | 分析次数流水 |
| user_balance_logs | 永久保留 | 余额流水 |
| user_login_logs | 保留1年 | 登录日志 |
| analysis_tasks | 永久保留 | 分析记录 |
| analysis_reports | 永久保留 | 报告数据 |
| orders | 永久保留 | 订单数据 |
| payments | 永久保留 | 支付数据 |
| refunds | 永久保留 | 退款数据 |
| promoters | 永久保留 | 推广员数据 |
| commissions | 永久保留 | 佣金数据 |
| withdraws | 永久保留 | 提现数据 |
| ai_logs | 保留6个月 | 日志定期清理 |
| invite_clicks | 保留3个月 | 点击记录 |
| invite_registrations | 永久保留 | 注册记录 |
| risk_events | 保留1年 | 风控事件 |
| operation_logs | 保留6个月 | 操作日志 |
| system_messages | 保留1年 | 系统消息 |
| customer_service_messages | 保留2年 | 客服消息 |

---

## 5. 数据库规范

### 5.1 命名规范

| 项目 | 规范 | 示例 |
|------|------|------|
| 表名 | 小写+下划线 | users, analysis_tasks |
| 字段名 | 小写+下划线 | user_id, task_no |
| 索引名 | 前缀+字段 | uk_mobile, idx_user_id |
| 主键 | id | id BIGINT UNSIGNED |

### 5.2 字段规范

| 类型 | 规范 |
|------|------|
| 主键 | BIGINT UNSIGNED AUTO_INCREMENT |
| 状态 | TINYINT NOT NULL DEFAULT 0 |
| 金额 | DECIMAL(10,2) NOT NULL DEFAULT 0 |
| 时间 | TIMESTAMP NULL DEFAULT NULL |
| 枚举 | TINYINT + 注释说明 或 ENUM类型 |
| JSON | JSON DEFAULT NULL |
| 外键 | 命名:表名_字段名_foreign |

### 5.3 字符集

- 数据库：utf8mb4
- 表：utf8mb4
- 排序规则：utf8mb4_unicode_ci

---

> **相关文档**：
> - [users表详细说明](DATABASE_USERS.md)
> - [系统架构设计](03-architecture.md)
> - [API 设计](05-api.md)
> - [后端设计](08-backend.md)
