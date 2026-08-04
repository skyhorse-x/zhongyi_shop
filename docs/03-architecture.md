# 系统架构设计

> **版本**：v2.0  
> **日期**：2026-08-04  
> **对应 ai.md 阶段**：第二阶段（系统设计）+ ADR + 模块依赖  
> **变更说明**：根据实际代码修正技术栈描述（Sanctum认证、File缓存、Database队列、Element Plus统一UI库、单web项目）

---

## 1. 整体架构

```
┌─────────────────────────────────────────────────────────────────────┐
│                            CDN                                      │
│                     静态资源加速 + 图片加速                          │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Nginx 网关                                    │
│               SSL终止 + 限流 + 反向代理 + 静态缓存                    │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      Web 前端（单一项目）                             │
│                  Vue3 + Element Plus + TailwindCSS                  │
│              同时适配用户端(移动端)和管理端(PC端)                      │
└─────────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      API 服务器（Laravel 13）                        │
│               Sanctum 认证 + File 缓存 + Database 队列               │
└─────────────────────────────────────────────────────────────────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        │                       │                       │
        ▼                       ▼                       ▼
┌───────────────┐     ┌───────────────┐     ┌───────────────┐
│    SQLite     │     │  本地文件存储  │     │   AI 服务     │
│  (开发数据库)  │     │  (上传文件)   │     │ (豆包/DeepSeek)│
└───────────────┘     └───────────────┘     └───────────────┘
```

---

## 2. 系统架构图

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              客户端层                                        │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                   单一 Web 项目 (web/)                               │    │
│  │     用户端 H5 (postcss-px-to-viewport适配) + 管理端 PC               │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              网关层                                          │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                          Nginx                                      │    │
│  │   - SSL终止    - 负载均衡    - 限流    - 静态资源缓存                  │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              应用层（Laravel 13 API）                        │
│                                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │  用户中心    │  │  AI分析中心  │  │   支付中心   │  │   推广中心   │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
│                                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │  健康问答    │  │   客服系统   │  │   风控系统   │  │  数据分析   │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
│                                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │  系统消息    │  │   反馈申诉   │  │   退款管理   │  │  闲鱼商品   │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              数据层                                          │
│                                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   SQLite    │  │  File 缓存   │  │  本地存储    │  │   AI服务    │        │
│  │  (数据库)    │  │  (CacheService)│ │  (uploads)  │  │ (豆包/DeepSeek)│      │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. 技术栈选型

### 3.1 前端技术栈

| 技术 | 版本 | 用途 | 选择理由 |
|------|------|------|---------|
| Vue3 | ^3.5.39 | 框架 | 组合式API，性能优秀，生态丰富 |
| TypeScript | ~6.0.2 | 语言 | 类型安全，减少Bug |
| Vite | ^8.1.1 | 构建工具 | 开发体验极佳，构建速度快 |
| Pinia | ^4.0.2 | 状态管理 | Vue3官方推荐，轻量简洁 |
| Vue Router | ^4.6.4 | 路由 | Vue官方路由 |
| Element Plus | ^2.14.3 | UI组件库 | 同时用于用户端和管理端 |
| Axios | ^1.18.1 | HTTP请求 | 成熟稳定，拦截器丰富 |
| TailwindCSS | ^4.3.3 | 样式框架 | 原子化CSS，开发效率高 |
| postcss-px-to-viewport | ^1.2.5 | 移动端适配 | 自动将px转为vw实现移动端适配 |

### 3.2 后端技术栈

| 技术 | 版本 | 用途 | 选择理由 |
|------|------|------|---------|
| Laravel | ^13.8 | 框架 | 开发效率高，生态丰富 |
| PHP | ^8.3 | 语言 | 性能稳定，类型系统完善 |
| Laravel Sanctum | ^4.0 | API认证 | PersonalAccessToken，轻量级认证 |
| SQLite | - | 开发数据库 | 零配置，便于开发 |
| File Cache | - | 缓存驱动 | 无需额外服务，适合中小型项目 |
| Database Queue | - | 队列驱动 | 无需Redis，降低运维复杂度 |
| Nginx | ^1.24 | Web服务器 | 高性能，反向代理 |

### 3.3 AI服务

| 服务 | 用途 | 选择理由 |
|------|------|---------|
| 豆包 Vision | 舌诊/面诊图片识别 | 中文理解好，价格适中 |
| DeepSeek | 备选模型/健康问答 | 性价比高，中文优秀 |
| OpenAI | 海外备选 | 技术领先 |

---

