# 前端设计 - 管理端PC

> **版本**：v2.0  
> **日期**：2026-08-04  
> **对应 ai.md 阶段**：第五阶段（前端设计 - 后台）

---

## 1. 技术栈

| 技术 | 版本 | 用途 |
|------|------|------|
| Vue3 | ^3.5 | 框架 |
| TypeScript | ~6.0 | 语言 |
| Vite | ^8.1 | 构建工具 |
| Pinia | ^4.0 | 状态管理 |
| Vue Router | ^4.6 | 路由 |
| Element Plus | ^2.14 | UI组件库 |
| TailwindCSS | ^4.3 | 样式 |

> **注意**：未使用 ECharts，数据展示使用 Element Plus 表格和卡片组件。

---

## 2. 页面清单

| 页面 | 路径 | 标题 | 权限 |
|------|------|------|------|
| 登录 | /admin/login | 管理后台登录 | 否 |
| 仪表盘 | /admin/dashboard | 数据概览 | admin |
| 运营BI | /admin/analytics | 运营数据 | admin |
| 风控管理 | /admin/risk | 风控规则与事件 | admin |
| 用户管理 | /admin/users | 用户列表 | admin |
| 管理员管理 | /admin/admins | 管理员列表 | super_admin |
| 订单管理 | /admin/orders | 订单列表 | admin |
| 次数包管理 | /admin/packages | 次数包配置 | admin |
| 闲鱼商品管理 | /admin/xianyu-products | 闲鱼商品 | admin |
| 体质题目 | /admin/constitution | 体质测试题目 | admin |
| AI管理 | /admin/ai | AI模型配置 | admin |
| 推广管理 | /admin/promoters | 推广员列表 | admin |
| 提现审核 | /admin/withdraws | 提现审核 | admin |
| 文章管理 | /admin/articles | 文章列表 | admin |
| 系统设置 | /admin/settings | 系统配置 | super_admin |
| 客服管理 | /admin/customer-service | 客服会话 | admin |

---

## 3. 布局结构

```
┌─────────────────────────────────────────────────────────┐
│                      顶栏                                │
│  [折叠菜单] [展开/收起图标]  页面标题        管理员  退出  │
├──────────┬──────────────────────────────────────────────┤
│          │                                              │
│  侧边栏   │              主内容区                         │
│          │                                              │
│  ⚕ Logo │    ┌────────────────────────────────┐       │
│  管理后台│    │                                │       │
│          │    │                                │       │
│  📊 仪表盘│    │         页面内容                │       │
│  💬 客服管理│   │                                │       │
│  👤 用户管理│   │                                │       │
│  📋 订单管理│   │                                │       │
│  📦 次数包 │    │                                │       │
│  🛒 闲鱼商品│   │                                │       │
│  🤖 AI管理│    │                                │       │
│  📢 推广管理│   │                                │       │
│  💰 提现审核│   │                                │       │
│  📄 文章管理│   │                                │       │
│  📝 体质题目│   │                                │       │
│  ⚙️ 系统设置│   │                                │       │
│          │    └────────────────────────────────┘       │
│  [退出]  │                                              │
│          │                                              │
└──────────┴──────────────────────────────────────────────┘
```

**特点**：
- 侧边栏支持折叠/展开（220px ↔ 60px）
- 移动端响应式设计（侧边栏变为抽屉式）
- 客服菜单显示待接入数量徽章（红色圆点）

---

## 4. 路由配置

