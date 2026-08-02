import { ElMessage } from 'element-plus'
import router from '@/router'

// 标记是否正在跳转登录页，避免重复跳转
let isRedirectingToUserLogin = false
let isRedirectingToAdminLogin = false

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
          handleUserUnauthorized()
        }
      }
    } catch {
      // 如果不是 JSON，直接根据 URL 判断
      if (isAdminRequest(url)) {
        handleAdminUnauthorized()
      } else {
        handleUserUnauthorized()
      }
    }
  }

  return response
}
