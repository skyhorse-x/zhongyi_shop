<script setup lang="ts">
import { ref, nextTick, onMounted, onUnmounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { Picture, ArrowLeft } from '@element-plus/icons-vue'

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
const audioRef = ref<HTMLAudioElement | null>(null)
const showChat = ref(false) // 手机端显示聊天界面
const activeTab = ref('chat') // chat | phrases | messages | balance | settings

// 常用话术
interface Phrase {
  id: number
  title: string
  content: string
  category: string
  sort_order: number
  is_public: boolean
}
const phrases = ref<Phrase[]>([])
const phraseDialogVisible = ref(false)
const editingPhrase = ref<Phrase | null>(null)
const phraseForm = ref({
  title: '',
  content: '',
  category: 'common',
  is_public: false,
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
})
const configDialogVisible = ref(false)

// 标签页
const tabs = [
  { key: 'chat', label: '客服聊天' },
  { key: 'phrases', label: '常用话术' },
  { key: 'messages', label: '系统消息' },
  { key: 'balance', label: '余额记录' },
  { key: 'settings', label: '客服设置' },
]

// 获取认证token
import { getAdminToken } from '@/utils/auth'

const getAuthToken = (): string => getAdminToken() || ''

// 播放提示音
const playNotificationSound = () => {
  // 创建简单的提示音
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

// 获取统计数据
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
  } catch (e) {
    // 忽略错误
  }
}

// 获取会话列表
const loadSessions = async () => {
  try {
    let url = '/api/v1/admin/customer-service/sessions?'
    if (statusFilter.value !== '') {
      url += `status=${statusFilter.value}&`
    }
    if (searchKeyword.value) {
      url += `keyword=${encodeURIComponent(searchKeyword.value)}&`
    }
    const token = getToken()
    console.log('Loading sessions, token:', token ? 'exists' : 'missing')
    const res = await safeFetch(url, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    console.log('Sessions response:', data)
    if (data.code === 0) {
      sessions.value = data.data.data || []
      console.log('Sessions loaded:', sessions.value.length)
    } else {
      console.error('Failed to load sessions:', data.message)
      ElMessage.error(data.message || '加载失败')
    }
  } catch (e) {
    console.error('Error loading sessions:', e)
    ElMessage.error('网络错误')
  }
}

// 获取消息列表
const loadMessages = async (sessionNo: string, silent = false) => {
  try {
    const res = await safeFetch(`/api/v1/admin/customer-service/sessions/${sessionNo}/messages`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    console.log('Messages response for', sessionNo, ':', data)
    if (data.code === 0) {
      const newMessages = data.data.data || []
      // 检查是否有新消息（来自用户）
      if (!silent && newMessages.length > lastMessageCount.value) {
        const lastMsg = newMessages[newMessages.length - 1]
        if (lastMsg.sender_type === 'user' && lastMsg.id !== messages.value[messages.value.length - 1]?.id) {
          playNotificationSound()
        }
      }
      lastMessageCount.value = newMessages.length
      messages.value = newMessages
      console.log('Messages loaded:', messages.value.length)
      await scrollToBottom()
    } else {
      console.error('Failed to load messages:', data.message)
    }
  } catch (e) {
    console.error('Error loading messages:', e)
  }
}

// 选择会话
const selectSession = async (session: Session) => {
  currentSession.value = session
  showChat.value = true
  await loadMessages(session.session_no)
}

// 返回会话列表（手机端）
const backToList = () => {
  showChat.value = false
  currentSession.value = null
}

// 发送消息
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
      // 更新会话列表
      loadSessions()
    } else {
      ElMessage.error(data.message || '发送失败')
    }
  } catch (e) {
    ElMessage.error('发送失败')
  }
}

// 触发文件选择
const triggerFileInput = () => {
  fileInputRef.value?.click()
}

