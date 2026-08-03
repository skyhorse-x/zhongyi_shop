<script setup lang="ts">
/**
 * 报告摘要区：总评分 + 文字总结
 */
defineProps<{
  summary?: string
  score?: number
}>()
</script>

<template>
  <el-card class="report-summary" shadow="never">
    <template v-if="score !== undefined">
      <div class="score-row">
        <div class="score-label">健康评分</div>
        <el-progress
          type="circle"
          :percentage="score"
          :stroke-width="10"
          :width="100"
          :color="score >= 80 ? '#67c23a' : score >= 60 ? '#e6a23c' : '#f56c6c'"
        />
      </div>
    </template>
    <div v-if="summary" class="summary-text">{{ summary }}</div>
  </el-card>
</template>

<style scoped>
.report-summary { margin-bottom: 20px; }
.score-row {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 16px;
}
.score-label { font-size: 16px; font-weight: 500; }
.summary-text {
  padding: 16px;
  background: #f8f9fa;
  border-radius: 8px;
  line-height: 1.8;
  color: #303133;
}
</style>
