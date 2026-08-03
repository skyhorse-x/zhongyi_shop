import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { login, register, getUserInfo, logout } from '@/api/auth'
import { getToken, setToken, clearToken, getUserInfo as readUserInfo, setUserInfo, clearUserInfo } from '@/utils/auth'

export const useUserStore = defineStore('user', () => {
  // 状态
  const token = ref(getToken() || '')
  const userInfo = ref<any>(readUserInfo())

  // 计算属性
  const isLoggedIn = computed(() => !!token.value)
  const isPromoter = computed(() => userInfo.value?.is_promoter === 1)

  // 登录
  const loginAction = async (account: string, password: string) => {
    const res: any = await login({ account, password })
    token.value = res.token
    userInfo.value = res.user
    setToken(res.token)
    setUserInfo(res.user)
    return res
  }

  // 注册
  const registerAction = async (data: any) => {
    const res: any = await register(data)
    token.value = res.token
    userInfo.value = res.user
    setToken(res.token)
    setUserInfo(res.user)
    return res
  }

  // 获取用户信息
  const fetchUserInfo = async () => {
    const res = await getUserInfo()
    userInfo.value = res
    setUserInfo(res)
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
    clearToken()
    clearUserInfo()
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
