import router from '@/router'
import { useAdminStore } from '@/stores/admin'
import { ElMessage } from 'element-plus'

// 路由守卫 - 权限检查
export function setupPermissionGuard() {
  router.beforeEach((to, _from, next) => {
    document.title = (to.meta.title as string) || 'AI中医健康管理'

    // 用户端认证
    if (to.meta.needAuth) {
      const token = localStorage.getItem('token')
      if (!token) {
        next({ name: 'Login', query: { redirect: to.fullPath } })
        return
      }
    }

    // 管理后台认证
    if (to.meta.needAdminAuth) {
      const adminToken = localStorage.getItem('admin_token')
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
