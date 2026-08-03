/**
 * 管理端客服工作台业务逻辑
 *
 * 把 customer-service.vue 中的状态管理、数据加载、消息收发抽离出来。
 * 后续重构时直接 useAdminCustomerService() 替代即可，无需关心具体实现。
 */
import { ref, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { getAdminToken } from '@/utils/auth'
import request from '@/api/request'

export interface Session {
  id: number
  session_no: string
  status: number
  user?: { id: number; nickname: string; mobile: string; avatar: string }
  message_count: number
  admin_unread: number
  last_message_at: string
}

export interface Message {
  id: number
  sender_type: 'user' | 'admin'
  content: string
  msg_type: 'text' | 'image' | 'file'
  file_url?: string
  file_name?: string
  created_at: string
}

export const useAdminCustomerService = () => {
  const sessions = ref<Session[]>([])
  const currentSession = ref<Session | null>(null)
  const messages = ref<Message[]>([])
  const inputText = ref('')
  const loading = ref(false)
  const stats = ref({ waiting: 0, active: 0, today_messages: 0 })

  const hasCurrentSession = computed(() => !!currentSession.value)

  const loadSessions = async (status?: number) => {
    loading.value = true
    try {
      const data: any = await request.get('/admin/customer-service/sessions', { params: { status } })
      sessions.value = data.list || data || []
    } finally {
      loading.value = false
    }
  }

  const selectSession = async (sessionNo: string) => {
    const session = sessions.value.find((s) => s.session_no === sessionNo)
    if (!session) return
    currentSession.value = session
    await loadMessages(sessionNo)
  }

  const loadMessages = async (sessionNo: string) => {
    const data: any = await request.get(`/admin/customer-service/sessions/${sessionNo}/messages`)
    messages.value = data.list || data || []
  }

  const sendMessage = async () => {
    if (!currentSession.value || !inputText.value.trim()) return
    const content = inputText.value.trim()
    try {
      const data: any = await request.post(
        `/admin/customer-service/sessions/${currentSession.value.session_no}/messages`,
        { content, msg_type: 'text' }
      )
      messages.value.push({
        id: data.id || Date.now(),
        sender_type: 'admin',
        content,
        msg_type: 'text',
        created_at: new Date().toISOString(),
      })
      inputText.value = ''
    } catch (e: any) {
      ElMessage.error(e.message || '发送失败')
    }
  }

  const loadStats = async () => {
    const data: any = await request.get('/admin/customer-service/statistics')
    stats.value = data || { waiting: 0, active: 0, today_messages: 0 }
  }

  return {
    sessions, currentSession, messages, inputText, loading, stats, hasCurrentSession,
    loadSessions, selectSession, loadMessages, sendMessage, loadStats,
  }
}
