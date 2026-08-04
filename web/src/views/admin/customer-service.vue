<script setup lang="ts">
import { ref, nextTick, onMounted, onUnmounted, defineComponent, h } from 'vue'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { Picture, ArrowLeft, ChatDotRound, User, Document, Money, Setting, Plus, Search, Refresh, MagicStick } from '@element-plus/icons-vue'
import type { Component } from 'vue'
import { getAdminToken } from '@/utils/auth'

interface Session {
  id: number
  session_no: string
  user_id: number
  admin_id: number
  title: string
  status: number
  message_count: number
  user_unread: number
  admin_unread: number
  last_message_at: string
  created_at: string
  user?: {
    id: number
    nickname: string
    mobile: string
    avatar: string
  }
  admin?: {
    id: number
    name: string
  }
}

interface Message {
  id: number
  session_id: number
  sender_id: number
  sender_type: 'user' | 'admin'
  content: string
  msg_type: 'text' | 'image' | 'file'
  file_url: string
  file_name: string
  created_at: string
}

const sessions = ref<Session[]>([])
const currentSession = ref<Session | null>(null)
const messages = ref<Message[]>([])
const inputText = ref('')
const loading = ref(false)
const messageListRef = ref<HTMLElement | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const stats = ref({
  waiting: 0,
  active: 0,
  closed: 0,
  total: 0,
})

const statusFilter = ref('')
const searchKeyword = ref('')
const lastMessageCount = ref(0)
const showChat = ref(false)
const activeTab = ref('chat')

// 常用话术
interface Phrase {
  id: number
  title: string
  content: string
  category: string
  sort_order: number
  is_public: boolean
  is_auto_reply?: boolean  // 是否为自动回复话术
}
const phrases = ref<Phrase[]>([])
const phraseDialogVisible = ref(false)
const editingPhrase = ref<Phrase | null>(null)
const phraseForm = ref({
  title: '',
  content: '',
  category: 'common',
  is_public: false,
  is_auto_reply: false,
})

// 系统消息
interface SystemMessage {
  id: number
  user_id: number
  title: string
  content: string
  type: string
  is_read: boolean
  created_at: string
  user?: { nickname: string }
}
const systemMessages = ref<SystemMessage[]>([])
const systemMessageDialogVisible = ref(false)
const systemMessageForm = ref({
  user_id: 0,
  title: '',
  content: '',
  type: 'notice',
  target_url: '',
})

// 余额不足记录
interface BalanceLog {
  id: number
  user_id: number
  current_balance: number
  required_amount: number
  action_type: string
  is_notified: boolean
  message: string
  created_at: string
  user?: { nickname: string }
}
const balanceLogs = ref<BalanceLog[]>([])

// 客服配置
const csConfig = ref({
  welcome_message: '',
  auto_welcome: true,
  auto_reply_phrase_id: null as number | null,  // 自动回复话术ID
})

// 标签页
const tabs = [
  { key: 'chat', label: '客服聊天', icon: ChatDotRound },
  { key: 'phrases', label: '常用话术', icon: Document },
  { key: 'messages', label: '系统消息', icon: User },
  { key: 'balance', label: '余额记录', icon: Money },
  { key: 'settings', label: '客服设置', icon: Setting },
]

// 图标组件包装器
const IconWrapper = defineComponent({
  props: {
    icon: { type: Object as () => Component, required: true },
  },
  render() {
    return h(this.icon)
  },
})

const getToken = (): string => getAdminToken() || ''

// 播放提示音
const playNotificationSound = () => {
  const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)()
  const oscillator = audioContext.createOscillator()
  const gainNode = audioContext.createGain()
  oscillator.connect(gainNode)
  gainNode.connect(audioContext.destination)
  oscillator.frequency.value = 800
  oscillator.type = 'sine'
  gainNode.gain.setValueAtTime(0.3, audioContext.currentTime)
  gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5)
  oscillator.start(audioContext.currentTime)
  oscillator.stop(audioContext.currentTime + 0.5)
}

const loadStatistics = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/statistics', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      stats.value = data.data
    }
  } catch (e) { /* 忽略 */ }
}

const loadSessions = async () => {
  loading.value = true
  try {
    let url = '/api/v1/admin/customer-service/sessions?'
    if (statusFilter.value !== '') {
      url += `status=${statusFilter.value}&`
    }
    if (searchKeyword.value) {
      url += `keyword=${encodeURIComponent(searchKeyword.value)}&`
    }
    const res = await safeFetch(url, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      sessions.value = data.data.data || []
    } else {
      ElMessage.error(data.message || '加载失败')
    }
  } catch (e) {
    ElMessage.error('网络错误')
  } finally {
    loading.value = false
  }
}

const loadMessages = async (sessionNo: string, silent = false) => {
  try {
    const res = await safeFetch(`/api/v1/admin/customer-service/sessions/${sessionNo}/messages`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const newMessages = data.data.data || []
      if (!silent && newMessages.length > lastMessageCount.value) {
        const lastMsg = newMessages[newMessages.length - 1]
        if (lastMsg.sender_type === 'user' && lastMsg.id !== messages.value[messages.value.length - 1]?.id) {
          playNotificationSound()
          // 检查是否需要自动回复
          checkAutoReply(lastMsg.content)
        }
      }
      lastMessageCount.value = newMessages.length
      messages.value = newMessages
      await scrollToBottom()
    }
  } catch (e) { /* 忽略 */ }
}

