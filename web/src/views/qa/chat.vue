<script setup lang="ts">
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus, List, Coin, User, FirstAidKit, CopyDocument, Microphone, VideoPause } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'

const route = useRoute()
const router = useRouter()

// 从路由参数获取可选的 sessionNo
const sessionNo = ref((route.params.sessionNo as string) || '')

interface ChatMessage {
  id: number
  role: 'user' | 'assistant'
  content: string
  timestamp: number | string
}

const messages = ref<ChatMessage[]>([])
const inputText = ref('')
const loading = ref(false)
const messageListRef = ref<HTMLElement | null>(null)
const analysisTimes = ref<number>(0)

// 获取认证token
import { getToken } from '@/utils/auth'

const getAuthToken = (): string => getToken() || ''

// 加载用户剩余积分
const loadUserInfo = async () => {
  try {
    const res = await safeFetch('/api/v1/user/info', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      analysisTimes.value = data.data?.analysis_times ?? 0
    }
  } catch (e) {
    // 忽略错误
  }
}

// 创建新会话
const createSession = async (): Promise<string> => {
  const res = await safeFetch('/api/v1/qa/sessions', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${getToken()}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
  })
  const data = await res.json()
  if (data.code === 0) {
    return data.data.session_no
  }
  throw new Error(data.message || '创建会话失败')
}

