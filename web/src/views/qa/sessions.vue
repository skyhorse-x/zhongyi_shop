<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, ChatDotSquare, ArrowRight } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'

const router = useRouter()

interface SessionItem {
  session_no: string
  title: string
  last_message: string
  created_at: string
  message_count: number
}

const sessions = ref<SessionItem[]>([])
const loading = ref(false)

import { getToken } from '@/utils/auth'

const getAuthToken = (): string => getToken() || ''

// 从后端加载会话列表（不带任何硬编码）
const fetchSessions = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/qa/sessions', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      sessions.value = (data.data?.data || data.data || []).map((s: any) => ({
        session_no: s.session_no,
        title: s.title || '健康咨询',
        last_message: s.last_message || '',
        created_at: s.last_message_at || s.created_at,
        message_count: s.message_count || 0,
      }))
    } else {
      ElMessage.error(data.message || '加载会话失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误，请稍后重试')
  } finally {
    loading.value = false
  }
}

// 下拉刷新
const onRefresh = async () => {
  await fetchSessions()
  ElMessage.success('刷新成功')
}

// 进入会话
const enterSession = (sessionNo: string) => {
  router.push(`/qa/chat/${sessionNo}`)
}

// 删除会话
const deleteSession = async (session: SessionItem) => {
  try {
    await ElMessageBox.confirm('确定要删除这条问答记录吗？', '删除记录', { type: 'warning' })
    const res = await safeFetch(`/api/v1/qa/sessions/${session.session_no}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('删除成功')
      fetchSessions()
    } else {
      ElMessage.error(data.message || '删除失败')
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      // 取消或错误
    }
  }
}

// 开始新对话
const startNewChat = () => {
  router.push('/qa/chat')
}

onMounted(() => {
  fetchSessions()
})
</script>

<template>
  <div class="qa-sessions-page">
    <!-- 操作按钮 -->
    <div class="action-bar">
      <el-icon @click="startNewChat" class="action-btn" title="新建对话"><Plus /></el-icon>
    </div>

    <!-- 会话列表 -->
    <div v-loading="loading" class="session-list">
      <div v-if="sessions.length === 0" class="empty-state">
        <el-icon size="64" color="#c8c9cc"><ChatDotSquare /></el-icon>
        <div class="empty-text">暂无问答记录</div>
        <div class="empty-desc">开始您的第一次健康咨询吧</div>
        <el-button type="primary" class="empty-btn" @click="startNewChat">
          开始问答
        </el-button>
      </div>

      <div v-for="session in sessions" :key="session.session_no">
        <div class="session-item" @click="enterSession(session.session_no)">
          <div class="session-icon">💬</div>
          <div class="session-info">
            <div class="session-header">
              <div class="session-title">{{ session.title }}</div>
              <div class="session-time">{{ session.created_at }}</div>
            </div>
            <div class="session-last">{{ session.last_message || '暂无消息' }}</div>
            <div class="session-meta">
              <el-icon size="12"><ChatDotSquare /></el-icon>
              <span>{{ session.message_count }} 条对话</span>
            </div>
          </div>
          <el-icon class="session-arrow"><ArrowRight /></el-icon>
        </div>
        <div class="delete-row">
          <el-button type="danger" size="small" @click="deleteSession(session)">
            删除
          </el-button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.qa-sessions-page {
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

.session-list {
  flex: 1;
  overflow-y: auto;
  padding: 12px 16px;
}

/* 空状态 */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  text-align: center;
}

.empty-text {
  font-size: 16px;
  color: #646566;
  margin-top: 16px;
}

.empty-desc {
  font-size: 13px;
  color: #969799;
  margin-top: 8px;
  margin-bottom: 24px;
}

.empty-btn {
  width: 160px;
}

/* 会话项 */
.session-item {
  display: flex;
  align-items: center;
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 4px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  cursor: pointer;
}

.session-item:active {
  background: #f2f3f5;
}

.session-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: #e8f4ff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  margin-right: 12px;
  flex-shrink: 0;
}

.session-info {
  flex: 1;
  min-width: 0;
}

.session-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}

.session-title {
  font-size: 15px;
  font-weight: bold;
  color: #323233;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.session-time {
  font-size: 12px;
  color: #c8c9cc;
  flex-shrink: 0;
  margin-left: 8px;
}

.session-last {
  font-size: 13px;
  color: #969799;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-bottom: 6px;
}

.session-meta {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #c8c9cc;
}

.session-arrow {
  color: #c8c9cc;
  margin-left: 8px;
}

.delete-row {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 10px;
  padding: 0 4px;
}
</style>