// 检查并执行自动回复
const checkAutoReply = (userMessage: string) => {
  if (!csConfig.value.auto_reply_phrase_id) return
  
  const autoReplyPhrase = phrases.value.find(p => p.id === csConfig.value.auto_reply_phrase_id)
  if (autoReplyPhrase && currentSession.value) {
    // 延迟1秒发送自动回复，模拟人工回复
    setTimeout(() => {
      sendAutoReply(autoReplyPhrase.content)
    }, 1000)
  }
}

// 发送自动回复
const sendAutoReply = async (content: string) => {
  if (!currentSession.value) return
  try {
    const res = await safeFetch(`/api/v1/admin/customer-service/sessions/${currentSession.value.session_no}/messages`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ content, is_auto_reply: true }),
    })
    const data = await res.json()
    if (data.code === 0) {
      messages.value.push(data.data)
      await scrollToBottom()
      loadSessions()
    }
  } catch (e) {
    console.error('自动回复失败:', e)
  }
}

const selectSession = async (session: Session) => {
  currentSession.value = session
  showChat.value = true
  await loadMessages(session.session_no)
}

const backToList = () => {
  showChat.value = false
  currentSession.value = null
}

const sendMessage = async () => {
  const text = inputText.value.trim()
  if (!text || !currentSession.value) return
  try {
    const res = await safeFetch(`/api/v1/admin/customer-service/sessions/${currentSession.value.session_no}/messages`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ content: text }),
    })
    const data = await res.json()
    if (data.code === 0) {
      messages.value.push(data.data)
      inputText.value = ''
      await scrollToBottom()
      loadSessions()
    } else {
      ElMessage.error(data.message || '发送失败')
    }
  } catch (e) {
    ElMessage.error('发送失败')
  }
}

// 快速发送话术
const quickSendPhrase = async (content: string) => {
  if (!currentSession.value) {
    ElMessage.warning('请先选择一个会话')
    return
  }
  inputText.value = content
  await sendMessage()
}

const triggerFileInput = () => {
  fileInputRef.value?.click()
}

const handleFileChange = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file || !currentSession.value) return
  const formData = new FormData()
  formData.append('image', file)
  try {
    const res = await safeFetch(`/api/v1/admin/customer-service/sessions/${currentSession.value.session_no}/upload-image`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
      body: formData,
    })
    const data = await res.json()
    if (data.code === 0) {
      messages.value.push(data.data)
      await scrollToBottom()
      loadSessions()
      ElMessage.success('上传成功')
    } else {
      ElMessage.error(data.message || '上传失败')
    }
  } catch (e: any) {
    ElMessage.error('上传失败: ' + (e.message || '未知错误'))
  } finally {
    target.value = ''
  }
}

const closeSession = async (session: Session) => {
  try {
    const res = await safeFetch(`/api/v1/admin/customer-service/sessions/${session.session_no}/close`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('会话已关闭')
      if (currentSession.value?.id === session.id) {
        currentSession.value = null
        messages.value = []
      }
      loadSessions()
      loadStatistics()
    } else {
      ElMessage.error(data.message || '关闭失败')
    }
  } catch (e) {
    ElMessage.error('关闭失败')
  }
}

const scrollToBottom = async () => {
  await nextTick()
  if (messageListRef.value) {
    messageListRef.value.scrollTop = messageListRef.value.scrollHeight
  }
}

const formatTime = (timestamp: string): string => {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  return `${date.getMonth() + 1}/${date.getDate()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`
}

const getStatusText = (status: number): string => {
  const statusMap: Record<number, string> = { 0: '待接入', 1: '服务中', 2: '已关闭' }
  return statusMap[status] || '未知'
}

const getStatusColor = (status: number): string => {
  const colorMap: Record<number, string> = { 0: '#ff976a', 1: '#07c160', 2: '#969799' }
  return colorMap[status] || '#969799'
}

const previewImage = (url: string) => {
  window.open(url, '_blank')
}

let sessionsInterval: number | null = null
let messagesInterval: number | null = null
let statsInterval: number | null = null

const loadPhrases = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/phrases', {
      headers: { 'Authorization': `Bearer ${getToken()}`, 'Accept': 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) phrases.value = data.data
  } catch (e) { /* 忽略 */ }
}

const openPhraseDialog = (phrase: Phrase | null = null) => {
  if (phrase) {
    editingPhrase.value = phrase
    phraseForm.value = { 
      title: phrase.title, 
      content: phrase.content, 
      category: phrase.category, 
      is_public: phrase.is_public,
      is_auto_reply: phrase.is_auto_reply || false,
    }
  } else {
    editingPhrase.value = null
    phraseForm.value = { title: '', content: '', category: 'common', is_public: false, is_auto_reply: false }
  }
  phraseDialogVisible.value = true
}

