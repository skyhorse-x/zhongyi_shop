/**
 * AI 分析任务状态管理
 * 用于跨组件共享分析任务进度
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import request from '@/api/request'

interface AnalysisTask {
  taskNo: string
  type: 'tongue' | 'face'
  status: 'pending' | 'running' | 'completed' | 'failed'
  progress: number
  result?: any
}

export const useAnalysisStore = defineStore('analysis', () => {
  const currentTask = ref<AnalysisTask | null>(null)
  const history = ref<any[]>([])

  // 提交分析
  const submitTongue = async (imageData: string): Promise<AnalysisTask> => {
    const data: any = await request.post('/analysis/tongue', { image: imageData })
    const task: AnalysisTask = {
      taskNo: data.task_no,
      type: 'tongue',
      status: 'pending',
      progress: 0,
    }
    currentTask.value = task
    return task
  }

  const submitFace = async (imageData: string): Promise<AnalysisTask> => {
    const data: any = await request.post('/analysis/face', { image: imageData })
    const task: AnalysisTask = {
      taskNo: data.task_no,
      type: 'face',
      status: 'pending',
      progress: 0,
    }
    currentTask.value = task
    return task
  }

  // 轮询状态
  const pollStatus = async (taskNo: string): Promise<AnalysisTask> => {
    const data: any = await request.get(`/analysis/status/${taskNo}`)
    if (currentTask.value?.taskNo === taskNo) {
      currentTask.value.status = data.status
      currentTask.value.progress = data.progress || 0
      if (data.status === 'completed') {
        currentTask.value.result = data.result
      }
    }
    return currentTask.value!
  }

  const clearCurrent = () => { currentTask.value = null }

  return { currentTask, history, submitTongue, submitFace, pollStatus, clearCurrent }
})
