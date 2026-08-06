/**
 * 提交分析任务（直接返回完整结果，不再需要轮询）
 */
import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/api/request'

export interface AnalysisResult {
  task_no: string
  status: number
  type: 'tongue' | 'face' | 'palm'
  health_score: number
  summary: string
  result: {
    content: string
    summary: string
    health_score: number
    model: string
    usage: any
    mode: 'image' | 'text'
  }
  created_at: string
}

export const useAnalysisSubmit = () => {
  const submitting = ref(false)

  const submitAnalysis = async (payload: {
    type: 'tongue' | 'face' | 'palm'
    image_urls?: string[]
    text?: string
    gender: number
    age: number
  }): Promise<AnalysisResult | null> => {
    submitting.value = true
    try {
      const data: any = await request.post('/analysis/submit', payload)
      // 直接返回完整结果
      return data.data as AnalysisResult
    } catch (e: any) {
      ElMessage.error(e.message || '提交失败')
      return null
    } finally {
      submitting.value = false
    }
  }

  return { submitting, submitAnalysis }
}