# 前端设计 - 用户端H5

> **版本**：v1.0  
> **日期**：2026-07-28  
> **对应 ai.md 阶段**：第五阶段（前端设计 - 官网）

---

## 1. 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| Vue3 | ^3.4 | 框架 |
| TypeScript | ^5.0 | 语言 |
| Vite | ^5.0 | 构建工具 |
| Pinia | ^2.0 | 状态管理 |
| Vue Router | ^4.0 | 路由 |
| Vant4 | ^4.0 | UI组件库 |
| Axios | ^1.6 | HTTP请求 |

---

## 2. 页面清单

| 页面 | 路径 | 标题 | 认证 |
|------|------|------|------|
| 首页 | /home | 首页 | 否 |
| 登录 | /auth/login | 登录 | 否 |
| 注册 | /auth/register | 注册 | 否 |
| 分析上传 | /analysis/index | AI分析 | 是 |
| 分析中 | /analysis/processing | 分析中 | 是 |
| 分析结果 | /analysis/result | 分析结果 | 是 |
| 个人中心 | /profile/index | 我的 | 是 |
| 历史记录 | /profile/history | 历史记录 | 是 |
| 会员中心 | /member/index | 会员中心 | 是 |
| 我的订单 | /member/orders | 我的订单 | 是 |
| 开通推广员 | /member/promote/apply | 开通推广员 | 是 |
| 推广中心 | /promoter/index | 推广中心 | 是 |
| 推广海报 | /promoter/poster | 推广海报 | 是 |
| 佣金明细 | /promoter/commissions | 佣金明细 | 是 |
| 提现 | /promoter/withdraw | 提现 | 是 |
| 提现记录 | /promoter/withdraw-history | 提现记录 | 是 |

---

## 3. 路由配置

```typescript
// src/router/index.ts
const routes = [
    { path: '/', redirect: '/home' },
    { path: '/home', component: () => import('@/views/home/index.vue'), meta: { title: '首页' } },
    { path: '/auth/login', component: () => import('@/views/auth/login.vue'), meta: { title: '登录' } },
    { path: '/auth/register', component: () => import('@/views/auth/register.vue'), meta: { title: '注册' } },
    { path: '/analysis/index', component: () => import('@/views/analysis/index.vue'), meta: { title: 'AI分析', needAuth: true } },
    { path: '/analysis/processing', component: () => import('@/views/analysis/processing.vue'), meta: { title: '分析中', needAuth: true } },
    { path: '/analysis/result', component: () => import('@/views/analysis/result.vue'), meta: { title: '分析结果', needAuth: true } },
    { path: '/profile/index', component: () => import('@/views/profile/index.vue'), meta: { title: '我的', needAuth: true } },
    { path: '/profile/history', component: () => import('@/views/profile/history.vue'), meta: { title: '历史记录', needAuth: true } },
    { path: '/member/index', component: () => import('@/views/member/index.vue'), meta: { title: '会员中心', needAuth: true } },
    { path: '/member/orders', component: () => import('@/views/member/orders.vue'), meta: { title: '我的订单', needAuth: true } },
    { path: '/member/promote/apply', component: () => import('@/views/member/promote-apply.vue'), meta: { title: '开通推广员', needAuth: true } },
    { path: '/promoter/index', component: () => import('@/views/promoter/index.vue'), meta: { title: '推广中心', needAuth: true } },
    { path: '/promoter/poster', component: () => import('@/views/promoter/poster.vue'), meta: { title: '推广海报', needAuth: true } },
    { path: '/promoter/commissions', component: () => import('@/views/promoter/commissions.vue'), meta: { title: '佣金明细', needAuth: true } },
    { path: '/promoter/withdraw', component: () => import('@/views/promoter/withdraw.vue'), meta: { title: '提现', needAuth: true } },
    { path: '/promoter/withdraw-history', component: () => import('@/views/promoter/withdraw-history.vue'), meta: { title: '提现记录', needAuth: true } },
]
```

---

## 4. 状态管理

### 4.1 用户状态（stores/user.ts）

