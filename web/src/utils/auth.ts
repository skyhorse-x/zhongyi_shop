/**
 * 前端 Token 与认证上下文统一管理
 *
 * 设计原则：
 *   1. 严格分离用户端 / 管理后台 Token（不同存储 key，不同函数）
 *   2. 不再做"兼容读取"——谁调用谁负责明确指定
 *   3. 所有读写都走本文件，禁止业务代码直接访问 localStorage
 *
 * 模块化：
 *   - 用户端：getToken / setToken / clearToken
 *   - 管理端：getAdminToken / setAdminToken / clearAdminToken
 *   - 用户信息：getUserInfo / setUserInfo / clearUserInfo
 *   - Headers：buildUserHeaders / buildAdminHeaders
 *   - 跳转：handleUserUnauthorized / handleAdminUnauthorized
 */

import { ElMessage } from 'element-plus'
import router from '@/router'

// ===================== 存储键 =====================
const TOKEN_KEY = 'token'
const ADMIN_TOKEN_KEY = 'admin_token'
const USER_INFO_KEY = 'user_info'

// ===================== 防重入锁 =====================
let isRedirectingToUserLogin = false
let isRedirectingToAdminLogin = false

// ===================== 用户端 Token =====================
export const getToken = (): string | null => localStorage.getItem(TOKEN_KEY)
export const setToken = (token: string): void => localStorage.setItem(TOKEN_KEY, token)
export const clearToken = (): void => localStorage.removeItem(TOKEN_KEY)

// ===================== 管理后台 Token =====================
export const getAdminToken = (): string | null => localStorage.getItem(ADMIN_TOKEN_KEY)
export const setAdminToken = (token: string): void => localStorage.setItem(ADMIN_TOKEN_KEY, token)
export const clearAdminToken = (): void => localStorage.removeItem(ADMIN_TOKEN_KEY)

// ===================== 用户信息 =====================
export const getUserInfo = <T = Record<string, unknown>>(): T | null => {
  const raw = localStorage.getItem(USER_INFO_KEY)
  if (!raw) return null
  try {
    return JSON.parse(raw) as T
  } catch {
    return null
  }
}
export const setUserInfo = (info: unknown): void =>
  localStorage.setItem(USER_INFO_KEY, JSON.stringify(info))
export const clearUserInfo = (): void => localStorage.removeItem(USER_INFO_KEY)

// ===================== 工具函数 =====================

/**
 * 判断请求是否发往管理后台
 */
export const isAdminRequest = (url: string): boolean => url.includes('/api/v1/admin')

/**
 * 用户端 Authorization 头
 */
export const buildUserHeaders = (): HeadersInit => {
  const token = getToken()
  return token ? { Authorization: `Bearer ${token}` } : {}
}

/**
 * 管理后台 Authorization 头
 */
export const buildAdminHeaders = (): HeadersInit => {
  const token = getAdminToken()
  return token ? { Authorization: `Bearer ${token}` } : {}
}

/**
 * 按 URL 自动选用户/管理头
 */
export const buildAuthHeadersByUrl = (url: string): HeadersInit =>
  isAdminRequest(url) ? buildAdminHeaders() : buildUserHeaders()

// ===================== 登录态失效处理 =====================

/**
 * 用户端未登录/登录失效：清除状态并跳转登录页
 */
export const handleUserUnauthorized = (): void => {
  if (!getToken()) {
    ElMessage.error('请先登录')
  }
  if (isRedirectingToUserLogin) return
  isRedirectingToUserLogin = true

  clearToken()
  clearUserInfo()

  router.replace({ name: 'Login' }).finally(() => {
    isRedirectingToUserLogin = false
  })
}

/**
 * 管理后台未登录/登录失效：清除状态并跳转管理员登录
 */
export const handleAdminUnauthorized = (): void => {
  if (!getAdminToken()) {
    ElMessage.error('请先登录')
  }
  if (isRedirectingToAdminLogin) return
  isRedirectingToAdminLogin = true

  clearAdminToken()

  router.replace({ name: 'AdminLogin' }).finally(() => {
    isRedirectingToAdminLogin = false
  })
}

/**
 * 全部清空（用户端 + 管理端），用于退出登录
 */
export const clearAllAuth = (): void => {
  clearToken()
  clearAdminToken()
  clearUserInfo()
}
