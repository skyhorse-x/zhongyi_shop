# 数据库设计

> **版本**：v1.1  
> **日期**：2026-07-28  
> **对应 ai.md 阶段**：第三阶段（数据库设计）  
> **变更说明**：增加体质测试题库表、健康问答表、面诊分析字段

---

## 1. ER图（核心实体关系）

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│   users     │       │user_profiles│       │ user_login_ │
│             │───────│             │       │    logs     │
│ 用户基础信息 │  1:1  │ 用户详细信息 │       │ 登录日志    │
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
│  questions  │───────│   answers   │───────│   reports   │
│ 体质测试题目 │  1:N  │ 用户答题记录 │  1:1  │ 体质报告    │
└─────────────┘       └─────────────┘       └─────────────┘

┌─────────────┐       ┌─────────────┐
│  health_    │       │  health_    │
│  qa_sessions│───────│  qa_messages│
│ 问答会话    │  1:N  │ 问答消息    │
└─────────────┘       └─────────────┘
```

---

## 2. 核心数据表结构

### 2.1 用户表（users）

```sql
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
    `mobile` VARCHAR(20) NOT NULL COMMENT '手机号',
    `password` VARCHAR(255) DEFAULT NULL COMMENT '密码(bcrypt)',
    `nickname` VARCHAR(50) DEFAULT NULL COMMENT '昵称',
    `avatar` VARCHAR(500) DEFAULT NULL COMMENT '头像URL',
    `openid` VARCHAR(100) DEFAULT NULL COMMENT '微信openid',
    `unionid` VARCHAR(100) DEFAULT NULL COMMENT '微信unionid',
    `promoter_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '推广员ID(邀请人)',
    `invite_code` VARCHAR(20) DEFAULT NULL COMMENT '邀请码',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1正常 0禁用',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(50) DEFAULT NULL COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_mobile` (`mobile`),
    UNIQUE KEY `uk_openid` (`openid`),
    KEY `idx_promoter_id` (`promoter_id`),
    KEY `idx_invite_code` (`invite_code`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';
```

### 2.2 用户详情表（user_profiles）

```sql
CREATE TABLE `user_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `gender` TINYINT DEFAULT 0 COMMENT '性别:0未知 1男 2女',
    `birthday` DATE DEFAULT NULL COMMENT '生日',
    `height` DECIMAL(5,2) DEFAULT NULL COMMENT '身高(cm)',
    `weight` DECIMAL(5,2) DEFAULT NULL COMMENT '体重(kg)',
    `blood_type` VARCHAR(10) DEFAULT NULL COMMENT '血型',
    `constitution_type` VARCHAR(20) DEFAULT NULL COMMENT '体质类型',
    `chronic_disease` TEXT DEFAULT NULL COMMENT '慢性病(JSON)',
    `allergy` TEXT DEFAULT NULL COMMENT '过敏史(JSON)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_constitution_type` (`constitution_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户详情表';
```

### 2.3 分析任务表（analysis_tasks）

```sql
CREATE TABLE `analysis_tasks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_no` VARCHAR(32) NOT NULL COMMENT '任务编号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` VARCHAR(20) NOT NULL COMMENT '类型:tongue舌诊 face面诊 constitution体质',
    `image_url` VARCHAR(500) DEFAULT NULL COMMENT '原始图片URL',
    `image_md5` VARCHAR(32) DEFAULT NULL COMMENT '图片MD5(用于缓存)',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待处理 1处理中 2完成 3失败',
    `model` VARCHAR(50) DEFAULT NULL COMMENT '使用的AI模型',
    `prompt` TEXT DEFAULT NULL COMMENT '使用的Prompt',
    `tokens` INT DEFAULT 0 COMMENT '消耗Token数',
    `cost` DECIMAL(10,4) DEFAULT 0 COMMENT 'AI调用成本(元)',
    `result` JSON DEFAULT NULL COMMENT 'AI返回结果',
    `error_msg` VARCHAR(500) DEFAULT NULL COMMENT '错误信息',
    `started_at` DATETIME DEFAULT NULL COMMENT '开始处理时间',
    `completed_at` DATETIME DEFAULT NULL COMMENT '完成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_task_no` (`task_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_type` (`type`),
    KEY `idx_image_md5` (`image_md5`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分析任务表';
```

### 2.4 分析报告表（analysis_reports）

```sql
CREATE TABLE `analysis_reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id` BIGINT UNSIGNED NOT NULL COMMENT '任务ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` VARCHAR(20) NOT NULL COMMENT '报告类型:tongue face constitution',
    `health_score` TINYINT DEFAULT NULL COMMENT '健康评分(0-100)',
    
    -- 舌诊专用字段
    `tongue_color` VARCHAR(50) DEFAULT NULL COMMENT '舌色',
    `tongue_shape` VARCHAR(100) DEFAULT NULL COMMENT '舌形',
    `tongue_coating` VARCHAR(100) DEFAULT NULL COMMENT '舌苔',
    `sublingual_vein` VARCHAR(100) DEFAULT NULL COMMENT '舌下络脉',
    `tongue_analysis` TEXT DEFAULT NULL COMMENT '舌象分析详情',
    
    -- 面诊专用字段
    `face_color` VARCHAR(50) DEFAULT NULL COMMENT '面色',
    `lip_color` VARCHAR(50) DEFAULT NULL COMMENT '唇色',
    `eye_analysis` VARCHAR(200) DEFAULT NULL COMMENT '眼部分析',
    `skin_analysis` VARCHAR(200) DEFAULT NULL COMMENT '皮肤分析',
    `face_analysis` TEXT DEFAULT NULL COMMENT '面诊分析详情',
    
    -- 体质专用字段
    `constitution_type` VARCHAR(20) DEFAULT NULL COMMENT '体质类型',
    `constitution_analysis` TEXT DEFAULT NULL COMMENT '体质分析详情',
    
    -- 通用字段
    `life_advice` TEXT DEFAULT NULL COMMENT '生活建议',
    `diet_advice` TEXT DEFAULT NULL COMMENT '饮食建议',
    `exercise_advice` TEXT DEFAULT NULL COMMENT '运动建议',
    `precautions` TEXT DEFAULT NULL COMMENT '注意事项',
    `summary` VARCHAR(500) DEFAULT NULL COMMENT '摘要(未付费可见)',
    `content` JSON DEFAULT NULL COMMENT '完整报告内容(付费可见)',
    `is_paid` TINYINT NOT NULL DEFAULT 0 COMMENT '是否已付费:0否 1是',
    `viewed_at` DATETIME DEFAULT NULL COMMENT '查看时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_task_id` (`task_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_type` (`type`),
    KEY `idx_is_paid` (`is_paid`),
    KEY `idx_constitution_type` (`constitution_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分析报告表';
```

### 2.5 体质测试题目表（constitution_questions）

```sql
CREATE TABLE `constitution_questions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category` VARCHAR(50) NOT NULL COMMENT '题目分类:躯体心理情志',
    `question` VARCHAR(500) NOT NULL COMMENT '题目内容',
    `type` VARCHAR(20) NOT NULL DEFAULT 'single' COMMENT '题型:single单选 multiple多选',
    `options` JSON NOT NULL COMMENT '选项 [{"key":"A","text":"选项内容","scores":{"qixu":2,"pinghe":0}}]',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `is_enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用:0否 1是',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`),
    KEY `idx_is_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='体质测试题目表';
```

### 2.6 体质测试答题记录表（constitution_answers）

```sql
CREATE TABLE `constitution_answers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id` BIGINT UNSIGNED NOT NULL COMMENT '分析任务ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `question_id` INT UNSIGNED NOT NULL COMMENT '题目ID',
    `answer` VARCHAR(20) NOT NULL COMMENT '答案:A B C D',
    `scores` JSON DEFAULT NULL COMMENT '各体质得分 {"qixu":2,"pinghe":0}',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_task_id` (`task_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='体质测试答题记录表';
```

### 2.7 健康问答会话表（health_qa_sessions）

```sql
CREATE TABLE `health_qa_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_no` VARCHAR(32) NOT NULL COMMENT '会话编号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `title` VARCHAR(100) DEFAULT NULL COMMENT '会话标题(首问摘要)',
    `last_question_at` DATETIME DEFAULT NULL COMMENT '最后提问时间',
    `message_count` INT NOT NULL DEFAULT 0 COMMENT '消息数量',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1进行中 2已结束',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_session_no` (`session_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='健康问答会话表';
```

### 2.8 健康问答消息表（health_qa_messages）

```sql
CREATE TABLE `health_qa_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` BIGINT UNSIGNED NOT NULL COMMENT '会话ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `role` VARCHAR(20) NOT NULL COMMENT '角色:user用户 assistant助手',
    `content` TEXT NOT NULL COMMENT '消息内容',
    `tokens` INT DEFAULT 0 COMMENT 'Token消耗',
    `cost` DECIMAL(10,4) DEFAULT 0 COMMENT '成本(元)',
    `model` VARCHAR(50) DEFAULT NULL COMMENT '使用的模型',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_session_id` (`session_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='健康问答消息表';
```

### 2.9 订单表（orders）

```sql
CREATE TABLE `orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单编号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `type` VARCHAR(20) NOT NULL COMMENT '类型:analysis分析 member会员 times次数包',
    `relation_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联ID(任务ID或会员ID)',
    `relation_type` VARCHAR(50) DEFAULT NULL COMMENT '关联类型:tongue face constitution qa',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '订单金额(元)',
    `discount_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '优惠金额',
    `pay_amount` DECIMAL(10,2) NOT NULL COMMENT '实付金额',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待支付 1已支付 2已取消 3已退款',
    `pay_time` DATETIME DEFAULT NULL COMMENT '支付时间',
    `pay_type` VARCHAR(20) DEFAULT NULL COMMENT '支付方式:wechat支付宝',
    `pay_trade_no` VARCHAR(100) DEFAULT NULL COMMENT '第三方支付流水号',
    `refund_time` DATETIME DEFAULT NULL COMMENT '退款时间',
    `refund_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '退款金额',
    `remark` VARCHAR(500) DEFAULT NULL COMMENT '备注',
    `expired_at` DATETIME NOT NULL COMMENT '订单过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_relation` (`relation_id`, `relation_type`),
    KEY `idx_pay_trade_no` (`pay_trade_no`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单表';
```

### 2.10 支付记录表（payments）

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
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_no` (`order_no`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_trade_no` (`trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付记录表';
```

### 2.11 推广员表（promoters）

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
    `total_commission` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '累计佣金',
    `frozen_commission` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '冻结佣金',
    `withdrawn_commission` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '已提现佣金',
    `activated_at` DATETIME DEFAULT NULL COMMENT '开通时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    UNIQUE KEY `uk_invite_code` (`invite_code`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广员表';
```

### 2.12 佣金记录表（commissions）

```sql
CREATE TABLE `commissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `promoter_id` BIGINT UNSIGNED NOT NULL COMMENT '推广员ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '消费用户ID',
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单编号',
    `order_amount` DECIMAL(10,2) NOT NULL COMMENT '订单金额',
    `commission_rate` DECIMAL(5,2) NOT NULL COMMENT '佣金比例',
    `commission_amount` DECIMAL(10,2) NOT NULL COMMENT '佣金金额',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0冻结 1已结算 2已提现',
    `frozen_at` DATETIME DEFAULT NULL COMMENT '冻结时间',
    `settled_at` DATETIME DEFAULT NULL COMMENT '结算时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_promoter_id` (`promoter_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_order_no` (`order_no`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='佣金记录表';
```

### 2.13 提现记录表（withdraws）

```sql
CREATE TABLE `withdraws` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `withdraw_no` VARCHAR(32) NOT NULL COMMENT '提现编号',
    `promoter_id` BIGINT UNSIGNED NOT NULL COMMENT '推广员ID',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '提现金额',
    `fee` DECIMAL(10,2) DEFAULT 0 COMMENT '手续费',
    `actual_amount` DECIMAL(10,2) NOT NULL COMMENT '实际到账',
    `pay_type` VARCHAR(20) NOT NULL COMMENT '提现方式:wechat微信',
    `pay_account` VARCHAR(100) NOT NULL COMMENT '收款账号',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待审核 1审核通过 2审核拒绝 3打款中 4打款成功 5打款失败',
    `audit_remark` VARCHAR(500) DEFAULT NULL COMMENT '审核备注',
    `audited_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `paid_at` DATETIME DEFAULT NULL COMMENT '打款时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_withdraw_no` (`withdraw_no`),
    KEY `idx_promoter_id` (`promoter_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提现记录表';
```

### 2.14 AI模型配置表（ai_models）

```sql
CREATE TABLE `ai_models` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL COMMENT '模型名称',
    `provider` VARCHAR(50) NOT NULL COMMENT '提供商:doubao deepseek openai',
    `model` VARCHAR(50) NOT NULL COMMENT '模型标识',
    `api_url` VARCHAR(500) NOT NULL COMMENT 'API地址',
    `api_key` VARCHAR(255) NOT NULL COMMENT 'API密钥',
    `type` VARCHAR(20) NOT NULL COMMENT '类型:vision视觉 chat文本',
    `analysis_type` VARCHAR(50) DEFAULT NULL COMMENT '分析类型:tongue舌诊 face面诊 constitution体质 qa问答(可多选JSON)',
    `prompt` TEXT DEFAULT NULL COMMENT '默认Prompt',
    `tokens_price` DECIMAL(10,6) NOT NULL DEFAULT 0 COMMENT '每Token价格(元)',
    `timeout` INT NOT NULL DEFAULT 30 COMMENT '超时时间(秒)',
    `retry_times` TINYINT NOT NULL DEFAULT 3 COMMENT '重试次数',
    `is_enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用:0否 1是',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序(优先级)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_is_enabled` (`is_enabled`),
    KEY `idx_type` (`type`),
    KEY `idx_analysis_type` (`analysis_type`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI模型配置表';
```

### 2.15 AI调用日志表（ai_logs）

```sql
CREATE TABLE `ai_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `model_id` INT NOT NULL COMMENT '模型ID',
    `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '用户ID',
    `task_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '任务ID',
    `type` VARCHAR(20) DEFAULT NULL COMMENT '调用类型:tongue face constitution qa',
    `prompt_tokens` INT DEFAULT 0 COMMENT '提示词Token数',
    `completion_tokens` INT DEFAULT 0 COMMENT '完成Token数',
    `total_tokens` INT DEFAULT 0 COMMENT '总Token数',
    `cost` DECIMAL(10,4) DEFAULT 0 COMMENT '成本(元)',
    `response_time` INT DEFAULT 0 COMMENT '响应时间(ms)',
    `status` TINYINT DEFAULT 1 COMMENT '状态:1成功 0失败',
    `error` TEXT DEFAULT NULL COMMENT '错误信息',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_model_id` (`model_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_task_id` (`task_id`),
    KEY `idx_type` (`type`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI调用日志表';
```

### 2.16 管理员表（admins）

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
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';
```

### 2.17 系统配置表（system_configs）

```sql
CREATE TABLE `system_configs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(100) NOT NULL COMMENT '配置键',
    `value` TEXT DEFAULT NULL COMMENT '配置值',
    `name` VARCHAR(100) DEFAULT NULL COMMENT '配置名称',
    `group` VARCHAR(50) DEFAULT NULL COMMENT '配置分组',
    `type` VARCHAR(20) DEFAULT 'text' COMMENT '类型:text number select json',
    `remark` VARCHAR(500) DEFAULT NULL COMMENT '备注',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_key` (`key`),
    KEY `idx_group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';
```

### 2.18 次数包表（product_packages）

```sql
CREATE TABLE `product_packages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT '套餐名称',
    `type` VARCHAR(50) NOT NULL COMMENT '类型:tongue舌诊 face面诊 constitution体质 qa问答',
    `times` INT NOT NULL COMMENT '次数',
    `days` INT NOT NULL DEFAULT 30 COMMENT '有效天数',
    `price` DECIMAL(10,2) NOT NULL COMMENT '价格(元)',
    `original_price` DECIMAL(10,2) DEFAULT NULL COMMENT '原价',
    `is_recommend` TINYINT NOT NULL DEFAULT 0 COMMENT '是否推荐:0否 1是',
    `is_enabled` TINYINT NOT NULL DEFAULT 1 COMMENT '是否启用:0否 1是',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_type` (`type`),
    KEY `idx_is_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='次数包表';
```

---

## 3. 索引规范

| 表名 | 索引名 | 字段 | 类型 | 说明 |
|------|--------|------|------|------|
| users | uk_mobile | mobile | UNIQUE | 手机号唯一 |
| users | uk_openid | openid | UNIQUE | 微信openid唯一 |
| users | idx_promoter_id | promoter_id | INDEX | 推广员查询 |
| analysis_tasks | uk_task_no | task_no | UNIQUE | 任务编号唯一 |
| analysis_tasks | idx_type | type | INDEX | 类型筛选 |
| analysis_tasks | idx_image_md5 | image_md5 | INDEX | 图片缓存查询 |
| analysis_reports | uk_task_id | task_id | UNIQUE | 任务ID唯一 |
| analysis_reports | idx_type | type | INDEX | 报告类型筛选 |
| constitution_questions | idx_category | category | INDEX | 题目分类 |
| constitution_answers | idx_task_id | task_id | INDEX | 答题记录查询 |
| health_qa_sessions | uk_session_no | session_no | UNIQUE | 会话编号唯一 |
| health_qa_messages | idx_session_id | session_id | INDEX | 会话消息查询 |
| orders | uk_order_no | order_no | UNIQUE | 订单编号唯一 |
| orders | idx_relation | relation_id, relation_type | INDEX | 关联查询 |
| orders | idx_pay_trade_no | pay_trade_no | INDEX | 支付回调查询 |
| promoters | uk_invite_code | invite_code | UNIQUE | 推广码唯一 |
| commissions | idx_promoter_id | promoter_id | INDEX | 佣金查询 |
| ai_logs | idx_type | type | INDEX | 调用类型筛选 |

---

## 4. 数据生命周期

| 表名 | 保留策略 | 说明 |
|------|---------|------|
| users | 永久保留 | 用户基础数据 |
| user_profiles | 永久保留 | 用户详情 |
| analysis_tasks | 永久保留 | 分析记录 |
| analysis_reports | 永久保留 | 报告数据 |
| constitution_questions | 永久保留 | 题库数据 |
| constitution_answers | 永久保留 | 答题记录 |
| health_qa_sessions | 保留2年 | 问答会话 |
| health_qa_messages | 保留2年 | 问答消息 |
| orders | 永久保留 | 订单数据 |
| payments | 永久保留 | 支付数据 |
| promoters | 永久保留 | 推广员数据 |
| commissions | 永久保留 | 佣金数据 |
| withdraws | 永久保留 | 提现数据 |
| ai_logs | 保留6个月 | 日志定期清理 |
| product_packages | 永久保留 | 套餐配置 |

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
| 时间 | DATETIME DEFAULT NULL |
| 枚举 | TINYINT + 注释说明 |
| JSON | JSON DEFAULT NULL |

### 5.3 字符集

- 数据库：utf8mb4
- 表：utf8mb4
- 排序规则：utf8mb4_unicode_ci

---

> **相关文档**：
> - [系统架构设计](03-architecture.md)
> - [API 设计](05-api.md)
> - [后端设计](08-backend.md)
