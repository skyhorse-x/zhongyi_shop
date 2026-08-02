<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Picture } from '@element-plus/icons-vue'

interface ChatMessage {
  id: number
  sender_type: 'user' | 'admin'
  content: string
  msg_type: 'text' | 'image' | 'file'
  file_url: string
  file_name: string
  created_at: string
}

const sessionNo = ref('')
const messages = ref<ChatMessage[]>([])
const inputText = ref('')
const loading = ref(false)
const messageListRef = ref<HTMLElement | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)

// 获取认证token
const getToken = (): string => localStorage.getItem('token') || ''

// 获取或创建会话
const getOrCreateSession = async () => {
  try {
    const res = await fetch('/api/v1/customer-service/session', {
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
    const res = await fetch(`/api/v1/customer-service/sessions/${sessionNo.value}/messages`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      messages.value = data.data.data || []
      await scrollToBottom()
    }
  } catch (e) {
    // 忽略错误
  }
}

// 发送文本消息
const sendMessage = async () => {
  const text = inputText.value.trim()
  if (!text) return

  if (!sessionNo.value) {
    await getOrCreateSession()
  }

  try {
    const res = await fetch(`/api/v1/customer-service/sessions/${sessionNo.value}/messages`, {
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
    const res = await fetch(`/api/v1/customer-service/sessions/${sessionNo.value}/upload-image`, {
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
})
</script>

<template>
  <div class="customer-service-page">
    <!-- 消息列表 -->
    <div ref="messageListRef" class="message-list">
      <!-- 服务提示 -->
      <div class="service-tip">
        <span class="tip-text">工作日 9:00-18:00 在线服务</span>
      </div>

      <!-- 欢迎消息 -->
      <div v-if="messages.length === 0" class="welcome">
        <div class="welcome-icon">🎧</div>
        <div class="welcome-title">在线客服</div>
        <div class="welcome-desc">有什么问题可以告诉我们，我们会尽快为您解答</div>
      </div>

      <!-- 聊天消息 -->
      <div
        v-for="msg in messages"
        :key="msg.id"
        class="message-item"
        :class="msg.sender_type"
      >
        <div class="avatar">
          {{ msg.sender_type === 'user' ? '👤' : '🎧' }}
        </div>
        <div class="message-content">
          <!-- 文本消息 -->
          <div v-if="msg.msg_type === 'text'" class="message-bubble">
            {{ msg.content }}
          </div>
          <!-- 图片消息 -->
          <div v-else-if="msg.msg_type === 'image'" class="message-bubble image-bubble">
            <img :src="msg.file_url" class="message-image" @click="previewImage(msg.file_url)" />
          </div>
          <div class="message-time">{{ formatTime(msg.created_at) }}</div>
        </div>
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
</style>