## 4. 模块划分

### 4.1 前端模块

| 模块 | 职责 | 技术 |
|------|------|------|
| 用户端 | 面向C端用户的移动端页面 | Vue3 + Element Plus + postcss-px-to-viewport |
| 管理端 | 面向运营人员的管理后台 | Vue3 + Element Plus + TailwindCSS |

### 4.2 后端模块

| 模块 | 职责 | 依赖 |
|------|------|------|
| 用户中心 | 注册、登录、个人信息、Sanctum鉴权 | MySQL, File Cache |
| AI分析中心 | 舌诊、面诊、体质测试、健康问答、报告生成 | AI服务, MySQL, File Cache |
| 支付中心 | 微信支付、支付宝、订单、余额支付 | 支付SDK, MySQL |
| 推广中心 | 推广码、佣金计算、提现 | MySQL |
| 客服系统 | 客服会话、消息、常用话术、评价 | MySQL |
| 风控系统 | 规则引擎、黑名单、事件记录 | MySQL, CacheService |
| 数据分析 | 漏斗、留存、收入、用户增长、推广转化 | MySQL |
| 系统消息 | 通知、公告 | MySQL |
| 反馈申诉 | 用户反馈、AI诊断申诉、退款管理 | MySQL |
| 闲鱼商品 | 充值商品管理 | MySQL |

---

## 5. 模块关系

```
                    ┌─────────────────┐
                    │    用户中心      │
                    │  (基础模块)      │
                    └────────┬────────┘
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│   AI分析中心     │ │    支付中心      │ │    推广中心      │
│                 │ │                 │ │                 │
│  依赖：用户中心  │ │  依赖：用户中心  │ │  依赖：用户中心  │
│  依赖：AI服务   │ │  依赖：支付SDK   │ │  依赖：支付中心  │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         └───────────────────┼───────────────────┘
                             │
                             ▼
                    ┌─────────────────┐
                    │    客服系统      │
                    │    风控系统      │
                    │    数据分析      │
                    │   (业务支撑)     │
                    └─────────────────┘
```

---

## 6. 领域划分

### 6.1 领域定义

| 领域 | 描述 | 核心实体 |
|------|------|---------|
| 用户领域 | 用户注册、登录、认证 | User, UserProfile, Admin |
| AI分析领域 | AI分析任务、报告 | AnalysisTask, AnalysisReport |
| 支付领域 | 支付订单、余额、退款 | Order, Payment, Refund |
| 推广领域 | 推广关系、佣金 | Promoter, Commission, Withdraw |
| 客服领域 | 客服会话、消息 | CustomerServiceSession, CustomerServiceMessage |
| 风控领域 | 风控规则、黑名单 | RiskRule, RiskEvent, RiskBlacklist |
| 内容领域 | 文章、系统消息 | Article, SystemMessage |

---

## 7. 架构选择

**选择：Monolith（单体架构）+ 简化分层**

### 实际分层结构

```
┌─────────────────────────────────────────────────────────┐
│                      Controller 层                       │
│              接收请求、参数验证、调用Service              │
├─────────────────────────────────────────────────────────┤
│                       Service 层                         │
│              业务逻辑、事务管理                          │
├─────────────────────────────────────────────────────────┤
│                       Model 层                           │
│              数据模型、关系定义、查询构建                 │
├─────────────────────────────────────────────────────────┤
│                      数据库                              │
└─────────────────────────────────────────────────────────┘
```

**说明**：
- 无独立 Repository 层（直接使用 Eloquent Model）
- 无队列 Jobs（AI 分析采用同步处理）
- 无 Events/Listeners（业务逻辑直接在 Service 中处理）
- 无 Horizon（未安装）

---

## 8. 目录结构

### 8.1 前端目录结构（单一 web/ 项目）

