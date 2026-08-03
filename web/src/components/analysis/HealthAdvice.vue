<script setup lang="ts">
/**
 * 健康建议列表
 */
defineProps<{
  suggestions?: Array<{ category: string; content: string }>
}>()
</script>

<template>
  <el-card v-if="suggestions && suggestions.length" class="report-advice" shadow="never">
    <template #header><span class="section-title">健康建议</span></template>
    <div v-for="(item, idx) in suggestions" :key="idx" class="advice-item">
      <div class="advice-category">{{ item.category }}</div>
      <div class="advice-content">{{ item.content }}</div>
    </div>
  </el-card>
</template>

<style scoped>
.report-advice { margin-bottom: 20px; }
.section-title { font-size: 16px; font-weight: 500; }
.advice-item {
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
}
.advice-item:last-child { border-bottom: 0; }
.advice-category { font-size: 14px; font-weight: 500; color: #1989fa; margin-bottom: 4px; }
.advice-content { color: #606266; line-height: 1.6; }
</style>