// 上传图片
const handleFileChange = async (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file || !currentSession.value) return

  console.log('Uploading file:', file.name, 'size:', file.size, 'type:', file.type)

  const formData = new FormData()
  formData.append('image', file)

  try {
    const token = getToken()
    console.log('Upload token:', token ? 'exists' : 'missing')
    
    const res = await safeFetch(`/api/v1/admin/customer-service/sessions/${currentSession.value.session_no}/upload-image`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
      body: formData,
    })
    console.log('Upload response status:', res.status)
    const data = await res.json()
    console.log('Upload response:', data)
    if (data.code === 0) {
      messages.value.push(data.data)
      await scrollToBottom()
      loadSessions()
      ElMessage.success('上传成功')
    } else {
      ElMessage.error(data.message || '上传失败')
    }
  } catch (e: any) {
    console.error('Upload error:', e)
    ElMessage.error('上传失败: ' + (e.message || '未知错误'))
  } finally {
    target.value = ''
  }
}

// 关闭会话
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

// 滚动到底部
const scrollToBottom = async () => {
  await nextTick()
  if (messageListRef.value) {
    messageListRef.value.scrollTop = messageListRef.value.scrollHeight
  }
}

// 格式化时间
const formatTime = (timestamp: string): string => {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  return `${date.getMonth() + 1}/${date.getDate()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`
}

// 获取状态文本
const getStatusText = (status: number): string => {
  const statusMap: Record<number, string> = {
    0: '待接入',
    1: '服务中',
    2: '已关闭',
  }
  return statusMap[status] || '未知'
}

// 获取状态颜色
const getStatusColor = (status: number): string => {
  const colorMap: Record<number, string> = {
    0: '#ff976a',
    1: '#07c160',
    2: '#969799',
  }
  return colorMap[status] || '#969799'
}

// 预览图片
const previewImage = (url: string) => {
  window.open(url, '_blank')
}

// 定时器引用
let sessionsInterval: number | null = null
let messagesInterval: number | null = null
let statsInterval: number | null = null

// ===== 常用话术 =====
const loadPhrases = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/phrases', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      phrases.value = data.data
    }
  } catch (e) {
    // 忽略
  }
}

const openPhraseDialog = (phrase: Phrase | null = null) => {
  if (phrase) {
    editingPhrase.value = phrase
    phraseForm.value = {
      title: phrase.title,
      content: phrase.content,
      category: phrase.category,
      is_public: phrase.is_public,
    }
  } else {
    editingPhrase.value = null
    phraseForm.value = { title: '', content: '', category: 'common', is_public: false }
  }
  phraseDialogVisible.value = true
}

const savePhrase = async () => {
  if (!phraseForm.value.title || !phraseForm.value.content) {
    ElMessage.warning('请填写标题和内容')
    return
  }
  try {
    const url = editingPhrase.value
      ? `/api/v1/admin/customer-service/phrases/${editingPhrase.value.id}`
      : '/api/v1/admin/customer-service/phrases'
    const method = editingPhrase.value ? 'PUT' : 'POST'
    const res = await safeFetch(url, {
      method,
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
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
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
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

// 插入话术到输入框
const insertPhrase = (content: string) => {
  inputText.value = content
  activeTab.value = 'chat'
}

// ===== 系统消息 =====
const loadSystemMessages = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/system-messages?per_page=20', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      systemMessages.value = data.data.data || []
    }
  } catch (e) {
    // 忽略
  }
}

const sendSystemMessage = async () => {
  if (!systemMessageForm.value.title || !systemMessageForm.value.content) {
    ElMessage.warning('请填写标题和内容')
    return
  }
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/system-messages', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
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

// ===== 余额不足记录 =====
const loadBalanceLogs = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/balance-insufficient-logs?per_page=20', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      balanceLogs.value = data.data.data || []
    }
  } catch (e) {
    // 忽略
  }
}

// ===== 客服配置 =====
const loadCsConfig = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/configs', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      csConfig.value = data.data
    }
  } catch (e) {
    // 忽略
  }
}

const saveCsConfig = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/configs', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(csConfig.value),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('保存成功')
      configDialogVisible.value = false
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
  
  // 每15秒刷新会话列表
  sessionsInterval = window.setInterval(() => {
    loadSessions()
  }, 15000)
  
  // 每30秒刷新统计数据
  statsInterval = window.setInterval(() => {
    loadStatistics()
  }, 30000)
  
  // 每5秒刷新消息（当选中会话时）
  messagesInterval = window.setInterval(() => {
    if (currentSession.value) {
      loadMessages(currentSession.value.session_no, true)
    }
  }, 5000)
})

