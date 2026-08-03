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
    const data = res?.data || res
    const tokenVal = data?.token || res?.token
    const userVal = data?.user || res?.user
    token.value = tokenVal
    userInfo.value = userVal
    if (tokenVal) setToken(tokenVal)
    if (userVal) setUserInfo(userVal)
    return res
  }

  // 注册
  const registerAction = async (data: any) => {
    const res: any = await register(data)
    const r = res?.data || res
    const tokenVal = r?.token || res?.token
    const userVal = r?.user || res?.user
    token.value = tokenVal
    userInfo.value = userVal
    if (tokenVal) setToken(tokenVal)
    if (userVal) setUserInfo(userVal)
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
