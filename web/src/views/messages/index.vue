<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ChatLineRound, Bell } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'
import { getToken } from '@/utils/auth'
import { useUnreadCount } from '@/composables/useUnreadCount'

const router = useRouter()
const { fetchUnreadCount, decrementUnread } = useUnreadCount()

// 系统消息列表
interface SystemMessage {
  id: number
  title: string
  content: string
  created_at: string
  is_read: boolean
  type: string
}

const systemMessages = ref<SystemMessage[]>([])
const loading = ref(false)

// 客服消息未读数
const customerServiceUnread = ref(0)
const customerServiceLastMessage = ref('您好，请问有什么可以帮您？')
const customerServiceTime = ref('')

// 获取系统消息列表
const loadSystemMessages = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/system-messages', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      systemMessages.value = data.data.data || []
    }
  } catch {
    ElMessage.error('获取消息失败')
  } finally {
    loading.value = false
  }
}

// 获取客服消息未读数
const loadCustomerServiceUnread = async () => {
  try {
    const res = await safeFetch('/api/v1/customer-service/sessions', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0 && data.data?.data) {
      let totalUnread = 0
      data.data.data.forEach((session: any) => {
        totalUnread += session.user_unread || 0
        // 获取最近一条消息
        if (session.latest_message && !customerServiceLastMessage.value) {
          customerServiceLastMessage.value = session.latest_message.content || '新消息'
          customerServiceTime.value = formatTime(session.latest_message.created_at)
        }
      })
      customerServiceUnread.value = totalUnread
    }
  } catch {
    // 获取失败时不更新
  }
}

// 格式化时间
const formatTime = (timeStr: string) => {
  if (!timeStr) return ''
  const date = new Date(timeStr)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  // 1分钟内
  if (diff < 60000) return '刚刚'
  // 1小时内
  if (diff < 3600000) return `${Math.floor(diff / 60000)}分钟前`
  // 今天内
  if (diff < 86400000 && date.getDate() === now.getDate()) {
    return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`
  }
  // 昨天
  const yesterday = new Date(now)
  yesterday.setDate(yesterday.getDate() - 1)
  if (date.getDate() === yesterday.getDate()) return '昨天'
  // 其他
  return `${date.getMonth() + 1}/${date.getDate()}`
}

// 跳转到客服聊天
const goToCustomerService = () => {
  router.push('/messages/customer-service')
}

// 查看系统消息详情
const viewSystemMessage = async (msg: SystemMessage) => {
  if (!msg.is_read) {
    // 调用标记已读 API
    try {
      await safeFetch(`/api/v1/system-messages/${msg.id}/read`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${getToken()}`,
          'Accept': 'application/json',
        },
      })
      msg.is_read = true
      decrementUnread()
      fetchUnreadCount() // 刷新未读数量
    } catch {
      // 标记失败
    }
  }
  ElMessage.info(msg.content)
}

// 获取未读系统消息数
const systemUnreadCount = computed(() => systemMessages.value.filter(m => !m.is_read).length)

onMounted(() => {
  loadSystemMessages()
  loadCustomerServiceUnread()
})
</script>

<template>
  <div class="messages-page">
    <!-- 客服消息 -->
    <div class="message-section">
      <div class="section-title">客服消息</div>
      <div class="message-card" @click="goToCustomerService">
        <div class="card-left">
          <div class="avatar service-avatar">
            <el-icon><ChatLineRound /></el-icon>
          </div>
        </div>
        <div class="card-center">
          <div class="card-title">在线客服</div>
          <div class="card-desc">{{ customerServiceLastMessage }}</div>
        </div>
        <div class="card-right">
          <div class="time">{{ customerServiceTime }}</div>
          <el-badge :value="customerServiceUnread" class="unread-badge" v-if="customerServiceUnread > 0" />
        </div>
      </div>
    </div>

    <!-- 系统消息 -->
    <div class="message-section">
      <div class="section-title">系统消息</div>
      <div class="system-list" v-loading="loading">
        <div
          v-for="msg in systemMessages"
          :key="msg.id"
          class="message-card"
          @click="viewSystemMessage(msg)"
        >
          <div class="card-left">
            <div class="avatar" :class="`type-${msg.type}`">
              <el-icon><Bell /></el-icon>
            </div>
          </div>
          <div class="card-center">
            <div class="card-title">
              {{ msg.title }}
              <span class="unread-dot" v-if="!msg.is_read"></span>
            </div>
            <div class="card-desc">{{ msg.content }}</div>
          </div>
          <div class="card-right">
            <div class="time">{{ formatTime(msg.created_at) }}</div>
          </div>
        </div>
        <div v-if="systemMessages.length === 0 && !loading" class="empty-tip">
          暂无消息
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.messages-page {
  padding: 12px;
}

.message-section {
  margin-bottom: 20px;
}

.section-title {
  font-size: 14px;
  color: #969799;
  margin-bottom: 12px;
  padding-left: 4px;
}

/* 消息卡片 */
.message-card {
  display: flex;
  align-items: center;
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 10px;
  cursor: pointer;
  transition: background 0.15s;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.message-card:active {
  background: #f2f3f5;
}

.card-left {
  margin-right: 12px;
}

.avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: #fff;
}

.service-avatar {
  background: linear-gradient(135deg, #07c160 0%, #06ad56 100%);
}

.type-welcome {
  background: linear-gradient(135deg, #1989fa 0%, #0765c0 100%);
}

.type-update {
  background: linear-gradient(135deg, #ff976a 0%, #f5621a 100%);
}

.type-activity {
  background: linear-gradient(135deg, #ee0a24 0%, #d6081a 100%);
}

.card-center {
  flex: 1;
  min-width: 0;
}

.card-title {
  font-size: 16px;
  font-weight: 500;
  color: #323233;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.unread-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ee0a24;
  display: inline-block;
}

.card-desc {
  font-size: 13px;
  color: #969799;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.card-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.time {
  font-size: 12px;
  color: #c8c9cc;
}

.empty-tip {
  text-align: center;
  color: #c8c9cc;
  padding: 24px 0;
  font-size: 14px;
}

.unread-badge {
  margin-top: 4px;
}
</style>
