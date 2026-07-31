import { ref, type Ref } from 'vue'

export function useLoading(initial = false) {
  const loading: Ref<boolean> = ref(initial)
  
  const startLoading = () => { loading.value = true }
  const stopLoading = () => { loading.value = false }
  const toggleLoading = () => { loading.value = !loading.value }
  
  const withLoading = async <T>(fn: () => Promise<T>): Promise<T> => {
    loading.value = true
    try {
      return await fn()
    } finally {
      loading.value = false
    }
  }
  
  return { loading, startLoading, stopLoading, toggleLoading, withLoading }
}
