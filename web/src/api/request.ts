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
      localStorage.removeItem('token')
      router.push({ name: 'Login' })
      ElMessage.error('请先登录')
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
        localStorage.removeItem('token')
        router.push({ name: 'Login' })
        ElMessage.error('请先登录')
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
