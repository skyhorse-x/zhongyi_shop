<script setup lang="ts">
/**
 * 消息输入区
 */
import { ref } from 'vue'

const emit = defineEmits<{
  (e: 'send', content: string): void
}>()

const draft = ref('')
const sending = ref(false)

const handleSend = () => {
  const text = draft.value.trim()
  if (!text || sending.value) return
  emit('send', text)
  draft.value = ''
}
</script>

<template>
  <div class="message-input">
    <el-input
      v-model="draft"
      type="textarea"
      :rows="3"
      :maxlength="500"
      placeholder="请输入消息（Enter 发送，Shift+Enter 换行）"
      @keydown.enter.exact.prevent="handleSend"
    />
    <div class="input-actions">
      <span class="char-count">{{ draft.length }} / 500</span>
      <el-button type="primary" :disabled="!draft.trim()" @click="handleSend">发送</el-button>
    </div>
  </div>
</template>

<style scoped>
.message-input {
  border-top: 1px solid #eee;
  padding: 12px;
  background: #fafafa;
}
.input-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
}
.char-count { color: #999; font-size: 12px; }
</style>
