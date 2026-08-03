/**
 * 用户端路由：会员中心、套餐、消息、推广、问答、体质
 */
import type { RouteRecordRaw } from 'vue-router'

const userRoutes: RouteRecordRaw[] = [
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
  // 反馈与申诉
  {
    path: '/user/feedback',
    name: 'UserFeedback',
    component: () => import('@/views/user/feedback.vue'),
    meta: { title: '反馈与申诉', needAuth: true },
  },
]

export default userRoutes