```typescript
export const useUserStore = defineStore('user', {
    state: () => ({
        token: localStorage.getItem('token') || '',
        refreshToken: localStorage.getItem('refresh_token') || '',
        userInfo: null as UserInfo | null,
    }),
    getters: {
        isLoggedIn: (state) => !!state.token,
        isVip: (state) => state.userInfo?.is_vip || false,
        isPromoter: (state) => state.userInfo?.is_promoter || false,
    },
    actions: {
        async login(mobile: string, password: string) { /* ... */ },
        async register(data: RegisterForm) { /* ... */ },
        async getUserInfo() { /* ... */ },
        async wechatLogin(code: string) { /* ... */ },
        logout() {
            this.token = ''
            this.refreshToken = ''
            this.userInfo = null
            localStorage.removeItem('token')
            localStorage.removeItem('refresh_token')
        },
    },
})
```

### 4.2 分析状态（stores/analysis.ts）

```typescript
export const useAnalysisStore = defineStore('analysis', {
    state: () => ({
        currentTask: null as Task | null,
        historyList: [] as Task[],
    }),
    actions: {
        async submitTask(imageUrl: string, type: string) { /* ... */ },
        async getTaskStatus(taskNo: string) { /* ... */ },
        async getReport(taskNo: string) { /* ... */ },
        async getHistory(page: number = 1) { /* ... */ },
    },
})
```

---

## 5. 组件设计

### 5.1 公共组件

| 组件 | 路径 | 说明 |
|------|------|------|
| NavBar | components/NavBar.vue | 顶部导航栏 |
| TabBar | components/TabBar.vue | 底部标签栏 |
| Loading | components/Loading.vue | 加载组件 |
| Empty | components/Empty.vue | 空状态 |
| PayModal | components/PayModal.vue | 支付弹窗 |
| UploadImage | components/UploadImage.vue | 图片上传 |

### 5.2 页面组件

| 页面 | 主要组件 | API调用 |
|------|---------|---------|
| 首页 | 功能入口、文章列表 | getArticles |
| 登录 | 表单、短信验证码 | sendSmsCode, login |
| 分析上传 | 图片上传、裁剪 | uploadUrl, submitTask |
| 分析中 | 进度动画 | getTaskStatus |
| 分析结果 | 报告卡片、支付按钮 | getReport, createPayment |
| 推广中心 | 二维码、收益统计 | getPromoterInfo |

---

## 6. 核心页面设计

### 6.1 首页

