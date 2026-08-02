import axios, { type AxiosInstance, type AxiosResponse } from 'axios'
import { ElMessage } from 'element-plus'
import router from '@/router'

// 创建 axios 实例
const request: AxiosInstance = axios.create({
  baseURL: '/api/v1',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// 请求拦截器
request.interceptors.request.use(
  (config) => {
    // 添加 token
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// 标记是否正在跳转登录页，避免重复跳转
let isRedirectingToLogin = false

// 处理未登录跳转
const handleUnauthorized = () => {
  if (isRedirectingToLogin) return
  isRedirectingToLogin = true
  
  localStorage.removeItem('token')
  ElMessage.error('请先登录')
  
  // 使用 replace 避免导航冲突，并在完成后重置标记
  router.replace({ name: 'Login' }).finally(() => {
    isRedirectingToLogin = false
  })
}

// 响应拦截器
request.interceptors.response.use(
  (response: AxiosResponse) => {
    const { code, message, data } = response.data

    // 成功
    if (code === 0) {
      return data
    }

    // 未登录
    if (code === 401) {
      handleUnauthorized()
      return Promise.reject(new Error(message))
    }

    // 其他错误
    ElMessage.error(message || '请求失败')
    return Promise.reject(new Error(message))
  },
  (error) => {
    // 网络错误
    if (error.response) {
      const { status, data } = error.response
      if (status === 401) {
        // 检查是否是登录接口本身的失败（账号密码错误等）
        const isLoginRequest = error.config?.url?.includes('/auth/login')
        if (isLoginRequest) {
          // 登录失败 - 显示后端返回的错误信息
          const errorMsg = data?.message || '账号或密码错误'
          ElMessage.error(errorMsg)
        } else {
          // 其他接口 401 - 未登录或登录过期
          handleUnauthorized()
        }
      } else if (status === 422) {
        // 验证错误
        const messages = Object.values(data.errors || {}).flat()
        ElMessage.error((messages[0] as string) || '输入有误')
      } else {
        ElMessage.error(data.message || '服务器错误')
      }
    } else {
      ElMessage.error('网络错误，请检查网络')
    }
    return Promise.reject(error)
  }
)

export default request
