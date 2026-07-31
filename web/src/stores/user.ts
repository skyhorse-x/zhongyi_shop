import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { login, register, getUserInfo, logout } from '@/api/auth'

export const useUserStore = defineStore('user', () => {
  // 状态
  const token = ref(localStorage.getItem('token') || '')
  const userInfo = ref<any>(null)

  // 计算属性
  const isLoggedIn = computed(() => !!token.value)
  const isPromoter = computed(() => userInfo.value?.is_promoter === 1)

  // 登录
  const loginAction = async (account: string, password: string) => {
    const res: any = await login({ account, password })
    token.value = res.token
    userInfo.value = res.user
    localStorage.setItem('token', res.token)
    return res
  }

  // 注册
  const registerAction = async (data: any) => {
    const res: any = await register(data)
    token.value = res.token
    userInfo.value = res.user
    localStorage.setItem('token', res.token)
    return res
  }

  // 获取用户信息
  const fetchUserInfo = async () => {
    const res = await getUserInfo()
    userInfo.value = res
    return res
  }

  // 退出登录
  const logoutAction = async () => {
    try {
      await logout()
    } catch (e) {
      // 忽略错误
    }
    token.value = ''
    userInfo.value = null
    localStorage.removeItem('token')
  }

  return {
    token,
    userInfo,
    isLoggedIn,
    isPromoter,
    loginAction,
    registerAction,
    fetchUserInfo,
    logoutAction,
  }
})