```
web/
├── public/                     # 静态资源
│   ├── index.html
│   ├── favicon.svg
│   └── icons.svg
├── src/
│   ├── api/                    # API接口
│   │   ├── admin.ts
│   │   ├── auth.ts
│   │   └── request.ts
│   ├── assets/                 # 静态资源
│   ├── components/             # 公共组件
│   │   ├── analysis/           # 分析相关组件
│   │   │   ├── HealthAdvice.vue
│   │   │   ├── ReportHeader.vue
│   │   │   └── ReportSummary.vue
│   │   ├── base/               # 基础组件
│   │   │   ├── BaseCard.vue
│   │   │   ├── BaseDialog.vue
│   │   │   ├── BaseForm.vue
│   │   │   ├── BasePagination.vue
│   │   │   ├── BaseSearch.vue
│   │   │   ├── BaseTable.vue
│   │   │   └── index.ts
│   │   └── chat/               # 聊天组件
│   │       ├── MessageBubble.vue
│   │       ├── MessageInput.vue
│   │       └── SessionListItem.vue
│   ├── composables/            # 组合式函数
│   ├── config/                 # 配置
│   ├── hooks/                  # 自定义钩子
│   ├── layouts/                # 布局组件
│   │   ├── AdminLayout.vue
│   │   └── MiniProgramLayout.vue
│   ├── router/                 # 路由
│   │   ├── index.ts
│   │   └── modules/
│   │       ├── admin.ts
│   │       ├── analysis.ts
│   │       ├── common.ts
│   │       └── user.ts
│   ├── stores/                 # Pinia状态管理
│   │   ├── admin.ts
│   │   ├── analysis.ts
│   │   ├── auth.ts
│   │   ├── chat.ts
│   │   ├── order.ts
│   │   ├── promoter.ts
│   │   └── user.ts
│   ├── styles/                 # 样式文件
│   │   ├── index.css
│   │   └── mobile.css
│   ├── types/                  # 类型定义
│   ├── utils/                  # 工具函数
│   ├── views/                  # 页面
│   │   ├── admin/              # 管理端页面
│   │   │   ├── admins.vue
│   │   │   ├── ai.vue
│   │   │   ├── analytics.vue
│   │   │   ├── articles.vue
│   │   │   ├── constitution.vue
│   │   │   ├── customer-service.vue
│   │   │   ├── dashboard.vue
│   │   │   ├── login.vue
│   │   │   ├── orders.vue
│   │   │   ├── packages.vue
│   │   │   ├── promoters.vue
│   │   │   ├── risk.vue
│   │   │   ├── settings.vue
│   │   │   ├── users.vue
│   │   │   ├── withdraws.vue
│   │   │   └── xianyu-products.vue
│   │   ├── analysis/           # 分析页面
│   │   │   ├── face.vue
│   │   │   ├── result.vue
│   │   │   └── tongue.vue
│   │   ├── auth/               # 认证页面
│   │   │   ├── login.vue
│   │   │   └── register.vue
│   │   ├── constitution/       # 体质测试
│   │   ├── health/             # 健康档案
│   │   ├── home/               # 首页
│   │   ├── member/             # 会员中心
│   │   ├── messages/           # 消息中心
│   │   ├── packages/           # 套餐
│   │   ├── promoter/           # 推广中心
│   │   ├── qa/                 # 健康问答
│   │   ├── recharge/           # 充值
│   │   └── user/               # 用户相关
│   ├── App.vue                 # 根组件
│   └── main.ts                 # 入口文件
├── .env.development            # 开发环境配置
├── .env.example                # 环境变量示例
├── package.json
├── tsconfig.json
└── vite.config.ts
```

### 8.2 后端目录结构（Laravel 13）

