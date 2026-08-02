<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Check } from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()

// 从路由参数获取任务编号
const taskNo = computed(() => route.params.taskNo as string)

// 加载中状态
const loading = ref(false)

// 体质分析结果数据
const result = ref({
  constitutionType: '',
  score: 0,
  description: '',
  characteristics: [] as string[],
  suggestions: [] as { category: string; content: string }[],
  scores: [] as { name: string; score: number; isMain: boolean }[],
})

const getToken = (): string => localStorage.getItem('token') || ''

// 体质类型对应颜色
const constitutionColor = computed(() => {
  const colorMap: Record<string, string> = {
    '平和质': '#52c41a',
    '气虚质': '#faad14',
    '阳虚质': '#1890ff',
    '阴虚质': '#722ed1',
    '痰湿质': '#fa8c16',
    '湿热质': '#f5222d',
    '血瘀质': '#eb2f96',
    '气郁质': '#13c2c2',
    '特禀质': '#8c8c8c',
  }
  return colorMap[result.value.constitutionType] || '#4f8cff'
})

// 加载测试结果
const loadResult = async () => {
  if (!taskNo.value) {
    ElMessage.error('任务编号无效')
    router.back()
    return
  }
  loading.value = true
  try {
    const res = await safeFetch(`/api/v1/constitution/report/${taskNo.value}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()

    if (data.code === 0) {
      const report = data.data
      result.value = {
        constitutionType: report.constitution_type,
        score: Math.max(...(report.scores ? Object.values(report.scores) as number[] : [0])),
        description: report.features || '',
        characteristics: report.features ? [report.features] : [],
        suggestions: [
          { category: '饮食建议', content: report.diet_advice || '请咨询专业中医师获取饮食建议' },
          { category: '运动建议', content: report.exercise_advice || '请咨询专业中医师获取运动建议' },
          { category: '起居建议', content: report.life_advice || '请咨询专业中医师获取起居建议' },
          { category: '情志建议', content: report.emotion_advice || '请咨询专业中医师获取情志调节建议' },
        ],
        scores: report.scores
          ? Object.entries(report.scores).map(([name, score]) => ({
              name,
              score: score as number,
              isMain: name === report.constitution_type,
            }))
          : [],
      }
    } else {
      ElMessage.error(data.message || '获取报告失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '获取报告失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadResult()
})
</script>

<template>
  <div class="constitution-result-page" v-loading="loading" element-loading-text="正在加载报告...">
    <template v-if="!loading && result.constitutionType">
      <!-- 报告头部 -->
      <div class="result-header">
        <div class="header-top">
          <div class="task-no">报告编号：{{ taskNo }}</div>
          <div class="report-date">测试日期：{{ new Date().toLocaleDateString('zh-CN') }}</div>
        </div>
        <div class="constitution-badge" :style="{ background: constitutionColor }">
          {{ result.constitutionType }}
        </div>
        <div class="score-section">
          <div class="score-circle">
            <el-progress
              type="circle"
              :percentage="result.score"
              :color="constitutionColor"
              :stroke-width="8"
              :width="100"
            >
              <div class="score-text">
                <div class="score-value">{{ result.score }}</div>
                <div class="score-label">分</div>
              </div>
            </el-progress>
          </div>
        </div>
      </div>

      <!-- 体质描述 -->
      <div class="result-section">
        <div class="section-title">体质概述</div>
        <div class="section-content">
          <p>{{ result.description }}</p>
        </div>
      </div>

      <!-- 主要特征 -->
      <div v-if="result.characteristics.length > 0" class="result-section">
        <div class="section-title">主要特征</div>
        <div class="section-content">
          <div class="characteristic-list">
            <div
              v-for="(item, index) in result.characteristics"
              :key="index"
              class="characteristic-item"
            >
              <el-icon :size="16" color="#52c41a"><Check /></el-icon>
              <span>{{ item }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 调理建议 -->
      <div class="result-section">
        <div class="section-title">调理建议</div>
        <div class="section-content">
          <div class="suggestion-list">
            <div
              v-for="(item, index) in result.suggestions"
              :key="index"
              class="suggestion-card"
            >
              <div class="suggestion-category">{{ item.category }}</div>
              <div class="suggestion-content">{{ item.content }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 九种体质得分 -->
      <div v-if="result.scores.length > 0" class="result-section">
        <div class="section-title">九种体质得分</div>
        <div class="section-content">
          <div class="scores-list">
            <div
              v-for="(item, index) in result.scores"
              :key="index"
              class="score-bar-item"
            >
              <div class="score-bar-label">
                <span :class="{ 'main-type': item.isMain }">{{ item.name }}</span>
                <el-tag v-if="item.isMain" type="primary" size="small">主导体质</el-tag>
              </div>
              <div class="score-bar-track">
                <div
                  class="score-bar-fill"
                  :style="{
                    width: item.score + '%',
                    background: item.isMain ? constitutionColor : '#dcdee0',
                  }"
                ></div>
              </div>
              <div class="score-bar-value">{{ item.score }}分</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 底部操作 -->
      <div class="result-actions">
        <el-button
          round
          plain
          type="primary"
          @click="router.push('/constitution/test')"
        >
          重新测试
        </el-button>
        <el-button
          round
          type="primary"
          @click="router.push('/qa/chat')"
        >
          咨询调理方案
        </el-button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.constitution-result-page {
  padding: 16px;
  padding-bottom: 100px;
  min-height: 100vh;
  background: #f7f8fa;
}

.result-header {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.header-top {
  display: flex;
  justify-content: space-between;
  margin-bottom: 16px;
  font-size: 12px;
  color: #969799;
}

.task-no {
  font-family: monospace;
}

.constitution-badge {
  display: inline-block;
  padding: 6px 20px;
  border-radius: 20px;
  color: #fff;
  font-size: 18px;
  font-weight: bold;
  margin-bottom: 20px;
}

.score-section {
  display: flex;
  justify-content: center;
}

.score-circle {
  position: relative;
}

.score-text {
  text-align: center;
}

.score-value {
  font-size: 24px;
  font-weight: bold;
  color: #323233;
}

.score-label {
  font-size: 12px;
  color: #969799;
}

.result-section {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.section-title {
  font-size: 16px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 12px;
  padding-left: 8px;
  border-left: 3px solid #4f8cff;
}

.section-content {
  font-size: 14px;
  color: #646566;
  line-height: 1.8;
}

.characteristic-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.characteristic-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #323233;
}

.suggestion-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.suggestion-card {
  background: #f7f8fa;
  border-radius: 8px;
  padding: 12px;
}

.suggestion-category {
  font-size: 13px;
  font-weight: bold;
  color: #4f8cff;
  margin-bottom: 6px;
}

.suggestion-content {
  font-size: 13px;
  color: #646566;
  line-height: 1.6;
}

.scores-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.score-bar-item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.score-bar-label {
  width: 90px;
  font-size: 13px;
  color: #323233;
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.score-bar-label .main-type {
  font-weight: bold;
  color: #4f8cff;
}

.score-bar-track {
  flex: 1;
  height: 10px;
  background: #f5f5f5;
  border-radius: 5px;
  overflow: hidden;
}

.score-bar-fill {
  height: 100%;
  border-radius: 5px;
  transition: width 0.6s ease;
}

.score-bar-value {
  width: 45px;
  font-size: 12px;
  color: #969799;
  text-align: right;
  flex-shrink: 0;
}

.result-actions {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 12px 16px;
  background: #fff;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
  display: flex;
  gap: 12px;
}
</style>
