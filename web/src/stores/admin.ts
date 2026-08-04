import { defineStore } from 'pinia'
import { ref, shallowRef, type Component } from 'vue'
import { TrendCharts, UserFilled, Tickets, Cpu, Promotion, Money, Document, Setting, Avatar } from '@element-plus/icons-vue'
import { getAdminToken, setAdminToken as persistAdminToken, clearAdminToken } from '@/utils/auth'

interface AdminMenuItem {
  title: string
  icon: Component
  path: string
}

export const useAdminStore = defineStore('admin', () => {
  const token = ref(getAdminToken() || '')
  const adminInfo = ref<any>(null)
  const permissions = ref<string[]>([])

  // 使用 shallowRef 避免图标组件被 reactive 包裹
  const menuItems = shallowRef<AdminMenuItem[]>([
    { title: '仪表盘', icon: TrendCharts, path: '/admin/dashboard' },
    { title: '用户管理', icon: UserFilled, path: '/admin/users' },
    { title: '管理员管理', icon: Avatar, path: '/admin/admins' },
    { title: '订单管理', icon: Tickets, path: '/admin/orders' },
    { title: 'AI管理', icon: Cpu, path: '/admin/ai' },
    { title: '推广管理', icon: Promotion, path: '/admin/promoters' },
    { title: '提现审核', icon: Money, path: '/admin/withdraws' },
    { title: '文章管理', icon: Document, path: '/admin/articles' },
    { title: '系统设置', icon: Setting, path: '/admin/settings' },
  ])

  const isLoggedIn = () => !!token.value

  const setToken = (t: string) => {
    token.value = t
    persistAdminToken(t)
  }

  const logout = () => {
    token.value = ''
    adminInfo.value = null
    permissions.value = []
    clearAdminToken()
  }

  return { token, adminInfo, permissions, menuItems, isLoggedIn, setToken, logout }
})
