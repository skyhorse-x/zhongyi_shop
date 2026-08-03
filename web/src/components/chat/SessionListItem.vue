<script setup lang="ts">
/**
 * 会话列表项
 */
defineProps<{
  session: {
    session_no: string
    user_name?: string
    user_avatar?: string
    last_message?: string
    last_time?: string
    unread_count?: number
  }
  active?: boolean
}>()
const emit = defineEmits<{ (e: 'select', sessionNo: string): void }>()
</script>

<template>
  <div class="session-item" :class="{ active }" @click="emit('select', session.session_no)">
    <el-avatar :size="40" :src="session.user_avatar" />
    <div class="session-info">
      <div class="session-name">{{ session.user_name || '访客' }}</div>
      <div class="session-preview">{{ session.last_message || '暂无消息' }}</div>
    </div>
    <div class="session-meta">
      <span class="session-time">{{ session.last_time }}</span>
      <el-badge v-if="session.unread_count" :value="session.unread_count" :max="99" />
    </div>
  </div>
</template>

<style scoped>
.session-item {
  display: flex;
  align-items: center;
  padding: 12px;
  border-bottom: 1px solid #f0f0f0;
  cursor: pointer;
  transition: background 0.2s;
}
.session-item:hover { background: #f5f7fa; }
.session-item.active { background: #ecf5ff; }
.session-info { flex: 1; margin-left: 12px; min-width: 0; }
.session-name { font-size: 14px; font-weight: 500; }
.session-preview {
  font-size: 12px; color: #999;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.session-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.session-time { font-size: 11px; color: #999; }
</style>
