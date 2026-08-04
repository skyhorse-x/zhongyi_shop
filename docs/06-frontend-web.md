# 前端设计 - 用户端

> **版本**：v2.0  
> **日期**：2026-08-04  
> **项目结构**：单一 web 项目（用户端+管理端）

---

## 1. 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| Vue | ^3.5.39 | 框架 |
| TypeScript | ~6.0.2 | 语言 |
| Vite | ^8.1.1 | 构建工具 |
| Pinia | ^4.0.2 | 状态管理 |
| Vue Router | ^4.6.4 | 路由 |
| Element Plus | ^2.14.3 | UI组件库 |
| TailwindCSS | ^4.3.3 | CSS框架 |
| Axios | - | HTTP请求 |
| postcss-px-to-viewport-8-plugin | ^1.2.5 | 移动端适配 |

---

## 2. 页面清单

### 2.1 公共页面

| 页面 | 路径 | 标题 | 认证 |
|------|------|------|------|
| 首页 | / | AI中医健康管理 | 否 |
| 登录 | /auth/login | 登录 | 否 |
| 注册 | /auth/register | 注册 | 否 |
| 404 | /:pathMatch(.*)* | 页面不存在 | 否 |

### 2.2 AI分析页面

| 页面 | 路径 | 标题 | 认证 |
|------|------|------|------|
| 舌诊分析 | /analysis/tongue | 舌诊分析 | 是 |
| 面诊分析 | /analysis/face | 面诊分析 | 是 |
| 分析结果 | /analysis/result/:taskNo | 分析结果 | 是 |
| 体质测试 | /constitution/test | 体质测试 | 是 |
| 体质报告 | /constitution/result/:taskNo | 体质报告 | 是 |

### 2.3 健康档案页面

| 页面 | 路径 | 标题 | 认证 |
|------|------|------|------|
| 分析历史 | /health/history | 分析历史 | 是 |
| 健康趋势 | /health/trend | 健康趋势 | 是 |
| 体质档案 | /health/constitution | 体质档案 | 是 |

### 2.4 健康问答页面

| 页面 | 路径 | 标题 | 认证 |
|------|------|------|------|
| 问答聊天 | /qa/chat/:sessionNo? | 健康问答 | 是 |
| 问答记录 | /qa/sessions | 问答记录 | 是 |

### 2.5 消息中心页面

| 页面 | 路径 | 标题 | 认证 |
|------|------|------|------|
| 消息中心 | /messages | 消息中心 | 是 |
| 客服聊天 | /messages/customer-service | 客服聊天 | 是 |

### 2.6 会员中心页面

| 页面 | 路径 | 标题 | 认证 |
|------|------|------|------|
| 会员中心 | /member | 会员中心 | 是 |
| 我的订单 | /member/orders | 我的订单 | 是 |
| 余额明细 | /member/balance | 余额明细 | 是 |

### 2.7 次数包与充值页面

| 页面 | 路径 | 标题 | 认证 |
|------|------|------|------|
| 购买次数包 | /packages | 购买次数包 | 是 |
| 充值中心 | /recharge | 充值中心 | 是 |

### 2.8 推广中心页面

| 页面 | 路径 | 标题 | 认证 |
|------|------|------|------|
| 开通推广员 | /promoter/activate | 开通推广员 | 是 |
| 推广中心 | /promoter | 推广中心 | 是 |
| 佣金明细 | /promoter/commissions | 佣金明细 | 是 |
| 提现 | /promoter/withdraw | 提现 | 是 |
| 提现记录 | /promoter/withdraw-history | 提现记录 | 是 |

### 2.9 用户服务页面

| 页面 | 路径 | 标题 | 认证 |
|------|------|------|------|
| 反馈与申诉 | /user/feedback | 反馈与申诉 | 是 |

---

## 3. 路由配置

