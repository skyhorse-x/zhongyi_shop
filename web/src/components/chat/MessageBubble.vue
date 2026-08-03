<script setup lang="ts">
/**
 * 单条消息气泡组件
 * 用于客服聊天中渲染一条消息（用户 / 客服 / 系统）
 */
defineProps<{
  message: {
    content: string
    type: 'text' | 'image' | 'system'
    from: 'user' | 'admin' | 'system'
    created_at: string
  }
}>()
</script>

<template>
  <div v-if="message.type === 'system'" class="msg-system">
    <span>{{ message.content }}</span>
  </div>
  <div v-else class="msg-bubble" :class="`msg-${message.from}`">
    <div class="bubble-content">{{ message.content }}</div>
    <div class="bubble-time">{{ message.created_at }}</div>
  </div>
</template>

<style scoped>
.msg-system {
  text-align: center;
  color: #999;
  font-size: 12px;
  margin: 12px 0;
}
.msg-bubble {
  display: flex;
  flex-direction: column;
  margin: 8px 16px;
  max-width: 70%;
}
.msg-user { align-self: flex-end; }
.msg-admin { align-self: flex-start; }
.bubble-content {
  padding: 10px 14px;
  border-radius: 12px;
  background: #f0f0f0;
  word-break: break-word;
}
.msg-user .bubble-content {
  background: #1989fa;
  color: #fff;
}
.bubble-time {
  font-size: 11px;
  color: #999;
  margin-top: 4px;
}
.msg-user .bubble-time { text-align: right; }
</style>