onUnmounted(() => {
  // 清除定时器
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
        {{ tab.label }}
      </button>
    </div>

    <!-- 客服聊天 -->
    <div v-show="activeTab === 'chat'">
    <!-- 统计卡片 -->
    <div class="stats-bar">
      <div class="stat-item">
        <div class="stat-value" style="color: #ff976a">{{ stats.waiting }}</div>
        <div class="stat-label">待接入</div>
      </div>
      <div class="stat-item">
        <div class="stat-value" style="color: #07c160">{{ stats.active }}</div>
        <div class="stat-label">服务中</div>
      </div>
      <div class="stat-item">
        <div class="stat-value" style="color: #969799">{{ stats.closed }}</div>
        <div class="stat-label">已关闭</div>
      </div>
      <div class="stat-item">
        <div class="stat-value" style="color: #1989fa">{{ stats.total }}</div>
        <div class="stat-label">总计</div>
      </div>
    </div>

    <!-- 主内容区 -->
    <div class="main-content">
      <!-- 左侧会话列表 -->
      <div class="session-list" :class="{ 'mobile-hidden': showChat }">
        <div class="list-header">
          <div class="search-bar">
            <input
              v-model="searchKeyword"
              placeholder="搜索用户/会话号"
              @keyup.enter="loadSessions"
            />
          </div>
          <div class="filter-bar">
            <select v-model="statusFilter" @change="loadSessions">
              <option value="">全部</option>
              <option value="0">待接入</option>
              <option value="1">服务中</option>
              <option value="2">已关闭</option>
            </select>
          </div>
        </div>
        <div class="list-content">
          <div
            v-for="session in sessions"
            :key="session.id"
            class="session-item"
            :class="{ active: currentSession?.id === session.id }"
            @click="selectSession(session)"
          >
            <div class="session-avatar">
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
                <span v-if="session.admin_unread > 0" class="unread-badge">{{ session.admin_unread }}</span>
              </div>
            </div>
          </div>
          <div v-if="sessions.length === 0" class="empty-state">暂无会话</div>
        </div>
      </div>

      <!-- 右侧聊天区域 -->
      <div class="chat-area" :class="{ 'mobile-hidden': !showChat }">
        <template v-if="currentSession">
          <!-- 聊天头部 -->
          <div class="chat-header">
            <div class="user-info">
              <button class="back-btn" @click="backToList">
                <el-icon><ArrowLeft /></el-icon>
              </button>
              <span class="user-name">{{ currentSession.user?.nickname || '未知用户' }}</span>
              <span class="user-mobile">{{ currentSession.user?.mobile }}</span>
            </div>
            <div class="header-actions">
              <span class="session-no">{{ currentSession.session_no }}</span>
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
            <div
              v-for="msg in messages"
              :key="msg.id"
              class="message-item"
              :class="msg.sender_type"
            >
              <div class="avatar">
                {{ msg.sender_type === 'user' ? currentSession.user?.nickname?.[0] || 'U' : '客服' }}
              </div>
              <div class="message-content">
                <div v-if="msg.msg_type === 'text'" class="message-bubble">
                  {{ msg.content }}
                </div>
                <div v-else-if="msg.msg_type === 'image'" class="message-bubble image-bubble">
                  <img :src="msg.file_url" class="message-image" @click="previewImage(msg.file_url)" />
                </div>
                <div class="message-time">{{ formatTime(msg.created_at) }}</div>
              </div>
            </div>
          </div>

          <!-- 输入区域 -->
          <div class="input-area" v-if="currentSession.status !== 2">
            <div class="input-wrapper">
              <input
                v-model="inputText"
                placeholder="输入回复内容..."
                class="input-field"
                @keyup.enter="sendMessage"
              />
              <button class="action-btn" @click="triggerFileInput" title="发送图片">
                <el-icon><Picture /></el-icon>
              </button>
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
          <div v-else class="closed-tip">会话已关闭</div>
        </template>
        <div v-else class="no-session">
          <div class="no-session-icon">💬</div>
          <div class="no-session-text">选择一个会话开始回复</div>
        </div>
      </div>
    </div>
    </div><!-- End chat tab -->

    <!-- 常用话术 -->
    <div v-show="activeTab === 'phrases'" class="tab-content">
      <div class="content-header">
        <h3>常用话术管理</h3>
        <button class="add-btn" @click="openPhraseDialog()">+ 添加话术</button>
      </div>
      <div class="phrases-list">
        <div v-for="phrase in phrases" :key="phrase.id" class="phrase-card">
          <div class="phrase-header">
            <span class="phrase-title">{{ phrase.title }}</span>
            <span class="phrase-category">{{ phrase.category === 'greeting' ? '问候语' : phrase.category === 'promotion' ? '推广' : '常见问题' }}</span>
          </div>
          <div class="phrase-content">{{ phrase.content }}</div>
          <div class="phrase-actions">
            <button class="action-btn insert" @click="insertPhrase(phrase.content)">插入聊天</button>
            <button class="action-btn edit" @click="openPhraseDialog(phrase)">编辑</button>
            <button class="action-btn delete" @click="deletePhrase(phrase.id)">删除</button>
          </div>
        </div>
        <div v-if="phrases.length === 0" class="empty-tip">暂无话术，点击添加</div>
      </div>
    </div>

    <!-- 系统消息 -->
    <div v-show="activeTab === 'messages'" class="tab-content">
      <div class="content-header">
        <h3>系统消息</h3>
        <button class="add-btn" @click="systemMessageDialogVisible = true">+ 发送消息</button>
      </div>
      <div class="messages-list">
        <div v-for="msg in systemMessages" :key="msg.id" class="message-card">
          <div class="message-card-header">
            <span class="message-title">{{ msg.title }}</span>
            <span class="message-type">{{ msg.type === 'notice' ? '通知' : msg.type === 'activity' ? '活动' : msg.type === 'balance' ? '余额' : '系统' }}</span>
          </div>
          <div class="message-card-content">{{ msg.content }}</div>
          <div class="message-card-footer">
            <span>接收人: {{ msg.user_id === 0 ? '全部用户' : (msg.user?.nickname || msg.user_id) }}</span>
            <span>{{ formatTime(msg.created_at) }}</span>
          </div>
        </div>
        <div v-if="systemMessages.length === 0" class="empty-tip">暂无系统消息</div>
      </div>
    </div>

    <!-- 余额不足记录 -->
    <div v-show="activeTab === 'balance'" class="tab-content">
      <div class="content-header">
        <h3>余额不足记录</h3>
      </div>
      <div class="balance-logs">
        <div v-for="log in balanceLogs" :key="log.id" class="log-card">
          <div class="log-header">
            <span class="log-user">{{ log.user?.nickname || '用户' + log.user_id }}</span>
            <span class="log-type">{{ log.action_type === 'analysis' ? '分析' : log.action_type === 'constitution' ? '体质测试' : '问答' }}</span>
          </div>
          <div class="log-body">
            <span>余额: ¥{{ log.current_balance }}</span>
            <span>所需: ¥{{ log.required_amount }}</span>
            <span :class="['log-status', log.is_notified ? 'notified' : 'pending']">{{ log.is_notified ? '已通知' : '未通知' }}</span>
          </div>
          <div class="log-time">{{ formatTime(log.created_at) }}</div>
        </div>
        <div v-if="balanceLogs.length === 0" class="empty-tip">暂无余额不足记录</div>
      </div>
    </div>

    <!-- 客服设置 -->
    <div v-show="activeTab === 'settings'" class="tab-content">
      <div class="content-header">
        <h3>客服设置</h3>
      </div>
      <div class="settings-form">
        <div class="form-item">
          <label>自动发送欢迎消息</label>
          <input type="checkbox" v-model="csConfig.auto_welcome" />
        </div>
        <div class="form-item">
          <label>欢迎消息内容</label>
          <textarea v-model="csConfig.welcome_message" rows="4" placeholder="请输入欢迎消息内容..."></textarea>
        </div>
        <button class="save-btn" @click="saveCsConfig">保存设置</button>
      </div>
    </div>

    <!-- 话术编辑弹窗 -->
    <div v-if="phraseDialogVisible" class="dialog-overlay" @click.self="phraseDialogVisible = false">
      <div class="dialog">
        <h3>{{ editingPhrase ? '编辑话术' : '添加话术' }}</h3>
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
        <div class="form-group">
          <label>
            <input type="checkbox" v-model="phraseForm.is_public" />
            设为公共话术
          </label>
        </div>
        <div class="dialog-actions">
          <button @click="phraseDialogVisible = false">取消</button>
          <button class="primary" @click="savePhrase">保存</button>
        </div>
      </div>
    </div>

    <!-- 系统消息发送弹窗 -->
    <div v-if="systemMessageDialogVisible" class="dialog-overlay" @click.self="systemMessageDialogVisible = false">
      <div class="dialog">
        <h3>发送系统消息</h3>
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
        <div class="dialog-actions">
          <button @click="systemMessageDialogVisible = false">取消</button>
          <button class="primary" @click="sendSystemMessage">发送</button>
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
}

/* 统计栏 */
.stats-bar {
  display: flex;
  gap: 16px;
  padding: 16px 20px;
  background: #fff;
  border-bottom: 1px solid #ebedf0;
}

.stat-item {
  flex: 1;
  text-align: center;
  padding: 12px;
  background: #f7f8fa;
  border-radius: 8px;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 12px;
  color: #969799;
}

/* 主内容区 */
.main-content {
  flex: 1;
  display: flex;
  overflow: hidden;
}

/* 会话列表 */
.session-list {
  width: 300px;
  border-right: 1px solid #ebedf0;
  display: flex;
  flex-direction: column;
  background: #fff;
}

.list-header {
  padding: 12px;
  border-bottom: 1px solid #ebedf0;
}

.search-bar input {
  width: 100%;
  height: 36px;
  border: 1px solid #ebedf0;
  border-radius: 4px;
  padding: 0 12px;
  font-size: 14px;
  margin-bottom: 8px;
  outline: none;
}

.search-bar input:focus {
  border-color: #1989fa;
}

.filter-bar select {
  width: 100%;
  height: 36px;
  border: 1px solid #ebedf0;
  border-radius: 4px;
  padding: 0 12px;
  font-size: 14px;
  outline: none;
}

.list-content {
  flex: 1;
  overflow-y: auto;
}

.session-item {
  display: flex;
  padding: 12px;
  cursor: pointer;
  transition: background 0.15s;
  border-bottom: 1px solid #f5f5f5;
}

.session-item:hover {
  background: #f7f8fa;
}

.session-item.active {
  background: #ecf5ff;
}

.session-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #1989fa;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: bold;
  margin-right: 12px;
  flex-shrink: 0;
}

