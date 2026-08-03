/**
 * 订单状态管理
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import request from '@/api/request'

export const useOrderStore = defineStore('order', () => {
  const orders = ref<any[]>([])
  const loading = ref(false)
  const total = ref(0)

  const fetchOrders = async (params: any = {}) => {
    loading.value = true
    try {
      const data: any = await request.get('/user/orders', { params })
      orders.value = data?.list || data || []
      total.value = data?.total || 0
      return data
    } finally {
      loading.value = false
    }
  }

  const createOrder = async (packageId: number, payChannel: string) => {
    return request.post('/packages/buy', { package_id: packageId, pay_channel: payChannel })
  }

  const cancelOrder = async (orderNo: string) => {
    return request.post(`/user/orders/${orderNo}/cancel`)
  }

  return { orders, loading, total, fetchOrders, createOrder, cancelOrder }
})