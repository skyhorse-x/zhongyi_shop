import { useAdminStore } from '@/stores/admin'

export function usePermission() {
  const adminStore = useAdminStore()

  const hasPermission = (permission: string): boolean => {
    return adminStore.permissions.includes(permission) || adminStore.permissions.includes('*')
  }

  const hasAnyPermission = (permissions: string[]): boolean => {
    return permissions.some(p => hasPermission(p))
  }

  const hasAllPermissions = (permissions: string[]): boolean => {
    return permissions.every(p => hasPermission(p))
  }

  return { hasPermission, hasAnyPermission, hasAllPermissions }
}