.session-info {
  flex: 1;
  min-width: 0;
}

.session-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.session-name {
  font-size: 14px;
  font-weight: 500;
  color: #323233;
}

.session-time {
  font-size: 12px;
  color: #c8c9cc;
}

.session-bottom {
  display: flex;
  align-items: center;
  gap: 8px;
}

.session-status {
  font-size: 12px;
}

.unread-badge {
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  text-align: center;
  background: #ee0a24;
  color: #fff;
  font-size: 10px;
  border-radius: 9px;
  padding: 0 5px;
}

.empty-state {
  padding: 40px 20px;
  text-align: center;
  color: #c8c9cc;
  font-size: 14px;
}

/* 聊天区域 */
.chat-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #f7f8fa;
}

.chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background: #fff;
  border-bottom: 1px solid #ebedf0;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.back-btn {
  display: none;
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  color: #323233;
  cursor: pointer;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  margin-right: 8px;
}

.user-name {
  font-size: 16px;
  font-weight: 500;
  color: #323233;
}

.user-mobile {
  font-size: 12px;
  color: #969799;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.session-no {
  font-size: 12px;
  color: #969799;
}

.close-btn {
  padding: 6px 16px;
  background: #fff;
  color: #ee0a24;
  border: 1px solid #ee0a24;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;
}

