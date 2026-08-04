import router from '@/router'
import { useAdminStore } from '@/stores/admin'
import { ElMessage } from 'element-plus'
import { getToken, getAdminToken } from '@/utils/auth'

// 路由守卫 - 权限检查
export function setupPermissionGuard() {
  router.beforeEach((to, _from, next) => {
    document.title = (to.meta.title as string) || 'ai 中医健康助手'

    const token = getToken()
    const adminToken = getAdminToken()

    // 用户端认证 - 推广中心、会员中心、购买页面：用户或管理员都可以访问
    if (to.meta.needAuth) {
      if (!token && !adminToken) {
        next({ name: 'Login', query: { redirect: to.fullPath } })
        return
      }
    }

    // 管理后台认证 - 只需要 adminToken
    if (to.meta.needAdminAuth) {
      if (!adminToken) {
        next({ name: 'AdminLogin' })
        return
      }
    }

    next()
  })
}

// 权限指令
export const vPermission = {
  mounted(el: HTMLElement, binding: { value: string }) {
    const adminStore = useAdminStore()
    if (!adminStore.permissions.includes(binding.value) && !adminStore.permissions.includes('*')) {
      el.parentNode?.removeChild(el)
    }
  },
}
