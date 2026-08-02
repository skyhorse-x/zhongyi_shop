import { ElMessage } from 'element-plus'
import router from '@/router'

// 标记是否正在跳转登录页，避免重复跳转
let isRedirectingToUserLogin = false
let isRedirectingToAdminLogin = false

// 标记是否正在刷新 token，避免重复刷新
let isRefreshingToken = false
// 等待刷新完成的请求队列
let refreshSubscribers: Array<(token: string) => void> = []

// 刷新 token 并通知等待的请求
const doRefreshToken = async (oldToken: string): Promise<string | null> => {
  if (isRefreshingToken) {
    // 如果正在刷新，返回一个 Promise，等待刷新完成
    return new Promise((resolve) => {
      refreshSubscribers.push((token: string) => {
        resolve(token)
      })
    })
  }

  isRefreshingToken = true

  try {
    const res = await fetch('/api/v1/refresh', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ refresh_token: oldToken }),
    })

    const data = await res.json()

    if (data.code === 0 && data.data?.token) {
      const newToken = data.data.token
      localStorage.setItem('token', newToken)
      // 通知所有等待的请求
      refreshSubscribers.forEach((cb) => cb(newToken))
      refreshSubscribers = []
      return newToken
    }

    return null
  } catch {
    return null
  } finally {
    isRefreshingToken = false
  }
}

// 处理用户端未登录跳转
const handleUserUnauthorized = () => {
  // 如果没有 token，说明确实未登录
  const hasToken = !!localStorage.getItem('token')
  if (!hasToken) {
    ElMessage.error('请先登录')
  }
  
  if (isRedirectingToUserLogin) return
  isRedirectingToUserLogin = true

  localStorage.removeItem('token')

  router.replace({ name: 'Login' }).finally(() => {
    isRedirectingToUserLogin = false
  })
}

// 处理管理后台未登录跳转
const handleAdminUnauthorized = () => {
  if (isRedirectingToAdminLogin) return
  isRedirectingToAdminLogin = true

  localStorage.removeItem('admin_token')
  ElMessage.error('请先登录')

  router.replace({ name: 'AdminLogin' }).finally(() => {
    isRedirectingToAdminLogin = false
  })
}

// 判断是否为管理后台请求
const isAdminRequest = (url: string): boolean => {
  return url.includes('/api/v1/admin')
}

// 包装原生 fetch，统一处理 401 错误
export const safeFetch = async (url: string, options: RequestInit = {}): Promise<Response> => {
  // 跳过刷新接口本身
  if (url.includes('/api/v1/refresh')) {
    return fetch(url, options)
  }

  const response = await fetch(url, options)

  // 检查是否为 401 错误
  if (response.status === 401) {
    // 克隆响应体，因为响应体只能读取一次
    const clonedResponse = response.clone()

    try {
      const data = await clonedResponse.json()
      // 如果是业务 code 401（返回 JSON 格式）
      if (data.code === 401) {
        if (isAdminRequest(url)) {
          handleAdminUnauthorized()
        } else {
          // 尝试静默刷新 token
          const oldToken = localStorage.getItem('token')
          if (oldToken) {
            const newToken = await doRefreshToken(oldToken)
            if (newToken) {
              // 刷新成功，用新 token 重试原请求
              const newOptions = { ...options }
              const newHeaders = new Headers(options.headers)
              newHeaders.set('Authorization', `Bearer ${newToken}`)
              newOptions.headers = newHeaders
              return fetch(url, newOptions)
            }
          }
          // 刷新失败，跳转登录
          handleUserUnauthorized()
        }
      }
    } catch {
      // 如果不是 JSON，直接根据 URL 判断
      if (isAdminRequest(url)) {
        handleAdminUnauthorized()
      } else {
        // 尝试静默刷新 token
        const oldToken = localStorage.getItem('token')
        if (oldToken) {
          const newToken = await doRefreshToken(oldToken)
          if (newToken) {
            // 刷新成功，用新 token 重试原请求
            const newOptions = { ...options }
            const newHeaders = new Headers(options.headers)
            newHeaders.set('Authorization', `Bearer ${newToken}`)
            newOptions.headers = newHeaders
            return fetch(url, newOptions)
          }
        }
        handleUserUnauthorized()
      }
    }
  }

  return response
}
