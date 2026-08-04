/**
 * 管理后台路由：嵌套布局
 */
import type { RouteRecordRaw } from 'vue-router'

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
      { path: 'dashboard',       name: 'AdminDashboard',       component: () => import('@/views/admin/dashboard.vue'),       meta: { title: '仪表盘' } },
      { path: 'analytics',       name: 'AdminAnalytics',       component: () => import('@/views/admin/analytics.vue'),       meta: { title: '运营 BI' } },
      { path: 'risk',            name: 'AdminRisk',            component: () => import('@/views/admin/risk.vue'),            meta: { title: '风控管理' } },
      { path: 'users',           name: 'AdminUsers',           component: () => import('@/views/admin/users.vue'),           meta: { title: '用户管理' } },
      { path: 'admins',          name: 'AdminAdmins',          component: () => import('@/views/admin/admins.vue'),          meta: { title: '管理员管理' } },
      { path: 'roles',           name: 'AdminRoles',           component: () => import('@/views/admin/roles.vue'),           meta: { title: '角色管理' } },
      { path: 'orders',          name: 'AdminOrders',          component: () => import('@/views/admin/orders.vue'),          meta: { title: '订单管理' } },
      { path: 'packages',        name: 'AdminPackages',        component: () => import('@/views/admin/packages.vue'),        meta: { title: '次数包管理' } },
      { path: 'xianyu-products', name: 'AdminXianyuProducts',  component: () => import('@/views/admin/xianyu-products.vue'), meta: { title: '闲鱼商品管理' } },
      { path: 'constitution',    name: 'AdminConstitution',    component: () => import('@/views/admin/constitution.vue'),    meta: { title: '体质题目' } },
      { path: 'ai',              name: 'AdminAi',              component: () => import('@/views/admin/ai.vue'),              meta: { title: 'AI管理' } },
      { path: 'promoters',       name: 'AdminPromoters',       component: () => import('@/views/admin/promoters.vue'),       meta: { title: '推广管理' } },
      { path: 'withdraws',       name: 'AdminWithdraws',       component: () => import('@/views/admin/withdraws.vue'),       meta: { title: '提现审核' } },
      { path: 'articles',        name: 'AdminArticles',        component: () => import('@/views/admin/articles.vue'),        meta: { title: '文章管理' } },
      { path: 'settings',        name: 'AdminSettings',        component: () => import('@/views/admin/settings.vue'),        meta: { title: '系统设置' } },
      { path: 'customer-service',name: 'AdminCustomerService', component: () => import('@/views/admin/customer-service.vue'),meta: { title: '客服管理' } },
    ],
  },
]

export default adminRoutes
