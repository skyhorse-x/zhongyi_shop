# 前端设计 - 管理端PC

> **版本**：v1.0  
> **日期**：2026-07-28  
> **对应 ai.md 阶段**：第五阶段（前端设计 - 后台）

---

## 1. 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| Vue3 | ^3.4 | 框架 |
| TypeScript | ^5.0 | 语言 |
| Vite | ^5.0 | 构建工具 |
| Pinia | ^2.0 | 状态管理 |
| Vue Router | ^4.0 | 路由 |
| Element Plus | ^2.4 | UI组件库 |
| ECharts | ^5.0 | 图表 |

---

## 2. 页面清单

| 页面 | 路径 | 标题 | 权限 |
|------|------|------|------|
| 登录 | /login | 管理员登录 | 否 |
| 首页 | /dashboard | 数据概览 | 是 |
| 用户管理 | /users | 用户列表 | admin |
| 用户详情 | /users/:id | 用户详情 | admin |
| 订单管理 | /orders | 订单列表 | admin |
| 订单详情 | /orders/:id | 订单详情 | admin |
| AI管理 | /ai/models | AI模型配置 | admin |
| AI日志 | /ai/logs | AI调用日志 | admin |
| 推广管理 | /promoters | 推广员列表 | admin |
| 提现审核 | /withdraws | 提现审核 | admin |
| 系统配置 | /system/config | 基础配置 | super_admin |

---

## 3. 布局结构

```
┌─────────────────────────────────────────────────────────┐
│                      顶部栏                               │
│  Logo    搜索框                   管理员  通知  退出      │
├──────────┬──────────────────────────────────────────────┤
│          │                                              │
│  侧边栏   │              主内容区                         │
│          │                                              │
│  首页    │    ┌────────────────────────────────┐       │
│  用户    │    │         面包屑                  │       │
│  订单    │    │                                │       │
│  AI管理  │    ├────────────────────────────────┤       │
│  推广    │    │                                │       │
│  财务    │    │         页面内容                │       │
│  内容    │    │                                │       │
│  系统    │    │                                │       │
│          │    └────────────────────────────────┘       │
│          │                                              │
└──────────┴──────────────────────────────────────────────┘
```

---

## 4. 路由配置

```typescript
// src/router/index.ts
const routes = [
    { path: '/login', component: () => import('@/views/login/index.vue'), meta: { title: '登录' } },
    {
        path: '/',
        component: () => import('@/components/Layout.vue'),
        redirect: '/dashboard',
        children: [
            { path: 'dashboard', component: () => import('@/views/dashboard/index.vue'), meta: { title: '数据概览', permission: 'dashboard' } },
            { path: 'users', component: () => import('@/views/users/index.vue'), meta: { title: '用户管理', permission: 'user_view' } },
            { path: 'users/:id', component: () => import('@/views/users/detail.vue'), meta: { title: '用户详情' } },
            { path: 'orders', component: () => import('@/views/orders/index.vue'), meta: { title: '订单管理', permission: 'order_view' } },
            { path: 'orders/:id', component: () => import('@/views/orders/detail.vue'), meta: { title: '订单详情' } },
            { path: 'ai/models', component: () => import('@/views/ai/models.vue'), meta: { title: 'AI模型配置', permission: 'ai_manage' } },
            { path: 'ai/logs', component: () => import('@/views/ai/logs.vue'), meta: { title: 'AI调用日志' } },
            { path: 'promoters', component: () => import('@/views/promoters/index.vue'), meta: { title: '推广管理', permission: 'promoter_view' } },
            { path: 'withdraws', component: () => import('@/views/withdraws/index.vue'), meta: { title: '提现审核', permission: 'withdraw_audit' } },
            { path: 'system/config', component: () => import('@/views/system/config.vue'), meta: { title: '系统配置', permission: 'system_config' } },
        ],
    },
]
```

---

## 5. 核心页面设计

### 5.1 数据概览页

