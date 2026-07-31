<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Plus, List } from '@element-plus/icons-vue'

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

// 获取认证token
const getToken = (): string => localStorage.getItem('token') || ''

// 创建新会话
const createSession = async (): Promise<string> => {
  const res = await fetch('/api/v1/qa/sessions', {
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
    const res = await fetch(`/api/v1/qa/sessions/${sNo}/messages`, {
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

// 发送消息
const sendMessage = async () => {
  const text = inputText.value.trim()
  if (!text) {
    ElMessage.info('请输入您的问题')
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
    const res = await fetch(`/api/v1/qa/sessions/${sessionNo.value}/messages`, {
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
  // 如果有 sessionNo，加载历史消息
  if (sessionNo.value) {
    await loadMessages(sessionNo.value)
  }
})
</script>

<template>
  <div class="qa-chat-page">
    <!-- 操作按钮 -->
    <div class="action-bar">
      <el-icon @click="startNewChat" class="action-btn" title="新建对话"><Plus /></el-icon>
      <el-icon @click="goToSessions" class="action-btn" title="对话列表"><List /></el-icon>
    </div>

    <!-- 消息列表 -->
    <div ref="messageListRef" class="message-list">
      <!-- 欢迎消息 -->
      <div v-if="messages.length === 0" class="welcome">
        <div class="welcome-icon">🌿</div>
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
          {{ msg.role === 'user' ? '👤' : '🌿' }}
        </div>
        <div class="message-content">
          <div class="message-bubble">
            {{ msg.content }}
          </div>
          <div class="message-time">{{ formatTime(msg.timestamp) }}</div>
        </div>
      </div>

      <!-- 加载状态 -->
      <div v-if="loading" class="message-item assistant">
        <div class="avatar">🌿</div>
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

.action-bar {
  position: fixed;
  top: 52px;
  right: 16px;
  z-index: 50;
  display: flex;
  gap: 8px;
}

.action-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #07c160;
  font-size: 18px;
  transition: all 0.2s ease;
}

.action-btn:hover {
  transform: scale(1.1);
  box-shadow: 0 4px 12px rgba(7, 193, 96, 0.2);
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
  word-break: break-all;
}

.assistant .message-bubble {
  background: #fff;
  color: #323233;
  border-top-left-radius: 4px;
}

.user .message-bubble {
  background: #1989fa;
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
  background: #1989fa;
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
  padding: 8px 12px;
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
}

.send-btn {
  flex-shrink: 0;
}
</style>
