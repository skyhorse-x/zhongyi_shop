<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ChatLineRound, Bell } from '@element-plus/icons-vue'

const router = useRouter()

// 客服消息
const customerServiceMsg = ref({
  unread: 2,
  lastMessage: '您好，请问有什么可以帮您？',
  time: '10:30',
})

// 系统消息列表
const systemMessages = ref([
  {
    id: 1,
    title: '系统通知',
    content: '欢迎使用AI中医健康管理平台，祝您身体健康！',
    time: '2026-08-02 09:00',
    unread: true,
    type: 'welcome',
  },
  {
    id: 2,
    title: '功能更新',
    content: '体质测试功能已上线，快来体验您的中医体质吧！',
    time: '2026-08-01 14:30',
    unread: false,
    type: 'update',
  },
  {
    id: 3,
    title: '活动通知',
    content: '新用户注册即送3次免费分析机会，快来体验吧！',
    time: '2026-07-30 10:00',
    unread: false,
    type: 'activity',
  },
])

// 跳转到客服聊天
const goToCustomerService = () => {
  router.push('/messages/customer-service')
}

// 查看系统消息详情
const viewSystemMessage = (msg: typeof systemMessages.value[0]) => {
  ElMessage.info(msg.content)
  msg.unread = false
}

// 获取未读消息总数
const totalUnread = ref(systemMessages.value.filter(m => m.unread).length + customerServiceMsg.value.unread)
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
          <div class="card-desc">{{ customerServiceMsg.lastMessage }}</div>
        </div>
        <div class="card-right">
          <div class="time">{{ customerServiceMsg.time }}</div>
          <el-badge :value="customerServiceMsg.unread" class="unread-badge" v-if="customerServiceMsg.unread > 0" />
        </div>
      </div>
    </div>

    <!-- 系统消息 -->
    <div class="message-section">
      <div class="section-title">系统消息</div>
      <div class="system-list">
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
              <span class="unread-dot" v-if="msg.unread"></span>
            </div>
            <div class="card-desc">{{ msg.content }}</div>
          </div>
          <div class="card-right">
            <div class="time">{{ msg.time }}</div>
          </div>
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

.unread-badge {
  margin-top: 4px;
}
</style>
