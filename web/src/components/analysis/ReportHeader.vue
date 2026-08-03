<script setup lang="ts">
/**
 * 报告头部：标题、得分、状态、时间
 */
import type { AnalysisResult } from '@/composables/useAnalysisResult'

defineProps<{
  result: AnalysisResult
  elapsed: number
}>()
</script>

<template>
  <div class="report-header">
    <h1 class="report-title">
      {{ result.type === 'tongue' ? '舌诊分析报告' : '面诊分析报告' }}
    </h1>
    <div class="report-meta">
      <span>任务号：{{ result.task_no }}</span>
      <span>耗时：{{ elapsed }} 秒</span>
      <el-tag :type="result.status === 'completed' ? 'success' : result.status === 'failed' ? 'danger' : 'warning'">
        {{ result.status === 'completed' ? '已完成' : result.status === 'failed' ? '失败' : '进行中' }}
      </el-tag>
    </div>
  </div>
</template>

<style scoped>
.report-header {
  text-align: center;
  padding: 24px 0;
  border-bottom: 1px solid #f0f0f0;
  margin-bottom: 24px;
}
.report-title { font-size: 24px; margin: 0 0 12px; color: #303133; }
.report-meta { display: flex; justify-content: center; gap: 16px; color: #909399; font-size: 14px; }
</style>