```typescript
// src/router/modules/common.ts
const commonRoutes = [
  { path: '/', component: () => import('@/views/home/index.vue') },
  { path: '/auth/login', component: () => import('@/views/auth/login.vue') },
  { path: '/auth/register', component: () => import('@/views/auth/register.vue') },
]

// src/router/modules/analysis.ts
const analysisRoutes = [
  { path: '/analysis/tongue', component: () => import('@/views/analysis/tongue.vue') },
  { path: '/analysis/face', component: () => import('@/views/analysis/face.vue') },
  { path: '/analysis/result/:taskNo', component: () => import('@/views/analysis/result.vue') },
  { path: '/constitution/test', component: () => import('@/views/constitution/test.vue') },
  { path: '/constitution/result/:taskNo', component: () => import('@/views/constitution/result.vue') },
  { path: '/health/history', component: () => import('@/views/health/history.vue') },
  { path: '/health/trend', component: () => import('@/views/health/trend.vue') },
  { path: '/health/constitution', component: () => import('@/views/health/constitution.vue') },
]

// src/router/modules/user.ts
const userRoutes = [
  { path: '/qa/chat/:sessionNo?', component: () => import('@/views/qa/chat.vue') },
  { path: '/qa/sessions', component: () => import('@/views/qa/sessions.vue') },
  { path: '/messages', component: () => import('@/views/messages/index.vue') },
  { path: '/messages/customer-service', component: () => import('@/views/messages/customer-service.vue') },
  { path: '/packages', component: () => import('@/views/packages/index.vue') },
  { path: '/recharge', component: () => import('@/views/recharge/index.vue') },
  { path: '/member', component: () => import('@/views/member/index.vue') },
  { path: '/member/orders', component: () => import('@/views/member/orders.vue') },
  { path: '/member/balance', component: () => import('@/views/member/balance.vue') },
  { path: '/promoter/activate', component: () => import('@/views/promoter/activate.vue') },
  { path: '/promoter', component: () => import('@/views/promoter/index.vue') },
  { path: '/promoter/commissions', component: () => import('@/views/promoter/commissions.vue') },
  { path: '/promoter/withdraw', component: () => import('@/views/promoter/withdraw.vue') },
  { path: '/promoter/withdraw-history', component: () => import('@/views/promoter/withdraw-history.vue') },
  { path: '/user/feedback', component: () => import('@/views/user/feedback.vue') },
]
```

---

## 4. 状态管理（Pinia Stores）

| Store | 文件 | 说明 |
|-------|------|------|
| auth | stores/auth.ts | 认证状态（token、用户信息） |
| user | stores/user.ts | 用户信息 |
| analysis | stores/analysis.ts | AI分析状态 |
| chat | stores/chat.ts | 客服聊天状态 |
| order | stores/order.ts | 订单状态 |
| promoter | stores/promoter.ts | 推广员状态 |
| admin | stores/admin.ts | 管理员状态 |

### 4.1 认证状态（stores/auth.ts）

```typescript
export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('token') || '',
        userInfo: null as UserInfo | null,
    }),
    getters: {
        isLoggedIn: (state) => !!state.token,
        isAdmin: (state) => state.userInfo?.is_admin || false,
    },
    actions: {
        async login(account: string, password: string) { /* ... */ },
        async register(data: RegisterForm) { /* ... */ },
        async logout() { /* ... */ },
        async getUserInfo() { /* ... */ },
    },
})
```

---

## 5. 组件设计

### 5.1 基础组件（components/base/）

| 组件 | 路径 | 说明 |
|------|------|------|
| BaseCard | components/base/BaseCard.vue | 卡片组件 |
| BaseDialog | components/base/BaseDialog.vue | 对话框组件 |
| BaseForm | components/base/BaseForm.vue | 表单组件 |
| BasePagination | components/base/BasePagination.vue | 分页组件 |
| BaseSearch | components/base/BaseSearch.vue | 搜索组件 |
| BaseTable | components/base/BaseTable.vue | 表格组件 |

### 5.2 分析组件（components/analysis/）

| 组件 | 路径 | 说明 |
|------|------|------|
| ReportHeader | components/analysis/ReportHeader.vue | 报告头部 |
| ReportSummary | components/analysis/ReportSummary.vue | 报告摘要 |
| HealthAdvice | components/analysis/HealthAdvice.vue | 健康建议 |

### 5.3 聊天组件（components/chat/）

| 组件 | 路径 | 说明 |
|------|------|------|
| MessageBubble | components/chat/MessageBubble.vue | 消息气泡 |
| MessageInput | components/chat/MessageInput.vue | 消息输入 |
| SessionListItem | components/chat/SessionListItem.vue | 会话列表项 |

### 5.4 布局组件（layouts/）

| 组件 | 路径 | 说明 |
|------|------|------|
| AdminLayout | layouts/AdminLayout.vue | 管理后台布局 |
| MiniProgramLayout | layouts/MiniProgramLayout.vue | 小程序布局 |

---

## 6. API调用关系