.close-btn:hover {
  background: #fef0f0;
}

/* 消息列表 */
.message-list {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
}

.message-item {
  display: flex;
  margin-bottom: 16px;
  gap: 8px;
}

.message-item.admin {
  flex-direction: row-reverse;
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #07c160;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  flex-shrink: 0;
}

.message-item.admin .avatar {
  background: #1989fa;
}

.message-content {
  max-width: 60%;
}

.message-bubble {
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 14px;
  line-height: 1.6;
  word-break: break-all;
}

.admin .message-bubble {
  background: #1989fa;
  color: #fff;
  border-top-right-radius: 4px;
}

.user .message-bubble {
  background: #fff;
  color: #323233;
  border-top-left-radius: 4px;
}

.message-time {
  font-size: 11px;
  color: #c8c9cc;
  margin-top: 4px;
}

.admin .message-time {
  text-align: right;
}

/* 图片消息 */
.image-bubble {
  padding: 4px;
  overflow: hidden;
}

.message-image {
  max-width: 200px;
  max-height: 200px;
  border-radius: 8px;
  cursor: pointer;
}

/* 输入区域 */
.input-area {
  padding: 16px 20px;
  background: #fff;
  border-top: 1px solid #ebedf0;
}

.input-wrapper {
  display: flex;
  gap: 8px;
  align-items: center;
}