const savePhrase = async () => {
  if (!phraseForm.value.title || !phraseForm.value.content) {
    ElMessage.warning('请填写标题和内容')
    return
  }
  try {
    const url = editingPhrase.value ? `/api/v1/admin/customer-service/phrases/${editingPhrase.value.id}` : '/api/v1/admin/customer-service/phrases'
    const method = editingPhrase.value ? 'PUT' : 'POST'
    const res = await safeFetch(url, {
      method,
      headers: { 'Authorization': `Bearer ${getToken()}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(phraseForm.value),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('保存成功')
      phraseDialogVisible.value = false
      loadPhrases()
    } else {
      ElMessage.error(data.message || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  }
}

const deletePhrase = async (id: number) => {
  try {
    const res = await safeFetch(`/api/v1/admin/customer-service/phrases/${id}`, {
      method: 'DELETE',
      headers: { 'Authorization': `Bearer ${getToken()}`, 'Accept': 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('删除成功')
      loadPhrases()
    } else {
      ElMessage.error(data.message || '删除失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '删除失败')
  }
}

// 设置/取消自动回复话术
const toggleAutoReply = async (phrase: Phrase) => {
  try {
    const res = await safeFetch(`/api/v1/admin/customer-service/phrases/${phrase.id}/toggle-auto-reply`, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${getToken()}`, 'Accept': 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(phrase.is_auto_reply ? '已取消自动回复' : '已设置为自动回复')
      loadPhrases()
      // 更新本地配置
      if (phrase.is_auto_reply) {
        csConfig.value.auto_reply_phrase_id = null
      } else {
        csConfig.value.auto_reply_phrase_id = phrase.id
      }
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  }
}

const insertPhrase = (content: string) => {
  inputText.value = content
  activeTab.value = 'chat'
}

const loadSystemMessages = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/system-messages?per_page=20', {
      headers: { 'Authorization': `Bearer ${getToken()}`, 'Accept': 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) systemMessages.value = data.data.data || []
  } catch (e) { /* 忽略 */ }
}

const sendSystemMessage = async () => {
  if (!systemMessageForm.value.title || !systemMessageForm.value.content) {
    ElMessage.warning('请填写标题和内容')
    return
  }
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/system-messages', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${getToken()}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(systemMessageForm.value),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(data.message || '发送成功')
      systemMessageDialogVisible.value = false
      systemMessageForm.value = { user_id: 0, title: '', content: '', type: 'notice', target_url: '' }
      loadSystemMessages()
    } else {
      ElMessage.error(data.message || '发送失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '发送失败')
  }
}

const loadBalanceLogs = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/balance-insufficient-logs?per_page=20', {
      headers: { 'Authorization': `Bearer ${getToken()}`, 'Accept': 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) balanceLogs.value = data.data.data || []
  } catch (e) { /* 忽略 */ }
}

const loadCsConfig = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/configs', {
      headers: { 'Authorization': `Bearer ${getToken()}`, 'Accept': 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) csConfig.value = data.data
  } catch (e) { /* 忽略 */ }
}

const saveCsConfig = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/configs', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${getToken()}`, 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(csConfig.value),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('保存成功')
    } else {
      ElMessage.error(data.message || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  }
}

onMounted(() => {
  loadStatistics()
  loadSessions()
  loadPhrases()
  loadSystemMessages()
  loadBalanceLogs()
  loadCsConfig()
  sessionsInterval = window.setInterval(loadSessions, 15000)
  statsInterval = window.setInterval(loadStatistics, 30000)
  messagesInterval = window.setInterval(() => {
    if (currentSession.value) loadMessages(currentSession.value.session_no, true)
  }, 5000)
})

onUnmounted(() => {
  if (sessionsInterval) clearInterval(sessionsInterval)
  if (messagesInterval) clearInterval(messagesInterval)
  if (statsInterval) clearInterval(statsInterval)
})
</script>

