<script setup lang="ts">
import { ref, nextTick, onMounted, onUnmounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Picture, User, Headset, ChatDotRound, RefreshLeft, Delete, Close } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'

interface ChatMessage {
  id: number
  sender_type: 'user' | 'admin'
  content: string
  msg_type: 'text' | 'image' | 'file'
  file_url: string
  file_name: string
  created_at: string
  read_at: string | null
  is_deleted?: boolean
  is_recalled?: boolean
  reply_to_id?: number | null
  reply_to?: ChatMessage | null
}

const sessionNo = ref('')
const messages = ref<ChatMessage[]>([])
const inputText = ref('')
const loading = ref(false)
const messageListRef = ref<HTMLElement | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const isTyping = ref(false)
const typingTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const heartbeatInterval = ref<ReturnType<typeof setInterval> | null>(null)
const isLeaving = ref(false) // 标记是否正在离开页面

// 消息操作相关
const replyToMessage = ref<ChatMessage | null>(null)
const contextMenuVisible = ref(false)
const contextMenuPosition = ref({ x: 0, y: 0 })
const contextMenuMessage = ref<ChatMessage | null>(null)

// 获取认证token
import { getToken } from '@/utils/auth'

const getAuthToken = (): string => getToken() || ''

// 获取或创建会话
const getOrCreateSession = async () => {
  try {
    const res = await safeFetch('/api/v1/customer-service/session', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      sessionNo.value = data.data.session_no
      // 加载历史消息
      loadMessages()
    } else {
      ElMessage.error(data.message || '获取会话失败')
    }
  } catch (e: any) {
    ElMessage.error('网络错误')
  }
}

// 加载消息
const loadMessages = async () => {
  if (!sessionNo.value) return
  try {
    const res = await safeFetch(`/api/v1/customer-service/sessions/${sessionNo.value}/messages`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      messages.value = data.data.data || []
      await scrollToBottom()
      // 加载消息后标记为已读
      markAsRead()
    }
  } catch (e) {
    // 忽略错误
  }
}

