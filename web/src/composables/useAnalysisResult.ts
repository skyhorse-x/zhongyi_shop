/**
 * 分析结果数据加载与轮询
 * 抽取自 result.vue，统一管理任务状态
 */
import { ref, onUnmounted } from 'vue'
import request from '@/api/request'

export interface AnalysisResult {
  task_no: string
  status: 'pending' | 'running' | 'completed' | 'failed'
  type: 'tongue' | 'face' | 'constitution'
  result?: {
    summary?: string
    score?: number
    details?: any
    suggestions?: any[]
  }
  error?: string
}

export const useAnalysisResult = (taskNo: string, pollInterval = 2000) => {
  const data = ref<AnalysisResult | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const elapsed = ref(0)

  let timer: ReturnType<typeof setInterval> | null = null
  const start = Date.now()

  const fetchStatus = async () => {
    if (!taskNo) return
    loading.value = true
    try {
      const res: any = await request.get(`/analysis/status/${taskNo}`)
      data.value = res
      error.value = res.error || null
      elapsed.value = Math.floor((Date.now() - start) / 1000)

      if (res.status === 'completed' || res.status === 'failed') {
        if (timer) { clearInterval(timer); timer = null }
      }
    } catch (e: any) {
      error.value = e.message || '获取状态失败'
    } finally {
      loading.value = false
    }
  }

  const startPolling = () => {
    if (timer) return
    fetchStatus()
    timer = setInterval(fetchStatus, pollInterval)
  }

  const stopPolling = () => {
    if (timer) { clearInterval(timer); timer = null }
  }

  onUnmounted(stopPolling)

  return { data, loading, error, elapsed, fetchStatus, startPolling, stopPolling }
}