```typescript
// src/router/modules/admin.ts
const adminRoutes: RouteRecordRaw[] = [
  {
    path: '/admin/login',
    name: 'AdminLogin',
    component: () => import('@/views/admin/login.vue'),
    meta: { title: '管理后台登录' },
  },
  {
    path: '/admin',
    component: () => import('@/views/admin/AdminLayout.vue'),
    meta: { title: '管理后台', needAdminAuth: true },
    redirect: '/admin/dashboard',
    children: [
      { path: 'dashboard',        name: 'AdminDashboard',        component: () => import('@/views/admin/dashboard.vue'),        meta: { title: '仪表盘' } },
      { path: 'analytics',        name: 'AdminAnalytics',        component: () => import('@/views/admin/analytics.vue'),        meta: { title: '运营 BI' } },
      { path: 'risk',             name: 'AdminRisk',             component: () => import('@/views/admin/risk.vue'),             meta: { title: '风控管理' } },
      { path: 'users',            name: 'AdminUsers',            component: () => import('@/views/admin/users.vue'),            meta: { title: '用户管理' } },
      { path: 'admins',           name: 'AdminAdmins',           component: () => import('@/views/admin/admins.vue'),           meta: { title: '管理员管理' } },
      { path: 'orders',           name: 'AdminOrders',           component: () => import('@/views/admin/orders.vue'),           meta: { title: '订单管理' } },
      { path: 'packages',         name: 'AdminPackages',         component: () => import('@/views/admin/packages.vue'),         meta: { title: '次数包管理' } },
      { path: 'xianyu-products',  name: 'AdminXianyuProducts',   component: () => import('@/views/admin/xianyu-products.vue'),  meta: { title: '闲鱼商品管理' } },
      { path: 'constitution',     name: 'AdminConstitution',     component: () => import('@/views/admin/constitution.vue'),     meta: { title: '体质题目' } },
      { path: 'ai',               name: 'AdminAi',               component: () => import('@/views/admin/ai.vue'),               meta: { title: 'AI管理' } },
      { path: 'promoters',        name: 'AdminPromoters',        component: () => import('@/views/admin/promoters.vue'),        meta: { title: '推广管理' } },
      { path: 'withdraws',        name: 'AdminWithdraws',        component: () => import('@/views/admin/withdraws.vue'),        meta: { title: '提现审核' } },
      { path: 'articles',         name: 'AdminArticles',         component: () => import('@/views/admin/articles.vue'),         meta: { title: '文章管理' } },
      { path: 'settings',         name: 'AdminSettings',         component: () => import('@/views/admin/settings.vue'),         meta: { title: '系统设置' } },
      { path: 'customer-service', name: 'AdminCustomerService',  component: () => import('@/views/admin/customer-service.vue'), meta: { title: '客服管理' } },
    ],
  },
]
```

---

## 5. 核心页面设计

### 5.1 仪表盘页

```
┌─────────────────────────────────────────────────────────┐
│  仪表盘                                      2026-08-04 │
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
│  │         数据概览（使用 Element Plus 卡片）     │       │
│  └─────────────────────────────────────────────┘       │
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

### 5.3 AI管理页

```
┌─────────────────────────────────────────────────────────┐
│  AI管理                                    [添加模型]    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────────────────┐       │
│  │ 优先级1: 豆包 Vision                         │       │
│  │ 状态: ● 启用    费用: ¥0.001/Token           │       │
│  │ 超时: 30s      重试: 3次                     │       │
│  │ [编辑] [禁用]                                │       │
│  └─────────────────────────────────────────────┘       │
│                                                         │
│  ┌─────────────────────────────────────────────┐       │
│  │ 优先级2: DeepSeek-V3                        │       │
│  │ 状态: ● 启用    费用: ¥0.0005/Token          │       │
│  │ 超时: 30s      重试: 3次                     │       │
│  │ [编辑] [禁用]                                │       │
│  └─────────────────────────────────────────────┘       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### 5.4 风控管理页

```
┌─────────────────────────────────────────────────────────┐
│  风控管理                                                │
├─────────────────────────────────────────────────────────┤
│  [规则配置] [风险事件] [黑名单]                          │
├─────────────────────────────────────────────────────────┤
│  规则ID │ 规则名称        │ 类型  │ 状态  │ 操作       │
│  1      │ 高频提现限制    │ 提现  │ 启用  │ [编辑][删除]│
│  2      │ 异常登录检测    │ 登录  │ 启用  │ [编辑][删除]│
├─────────────────────────────────────────────────────────┤
└─────────────────────────────────────────────────────────┘
```

### 5.5 客服管理页

```
┌─────────────────────────────────────────────────────────┐
│  客服管理                                    待接入: 5  │
├─────────────────────────────────────────────────────────┤
│  [待接入] [进行中] [已结束]                              │
├─────────────────────────────────────────────────────────┤
│  会话ID │ 用户昵称 │ 状态   │ 创建时间   │ 操作        │
│  1001   │ 张三     │ 待接入 │ 10:30      │ [接入]      │
│  1002   │ 李四     │ 进行中 │ 09:15      │ [查看]      │
├─────────────────────────────────────────────────────────┤
└─────────────────────────────────────────────────────────┘
```

---

## 6. 组件设计

### 6.1 基础组件

