/**
 * 统一 HTTP 客户端（基于 axios）
 *
 * 设计原则：
 *   1. 全站统一走此文件，禁止业务代码直接 axios/safeFetch/fetch
 *   2. 根据 URL 自动选择用户端 / 管理后台 token
 *   3. 401 自动静默刷新 token 并重试原请求
 *   4. 业务码 0 视为成功，其余都通过 ElMessage 提示
 *
 * 使用：
 *   import request from '@/api/request'
 *   const data = await request.get('/users/me')
 *   await request.post('/users', { name: 'test' })
 */

import axios, {
  AxiosError,
  type AxiosInstance,
  type AxiosRequestConfig,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from 'axios'
import { ElMessage } from 'element-plus'
import {
  getToken,
  setToken,
  clearToken,
  clearUserInfo,
  clearAdminToken,
  getAdminToken,
  isAdminRequest,
  handleUserUnauthorized,
  handleAdminUnauthorized,
} from '@/utils/auth'

const request: AxiosInstance = axios.create({
  baseURL: '/api/v1',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// ============ 静默刷新 token ============
let isRefreshing = false
let refreshSubscribers: Array<(token: string) => void> = []

const notifyRefreshSubscribers = (token: string): void => {
  refreshSubscribers.forEach((cb) => cb(token))
  refreshSubscribers = []
}

const addRefreshSubscriber = (cb: (token: string) => void): void => {
  refreshSubscribers.push(cb)
}

const refreshToken = async (oldToken: string): Promise<string | null> => {
  if (isRefreshing) {
    return new Promise((resolve) => {
      addRefreshSubscriber((token) => resolve(token))
    })
  }
  isRefreshing = true

  try {
    const res = await fetch('/api/v1/refresh', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ refresh_token: oldToken }),
    })
    const data = await res.json()
    if (data.code === 0 && data.data?.token) {
      const newToken = data.data.token
      setToken(newToken)
      notifyRefreshSubscribers(newToken)
      return newToken
    }
    notifyRefreshSubscribers('')
    return null
  } catch {
    notifyRefreshSubscribers('')
    return null
  } finally {
    isRefreshing = false
  }
}

// ============ 请求拦截器 ============
request.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const url = config.url || ''
    const isAdmin = isAdminRequest(url)
    const token = isAdmin ? getAdminToken() : getToken()
    if (token) {
      config.headers.set('Authorization', `Bearer ${token}`)
    }
    return config
  },
  (error) => Promise.reject(error)
)

// ============ 响应拦截器 ============
request.interceptors.response.use(
  async (response: AxiosResponse) => {
    const { code, message, data } = response.data ?? {}

    // 业务码 0 成功
    if (code === 0 || code === undefined) {
      return data
    }

    // 业务码 401：触发登录态失效
    if (code === 401) {
      const url = response.config?.url || ''
      if (isAdminRequest(url)) {
        handleAdminUnauthorized()
      } else {
        handleUserUnauthorized()
      }
      return Promise.reject(new Error(message || '请先登录'))
    }

    // 其他业务错误
    ElMessage.error(message || '请求失败')
    return Promise.reject(new Error(message || '请求失败'))
  },
  async (error: AxiosError<any>) => {
    const status = error.response?.status
    const url = error.config?.url || ''
    const isAdmin = isAdminRequest(url)
    const originalConfig = error.config as InternalAxiosRequestConfig & {
      _retry?: boolean
    }

    // ========== HTTP 401：尝试静默刷新 ==========
    if (status === 401 && !isAdmin && !originalConfig._retry && !url.includes('/refresh') && !url.includes('/auth/login')) {
      originalConfig._retry = true
      const oldToken = getToken()
      if (!oldToken) {
        handleUserUnauthorized()
        return Promise.reject(error)
      }
      const newToken = await refreshToken(oldToken)
      if (newToken) {
        originalConfig.headers.set('Authorization', `Bearer ${newToken}`)
        return request(originalConfig)
      }
      clearToken()
      clearUserInfo()
      handleUserUnauthorized()
      return Promise.reject(error)
    }

    if (status === 401) {
      if (isAdmin) {
        clearAdminToken()
        handleAdminUnauthorized()
      } else if (url.includes('/auth/login')) {
        ElMessage.error(error.response?.data?.message || '账号或密码错误')
      } else {
        handleUserUnauthorized()
      }
    } else if (status === 422) {
      const messages = Object.values(error.response?.data?.errors || {}).flat() as string[]
      ElMessage.error(messages[0] || '输入有误')
    } else if (status === 429) {
      ElMessage.error('操作过于频繁，请稍后再试')
    } else {
      ElMessage.error(error.response?.data?.message || error.message || '网络错误')
    }

    return Promise.reject(error)
  }
)

// ============ 简化方法 ============
const makeMethod = <T = unknown>(method: 'get' | 'post' | 'put' | 'delete') =>
  <T = unknown>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<T> =>
    request({ url, method, data, ...config } as AxiosRequestConfig) as unknown as Promise<T>

export const get = makeMethod('get')
export const post = makeMethod('post')
export const put = makeMethod('put')
export const del = makeMethod('delete')

export default request