<template>
  <div class="customer-service-admin">
    <!-- 标签导航 -->
    <div class="tab-navigation">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="tab-btn"
        :class="{ active: activeTab === tab.key }"
        @click="activeTab = tab.key"
      >
        <el-icon class="tab-icon"><IconWrapper :icon="tab.icon" /></el-icon>
        <span>{{ tab.label }}</span>
      </button>
    </div>

    <!-- 客服聊天 -->
    <div v-show="activeTab === 'chat'" class="chat-container">
      <!-- 统计卡片 -->
      <div class="stats-bar">
        <div class="stat-card stat-waiting">
          <div class="stat-icon">
            <el-icon><ChatDotRound /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ stats.waiting }}</div>
            <div class="stat-label">待接入</div>
          </div>
        </div>
        <div class="stat-card stat-active">
          <div class="stat-icon">
            <el-icon><User /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ stats.active }}</div>
            <div class="stat-label">服务中</div>
          </div>
        </div>
        <div class="stat-card stat-closed">
          <div class="stat-icon">
            <el-icon><Document /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ stats.closed }}</div>
            <div class="stat-label">已关闭</div>
          </div>
        </div>
        <div class="stat-card stat-total">
          <div class="stat-icon">
            <el-icon><Refresh /></el-icon>
          </div>
          <div class="stat-info">
            <div class="stat-value">{{ stats.total }}</div>
            <div class="stat-label">总计</div>
          </div>
        </div>
      </div>

      <!-- 主内容区 -->
      <div class="main-content">
        <!-- 左侧会话列表 -->
        <div class="session-list" :class="{ 'mobile-hidden': showChat }">
          <div class="list-header">
            <div class="search-box">
              <el-icon class="search-icon"><Search /></el-icon>
              <input
                v-model="searchKeyword"
                placeholder="搜索用户/会话号"
                @keyup.enter="loadSessions"
              />
            </div>
            <div class="filter-box">
              <select v-model="statusFilter" @change="loadSessions">
                <option value="">全部状态</option>
                <option value="0">待接入</option>
                <option value="1">服务中</option>
                <option value="2">已关闭</option>
              </select>
            </div>
          </div>
          <div class="list-content" v-loading="loading">
            <div
              v-for="session in sessions"
              :key="session.id"
              class="session-item"
              :class="{ active: currentSession?.id === session.id, 'has-unread': session.admin_unread > 0 }"
              @click="selectSession(session)"
            >
              <div class="session-avatar" :style="{ background: session.status === 1 ? '#07c160' : session.status === 0 ? '#ff976a' : '#c8c9cc' }">
                {{ session.user?.nickname?.[0] || 'U' }}
              </div>
              <div class="session-info">
                <div class="session-top">
                  <span class="session-name">{{ session.user?.nickname || '未知用户' }}</span>
                  <span class="session-time">{{ formatTime(session.last_message_at) }}</span>
                </div>
                <div class="session-bottom">
                  <span class="session-status" :style="{ color: getStatusColor(session.status) }">
                    {{ getStatusText(session.status) }}
                  </span>
                  <span class="session-mobile">{{ session.user?.mobile }}</span>
                  <span v-if="session.admin_unread > 0" class="unread-badge">{{ session.admin_unread }}</span>
                </div>
              </div>
            </div>
            <div v-if="sessions.length === 0 && !loading" class="empty-state">
              <el-icon class="empty-icon"><ChatDotRound /></el-icon>
              <div class="empty-text">暂无会话</div>
            </div>
          </div>
        </div>

        <!-- 右侧聊天区域 -->
        <div class="chat-area" :class="{ 'mobile-hidden': !showChat }">
          <template v-if="currentSession">
            <!-- 聊天头部 -->
            <div class="chat-header">
              <div class="header-left">
                <button class="back-btn" @click="backToList">
                  <el-icon><ArrowLeft /></el-icon>
                </button>
                <div class="user-avatar">
                  {{ currentSession.user?.nickname?.[0] || 'U' }}
                </div>
                <div class="user-details">
                  <span class="user-name">{{ currentSession.user?.nickname || '未知用户' }}</span>
                  <span class="user-mobile">{{ currentSession.user?.mobile }}</span>
                </div>
              </div>
              <div class="header-right">
                <span class="session-tag">{{ currentSession.session_no }}</span>
                <button
                  v-if="currentSession.status !== 2"
                  class="close-btn"
                  @click="closeSession(currentSession)"
                >
                  关闭会话
                </button>
              </div>
            </div>

            <!-- 消息列表 -->
            <div ref="messageListRef" class="message-list">
              <!-- 用户消息在左侧 -->
              <div
                v-for="msg in messages"
                :key="msg.id"
                class="message-item"
                :class="msg.sender_type === 'user' ? 'msg-left' : 'msg-right'"
              >
                <div class="message-avatar">
                  {{ msg.sender_type === 'user' ? currentSession.user?.nickname?.[0] || 'U' : '客' }}
                </div>
                <div class="message-body">
                  <div v-if="msg.msg_type === 'text'" class="message-bubble">
                    {{ msg.content }}
                  </div>
                  <div v-else-if="msg.msg_type === 'image'" class="message-bubble image-bubble">
                    <img :src="msg.file_url" class="message-image" @click="previewImage(msg.file_url)" />
                  </div>
                  <div class="message-time">{{ formatTime(msg.created_at) }}</div>
                </div>
              </div>
              <div v-if="messages.length === 0" class="no-messages">
                <el-icon><ChatDotRound /></el-icon>
                <div>暂无消息，开始对话吧</div>
              </div>
            </div>

            <!-- 快速话术区域 -->
            <div class="quick-phrases" v-if="phrases.length > 0">
              <div class="quick-phrases-header">
                <el-icon><MagicStick /></el-icon>
                <span>快速话术</span>
              </div>
              <div class="quick-phrases-list">
                <button
                  v-for="phrase in phrases.slice(0, 6)"
                  :key="phrase.id"
                  class="quick-phrase-btn"
                  :title="phrase.content"
                  @click="quickSendPhrase(phrase.content)"
                >
                  {{ phrase.title }}
                </button>
              </div>
            </div>

            <!-- 输入区域 -->
            <div class="input-area" v-if="currentSession.status !== 2">
              <div class="input-wrapper">
                <button class="attach-btn" @click="triggerFileInput" title="发送图片">
                  <el-icon><Picture /></el-icon>
                </button>
                <input
                  v-model="inputText"
                  placeholder="输入回复内容，按 Enter 发送..."
                  class="input-field"
                  @keyup.enter="sendMessage"
                />
                <button class="send-btn" :disabled="!inputText.trim()" @click="sendMessage">
                  发送
                </button>
              </div>
              <input
                ref="fileInputRef"
                type="file"
                accept="image/*"
                class="hidden-input"
                @change="handleFileChange"
              />
            </div>
            <div v-else class="closed-notice">
              <el-icon><Document /></el-icon>
              <span>会话已关闭</span>
            </div>
          </template>
          <div v-else class="no-session">
            <div class="no-session-content">
              <el-icon class="no-session-icon"><ChatDotRound /></el-icon>
              <div class="no-session-title">选择一个会话</div>
              <div class="no-session-subtitle">从左侧列表中选择会话开始回复用户</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 常用话术 -->
    <div v-show="activeTab === 'phrases'" class="tab-content">
      <div class="content-header">
        <div class="header-title">
          <el-icon><Document /></el-icon>
          <h3>常用话术管理</h3>
        </div>
        <button class="add-btn" @click="openPhraseDialog()">
          <el-icon><Plus /></el-icon>
          <span>添加话术</span>
        </button>
      </div>
      <div class="card-grid">
        <div v-for="phrase in phrases" :key="phrase.id" class="phrase-card" :class="{ 'is-auto-reply': phrase.is_auto_reply }">
          <div class="card-header">
            <span class="card-title">{{ phrase.title }}</span>
            <div class="card-badges">
              <span v-if="phrase.is_auto_reply" class="auto-reply-badge">自动回复</span>
              <span class="card-badge" :class="phrase.category">{{ phrase.category === 'greeting' ? '问候语' : phrase.category === 'promotion' ? '推广' : '常见问题' }}</span>
            </div>
          </div>
          <div class="card-content">{{ phrase.content }}</div>
          <div class="card-actions">
            <button class="card-btn primary" @click="insertPhrase(phrase.content)">插入聊天</button>
            <button class="card-btn" :class="{ 'active': phrase.is_auto_reply }" @click="toggleAutoReply(phrase)">
              <el-icon><MagicStick /></el-icon>
              {{ phrase.is_auto_reply ? '取消自动回复' : '设为自动回复' }}
            </button>
            <button class="card-btn" @click="openPhraseDialog(phrase)">编辑</button>
            <button class="card-btn danger" @click="deletePhrase(phrase.id)">删除</button>
          </div>
        </div>
        <div v-if="phrases.length === 0" class="empty-card">
          <el-icon><Document /></el-icon>
          <div>暂无话术，点击添加</div>
        </div>
      </div>
    </div>

    <!-- 系统消息 -->
    <div v-show="activeTab === 'messages'" class="tab-content">
      <div class="content-header">
        <div class="header-title">
          <el-icon><User /></el-icon>
          <h3>系统消息</h3>
        </div>
        <button class="add-btn" @click="systemMessageDialogVisible = true">
          <el-icon><Plus /></el-icon>
          <span>发送消息</span>
        </button>
      </div>
      <div class="card-grid">
        <div v-for="msg in systemMessages" :key="msg.id" class="message-card">
          <div class="card-header">
            <span class="card-title">{{ msg.title }}</span>
            <span class="card-badge" :class="msg.type">{{ msg.type === 'notice' ? '通知' : msg.type === 'activity' ? '活动' : msg.type === 'balance' ? '余额' : '系统' }}</span>
          </div>
          <div class="card-content">{{ msg.content }}</div>
          <div class="card-footer">
            <span>接收人: {{ msg.user_id === 0 ? '全部用户' : (msg.user?.nickname || msg.user_id) }}</span>
            <span>{{ formatTime(msg.created_at) }}</span>
          </div>
        </div>
        <div v-if="systemMessages.length === 0" class="empty-card">
          <el-icon><User /></el-icon>
          <div>暂无系统消息</div>
        </div>
      </div>
    </div>

    <!-- 余额不足记录 -->
    <div v-show="activeTab === 'balance'" class="tab-content">
      <div class="content-header">
        <div class="header-title">
          <el-icon><Money /></el-icon>
          <h3>余额不足记录</h3>
        </div>
      </div>
      <div class="card-grid">
        <div v-for="log in balanceLogs" :key="log.id" class="log-card">
          <div class="card-header">
            <span class="card-title">{{ log.user?.nickname || '用户' + log.user_id }}</span>
            <span class="card-badge">{{ log.action_type === 'analysis' ? '分析' : log.action_type === 'constitution' ? '体质测试' : '问答' }}</span>
          </div>
          <div class="log-details">
            <span class="log-item">余额: <strong>¥{{ log.current_balance }}</strong></span>
            <span class="log-item">所需: <strong>¥{{ log.required_amount }}</strong></span>
            <span class="log-status" :class="log.is_notified ? 'success' : 'warning'">{{ log.is_notified ? '已通知' : '未通知' }}</span>
          </div>
          <div class="card-footer">
            <span>{{ formatTime(log.created_at) }}</span>
          </div>
        </div>
        <div v-if="balanceLogs.length === 0" class="empty-card">
          <el-icon><Money /></el-icon>
          <div>暂无余额不足记录</div>
        </div>
      </div>
    </div>

    <!-- 客服设置 -->
    <div v-show="activeTab === 'settings'" class="tab-content">
      <div class="content-header">
        <div class="header-title">
          <el-icon><Setting /></el-icon>
          <h3>客服设置</h3>
        </div>
      </div>
      <div class="settings-card">
        <div class="setting-item">
          <div class="setting-label">
            <span>自动发送欢迎消息</span>
            <span class="setting-desc">开启后，用户发起会话时将自动发送欢迎语</span>
          </div>
          <label class="switch">
            <input type="checkbox" v-model="csConfig.auto_welcome" />
            <span class="switch-slider"></span>
          </label>
        </div>
        <div class="setting-item vertical">
          <div class="setting-label">
            <span>欢迎消息内容</span>
            <span class="setting-desc">用户进入聊天时自动发送的消息</span>
          </div>
          <textarea v-model="csConfig.welcome_message" rows="4" placeholder="请输入欢迎消息内容..."></textarea>
        </div>
        <div class="setting-item vertical">
          <div class="setting-label">
            <span>自动回复话术</span>
            <span class="setting-desc">设置后，当用户发送消息时将自动回复该话术内容</span>
          </div>
          <select v-model="csConfig.auto_reply_phrase_id">
            <option :value="null">不启用自动回复</option>
            <option v-for="phrase in phrases" :key="phrase.id" :value="phrase.id">
              {{ phrase.title }} - {{ phrase.content.substring(0, 30) }}...
            </option>
          </select>
        </div>
        <div class="setting-actions">
          <button class="save-btn" @click="saveCsConfig">
            <el-icon><Setting /></el-icon>
            <span>保存设置</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 话术编辑弹窗 -->
    <div v-if="phraseDialogVisible" class="dialog-overlay" @click.self="phraseDialogVisible = false">
      <div class="dialog">
        <div class="dialog-header">
          <h3>{{ editingPhrase ? '编辑话术' : '添加话术' }}</h3>
        </div>
        <div class="dialog-body">
          <div class="form-group">
            <label>标题</label>
            <input v-model="phraseForm.title" placeholder="话术标题" />
          </div>
          <div class="form-group">
            <label>内容</label>
            <textarea v-model="phraseForm.content" rows="4" placeholder="话术内容"></textarea>
          </div>
          <div class="form-group">
            <label>分类</label>
            <select v-model="phraseForm.category">
              <option value="greeting">问候语</option>
              <option value="common">常见问题</option>
              <option value="promotion">推广</option>
            </select>
          </div>
          <div class="form-group inline">
            <label class="checkbox-label">
              <input type="checkbox" v-model="phraseForm.is_public" />
              <span>设为公共话术</span>
            </label>
          </div>
          <div class="form-group inline">
            <label class="checkbox-label">
              <input type="checkbox" v-model="phraseForm.is_auto_reply" />
              <span>设为自动回复话术</span>
            </label>
          </div>
        </div>
        <div class="dialog-footer">
          <button class="btn-cancel" @click="phraseDialogVisible = false">取消</button>
          <button class="btn-primary" @click="savePhrase">保存</button>
        </div>
      </div>
    </div>

    <!-- 系统消息发送弹窗 -->
    <div v-if="systemMessageDialogVisible" class="dialog-overlay" @click.self="systemMessageDialogVisible = false">
      <div class="dialog">
        <div class="dialog-header">
          <h3>发送系统消息</h3>
        </div>
        <div class="dialog-body">
          <div class="form-group">
            <label>接收用户ID (0=全部用户)</label>
            <input type="number" v-model.number="systemMessageForm.user_id" placeholder="0" />
          </div>
          <div class="form-group">
            <label>标题</label>
            <input v-model="systemMessageForm.title" placeholder="消息标题" />
          </div>
          <div class="form-group">
            <label>内容</label>
            <textarea v-model="systemMessageForm.content" rows="4" placeholder="消息内容"></textarea>
          </div>
          <div class="form-group">
            <label>类型</label>
            <select v-model="systemMessageForm.type">
              <option value="notice">通知</option>
              <option value="activity">活动</option>
              <option value="system">系统更新</option>
              <option value="balance">余额提醒</option>
            </select>
          </div>
          <div class="form-group">
            <label>跳转链接 (可选)</label>
            <input v-model="systemMessageForm.target_url" placeholder="https://" />
          </div>
        </div>
        <div class="dialog-footer">
          <button class="btn-cancel" @click="systemMessageDialogVisible = false">取消</button>
          <button class="btn-primary" @click="sendSystemMessage">发送</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.customer-service-admin {
  height: calc(100vh - 60px);
  display: flex;
  flex-direction: column;
  background: #f5f7fa;
}

