import { ref, computed } from 'vue'
import type { PaginationParams } from '@/types'

export function usePagination(defaultPageSize = 10) {
  const currentPage = ref(1)
  const pageSize = ref(defaultPageSize)
  const total = ref(0)

  const paginationParams = computed<PaginationParams>(() => ({
    page: currentPage.value,
    pageSize: pageSize.value,
  }))

  const setTotal = (val: number) => { total.value = val }
  const handlePageChange = (page: number) => { currentPage.value = page }
  const handleSizeChange = (size: number) => {
    pageSize.value = size
    currentPage.value = 1
  }
  const resetPage = () => { currentPage.value = 1 }

  return {
    currentPage, pageSize, total,
    paginationParams,
    setTotal,
    handlePageChange, handleSizeChange,
    resetPage,
  }
}