```
app/
├── Console/Commands/           # 命令行
│   ├── CleanDuplicateData.php
│   ├── ClearPlaceholderApiKeys.php
│   ├── ResetAdminPassword.php
│   └── SettleCommissions.php
├── Http/
│   ├── Controllers/            # 控制器
│   │   ├── Api/V1/
│   │   │   ├── Admin/          # 管理端控制器
│   │   │   │   ├── AnalyticsController.php
│   │   │   │   ├── AppealController.php
│   │   │   │   ├── CustomerServiceController.php
│   │   │   │   ├── CustomerServiceManageController.php
│   │   │   │   ├── CustomerServiceRatingController.php
│   │   │   │   ├── FeedbackController.php
│   │   │   │   ├── RefundController.php
│   │   │   │   ├── RiskController.php
│   │   │   │   └── XianyuProductController.php
│   │   │   ├── AdminController.php (统一后台管理)
│   │   │   ├── AnalysisController.php
│   │   │   ├── AppealController.php
│   │   │   ├── ArticleController.php
│   │   │   ├── AuthController.php
│   │   │   ├── ConfigController.php
│   │   │   ├── ConstitutionController.php
│   │   │   ├── CustomerServiceController.php
│   │   │   ├── CustomerServiceRatingController.php
│   │   │   ├── FeedbackController.php
│   │   │   ├── HealthController.php
│   │   │   ├── PackageController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── PromoterController.php
│   │   │   ├── QaController.php
│   │   │   ├── RefundController.php
│   │   │   ├── SystemMessageController.php
│   │   │   ├── UserController.php
│   │   │   └── XianyuProductController.php
│   │   └── Controller.php
│   └── Middleware/             # 中间件
│       ├── AdminMiddleware.php
│       ├── AuthenticateOrAdmin.php
│       ├── RequestLogMiddleware.php
│       ├── RiskControlMiddleware.php
│       ├── SuperAdminMiddleware.php
│       └── VisitCounterMiddleware.php
├── Models/                     # 模型
│   ├── Admin.php
│   ├── AiLog.php
│   ├── AiModel.php
│   ├── AnalysisAppeal.php
│   ├── AnalysisReport.php
│   ├── AnalysisTask.php
│   ├── Article.php
│   ├── BalanceInsufficientLog.php
│   ├── Commission.php
│   ├── ConstitutionQuestion.php
│   ├── CustomerServiceConfig.php
│   ├── CustomerServiceMessage.php
│   ├── CustomerServicePhrase.php
│   ├── CustomerServiceRating.php
│   ├── CustomerServiceSession.php
│   ├── Feedback.php
│   ├── HealthQaMessage.php
│   ├── HealthQaSession.php
│   ├── InviteClick.php
│   ├── InviteRegistration.php
│   ├── Order.php
│   ├── Payment.php
│   ├── ProductPackage.php
│   ├── Promoter.php
│   ├── Refund.php
│   ├── RiskBlacklist.php
│   ├── RiskEvent.php
│   ├── RiskRule.php
│   ├── SystemConfig.php
│   ├── SystemMessage.php
│   ├── User.php
│   ├── UserAnalysisLog.php
│   ├── UserBalanceLog.php
│   ├── UserProfile.php
│   ├── Withdraw.php
│   └── XianyuProduct.php
├── Providers/
│   └── AppServiceProvider.php
├── Services/                   # 服务层
│   ├── AiService.php
│   ├── AnalysisTimesService.php
│   ├── AnalyticsService.php
│   ├── CacheService.php
│   ├── LlmService.php
│   ├── NotificationService.php
│   ├── PaymentService.php
│   ├── RefundService.php
│   ├── RiskControlService.php
│   └── SystemConfigService.php
└── Support/
    ├── InviteTracker.php
    └── Site.php
```

---

## 9. 架构决策记录（ADR）

### ADR-001：为什么选择Vue3而不是React？

**决策**：选择Vue3作为前端框架

**理由**：
- 学习曲线低，团队上手快
- 组合式API更适合复杂业务逻辑
- 微信浏览器兼容性优秀

### ADR-002：为什么选择Laravel 13？

**决策**：选择Laravel 13作为后端框架

**理由**：
- 最新版本，性能更好，安全性更高
- 内置队列系统完善
- 开发速度快
- AI全部走HTTP调用，PHP完全够用
- 部署简单，运维成本低
- 生态丰富，支付、存储都有成熟包

### ADR-003：为什么选择Sanctum而不是JWT？

**决策**：使用Laravel Sanctum进行API认证

**理由**：
- Laravel原生支持，集成度最高
- PersonalAccessToken轻量级，适合本项目
- 无需额外维护JWT密钥
- 支持Token过期时间配置
- 与Laravel生态系统无缝集成

### ADR-004：为什么使用File缓存而不是Redis？

**决策**：使用File缓存驱动

**理由**：
- 无需额外安装Redis服务
- 降低运维复杂度
- 中小型项目性能足够
- 部署简单，环境一致性高

### ADR-005：为什么使用Database队列而不是Redis队列？

**决策**：使用Database作为队列驱动

**理由**：
- 无需额外安装Redis服务
- 数据持久化，重启不丢失
- 适合中小型项目
- 降低运维复杂度

### ADR-006：为什么使用单一Web项目而不是分离H5/Admin？

**决策**：使用单一web/项目同时服务用户端和管理端

**理由**
- 共享组件、工具函数、状态管理
- 减少代码重复
- 统一构建部署流程
- 通过postcss-px-to-viewport实现移动端适配
- 管理端使用TailwindCSS实现PC端布局

### ADR-007：为什么AI分析采用同步处理而不是队列？

**决策**：AI分析采用同步处理

**理由**：
- 简化架构复杂度
- 用户可立即获得结果反馈
- 当前业务规模下性能可接受
- 避免队列消费延迟导致的用户等待

---

> **相关文档**：
> - [产品需求文档](01-prd.md)
> - [数据库设计](04-database.md)
> - [API 设计](05-api.md)
> - [后端设计](08-backend.md)