/* 标签导航 */
.tab-navigation {
  display: flex;
  background: #fff;
  border-bottom: 1px solid #e4e7ed;
  padding: 0 24px;
  gap: 4px;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 14px 20px;
  border: none;
  background: transparent;
  font-size: 14px;
  color: #606266;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.3s;
}

.tab-btn:hover {
  color: #409eff;
  background: #f5f7fa;
}

.tab-btn.active {
  color: #409eff;
  border-bottom-color: #409eff;
  font-weight: 500;
}

.tab-icon {
  font-size: 16px;
}

/* 聊天容器 */
.chat-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* 统计栏 */
.stats-bar {
  display: flex;
  gap: 16px;
  padding: 20px 24px;
}

.stat-card {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  border: 1px solid #ebeef5;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.stat-waiting .stat-icon { background: #fff7e6; color: #ff976a; }
.stat-active .stat-icon { background: #f0f9eb; color: #07c160; }
.stat-closed .stat-icon { background: #f4f4f5; color: #909399; }
.stat-total .stat-icon { background: #ecf5ff; color: #409eff; }

.stat-info { flex: 1; }

.stat-value {
  font-size: 28px;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 4px;
}

.stat-waiting .stat-value { color: #ff976a; }
.stat-active .stat-value { color: #07c160; }
.stat-closed .stat-value { color: #909399; }
.stat-total .stat-value { color: #409eff; }

.stat-label {
  font-size: 13px;
  color: #909399;
}

/* 主内容区 */
.main-content {
  flex: 1;
  display: flex;
  margin: 0 24px 24px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

/* 会话列表 */
.session-list {
  width: 320px;
  border-right: 1px solid #ebeef5;
  display: flex;
  flex-direction: column;
  background: #fff;
}

.list-header {
  padding: 16px;
  border-bottom: 1px solid #ebeef5;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.search-box { position: relative; }

.search-box .search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #909399;
  font-size: 14px;
}

.search-box input {
  width: 100%;
  height: 38px;
  border: 1px solid #dcdfe6;
  border-radius: 8px;
  padding: 0 12px 0 36px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}

.search-box input:focus { border-color: #409eff; }

.filter-box select {
  width: 100%;
  height: 38px;
  border: 1px solid #dcdfe6;
  border-radius: 8px;
  padding: 0 12px;
  font-size: 14px;
  outline: none;
  cursor: pointer;
}

.list-content {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
}

.session-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: 4px;
}

.session-item:hover { background: #f5f7fa; }
.session-item.active { background: #ecf5ff; }
.session-item.has-unread { background: #fef0f0; }
.session-item.has-unread.active { background: #ecf5ff; }

.session-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 600;
  flex-shrink: 0;
}

.session-info { flex: 1; min-width: 0; }

.session-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.session-name {
  font-size: 14px;
  font-weight: 500;
  color: #303133;
}

.session-time {
  font-size: 12px;
  color: #c0c4cc;
}

.session-bottom {
  display: flex;
  align-items: center;
  gap: 8px;
}

.session-status {
  font-size: 12px;
  font-weight: 500;
}

.session-mobile {
  font-size: 12px;
  color: #909399;
}

.unread-badge {
  min-width: 20px;
  height: 20px;
  line-height: 20px;
  text-align: center;
  background: #f56c6c;
  color: #fff;
  font-size: 11px;
  border-radius: 10px;
  padding: 0 6px;
  margin-left: auto;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #c0c4cc;
}

.empty-icon { font-size: 48px; margin-bottom: 12px; }
.empty-text { font-size: 14px; }

/* 聊天区域 */
.chat-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #f5f7fa;
}

.chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  background: #fff;
  border-bottom: 1px solid #ebeef5;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.back-btn {
  display: none;
  width: 36px;
  height: 36px;
  border: none;
  background: #f5f7fa;
  color: #606266;
  border-radius: 8px;
  cursor: pointer;
  align-items: center;
  justify-content: center;
  font-size: 18px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #409eff;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 600;
}

.user-details { display: flex; flex-direction: column; }

.user-name {
  font-size: 15px;
  font-weight: 500;
  color: #303133;
}

.user-mobile {
  font-size: 12px;
  color: #909399;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.session-tag {
  font-size: 12px;
  color: #909399;
  background: #f5f7fa;
  padding: 4px 10px;
  border-radius: 4px;
}

.close-btn {
  padding: 8px 16px;
  background: #fff;
  color: #f56c6c;
  border: 1px solid #f56c6c;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
}

.close-btn:hover { background: #fef0f0; }

/* 消息列表 */
.message-list {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.message-item {
  display: flex;
  margin-bottom: 20px;
  gap: 12px;
}

/* 用户消息在左侧 */
.msg-left {
  flex-direction: row;
}

.msg-left .message-avatar {
  background: #07c160;
}

/* 客服消息在右侧 */
.msg-right {
  flex-direction: row-reverse;
}

.msg-right .message-avatar {
  background: #409eff;
}

.message-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 500;
  flex-shrink: 0;
}

.message-body {
  max-width: 60%;
}

.message-bubble {
  padding: 14px 18px;
  border-radius: 16px;
  font-size: 14px;
  line-height: 1.6;
  word-break: break-word;
}

/* 用户消息气泡 - 左侧白色 */
.msg-left .message-bubble {
  background: #fff;
  color: #303133;
  border-top-left-radius: 4px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

/* 客服消息气泡 - 右侧蓝色 */
.msg-right .message-bubble {
  background: #409eff;
  color: #fff;
  border-top-right-radius: 4px;
}

.message-time {
  font-size: 11px;
  color: #c0c4cc;
  margin-top: 6px;
}

.msg-right .message-time {
  text-align: right;
}

.image-bubble {
  padding: 4px;
  overflow: hidden;
}

.message-image {
  max-width: 200px;
  max-height: 200px;
  border-radius: 12px;
  cursor: pointer;
}

.no-messages {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #c0c4cc;
}

.no-messages .el-icon {
  font-size: 48px;
  margin-bottom: 12px;
}

/* 快速话术区域 */
.quick-phrases {
  padding: 12px 24px;
  background: #fff;
  border-top: 1px solid #ebeef5;
}

.quick-phrases-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #909399;
  margin-bottom: 10px;
}

.quick-phrases-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.quick-phrase-btn {
  padding: 6px 14px;
  background: #f5f7fa;
  border: 1px solid #e4e7ed;
  border-radius: 16px;
  font-size: 13px;
  color: #606266;
  cursor: pointer;
  transition: all 0.2s;
}

.quick-phrase-btn:hover {
  background: #ecf5ff;
  border-color: #409eff;
  color: #409eff;
}

/* 输入区域 */
.input-area {
  padding: 16px 24px;
  background: #fff;
  border-top: 1px solid #ebeef5;
}

.input-wrapper {
  display: flex;
  gap: 12px;
  align-items: center;
}

.attach-btn {
  width: 42px;
  height: 42px;
  border: 1px solid #dcdfe6;
  border-radius: 10px;
  background: #fff;
  color: #606266;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  transition: all 0.2s;
}

.attach-btn:hover {
  border-color: #409eff;
  color: #409eff;
}

.input-field {
  flex: 1;
  height: 42px;
  border: 1px solid #dcdfe6;
  border-radius: 10px;
  padding: 0 16px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}

.input-field:focus { border-color: #409eff; }

.send-btn {
  height: 42px;
  padding: 0 24px;
  background: #409eff;
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.send-btn:hover { background: #66b1ff; }
.send-btn:disabled {
  background: #c0c4cc;
  cursor: not-allowed;
}

.hidden-input { display: none; }

.no-session {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.no-session-content {
  text-align: center;
  color: #909399;
}

.no-session-icon {
  font-size: 64px;
  margin-bottom: 16px;
  color: #c0c4cc;
}

.no-session-title {
  font-size: 18px;
  font-weight: 500;
  color: #606266;
  margin-bottom: 8px;
}

.no-session-subtitle {
  font-size: 14px;
  color: #909399;
}

.closed-notice {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 16px;
  color: #f56c6c;
  font-size: 14px;
  background: #fef0f0;
}

/* 标签内容 */
.tab-content {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
}

.content-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-title .el-icon {
  font-size: 20px;
  color: #409eff;
}

.header-title h3 {
  font-size: 18px;
  font-weight: 600;
  color: #303133;
  margin: 0;
}

.add-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  background: #409eff;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.add-btn:hover { background: #66b1ff; }

/* 卡片网格 */
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.phrase-card,
.message-card,
.log-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #ebeef5;
  transition: all 0.2s;
}

.phrase-card:hover,
.message-card:hover,
.log-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.phrase-card.is-auto-reply {
  border-color: #409eff;
  background: #f5faff;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.card-badges {
  display: flex;
  gap: 6px;
  align-items: center;
}

.auto-reply-badge {
  font-size: 11px;
  padding: 2px 8px;
  background: #409eff;
  color: #fff;
  border-radius: 10px;
}

.card-title {
  font-size: 15px;
  font-weight: 500;
  color: #303133;
}

.card-badge {
  font-size: 12px;
  padding: 3px 10px;
  border-radius: 12px;
  font-weight: 500;
}

.card-badge.greeting,
.card-badge.notice { background: #ecf5ff; color: #409eff; }
.card-badge.common,
.card-badge.activity { background: #f0f9eb; color: #67c23a; }
.card-badge.promotion,
.card-badge.balance { background: #fdf6ec; color: #e6a23c; }
.card-badge.system { background: #f4f4f5; color: #909399; }

.card-content {
  font-size: 14px;
  color: #606266;
  line-height: 1.6;
  margin-bottom: 16px;
  white-space: pre-wrap;
}

.card-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.card-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 14px;
  border: 1px solid #dcdfe6;
  background: #fff;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
  color: #606266;
  transition: all 0.2s;
}

.card-btn:hover {
  border-color: #409eff;
  color: #409eff;
}

.card-btn.primary {
  background: #409eff;
  color: #fff;
  border-color: #409eff;
}

.card-btn.primary:hover { background: #66b1ff; }

.card-btn.active {
  background: #ecf5ff;
  border-color: #409eff;
  color: #409eff;
}

.card-btn.danger:hover {
  border-color: #f56c6c;
  color: #f56c6c;
}

.card-footer {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #909399;
  padding-top: 12px;
  border-top: 1px solid #f5f7fa;
}

.log-details {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 12px;
}

.log-item {
  font-size: 13px;
  color: #606266;
}

.log-item strong { color: #303133; }

.log-status {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  margin-left: auto;
}

.log-status.success { background: #f0f9eb; color: #67c23a; }
.log-status.warning { background: #fdf6ec; color: #e6a23c; }

.empty-card {
  grid-column: 1 / -1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px;
  background: #fff;
  border-radius: 12px;
  border: 1px dashed #dcdfe6;
  color: #909399;
}

.empty-card .el-icon {
  font-size: 48px;
  margin-bottom: 12px;
  color: #c0c4cc;
}

/* 设置卡片 */
.settings-card {
  max-width: 600px;
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #ebeef5;
}

.setting-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 0;
  border-bottom: 1px solid #f5f7fa;
}

.setting-item:last-child { border-bottom: none; }

.setting-item.vertical {
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
}

.setting-label {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.setting-label span:first-child {
  font-size: 15px;
  font-weight: 500;
  color: #303133;
}

.setting-desc {
  font-size: 13px;
  color: #909399;
}

.setting-item select,
.setting-item textarea {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #dcdfe6;
  border-radius: 8px;
  font-size: 14px;
  resize: vertical;
  outline: none;
}

.setting-item select:focus,
.setting-item textarea:focus { border-color: #409eff; }

.setting-actions { margin-top: 24px; }

.save-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 12px 24px;
  background: #409eff;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

.save-btn:hover { background: #66b1ff; }

/* 开关 */
.switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 26px;
}

.switch input { opacity: 0; width: 0; height: 0; }

.switch-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #dcdfe6;
  transition: 0.3s;
  border-radius: 26px;
}

.switch-slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
}

.switch input:checked + .switch-slider { background-color: #409eff; }
.switch input:checked + .switch-slider:before { transform: translateX(22px); }

/* 弹窗 */
.dialog-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.dialog {
  background: #fff;
  border-radius: 16px;
  width: 90%;
  max-width: 500px;
  max-height: 85vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.dialog-header {
  padding: 20px 24px;
  border-bottom: 1px solid #ebeef5;
}

.dialog-header h3 {
  margin: 0;
  font-size: 18px;
  color: #303133;
}

.dialog-body {
  padding: 24px;
  overflow-y: auto;
}

.dialog-footer {
  padding: 16px 24px;
  border-top: 1px solid #ebeef5;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn-cancel {
  padding: 10px 20px;
  border: 1px solid #dcdfe6;
  background: #fff;
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
  color: #606266;
}

.btn-cancel:hover {
  border-color: #409eff;
  color: #409eff;
}

.btn-primary {
  padding: 10px 20px;
  background: #409eff;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
}

.btn-primary:hover { background: #66b1ff; }

.form-group { margin-bottom: 16px; }
.form-group.inline { margin-bottom: 0; }

.form-group label {
  display: block;
  font-size: 14px;
  color: #606266;
  margin-bottom: 8px;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #dcdfe6;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus { border-color: #409eff; }

.checkbox-label {
  display: flex !important;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] { width: auto; }

/* 手机端适配 */
@media (max-width: 768px) {
  .tab-navigation {
    overflow-x: auto;
    padding: 0 12px;
  }
  
  .tab-btn {
    padding: 12px 16px;
    font-size: 13px;
    white-space: nowrap;
  }
  
  .stats-bar {
    flex-wrap: wrap;
    padding: 12px;
    gap: 8px;
  }
  
  .stat-card {
    min-width: calc(50% - 4px);
    padding: 14px;
  }
  
  .stat-value { font-size: 22px; }
  
  .main-content {
    margin: 0 12px 12px;
    flex-direction: column;
  }
  
  .session-list {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid #ebeef5;
    max-height: 300px;
  }
  
  .chat-area { min-height: 400px; }
  .mobile-hidden { display: none !important; }
  .back-btn { display: flex; }
  .session-no { display: none; }
  .card-grid { grid-template-columns: 1fr; }
  .tab-content { padding: 16px; }
}
</style>
