import { ref, type Ref } from 'vue'
import { ElMessage } from 'element-plus'

export function useRequest<T = any>() {
  const data: Ref<T | null> = ref(null) as Ref<T | null>
  const loading: Ref<boolean> = ref(false)
  const error: Ref<string | null> = ref(null)

  const execute = async (
    apiFn: () => Promise<T>,
    options?: { showError?: boolean; onSuccess?: (res: T) => void }
  ) => {
    loading.value = true
    error.value = null
    try {
      const res = await apiFn()
      data.value = res
      options?.onSuccess?.(res)
      return res
    } catch (e: any) {
      const msg = e.message || '请求失败'
      error.value = msg
      if (options?.showError !== false) {
        ElMessage.error(msg)
      }
      throw e
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, execute }
}
