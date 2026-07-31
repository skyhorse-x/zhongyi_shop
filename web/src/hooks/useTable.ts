import { ref, reactive } from 'vue'
import { usePagination } from './usePagination'
import type { PaginatedResponse } from '@/types'

interface UseTableOptions<T, F extends Record<string, any>> {
  fetchApi: (params: any) => Promise<PaginatedResponse<T>>
  defaultForm?: F
  immediate?: boolean
}

export function useTable<T, F extends Record<string, any> = any>(options: UseTableOptions<T, F>) {
  const { fetchApi, defaultForm, immediate = true } = options
  
  const tableData = ref<T[]>([])
  const tableLoading = ref(false)
  const searchForm = ref((defaultForm || {} as F)) as any
  const pagination = usePagination()

  const fetchData = async () => {
    tableLoading.value = true
    try {
      const params = {
        ...searchForm.value,
        page: pagination.currentPage.value,
        per_page: pagination.pageSize.value,
      }
      const res = await fetchApi(params)
      tableData.value = (res as any).data || res.list || res
      pagination.setTotal((res as any).total || 0)
    } catch {
      tableData.value = []
    } finally {
      tableLoading.value = false
    }
  }

  const handleSearch = () => {
    pagination.resetPage()
    fetchData()
  }

  const handleReset = () => {
    searchForm.value = { ...defaultForm }
    handleSearch()
  }

  if (immediate) fetchData()

  return {
    tableData,
    tableLoading,
    searchForm,
    pagination,
    fetchData,
    handleSearch,
    handleReset,
  }
}
