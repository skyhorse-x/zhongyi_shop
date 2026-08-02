import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  // 首页
  {
    path: '/',
    name: 'Home',
    component: () => import('@/views/home/index.vue'),
    meta: { title: 'AI中医健康管理' },
  },
  // 登录注册
  {
    path: '/auth/login',
    name: 'Login',
    component: () => import('@/views/auth/login.vue'),
    meta: { title: '登录' },
  },
  {
    path: '/auth/register',
    name: 'Register',
    component: () => import('@/views/auth/register.vue'),
    meta: { title: '注册' },
  },
  // 管理后台登录（独立，不套用 AdminLayout）
  {
    path: '/admin/login',
    name: 'AdminLogin',
    component: () => import('@/views/admin/login.vue'),
    meta: { title: '管理后台登录' },
  },
  // 管理后台（嵌套路由，套用 AdminLayout）
  {
    path: '/admin',
    component: () => import('@/views/admin/AdminLayout.vue'),
    meta: { title: '管理后台', needAdminAuth: true },
    redirect: '/admin/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'AdminDashboard',
        component: () => import('@/views/admin/dashboard.vue'),
        meta: { title: '仪表盘' },
      },
      {
        path: 'users',
        name: 'AdminUsers',
        component: () => import('@/views/admin/users.vue'),
        meta: { title: '用户管理' },
      },
      {
        path: 'admins',
        name: 'AdminAdmins',
        component: () => import('@/views/admin/admins.vue'),
        meta: { title: '管理员管理' },
      },
      {
        path: 'orders',
        name: 'AdminOrders',
        component: () => import('@/views/admin/orders.vue'),
        meta: { title: '订单管理' },
      },
      {
        path: 'packages',
        name: 'AdminPackages',
        component: () => import('@/views/admin/packages.vue'),
        meta: { title: '次数包管理' },
      },
      {
        path: 'constitution',
        name: 'AdminConstitution',
        component: () => import('@/views/admin/constitution.vue'),
        meta: { title: '体质题目' },
      },
      {
        path: 'ai',
        name: 'AdminAi',
        component: () => import('@/views/admin/ai.vue'),
        meta: { title: 'AI管理' },
      },
      {
        path: 'promoters',
        name: 'AdminPromoters',
        component: () => import('@/views/admin/promoters.vue'),
        meta: { title: '推广管理' },
      },
      {
        path: 'withdraws',
        name: 'AdminWithdraws',
        component: () => import('@/views/admin/withdraws.vue'),
        meta: { title: '提现审核' },
      },
      {
        path: 'articles',
        name: 'AdminArticles',
        component: () => import('@/views/admin/articles.vue'),
        meta: { title: '文章管理' },
      },
      {
        path: 'settings',
        name: 'AdminSettings',
        component: () => import('@/views/admin/settings.vue'),
        meta: { title: '系统设置' },
      },
      {
        path: 'customer-service',
        name: 'AdminCustomerService',
        component: () => import('@/views/admin/customer-service.vue'),
        meta: { title: '客服管理' },
      },
    ],
  },
  // 舌诊分析
  {
    path: '/analysis/tongue',
    name: 'TongueAnalysis',
    component: () => import('@/views/analysis/tongue.vue'),
    meta: { title: '舌诊分析', needAuth: true },
  },
  {
    path: '/analysis/face',
    name: 'FaceAnalysis',
    component: () => import('@/views/analysis/face.vue'),
    meta: { title: '面诊分析', needAuth: true },
  },
  {
    path: '/analysis/result/:taskNo',
    name: 'AnalysisResult',
    component: () => import('@/views/analysis/result.vue'),
    meta: { title: '分析结果', needAuth: true },
  },
  // 体质测试
  {
    path: '/constitution/test',
    name: 'ConstitutionTest',
    component: () => import('@/views/constitution/test.vue'),
    meta: { title: '体质测试', needAuth: true },
  },
  {
    path: '/constitution/result/:taskNo',
    name: 'ConstitutionResult',
    component: () => import('@/views/constitution/result.vue'),
    meta: { title: '体质报告', needAuth: true },
  },
  // 健康问答
  {
    path: '/qa/chat/:sessionNo?',
    name: 'QaChat',
    component: () => import('@/views/qa/chat.vue'),
    meta: { title: '健康问答', needAuth: true },
  },
  {
    path: '/qa/sessions',
    name: 'QaSessions',
    component: () => import('@/views/qa/sessions.vue'),
    meta: { title: '问答记录', needAuth: true },
  },
  // 消息中心
  {
    path: '/messages',
    name: 'Messages',
    component: () => import('@/views/messages/index.vue'),
    meta: { title: '消息中心', needAuth: true },
  },
  {
    path: '/messages/customer-service',
    name: 'CustomerService',
    component: () => import('@/views/messages/customer-service.vue'),
    meta: { title: '客服聊天', needAuth: true },
  },
  // 次数包
  {
    path: '/packages',
    name: 'Packages',
    component: () => import('@/views/packages/index.vue'),
    meta: { title: '购买次数包', needAuth: true },
  },
  // 健康档案
  {
    path: '/health/history',
    name: 'HealthHistory',
    component: () => import('@/views/health/history.vue'),
    meta: { title: '分析历史', needAuth: true },
  },
  {
    path: '/health/trend',
    name: 'HealthTrend',
    component: () => import('@/views/health/trend.vue'),
    meta: { title: '健康趋势', needAuth: true },
  },
  {
    path: '/health/constitution',
    name: 'HealthConstitution',
    component: () => import('@/views/health/constitution.vue'),
    meta: { title: '体质档案', needAuth: true },
  },
  // 会员中心
  {
    path: '/member',
    name: 'Member',
    component: () => import('@/views/member/index.vue'),
    meta: { title: '会员中心', needAuth: true },
  },
  {
    path: '/member/orders',
    name: 'Orders',
    component: () => import('@/views/member/orders.vue'),
    meta: { title: '我的订单', needAuth: true },
  },
  {
    path: '/member/balance',
    name: 'Balance',
    component: () => import('@/views/member/balance.vue'),
    meta: { title: '余额明细', needAuth: true },
  },
  // 推广中心
  {
    path: '/promoter/activate',
    name: 'PromoterActivate',
    component: () => import('@/views/promoter/activate.vue'),
    meta: { title: '开通推广员', needAuth: true },
  },
  {
    path: '/promoter',
    name: 'Promoter',
    component: () => import('@/views/promoter/index.vue'),
    meta: { title: '推广中心', needAuth: true },
  },
  {
    path: '/promoter/commissions',
    name: 'Commissions',
    component: () => import('@/views/promoter/commissions.vue'),
    meta: { title: '佣金明细', needAuth: true },
  },
  {
    path: '/promoter/withdraw',
    name: 'Withdraw',
    component: () => import('@/views/promoter/withdraw.vue'),
    meta: { title: '提现', needAuth: true },
  },
  {
    path: '/promoter/withdraw-history',
    name: 'WithdrawHistory',
    component: () => import('@/views/promoter/withdraw-history.vue'),
    meta: { title: '提现记录', needAuth: true },
  },
  // 404
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/error/404.vue'),
    meta: { title: '页面不存在' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
