/**
 * 提交分析任务
 */
import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/api/request'

export const useAnalysisSubmit = () => {
  const submitting = ref(false)

  const submitTongue = async (imageData: string): Promise<string | null> => {
    submitting.value = true
    try {
      const data: any = await request.post('/analysis/submit', { type: 'tongue', image: imageData })
      return data.task_no || data.taskNo || null
    } catch (e: any) {
      ElMessage.error(e.message || '提交失败')
      return null
    } finally {
      submitting.value = false
    }
  }

  const submitFace = async (imageData: string): Promise<string | null> => {
    submitting.value = true
    try {
      const data: any = await request.post('/analysis/submit', { type: 'face', image: imageData })
      return data.task_no || data.taskNo || null
    } catch (e: any) {
      ElMessage.error(e.message || '提交失败')
      return null
    } finally {
      submitting.value = false
    }
  }

  return { submitting, submitTongue, submitFace }
}