| 组件 | 路径 | 说明 |
|------|------|------|
| BaseCard | components/base/BaseCard.vue | 卡片容器 |
| BaseDialog | components/base/BaseDialog.vue | 弹窗组件 |
| BaseForm | components/base/BaseForm.vue | 表单组件 |
| BasePagination | components/base/BasePagination.vue | 分页组件 |
| BaseSearch | components/base/BaseSearch.vue | 搜索组件 |
| BaseTable | components/base/BaseTable.vue | 数据表格 |

### 6.2 业务组件

| 组件 | 路径 | 说明 |
|------|------|------|
| HealthAdvice | components/analysis/HealthAdvice.vue | 健康建议 |
| ReportHeader | components/analysis/ReportHeader.vue | 报告头部 |
| ReportSummary | components/analysis/ReportSummary.vue | 报告摘要 |
| MessageBubble | components/chat/MessageBubble.vue | 消息气泡 |
| MessageInput | components/chat/MessageInput.vue | 消息输入 |
| SessionListItem | components/chat/SessionListItem.vue | 会话列表项 |

---

## 7. 状态管理

### 7.1 管理员状态

```typescript
// src/stores/admin.ts
export const useAdminStore = defineStore('admin', () => {
  const token = ref(getAdminToken() || '')
  const adminInfo = ref<any>(null)
  const permissions = ref<string[]>([])

  const menuItems = ref<MenuItem[]>([
    { title: '仪表盘', icon: 'TrendCharts', path: '/admin/dashboard' },
    { title: '客服管理', icon: 'Service', path: '/admin/customer-service', badge: () => waitingCount.value },
    { title: '用户管理', icon: 'UserFilled', path: '/admin/users' },
    { title: '订单管理', icon: 'Tickets', path: '/admin/orders' },
    { title: '次数包管理', icon: 'Goods', path: '/admin/packages' },
    { title: '闲鱼商品管理', icon: 'Goods', path: '/admin/xianyu-products' },
    { title: 'AI管理', icon: 'Cpu', path: '/admin/ai' },
    { title: '推广管理', icon: 'Promotion', path: '/admin/promoters' },
    { title: '提现审核', icon: 'Money', path: '/admin/withdraws' },
    { title: '文章管理', icon: 'Document', path: '/admin/articles' },
    { title: '体质题目', icon: 'EditPen', path: '/admin/constitution' },
    { title: '系统设置', icon: 'Setting', path: '/admin/settings' },
  ])

  const isLoggedIn = () => !!token.value
  const setToken = (t: string) => { /* ... */ }
  const logout = () => { /* ... */ }

  return { token, adminInfo, permissions, menuItems, isLoggedIn, setToken, logout }
})
```

### 7.2 其他状态

| Store | 文件 | 说明 |
|-------|------|------|
| auth | stores/auth.ts | 用户认证状态 |
| user | stores/user.ts | 用户信息 |
| order | stores/order.ts | 订单状态 |
| chat | stores/chat.ts | 聊天会话 |
| analysis | stores/analysis.ts | 分析任务 |
| promoter | stores/promoter.ts | 推广员状态 |

---

## 8. 权限控制

### 8.1 路由守卫

```typescript
router.beforeEach((to, from, next) => {
  const adminStore = useAdminStore()
  
  if (to.path === '/admin/login') {
    next()
    return
  }
  
  // 需要管理员权限的路由
  if (to.meta.needAdminAuth && !adminStore.isLoggedIn()) {
    next('/admin/login')
    return
  }
  
  next()
})
```

### 8.2 权限判断

管理员权限通过后端中间件控制：
- `admin` 中间件：管理员可访问
- `super_admin` 中间件：仅超级管理员可访问（id===1 或 role_id===1）

前端根据 adminInfo 显示/隐藏敏感操作按钮。

---

## 9. 响应式设计

管理后台使用 CSS Media Query 实现响应式：

```css
/* 移动端适配 */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    left: -220px;  /* 隐藏侧边栏 */
  }
  .sidebar.mobile {
    left: 0;  /* 展开侧边栏 */
  }
  .menu-btn {
    display: flex;  /* 显示汉堡菜单按钮 */
  }
}
```

**特点**：
- 桌面端：侧边栏固定，可折叠
- 移动端：侧边栏变为抽屉式，点击遮罩关闭

---

> **相关文档**：
> - [前端设计 - 用户端H5](06-frontend-web.md)
> - [API 设计](05-api.md)
> - [安全设计](10-security.md)
