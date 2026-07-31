import request from './request'
import type { UserInfo, Order } from '@/types'

export const adminApi = {
  // 登录
  login: (data: { username: string; password: string }) =>
    request.post('/admin/auth/login', data),

  // 仪表盘
  getDashboard: () => request.get('/admin/dashboard'),

  // 用户管理
  getUsers: (params?: any) => request.get('/admin/users', { params }),
  getUserDetail: (id: number) => request.get(`/admin/users/${id}`),

  // 订单管理
  getOrders: (params?: any) => request.get('/admin/orders', { params }),
  getOrderDetail: (orderNo: string) => request.get(`/admin/orders/${orderNo}`),

  // AI管理
  getAiModels: () => request.get('/admin/ai/models'),
  createAiModel: (data: any) => request.post('/admin/ai/models', data),
  updateAiModel: (id: number, data: any) => request.put(`/admin/ai/models/${id}`, data),
  getAiLogs: (params?: any) => request.get('/admin/ai/logs', { params }),

  // 推广管理
  getPromoters: (params?: any) => request.get('/admin/promoters', { params }),

  // 提现审核
  getWithdraws: (params?: any) => request.get('/admin/withdraws', { params }),
  auditWithdraw: (id: number, action: 'approve' | 'reject', remark?: string) =>
    request.post(`/admin/withdraws/${id}/audit`, { action, remark }),

  // 文章管理
  getArticles: (params?: any) => request.get('/admin/articles', { params }),
  createArticle: (data: any) => request.post('/admin/articles', data),
  updateArticle: (id: number, data: any) => request.put(`/admin/articles/${id}`, data),
  deleteArticle: (id: number) => request.delete(`/admin/articles/${id}`),

  // 系统配置
  getConfigs: () => request.get('/admin/configs'),
  updateConfigs: (data: Record<string, any>) => request.post('/admin/configs', data),
}
