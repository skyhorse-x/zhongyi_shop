/**
 * 认证状态（用户端 + 管理端统一）
 * 严格分离 User/Admin token
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getToken, getAdminToken } from '@/utils/auth'

export const useAuthStore = defineStore('auth', () => {
  // 状态
  const userToken = ref<string | null>(getToken())
  const adminToken = ref<string | null>(getAdminToken())

  // 计算属性
  const isUserLoggedIn = computed(() => !!userToken.value)
  const isAdminLoggedIn = computed(() => !!adminToken.value)

  // 同步 token（供 request.ts 在刷新 token 后调用）
  const syncUserToken = (t: string | null) => { userToken.value = t }
  const syncAdminToken = (t: string | null) => { adminToken.value = t }

  return {
    userToken,
    adminToken,
    isUserLoggedIn,
    isAdminLoggedIn,
    syncUserToken,
    syncAdminToken,
  }
})