// 发送文本消息
const sendMessage = async () => {
  const text = inputText.value.trim()
  if (!text) return

  // 如果有引用消息，发送引用消息
  if (replyToMessage.value) {
    await sendReplyMessage()
    return
  }

  if (!sessionNo.value) {
    await getOrCreateSession()
  }

  try {
    const res = await safeFetch(`/api/v1/customer-service/sessions/${sessionNo.value}/messages`, {
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
    } else {
      ElMessage.error(data.message || '发送失败')
    }
  } catch (e: any) {
    ElMessage.error('网络错误')
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
  if (!file) return

  // 验证文件类型
  const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
  if (!allowedTypes.includes(file.type)) {
    ElMessage.error('只支持 jpg、png、gif、webp 格式的图片')
    return
  }

  // 验证文件大小 (5MB)
  if (file.size > 5 * 1024 * 1024) {
    ElMessage.error('图片大小不能超过 5MB')
    return
  }

  if (!sessionNo.value) {
    await getOrCreateSession()
  }

  const formData = new FormData()
  formData.append('image', file)

  try {
    loading.value = true
    const res = await safeFetch(`/api/v1/customer-service/sessions/${sessionNo.value}/upload-image`, {
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
    } else {
      ElMessage.error(data.message || '上传失败')
    }
  } catch (e: any) {
    ElMessage.error('上传失败')
  } finally {
    loading.value = false
    // 清空input
    target.value = ''
  }
}

// 滚动到底部
const scrollToBottom = async () => {
  await nextTick()
  if (messageListRef.value) {
    messageListRef.value.scrollTop = messageListRef.value.scrollHeight
  }
}

// 标记消息为已读
const markAsRead = async () => {
  if (!sessionNo.value) return
  try {
    await safeFetch(`/api/v1/customer-service/sessions/${sessionNo.value}/mark-as-read`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
  } catch (e) {
    // 忽略错误
  }
}

// 心跳上报（保持在线状态）
const sendHeartbeat = async () => {
  if (!sessionNo.value || isLeaving.value) return
  try {
    await safeFetch(`/api/v1/customer-service/sessions/${sessionNo.value}/heartbeat`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
  } catch (e) {
    // 忽略错误
  }
}

// 用户离开页面时标记为离线（不关闭会话，保留对话记录）
const markOfflineOnLeave = async () => {
  if (!sessionNo.value || isLeaving.value) return
  isLeaving.value = true

  // 停止心跳
  if (heartbeatInterval.value) {
    clearInterval(heartbeatInterval.value)
    heartbeatInterval.value = null
  }

  // 使用 sendBeacon 确保请求在页面关闭时也能发送
  const url = `${window.location.origin}/api/v1/customer-service/sessions/${sessionNo.value}/mark-offline`
  const token = getToken()

  if (navigator.sendBeacon) {
    // 使用 sendBeacon API（适合页面关闭场景）
    const blob = new Blob([JSON.stringify({})], { type: 'application/json' })
    navigator.sendBeacon(url, blob)
  } else {
    // 降级使用同步 XMLHttpRequest
    try {
      const xhr = new XMLHttpRequest()
      xhr.open('POST', url, false) // 同步请求
      xhr.setRequestHeader('Authorization', `Bearer ${token}`)
      xhr.setRequestHeader('Accept', 'application/json')
      xhr.setRequestHeader('Content-Type', 'application/json')
      xhr.send(JSON.stringify({}))
    } catch (e) {
      // 忽略错误
    }
  }
}

// 模拟对方正在输入（实际项目中应通过 WebSocket 接收）
const simulateTyping = () => {
  if (typingTimer.value) {
    clearTimeout(typingTimer.value)
  }
  isTyping.value = true
  typingTimer.value = setTimeout(() => {
    isTyping.value = false
  }, 3000)
}

// ========== 消息操作：删除、撤回、引用 ==========

// 删除消息
const deleteMessage = async (msg: ChatMessage) => {
  if (!sessionNo.value) return
  try {
    const res = await safeFetch(`/api/v1/customer-service/sessions/${sessionNo.value}/messages/${msg.id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      msg.is_deleted = true
      ElMessage.success('消息已删除')
    } else {
      ElMessage.error(data.message || '删除失败')
    }
  } catch {
    ElMessage.error('删除失败')
  }
  contextMenuVisible.value = false
}

// 撤回消息
const recallMessage = async (msg: ChatMessage) => {
  if (!sessionNo.value) return
  try {
    const res = await safeFetch(`/api/v1/customer-service/sessions/${sessionNo.value}/messages/${msg.id}/recall`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      msg.is_recalled = true
      ElMessage.success('消息已撤回')
    } else {
      ElMessage.error(data.message || '撤回失败')
    }
  } catch {
    ElMessage.error('撤回失败')
  }
  contextMenuVisible.value = false
}

// 引用消息
const setReplyTo = (msg: ChatMessage) => {
  replyToMessage.value = msg
  contextMenuVisible.value = false
}

// 取消引用
const cancelReply = () => {
  replyToMessage.value = null
}

// 发送引用消息
const sendReplyMessage = async () => {
  const text = inputText.value.trim()
  if (!text || !sessionNo.value || !replyToMessage.value) return
  try {
    const res = await safeFetch(`/api/v1/customer-service/sessions/${sessionNo.value}/reply`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        content: text,
        reply_to_id: replyToMessage.value.id,
      }),
    })
    const data = await res.json()
    if (data.code === 0) {
      messages.value.push(data.data)
      inputText.value = ''
      replyToMessage.value = null
      await scrollToBottom()
    } else {
      ElMessage.error(data.message || '发送失败')
    }
  } catch {
    ElMessage.error('发送失败')
  }
}

// 显示右键菜单
const showContextMenu = (event: MouseEvent, msg: ChatMessage) => {
  event.preventDefault()
  contextMenuMessage.value = msg
  contextMenuPosition.value = { x: event.clientX, y: event.clientY }
  contextMenuVisible.value = true
}

// 关闭右键菜单
const closeContextMenu = () => {
  contextMenuVisible.value = false
  contextMenuMessage.value = null
}

// 判断消息是否可以撤回（2分钟内，自己发送的）
const canRecall = (msg: ChatMessage): boolean => {
  if (msg.sender_type !== 'user') return false
  if (msg.is_deleted || msg.is_recalled) return false
  const createdAt = new Date(msg.created_at)
  const now = new Date()
  return (now.getTime() - createdAt.getTime()) < 2 * 60 * 1000
}

// 判断消息是否可以删除（只能删除自己发送的）
const canDelete = (msg: ChatMessage): boolean => {
  if (msg.sender_type !== 'user') return false
  return !msg.is_deleted && !msg.is_recalled
}

// 判断消息是否可以引用
const canReply = (msg: ChatMessage): boolean => {
  return !msg.is_deleted && !msg.is_recalled
}

// 格式化时间
const formatTime = (timestamp: string): string => {
  const date = new Date(timestamp)
  return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`
}

// 预览图片
const previewImage = (url: string) => {
  window.open(url, '_blank')
}

onMounted(() => {
  getOrCreateSession()

  // 启动心跳（每30秒发送一次）
  heartbeatInterval.value = setInterval(() => {
    sendHeartbeat()
  }, 30000)

  // 监听页面关闭/刷新事件
  window.addEventListener('beforeunload', markOfflineOnLeave)
})

onUnmounted(() => {
  // 清理心跳定时器
  if (heartbeatInterval.value) {
    clearInterval(heartbeatInterval.value)
    heartbeatInterval.value = null
  }

  // 如果用户离开页面（非 beforeunload 触发），标记离线
  markOfflineOnLeave()

  // 移除事件监听
  window.removeEventListener('beforeunload', markOfflineOnLeave)
})
</script>

<template>
  <div class="customer-service-page">
    <!-- 对方正在输入提示 -->
    <div class="typing-indicator" v-if="isTyping">
      <div class="typing-dots">
        <span></span>
        <span></span>
        <span></span>
      </div>
      <span class="typing-text">客服正在输入...</span>
    </div>

    <!-- 消息列表 -->
    <div ref="messageListRef" class="message-list" @click="closeContextMenu">
      <!-- 服务提示 -->
      <div class="service-tip">
        <span class="tip-text">工作日 9:00-18:00 在线服务</span>
      </div>

      <!-- 欢迎消息 -->
      <div v-if="messages.length === 0" class="welcome">
        <el-icon class="welcome-icon"><Headset /></el-icon>
        <div class="welcome-title">在线客服</div>
        <div class="welcome-desc">有什么问题可以告诉我们，我们会尽快为您解答</div>
      </div>

      <!-- 聊天消息 -->
      <div
        v-for="msg in messages"
        :key="msg.id"
        class="message-item"
        :class="msg.sender_type"
        @contextmenu.prevent="showContextMenu($event, msg)"
      >
        <div class="avatar">
          <el-icon v-if="msg.sender_type === 'user'"><User /></el-icon>
          <el-icon v-else><Headset /></el-icon>
        </div>
        <div class="message-content">
          <!-- 引用消息预览 -->
          <div v-if="msg.reply_to_id && msg.reply_to" class="reply-preview">
            <div class="reply-line"></div>
            <div class="reply-content">
              <span class="reply-sender">{{ msg.reply_to.sender_type === 'user' ? '我' : '客服' }}：</span>
              <span class="reply-text">{{ msg.reply_to.content || '[图片]' }}</span>
            </div>
          </div>
          <!-- 已删除/撤回提示 -->
          <div v-if="msg.is_deleted || msg.is_recalled" class="message-bubble deleted-bubble">
            <el-icon><Close /></el-icon>
            {{ msg.is_recalled ? '消息已撤回' : '消息已删除' }}
          </div>
          <!-- 正常消息内容 -->
          <template v-else>
            <!-- 文本消息 -->
            <div v-if="msg.msg_type === 'text'" class="message-bubble">
              {{ msg.content }}
            </div>
            <!-- 图片消息 -->
            <div v-else-if="msg.msg_type === 'image'" class="message-bubble image-bubble">
              <img :src="msg.file_url" class="message-image" @click.stop="previewImage(msg.file_url)" />
            </div>
          </template>
          <div class="message-time">
            {{ formatTime(msg.created_at) }}
            <!-- 已读/未读状态（仅自己发送的消息显示） -->
            <span v-if="msg.sender_type === 'user' && !msg.is_deleted && !msg.is_recalled" class="read-status">
              {{ msg.read_at ? '已读' : '未读' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- 引用消息预览条 -->
    <div v-if="replyToMessage" class="reply-bar">
      <div class="reply-bar-content">
        <el-icon class="reply-icon"><ChatDotRound /></el-icon>
        <span class="reply-label">引用：</span>
        <span class="reply-text">{{ replyToMessage.content || '[图片]' }}</span>
      </div>
      <button class="reply-cancel" @click="cancelReply">
        <el-icon><Close /></el-icon>
      </button>
    </div>

    <!-- 消息右键菜单 -->
    <div
      v-if="contextMenuVisible"
      class="context-menu"
      :style="{ left: contextMenuPosition.x + 'px', top: contextMenuPosition.y + 'px' }"
      @click.stop
    >
      <div v-if="contextMenuMessage && canReply(contextMenuMessage)" class="context-menu-item" @click="setReplyTo(contextMenuMessage)">
        <el-icon><ChatDotRound /></el-icon>
        <span>引用</span>
      </div>
      <div v-if="contextMenuMessage && canRecall(contextMenuMessage)" class="context-menu-item" @click="recallMessage(contextMenuMessage)">
        <el-icon><RefreshLeft /></el-icon>
        <span>撤回</span>
      </div>
      <div v-if="contextMenuMessage && canDelete(contextMenuMessage)" class="context-menu-item danger" @click="deleteMessage(contextMenuMessage)">
        <el-icon><Delete /></el-icon>
        <span>删除</span>
      </div>
    </div>

    <!-- 输入区域 -->
    <div class="input-area">
      <div class="input-wrapper">
        <input
          v-model="inputText"
          placeholder="请输入您的问题..."
          class="input-field"
          @keyup.enter="sendMessage"
        />
        <button class="action-btn" @click="triggerFileInput" title="发送图片">
          <el-icon><Picture /></el-icon>
        </button>
        <button
          class="send-btn"
          :disabled="!inputText.trim()"
          @click="sendMessage"
        >
          发送
        </button>
      </div>
      <!-- 隐藏的文件输入 -->
      <input
        ref="fileInputRef"
        type="file"
        accept="image/*"
        class="hidden-input"
        @change="handleFileChange"
      />
    </div>
  </div>
</template>

<style scoped>
.customer-service-page {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 100px);
  background-color: #f7f8fa;
}

/* 对方正在输入提示 */
.typing-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: rgba(7, 193, 96, 0.08);
  border-bottom: 1px solid rgba(7, 193, 96, 0.15);
}

.typing-dots {
  display: flex;
  gap: 3px;
}

.typing-dots span {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #07c160;
  animation: typing-bounce 1.4s infinite;
}

.typing-dots span:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-dots span:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typing-bounce {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 0.4;
  }
  30% {
    transform: translateY(-4px);
    opacity: 1;
  }
}

.typing-text {
  font-size: 12px;
  color: #07c160;
}

/* 服务提示 */
.service-tip {
  text-align: center;
  padding: 12px;
}

.tip-text {
  font-size: 12px;
  color: #969799;
  background: rgba(0, 0, 0, 0.04);
  padding: 4px 12px;
  border-radius: 12px;
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
  font-size: 18px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 8px;
}

.welcome-desc {
  font-size: 14px;
  color: #969799;
}

/* 消息列表 */
.message-list {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
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

.admin .message-bubble {
  background: #fff;
  color: #323233;
  border-top-left-radius: 4px;
}

.user .message-bubble {
  background: #07c160;
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

.read-status {
  margin-left: 6px;
  font-size: 10px;
  color: #07c160;
}

.read-status.unread {
  color: #c8c9cc;
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
  display: block;
}

/* 输入区域 */
.input-area {
  padding: 10px 12px;
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
  height: 40px;
  border: 1px solid #ebedf0;
  border-radius: 20px;
  padding: 0 16px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}

.input-field:focus {
  border-color: #07c160;
}

.action-btn {
  width: 40px;
  height: 40px;
  border: none;
  border-radius: 50%;
  background: #f5f5f5;
  color: #666;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  transition: background 0.2s;
}

.action-btn:hover {
  background: #e8e8e8;
}

.send-btn {
  height: 40px;
  padding: 0 20px;
  background: #07c160;
  color: #fff;
  border: none;
  border-radius: 20px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.send-btn:hover {
  background: #06ad56;
}

.send-btn:disabled {
  background: #c8c9cc;
  cursor: not-allowed;
}

.hidden-input {
  display: none;
}

/* 右键菜单 */
.context-menu {
  position: fixed;
  z-index: 9999;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  padding: 4px 0;
  min-width: 120px;
}

.context-menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  cursor: pointer;
  font-size: 14px;
  color: #333;
  transition: background 0.2s;
}

.context-menu-item:hover {
  background: #f5f5f5;
}

.context-menu-item.danger {
  color: #ee0a24;
}

.context-menu-item.danger:hover {
  background: #fef0f0;
}

/* 引用消息预览条 */
.reply-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  background: #f0f9eb;
  border-top: 1px solid #e1f3d8;
}

.reply-bar-content {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
  min-width: 0;
}

.reply-icon {
  color: #07c160;
  font-size: 14px;
}

.reply-label {
  color: #666;
  font-size: 12px;
  white-space: nowrap;
}

.reply-text {
  color: #333;
  font-size: 13px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  flex: 1;
}

.reply-cancel {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  color: #999;
  margin-left: 8px;
}

.reply-cancel:hover {
  color: #666;
}

/* 消息内的引用预览 */
.reply-preview {
  display: flex;
  gap: 8px;
  margin-bottom: 4px;
}

.reply-line {
  width: 3px;
  background: #07c160;
  border-radius: 2px;
}

.reply-content {
  flex: 1;
  padding: 6px 10px;
  background: rgba(0, 0, 0, 0.04);
  border-radius: 4px;
  font-size: 12px;
  color: #666;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.reply-sender {
  color: #07c160;
  font-weight: 500;
}

/* 已删除/撤回消息 */
.deleted-bubble {
  background: #f5f5f5 !important;
  color: #999 !important;
  font-style: italic;
  display: flex;
  align-items: center;
  gap: 4px;
}
</style>