// 加载历史消息
const loadMessages = async (sNo: string) => {
  try {
    const res = await safeFetch(`/api/v1/qa/sessions/${sNo}/messages`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data.data || data.data
      messages.value = list.map((msg: any) => ({
        id: msg.id,
        role: msg.role,
        content: msg.content,
        timestamp: msg.created_at || Date.now(),
      }))
      await scrollToBottom()
    } else {
      ElMessage.error(data.message || '加载消息失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载消息失败')
  }
}

// 格式化 AI 回复内容（支持简单 Markdown）
const formatContent = (content: string): string => {
  if (!content) return ''
  return content
    // 代码块
    .replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code class="lang-$1">$2</code></pre>')
    // 行内代码
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    // 加粗
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    // 斜体
    .replace(/\*([^*]+)\*/g, '<em>$1</em>')
    // 标题
    .replace(/^### (.+)$/gm, '<h4>$1</h4>')
    .replace(/^## (.+)$/gm, '<h3>$1</h3>')
    .replace(/^# (.+)$/gm, '<h2>$1</h2>')
    // 列表项
    .replace(/^- (.+)$/gm, '<li>$1</li>')
    .replace(/^(\d+)\. (.+)$/gm, '<li>$2</li>')
    // 换行
    .replace(/\n/g, '<br>')
}

// 复制文本
const copyText = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
    ElMessage.success('已复制到剪贴板')
  } catch {
    const input = document.createElement('textarea')
    input.value = text
    document.body.appendChild(input)
    input.select()
    document.execCommand('copy')
    document.body.removeChild(input)
    ElMessage.success('已复制到剪贴板')
  }
}

// 语音朗读相关
const speakingMsgId = ref<number | null>(null)
let speechSynthesis: SpeechSynthesis | null = null
let currentUtterance: SpeechSynthesisUtterance | null = null

const initSpeech = () => {
  if ('speechSynthesis' in window) {
    speechSynthesis = window.speechSynthesis
  }
}

// 朗读文本
const speakText = (msgId: number, text: string) => {
  if (!speechSynthesis) {
    ElMessage.warning('您的浏览器不支持语音朗读')
    return
  }

  // 如果正在朗读同一消息，则停止
  if (speakingMsgId.value === msgId) {
    speechSynthesis.cancel()
    speakingMsgId.value = null
    return
  }

  // 停止当前朗读
  speechSynthesis.cancel()

  // 移除 HTML 标签
  const plainText = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim()
  
  const utterance = new SpeechSynthesisUtterance(plainText)
  utterance.lang = 'zh-CN'
  utterance.rate = 1.0
  utterance.pitch = 1.0
  utterance.volume = 1.0

  utterance.onend = () => {
    speakingMsgId.value = null
  }

  utterance.onerror = () => {
    speakingMsgId.value = null
  }

  currentUtterance = utterance
  speakingMsgId.value = msgId
  speechSynthesis.speak(utterance)
}

// 停止朗读
const stopSpeaking = () => {
  if (speechSynthesis) {
    speechSynthesis.cancel()
    speakingMsgId.value = null
  }
}

// 发送消息
const sendMessage = async () => {
  const text = inputText.value.trim()
  if (!text) {
    ElMessage.info('请输入您的问题')
    return
  }

  // 检查剩余积分
  if (analysisTimes.value <= 0) {
    ElMessage.warning('您的分析次数已用完，请先购买套餐')
    return
  }

  // 如果没有会话，先创建
  if (!sessionNo.value) {
    try {
      sessionNo.value = await createSession()
    } catch (e: any) {
      ElMessage.error(e.message || '创建会话失败')
      return
    }
  }

  // 添加用户消息
  const userMessage: ChatMessage = {
    id: Date.now(),
    role: 'user',
    content: text,
    timestamp: Date.now(),
  }
  messages.value.push(userMessage)
  inputText.value = ''

  await scrollToBottom()

  // 调用AI获取回复
  loading.value = true
  try {
    const res = await safeFetch(`/api/v1/qa/sessions/${sessionNo.value}/messages`, {
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
      const assistantMessage: ChatMessage = {
        id: data.data.message_id || Date.now(),
        role: 'assistant',
        content: data.data.content,
        timestamp: data.data.created_at || Date.now(),
      }
      messages.value.push(assistantMessage)
      // 刷新剩余积分
      await loadUserInfo()
    } else {
      ElMessage.error(data.message || '发送失败')
      // 移除失败的用户消息
      messages.value.pop()
      inputText.value = text
    }
  } catch (e: any) {
    ElMessage.error(e.message || '网络错误，请稍后重试')
    // 移除失败的用户消息
    messages.value.pop()
    inputText.value = text
  } finally {
    loading.value = false
    await scrollToBottom()
  }
}

// 滚动到底部
const scrollToBottom = async () => {
  await nextTick()
  if (messageListRef.value) {
    messageListRef.value.scrollTop = messageListRef.value.scrollHeight
  }
}

// 开始新对话
const startNewChat = () => {
  messages.value = []
  sessionNo.value = ''
  router.push('/qa/chat')
}

// 跳转到历史记录
const goToSessions = () => {
  router.push('/qa/sessions')
}

// 格式化时间
const formatTime = (timestamp: number | string): string => {
  const date = new Date(timestamp)
  return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`
}

onMounted(async () => {
  // 初始化语音合成
  initSpeech()
  // 加载用户信息（积分）
  await loadUserInfo()
  // 如果有 sessionNo，加载历史消息
  if (sessionNo.value) {
    await loadMessages(sessionNo.value)
  }
})

onBeforeUnmount(() => {
  // 组件销毁时停止朗读
  stopSpeaking()
})
</script>

<template>
  <div class="qa-chat-page">
    <!-- 顶部信息栏 -->
    <div class="header-bar">
      <div class="header-left">
        <span class="header-title">健康问答</span>
      </div>
      <div class="header-right">
        <div class="credits-badge">
          <el-icon class="credits-icon"><Coin /></el-icon>
          <span class="credits-text">剩余 {{ analysisTimes }} 积分</span>
        </div>
        <el-icon @click="startNewChat" class="action-btn" title="新建对话"><Plus /></el-icon>
        <el-icon @click="goToSessions" class="action-btn" title="对话列表"><List /></el-icon>
      </div>
    </div>

    <!-- 消息列表 -->
    <div ref="messageListRef" class="message-list">
      <!-- 欢迎消息 -->
      <div v-if="messages.length === 0" class="welcome">
        <el-icon class="welcome-icon"><FirstAidKit /></el-icon>
        <div class="welcome-title">欢迎使用健康问答</div>
        <div class="welcome-desc">我是您的AI中医健康顾问，请描述您的健康问题</div>
        <div class="quick-questions">
          <div class="quick-item" @click="inputText = '我最近总是失眠，该怎么办？'; sendMessage()">
            我最近总是失眠，该怎么办？
          </div>
          <div class="quick-item" @click="inputText = '脾胃虚弱如何调理？'; sendMessage()">
            脾胃虚弱如何调理？
          </div>
          <div class="quick-item" @click="inputText = '什么是气虚体质？'; sendMessage()">
            什么是气虚体质？
          </div>
        </div>
      </div>

      <!-- 聊天消息 -->
      <div
        v-for="msg in messages"
        :key="msg.id"
        class="message-item"
        :class="msg.role"
      >
        <div class="avatar">
          <el-icon v-if="msg.role === 'user'"><User /></el-icon>
          <el-icon v-else><FirstAidKit /></el-icon>
        </div>
        <div class="message-content">
          <div class="message-bubble" v-html="msg.role === 'assistant' ? formatContent(msg.content) : msg.content">
          </div>
          <div class="message-meta">
            <span class="message-time">{{ formatTime(msg.timestamp) }}</span>
            <div v-if="msg.role === 'assistant'" class="message-actions">
              <el-icon
                class="action-icon"
                :title="'复制'"
                @click="copyText(msg.content)"
              ><CopyDocument /></el-icon>
              <el-icon
                class="action-icon"
                :title="speakingMsgId === msg.id ? '停止朗读' : '朗读'"
                @click="speakText(msg.id, msg.content)"
              ><VideoPause v-if="speakingMsgId === msg.id" /><Microphone v-else /></el-icon>
            </div>
          </div>
        </div>
      </div>

      <!-- 加载状态 -->
      <div v-if="loading" class="message-item assistant">
        <div class="avatar"><el-icon><FirstAidKit /></el-icon></div>
        <div class="message-content">
          <div class="message-bubble typing">
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
          </div>
        </div>
      </div>
    </div>

    <!-- 输入区域 -->
    <div class="input-area">
      <div class="input-wrapper">
        <el-input
          v-model="inputText"
          placeholder="请输入您的健康问题..."
          class="input-field"
          @keyup.enter="sendMessage"
        />
        <el-button
          type="primary"
          :disabled="loading"
          @click="sendMessage"
          class="send-btn"
        >
          发送
        </el-button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.qa-chat-page {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background-color: #f7f8fa;
}

/* 顶部信息栏 */
.header-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  color: #fff;
  box-shadow: 0 2px 8px rgba(7, 193, 96, 0.2);
}

.header-left {
  display: flex;
  align-items: center;
}

.header-title {
  font-size: 17px;
  font-weight: 600;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.credits-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(255, 255, 255, 0.2);
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
}

.credits-icon {
  font-size: 14px;
}

.credits-text {
  font-weight: 500;
}

.action-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s ease;
}

.action-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.05);
}

.message-list {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

/* 欢迎区域 */
.welcome {
  text-align: center;
  padding: 40px 20px;
}

.welcome-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.welcome-title {
  font-size: 20px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 8px;
}

.welcome-desc {
  font-size: 14px;
  color: #969799;
  margin-bottom: 24px;
}

.quick-questions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.quick-item {
  background: #fff;
  border-radius: 8px;
  padding: 12px 16px;
  font-size: 14px;
  color: #323233;
  text-align: left;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  cursor: pointer;
  transition: all 0.2s ease;
}

.quick-item:hover {
  background: #e8f5e9;
  transform: translateX(4px);
}

.quick-item:active {
  background: #f2f3f5;
}

/* 消息项 */
.message-item {
  display: flex;
  margin-bottom: 16px;
  gap: 8px;
}

.message-item.user {
  flex-direction: row-reverse;
}

.avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.message-content {
  max-width: 75%;
}

.message-bubble {
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 14px;
  line-height: 1.6;
  word-break: break-word;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

/* AI 回复样式 */
.assistant .message-bubble {
  background: #fff;
  color: #323233;
  border-top-left-radius: 4px;
}

.assistant .message-bubble :deep(h2),
.assistant .message-bubble :deep(h3),
.assistant .message-bubble :deep(h4) {
  margin: 12px 0 8px;
  color: #07c160;
  font-weight: 600;
}

.assistant .message-bubble :deep(h2) { font-size: 18px; }
.assistant .message-bubble :deep(h3) { font-size: 16px; }
.assistant .message-bubble :deep(h4) { font-size: 15px; }

.assistant .message-bubble :deep(strong) {
  color: #07c160;
  font-weight: 600;
}

.assistant .message-bubble :deep(em) {
  color: #666;
  font-style: italic;
}

.assistant .message-bubble :deep(li) {
  margin: 4px 0;
  padding-left: 8px;
}

.assistant .message-bubble :deep(code) {
  background: #f5f5f5;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 13px;
  color: #e83e8c;
}

.assistant .message-bubble :deep(pre) {
  background: #2d2d2d;
  color: #f8f8f2;
  padding: 12px;
  border-radius: 8px;
  overflow-x: auto;
  margin: 8px 0;
}

.assistant .message-bubble :deep(pre code) {
  background: transparent;
  color: inherit;
  padding: 0;
}

/* AI 消息操作按钮 */
.message-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4px;
}

.message-actions {
  display: flex;
  gap: 8px;
}

.action-icon {
  font-size: 14px;
  color: #969799;
  cursor: pointer;
  transition: color 0.2s ease;
}

.action-icon:hover {
  color: #07c160;
}

/* 用户消息样式 */
.user .message-bubble {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  color: #fff;
  border-top-right-radius: 4px;
}

.message-time {
  font-size: 11px;
  color: #c8c9cc;
  margin-top: 4px;
}

.user .message-time {
  text-align: right;
}

/* 打字动画 */
.typing {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 14px 18px;
}

.dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #07c160;
  animation: typing 1.4s infinite;
}

.dot:nth-child(2) {
  animation-delay: 0.2s;
}

.dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typing {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 0.4;
  }
  30% {
    transform: translateY(-6px);
    opacity: 1;
  }
}

/* 输入区域 */
.input-area {
  padding: 16px 20px;
  background: #fff;
  border-top: 1px solid #ebedf0;
}

.input-wrapper {
  display: flex;
  gap: 12px;
  align-items: center;
}

.input-field {
  flex: 1;
}

.input-field :deep(.el-input__wrapper) {
  border-radius: 24px;
  padding: 8px 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.send-btn {
  flex-shrink: 0;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border: none;
  border-radius: 24px;
  padding: 12px 28px;
  height: 44px;
  font-weight: 500;
}
</style>