.input-field {
  flex: 1;
  height: 44px;
  border: 1px solid #ebedf0;
  border-radius: 4px;
  padding: 0 16px;
  font-size: 14px;
  outline: none;
}

.input-field:focus {
  border-color: #1989fa;
}

.action-btn {
  width: 44px;
  height: 44px;
  border: 1px solid #ebedf0;
  border-radius: 4px;
  background: #fff;
  color: #666;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}

.action-btn:hover {
  background: #f5f5f5;
}

.send-btn {
  height: 44px;
  padding: 0 24px;
  background: #1989fa;
  color: #fff;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
}

.send-btn:hover {
  background: #0765c0;
}

.send-btn:disabled {
  background: #c8c9cc;
  cursor: not-allowed;
}

.hidden-input {
  display: none;
}

/* 空状态 */
.no-session {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.no-session-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.no-session-text {
  font-size: 14px;
  color: #969799;
}

.closed-tip {
  padding: 16px;
  text-align: center;
  color: #ee0a24;
  font-size: 14px;
  background: #fff;
  border-top: 1px solid #ebedf0;
}

/* 手机端适配 */
@media (max-width: 768px) {
  .stats-bar {
    padding: 10px 12px;
    gap: 8px;
  }

  .stat-item {
    padding: 8px 4px;
  }

  .stat-value {
    font-size: 18px;
  }

  .stat-label {
    font-size: 11px;
  }

  .main-content {
    position: relative;
  }

  .session-list {
    width: 100%;
    border-right: none;
  }

  .chat-area {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 10;
    background: #f7f8fa;
  }

  .mobile-hidden {
    display: none !important;
  }

  .back-btn {
    display: flex;
  }

  .chat-header {
    padding: 12px 16px;
  }

  .user-name {
    font-size: 14px;
  }

  .user-mobile {
    display: none;
  }

  .session-no {
    display: none;
  }

  .close-btn {
    padding: 4px 12px;
    font-size: 11px;
  }

  .message-list {
    padding: 12px 16px;
  }

  .message-content {
    max-width: 75%;
  }

  .message-bubble {
    padding: 10px 12px;
    font-size: 13px;
  }

  .input-area {
    padding: 10px 12px;
  }

  .input-field {
    height: 40px;
    font-size: 13px;
  }

  .action-btn {
    width: 40px;
    height: 40px;
  }

  .send-btn {
    height: 40px;
    padding: 0 16px;
    font-size: 13px;
  }

  .list-header {
    padding: 10px 12px;
  }

  .search-bar input,
  .filter-bar select {
    height: 34px;
    font-size: 13px;
  }

  .session-item {
    padding: 10px 12px;
  }

  .session-avatar {
    width: 36px;
    height: 36px;
    font-size: 14px;
  }

  .session-name {
    font-size: 13px;
  }

  .session-time {
    font-size: 11px;
  }
}

/* 标签导航 */
.tab-navigation {
  display: flex;
  background: #fff;
  border-bottom: 1px solid #ebedf0;
  padding: 0 16px;
}

.tab-btn {
  padding: 12px 20px;
  border: none;
  background: transparent;
  font-size: 14px;
  color: #646566;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.3s;
}

.tab-btn:hover {
  color: #1989fa;
}

.tab-btn.active {
  color: #1989fa;
  border-bottom-color: #1989fa;
  font-weight: 500;
}

/* 标签内容 */
.tab-content {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
}

.content-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.content-header h3 {
  font-size: 16px;
  font-weight: 500;
  color: #323233;
  margin: 0;
}

.add-btn {
  padding: 8px 16px;
  background: #1989fa;
  color: #fff;
  border: none;
  border-radius: 4px;
  font-size: 13px;
  cursor: pointer;
}

.add-btn:hover {
  background: #0776e8;
}

/* 话术卡片 */
.phrases-list,
.messages-list,
.balance-logs {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.phrase-card,
.message-card,
.log-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  border: 1px solid #ebedf0;
}

.phrase-header,
.message-card-header,
.log-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.phrase-title,
.message-title,
.log-user {
  font-weight: 500;
  color: #323233;
}

.phrase-category,
.message-type,
.log-type {
  font-size: 12px;
  padding: 2px 8px;
  background: #e8f4ff;
  color: #1989fa;
  border-radius: 4px;
}

.phrase-content,
.message-card-content {
  font-size: 14px;
  color: #646566;
  white-space: pre-wrap;
  margin-bottom: 12px;
}

.phrase-actions {
  display: flex;
  gap: 8px;
}

.action-btn {
  padding: 6px 12px;
  border: 1px solid #ebedf0;
  background: #fff;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;
  color: #646566;
}

.action-btn:hover {
  border-color: #1989fa;
  color: #1989fa;
}

.action-btn.delete:hover {
  border-color: #ee0a24;
  color: #ee0a24;
}

.message-card-footer,
.log-body,
.log-time {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #969799;
  margin-top: 8px;
}

.log-body {
  gap: 16px;
}

.log-status.notified {
  color: #07c160;
}

.log-status.pending {
  color: #ff976a;
}

.empty-tip {
  text-align: center;
  padding: 40px;
  color: #c8c9cc;
  font-size: 14px;
}

/* 设置表单 */
.settings-form {
  max-width: 600px;
}

.form-group,
.form-item {
  margin-bottom: 16px;
}

.form-group label,
.form-item label {
  display: block;
  font-size: 14px;
  color: #646566;
  margin-bottom: 8px;
}

.form-group input,
.form-group textarea,
.form-group select,
.form-item textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ebedf0;
  border-radius: 4px;
  font-size: 14px;
}

.form-group textarea {
  resize: vertical;
}

.save-btn,
.dialog-actions .primary {
  padding: 10px 24px;
  background: #1989fa;
  color: #fff;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
}

.save-btn:hover {
  background: #0776e8;
}

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
  z-index: 1000;
}

.dialog {
  background: #fff;
  border-radius: 8px;
  padding: 24px;
  width: 90%;
  max-width: 500px;
  max-height: 80vh;
  overflow-y: auto;
}

.dialog h3 {
  margin: 0 0 20px;
  font-size: 18px;
  color: #323233;
}

.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
}

.dialog-actions button {
  padding: 10px 20px;
  border: 1px solid #ebedf0;
  background: #fff;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
  color: #646566;
}

.dialog-actions button.primary {
  background: #1989fa;
  color: #fff;
  border-color: #1989fa;
}

@media (max-width: 768px) {
  .tab-navigation {
    overflow-x: auto;
  }
  
  .tab-btn {
    padding: 10px 12px;
    font-size: 13px;
    white-space: nowrap;
  }
  
  .tab-content {
    padding: 12px;
  }
}
</style>
