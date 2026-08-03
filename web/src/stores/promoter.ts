/**
 * 推广员状态管理
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import request from '@/api/request'

export const usePromoterStore = defineStore('promoter', () => {
  const info = ref<any>(null)
  const loading = ref(false)

  const isPromoter = computed(() => info.value?.status === 1)
  const availableCommission = computed(() => Number(info.value?.available_commission ?? 0))
  const frozenCommission = computed(() => Number(info.value?.frozen_commission ?? 0))
  const totalCommission = computed(() => Number(info.value?.total_commission ?? 0))

  const fetchInfo = async () => {
    loading.value = true
    try {
      const data: any = await request.get('/promoter/info')
      info.value = data
      return data
    } finally {
      loading.value = false
    }
  }

  const activate = async (code: string) => {
    return request.post('/promoter/activate', { invite_code: code })
  }

  const submitWithdraw = async (amount: number, channel: string, account: string) => {
    return request.post('/promoter/withdraw', { amount, channel, account })
  }

  return {
    info, loading,
    isPromoter, availableCommission, frozenCommission, totalCommission,
    fetchInfo, activate, submitWithdraw,
  }
})