```
┌─────────────────────────────────────────────────────────┐
│  数据概览                                    2026-07-28 │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐          │
│  │今日访问│ │今日注册│ │今日付费│ │今日收入│          │
│  │ 1,250  │ │   86   │ │   23   │ │¥227.70 │          │
│  └────────┘ └────────┘ └────────┘ └────────┘          │
│                                                         │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐          │
│  │今日佣金│ │AI调用  │ │AI成本  │ │今日利润│          │
│  │ ¥34.15 │ │  156   │ │ ¥78.50 │ │¥115.05 │          │
│  └────────┘ └────────┘ └────────┘ └────────┘          │
│                                                         │
│  ┌─────────────────────────────────────────────┐       │
│  │          收入趋势图（近30天）                 │       │
│  │     ╱╲    ╱╲                                │       │
│  │   ╱    ╲╱    ╲    ╱╲                        │       │
│  │ ╱            ╲╱    ╲╱╲                      │       │
│  └─────────────────────────────────────────────┘       │
│                                                         │
│  ┌─────────────────────┐ ┌─────────────────────┐       │
│  │    用户增长趋势      │ │     AI调用趋势       │       │
│  └─────────────────────┘ └─────────────────────┘       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### 5.2 用户管理页

```
┌─────────────────────────────────────────────────────────┐
│  用户管理                                                │
├─────────────────────────────────────────────────────────┤
│  手机号: [________]  昵称: [________]  状态: [全部▼]    │
│           [搜索] [重置]                                  │
├─────────────────────────────────────────────────────────┤
│   ID  │ 手机号      │ 昵称   │ 会员 │ 分析次数 │ 状态  │
│  10001│ 138****5678 │ 张三   │ 否   │    5     │ 正常  │
│  10002│ 139****1234 │ 李四   │ 是   │   25     │ 正常  │
│  10003│ 137****9999 │ 王五   │ 否   │    0     │ 禁用  │
├─────────────────────────────────────────────────────────┤
│                                    < 1 2 3 ... 10 >    │
└─────────────────────────────────────────────────────────┘
```

### 5.3 AI模型配置页

```
┌─────────────────────────────────────────────────────────┐
│  AI模型配置                              [添加模型]      │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────────────────┐       │
│  │ 优先级1: 豆包 Vision                         │       │
│  │ 状态: ● 启用    费用: ¥0.001/Token           │       │
│  │ 超时: 30s      重试: 3次                     │       │
│  │ [编辑] [禁用] [查看日志]                      │       │
│  └─────────────────────────────────────────────┘       │
│                                                         │
│  ┌─────────────────────────────────────────────┐       │
│  │ 优先级2: DeepSeek-V3                        │       │
│  │ 状态: ● 启用    费用: ¥0.0005/Token          │       │
│  │ 超时: 30s      重试: 3次                     │       │
│  │ [编辑] [禁用] [查看日志]                      │       │
│  └─────────────────────────────────────────────┘       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 6. 组件设计

### 6.1 公共组件

| 组件 | 路径 | 说明 |
|------|------|------|
| Layout | components/Layout.vue | 主布局 |
| Sidebar | components/Sidebar.vue | 侧边栏 |
| Header | components/Header.vue | 顶部栏 |
| Breadcrumb | components/Breadcrumb.vue | 面包屑 |
| Charts | components/Charts.vue | 图表组件 |

### 6.2 业务组件

| 组件 | 路径 | 说明 |
|------|------|------|
| StatCard | components/StatCard.vue | 统计卡片 |
| DataTable | components/DataTable.vue | 数据表格 |
| SearchForm | components/SearchForm.vue | 搜索表单 |
| AuditModal | components/AuditModal.vue | 审核弹窗 |

---

## 7. 状态管理

### 7.1 管理员状态

```typescript
export const useAdminStore = defineStore('admin', {
    state: () => ({
        token: localStorage.getItem('admin_token') || '',
        adminInfo: null as AdminInfo | null,
        permissions: [] as string[],
    }),
    getters: {
        isLoggedIn: (state) => !!state.token,
        hasPermission: (state) => (permission: string) => {
            return state.permissions.includes(permission) || state.permissions.includes('*')
        },
    },
    actions: {
        async login(username: string, password: string) { /* ... */ },
        async getAdminInfo() { /* ... */ },
        logout() { /* ... */ },
    },
})
```

---

## 8. 权限控制

### 8.1 路由守卫

```typescript
router.beforeEach((to, from, next) => {
    const adminStore = useAdminStore()
    
    if (to.path === '/login') {
        next()
        return
    }
    
    if (!adminStore.isLoggedIn) {
        next('/login')
        return
    }
    
    if (to.meta.permission && !adminStore.hasPermission(to.meta.permission as string)) {
        next('/403')
        return
    }
    
    next()
})
```

### 8.2 权限指令

```typescript
// v-permission="'user_view'"
app.directive('permission', {
    mounted(el, binding) {
        const adminStore = useAdminStore()
        if (!adminStore.hasPermission(binding.value)) {
            el.parentNode?.removeChild(el)
        }
    },
})
```

---

> **相关文档**：
> - [前端设计 - 用户端H5](06-frontend-web.md)
> - [API 设计](05-api.md)
> - [安全设计](10-security.md)