```
首页 ──→ GET /api/v1/articles
 │
登录页 ──→ POST /api/v1/auth/login
 │
舌诊分析 ──→ POST /api/v1/analysis/upload-image
         ──→ POST /api/v1/analysis/submit
         ──→ GET /api/v1/analysis/status/{taskNo}
         ──→ GET /api/v1/analysis/report/{taskNo}
 │
面诊分析 ──→ POST /api/v1/analysis/upload-image
         ──→ POST /api/v1/analysis/submit
         ──→ GET /api/v1/analysis/report/{taskNo}
 │
体质测试 ──→ GET /api/v1/constitution/questions
         ──→ POST /api/v1/constitution/submit
         ──→ GET /api/v1/constitution/report/{taskNo}
 │
健康问答 ──→ POST /api/v1/qa/sessions
         ──→ POST /api/v1/qa/sessions/{sessionNo}/messages
         ──→ GET /api/v1/qa/sessions/{sessionNo}/messages
 │
客服聊天 ──→ GET /api/v1/customer-service/session
         ──→ POST /api/v1/customer-service/sessions/{sessionNo}/messages
         ──→ POST /api/v1/customer-service/sessions/{sessionNo}/rate
 │
次数包 ──→ GET /api/v1/packages
        ──→ POST /api/v1/packages/buy
 │
充值 ──→ GET /api/v1/xianyu/products
      ──→ POST /api/v1/payment/create
 │
推广中心 ──→ GET /api/v1/promoter/info
         ──→ POST /api/v1/promoter/withdraw
         ──→ GET /api/v1/promoter/commissions
 │
反馈申诉 ──→ POST /api/v1/feedback
         ──→ POST /api/v1/appeals
         ──→ POST /api/v1/refunds
```

---

## 7. 移动端适配

### 7.1 适配方案

使用 `postcss-px-to-viewport-8-plugin` 实现移动端适配：

```typescript
// vite.config.ts
import postcssPxToViewport from 'postcss-px-to-viewport-8-plugin'

export default {
    css: {
        postcss: {
            plugins: [
                postcssPxToViewport({
                    viewportWidth: 375,
                    unitPrecision: 5,
                    viewportUnit: 'vw',
                }),
            ],
        },
    },
}
```

### 7.2 尺寸适配

| 设备 | 宽度 | 适配方案 |
|------|------|---------|
| 小屏手机 | 320px-375px | 基础布局 |
| 标准手机 | 375px-414px | 主要适配 |
| 大屏手机 | 414px-768px | 宽松布局 |
| 平板/PC | 768px+ | 管理后台布局 |

---

## 8. 核心页面设计

### 8.1 首页

```
┌─────────────────────────────────────┐
│  [Logo]   AI中医健康管理            │
├─────────────────────────────────────┤
│                                     │
│         ┌─────────────────┐         │
│         │   舌诊分析       │         │
│         └─────────────────┘         │
│         ┌─────────────────┐         │
│         │   面诊分析       │         │
│         └─────────────────┘         │
│         ┌─────────────────┐         │
│         │   体质测试       │         │
│         └─────────────────┘         │
│         ┌─────────────────┐         │
│         │   健康问答       │         │
│         └─────────────────┘         │
│                                     │
│  ────── 热门文章 ──────            │
│  ┌─────┐ ┌─────┐ ┌─────┐          │
│  │文章1│ │文章2│ │文章3│          │
│  └─────┘ └─────┘ └─────┘          │
│                                     │
├─────────────────────────────────────┤
│  首页    分析    推广    我的        │
└─────────────────────────────────────┘
```

### 8.2 会员中心页

```
┌─────────────────────────────────────┐
│              会员中心                │
├─────────────────────────────────────┤
│  ┌───────────────────────────────┐  │
│  │  昵称：张三                   │  │
│  │  剩余次数：5次                │  │
│  │  账户余额：¥100.00            │  │
│  └───────────────────────────────┘  │
│                                     │
│  ────── 我的服务 ──────            │
│  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐   │
│  │ 订单 │ │ 推广 │ │ 消息 │ │ 客服 │   │
│  └─────┘ └─────┘ └─────┘ └─────┘   │
│  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐   │
│  │ 问答 │ │ 档案 │ │ 充值 │ │ 反馈 │   │
│  └─────┘ └─────┘ └─────┘ └─────┘   │
│                                     │
└─────────────────────────────────────┘
```

---

> **相关文档**：
> - [API 设计](05-api.md)
> - [前端设计 - 管理端](07-frontend-admin.md)
> - [后端设计](08-backend.md)
