/**
 * 客服会话状态管理
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import request from '@/api/request'

export interface ChatMessage {
  id?: number
  content: string
  type: 'text' | 'image' | 'system'
  from: 'user' | 'admin' | 'system'
  created_at: string
}

export const useChatStore = defineStore('chat', () => {
  const currentSession = ref<string | null>(null)
  const messages = ref<ChatMessage[]>([])
  const unreadCount = ref(0)

  const loadSession = async (sessionNo: string) => {
    const data: any = await request.get('/customer-service/session')
    currentSession.value = sessionNo
    messages.value = data.messages || []
    return data
  }

  const sendMessage = async (content: string, type: 'text' | 'image' = 'text') => {
    if (!currentSession.value) throw new Error('未创建会话')
    const data: any = await request.post(`/customer-service/sessions/${currentSession.value}/messages`, {
      content, type,
    })
    messages.value.push({
      content, type, from: 'user',
      created_at: new Date().toISOString(),
      id: data.id,
    })
    return data
  }

  const clearUnread = () => { unreadCount.value = 0 }

  return { currentSession, messages, unreadCount, loadSession, sendMessage, clearUnread }
})