```
┌─────────────────────────────────────┐
│  [Logo]   AI中医健康管理    [搜索]  │
├─────────────────────────────────────┤
│                                     │
│         ┌─────────────────┐         │
│         │   立即分析       │         │
│         └─────────────────┘         │
│                                     │
│  舌诊    面诊    体质测试   问答     │
│  [icon]  [icon]  [icon]    [icon]  │
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

### 6.2 分析上传页

```
┌─────────────────────────────────────┐
│  [返回]       AI舌诊分析            │
├─────────────────────────────────────┤
│                                     │
│   ┌───────────────────────────┐     │
│   │                           │     │
│   │      [上传区域]           │     │
│   │      点击拍照或选择相册    │     │
│   │                           │     │
│   └───────────────────────────┘     │
│                                     │
│   拍摄提示：                         │
│   • 在自然光下拍摄                  │
│   • 舌头自然伸出，不要用力          │
│   • 保持手机稳定，避免模糊          │
│                                     │
│   ┌───────────────────────────┐     │
│   │        开始分析            │     │
│   └───────────────────────────┘     │
│                                     │
└─────────────────────────────────────┘
```

### 6.3 分析结果页

```
┌─────────────────────────────────────┐
│  [返回]       分析结果              │
├─────────────────────────────────────┤
│                                     │
│      ┌──────────────┐               │
│      │  健康评分    │               │
│      │    85       │               │
│      └──────────────┘               │
│                                     │
│  ────── 舌象摘要 ──────            │
│  舌质淡红，苔薄白，提示...          │
│                                     │
│  ─────────────────────────          │
│  [点击下方按钮查看完整报告]          │
│  ─────────────────────────          │
│                                     │
│  ┌───────────────────────────┐      │
│  │  查看完整报告  ¥9.9       │      │
│  └───────────────────────────┘      │
│                                     │
│  或购买次数包更划算：               │
│  10次包 ¥69  |  月度会员 ¥39       │
│                                     │
└─────────────────────────────────────┘
```

### 6.4 会员中心页（含推广员开通）

```
┌─────────────────────────────────────┐
│              会员中心                │
├─────────────────────────────────────┤
│  ┌───────────────────────────────┐  │
│  │  昵称：张三                   │  │
│  │  手机号：138****5678          │  │
│  │  剩余次数：舌诊5 面诊3 体质2  │  │
│  └───────────────────────────────┘  │
│                                     │
│  ────── 我的服务 ──────            │
│  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐   │
│  │ 订单 │ │ 推广 │ │ 收藏 │ │ 客服 │   │
│  └─────┘ └─────┘ └─────┘ └─────┘   │
│                                     │
│  ────── 推广联盟 ──────            │
│  ┌───────────────────────────────┐  │
│  │  💰 邀请好友 赚取佣金         │  │
│  │  每邀请一位好友消费，获得15%佣金│  │
│  │                               │  │
│  │      【立即开通推广员】        │  │
│  └───────────────────────────────┘  │
│                                     │
└─────────────────────────────────────┘
```

### 6.5 推广员中心页（开通后）

```
┌─────────────────────────────────────┐
│            推广中心                  │
├─────────────────────────────────────┤
│  ┌───────────────────────────────┐  │
│  │  推广码：ABC123               │  │
│  │  [复制] [生成海报]            │  │
│  └───────────────────────────────┘  │
│                                     │
│  ────── 收益统计 ──────            │
│  ┌───────────────────────────────┐  │
│  │  邀请人数：25人               │  │
│  │  消费人数：8人                │  │
│  │  累计佣金：¥126.50            │  │
│  │  可提现：¥86.50              │  │
│  │                               │  │
│  │      [申请提现]               │  │
│  └───────────────────────────────┘  │
│                                     │
│  ────── 最近佣金 ──────            │
│  2026-07-28  +¥9.9  来自李四      │
│  2026-07-27  +¥9.9  来自王五      │
│                                     │
└─────────────────────────────────────┘
```

---

## 7. API调用关系

```
首页 ──→ GET /api/v1/articles
 │
登录页 ──→ POST /api/v1/auth/sms-code
       ──→ POST /api/v1/auth/login
 │
分析上传 ──→ POST /api/v1/analysis/upload-url
         ──→ POST /api/v1/analysis/submit
 │
分析中 ──→ GET /api/v1/analysis/status/{taskNo} (轮询)
 │
分析结果 ──→ GET /api/v1/analysis/report/{taskNo}
         ──→ POST /api/v1/payment/create
 │
体质测试 ──→ GET /api/v1/constitution/questions
         ──→ POST /api/v1/constitution/submit
         ──→ GET /api/v1/constitution/report/{taskNo}
 │
健康问答 ──→ POST /api/v1/qa/sessions
         ──→ GET /api/v1/qa/sessions
         ──→ POST /api/v1/qa/sessions/{sessionNo}/messages
         ──→ GET /api/v1/qa/sessions/{sessionNo}/messages
 │
次数包 ──→ GET /api/v1/packages
        ──→ POST /api/v1/packages/buy
 │
健康档案 ──→ GET /api/v1/health/history
         ──→ GET /api/v1/health/trend
         ──→ GET /api/v1/health/constitution
 │
推广中心 ──→ GET /api/v1/promoter/info
         ──→ POST /api/v1/promoter/activate (开通推广员)
         ──→ POST /api/v1/promoter/withdraw
         ──→ GET /api/v1/promoter/commissions (佣金明细)
         ──→ GET /api/v1/promoter/poster (推广海报)
```

---

## 8. 响应式设计

### 8.1 尺寸适配

| 设备 | 宽度 | 适配方案 |
|------|------|---------|
| 小屏手机 | 320px-375px | 基础布局 |
| 标准手机 | 375px-414px | 主要适配 |
| 大屏手机 | 414px-768px | 宽松布局 |

### 8.2 rem适配

```typescript
// vite.config.ts
import postcssPxToViewport from 'postcss-px-to-viewport'

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

---

> **相关文档**：
> - [API 设计](05-api.md)
> - [前端设计 - 管理端PC](07-frontend-admin.md)
> - [后端设计](08-backend.md)
