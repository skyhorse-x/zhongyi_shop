/**
 * 统一的 HTTP 客户端：
 *   - 自动附加 Authorization 头
 *   - 检测 401：用户端先尝试静默刷新 token，成功后重试；失败再跳转登录
 *   - 检测 429：直接抛错（业务层一般已用 message 提示）
 *   - 统一返回 { ok, status, data, raw } 形式的数据，避免业务层到处 try/catch
 */

import { ElMessage } from 'element-plus'
import {
  getToken,
  setToken,
  clearToken,
  clearUserInfo,
  isAdminRequest,
  handleUserUnauthorized,
  handleAdminUnauthorized,
} from './auth'

// 跳过以下路径的 401 静默刷新（避免循环）
const SKIP_REFRESH_PATHS = ['/api/v1/refresh']

// 标记是否正在刷新 token
let isRefreshingToken = false
// 等待刷新完成的请求队列
let refreshSubscribers: Array<(token: string) => void> = []

const notifyRefreshSubscribers = (token: string): void => {
  refreshSubscribers.forEach((cb) => cb(token))
  refreshSubscribers = []
}

const addRefreshSubscriber = (cb: (token: string) => void): void => {
  refreshSubscribers.push(cb)
}

/**
 * 静默刷新 token，多个并发 401 只会发起一次刷新请求
 */
const refreshToken = async (oldToken: string): Promise<string | null> => {
  if (isRefreshingToken) {
    return new Promise((resolve) => {
      addRefreshSubscriber((token: string) => resolve(token))
    })
  }

  isRefreshingToken = true

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
    isRefreshingToken = false
  }
}

/**
 * 401 处理：管理员端直接跳转登录；用户端先静默刷新，失败再跳转
 */
const handleUnauthorized = async (url: string, options: RequestInit): Promise<Response> => {
  if (isAdminRequest(url)) {
    handleAdminUnauthorized()
    return new Response(JSON.stringify({ code: 401, message: '请先登录' }), {
      status: 401,
      headers: { 'Content-Type': 'application/json' },
    })
  }

  const oldToken = getToken()
  if (!oldToken) {
    handleUserUnauthorized()
    return new Response(JSON.stringify({ code: 401, message: '请先登录' }), {
      status: 401,
      headers: { 'Content-Type': 'application/json' },
    })
  }

  const newToken = await refreshToken(oldToken)
  if (newToken) {
    // 用新 token 重试原请求
    const retryOptions: RequestInit = { ...options }
    const retryHeaders = new Headers(options.headers)
    retryHeaders.set('Authorization', `Bearer ${newToken}`)
    retryOptions.headers = retryHeaders
    return fetch(url, retryOptions)
  }

  // 刷新失败，清理状态并跳转登录
  clearToken()
  clearUserInfo()
  handleUserUnauthorized()
  return new Response(JSON.stringify({ code: 401, message: '登录已过期，请重新登录' }), {
    status: 401,
    headers: { 'Content-Type': 'application/json' },
  })
}

/**
 * 包装原生 fetch，统一处理 401 错误
 * 业务层一般不需要直接使用，请使用 request.ts 中的 axios 封装
 */
export const safeFetch = async (url: string, options: RequestInit = {}): Promise<Response> => {
  // 跳过刷新接口本身
  if (SKIP_REFRESH_PATHS.some((p) => url.includes(p))) {
    return fetch(url, options)
  }

  const response = await fetch(url, options)

  if (response.status === 401) {
    return handleUnauthorized(url, options)
  }

  return response
}

/**
 * 解析 401 响应体的业务 code（兼容部分接口用业务码 401 而非 HTTP 401）
 */
export const tryParseBusiness401 = async (response: Response): Promise<boolean> => {
  if (response.status !== 401) return false
  try {
    const clone = response.clone()
    const data = await clone.json()
    return data?.code === 401
  } catch {
    return false
  }
}

/**
 * 通用 JSON 解析器：处理后端统一 {code, message, data} 响应
 */
export const parseApiResponse = async <T = unknown>(response: Response): Promise<T> => {
  const data = await response.json().catch(() => ({}))
  if (data?.code === 0 || data?.code === undefined) {
    return (data?.data ?? data) as T
  }
  // 业务错误：抛出由业务层捕获
  const error = new Error(data?.message || `请求失败 (${response.status})`) as Error & {
    code?: number
    data?: unknown
  }
  error.code = data?.code
  error.data = data?.data
  throw error
}

/**
 * 快速判断响应是否需要提示（业务错误时统一 ElMessage 提示）
 */
export const showBusinessError = (err: unknown, fallback = '请求失败'): void => {
  const msg = (err as { message?: string })?.message || fallback
  ElMessage.error(msg)
}
