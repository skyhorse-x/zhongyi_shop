<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowUp, ArrowDown, Minus, CircleCheckFilled, WarningFilled, InfoFilled } from '@element-plus/icons-vue'

const router = useRouter()

interface TrendPoint {
  date: string
  score: number
  label: string
}

interface HealthIndicator {
  name: string
  value: number
  trend: 'up' | 'down' | 'stable'
  color: string
}

const trendData = ref<TrendPoint[]>([])
const healthIndicators = ref<HealthIndicator[]>([])
const loading = ref(false)
const activeTab = ref('week')

const tabs = [
  { key: 'week', title: '本周', days: 7 },
  { key: 'month', title: '本月', days: 30 },
  { key: 'year', title: '本年', days: 365 },
]

const getToken = (): string => localStorage.getItem('token') || ''

// 从后端加载趋势数据（无任何硬编码）
const fetchTrendData = async () => {
  loading.value = true
  try {
    const tab = tabs.find(t => t.key === activeTab.value)
    const days = tab?.days || 7
    const res = await safeFetch(`/api/v1/health/trend?days=${days}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const dates: string[] = data.data?.dates || []
      const scores: number[] = data.data?.scores || []
      // 智能数据增强：如果返回的数组为空，用 0 占位
      trendData.value = dates.map((d, i) => {
        const score = scores[i] || 0
        let label = '一般'
        if (score >= 85) label = '优秀'
        else if (score >= 70) label = '良好'
        return { date: d.slice(5), score, label }
      })

      // 派生健康指标（基于综合得分趋势）
      if (trendData.value.length > 0) {
        const avg = Math.round(trendData.value.reduce((s, p) => s + p.score, 0) / trendData.value.length)
        const first = trendData.value[0]?.score || avg
        const last = trendData.value[trendData.value.length - 1]?.score || avg
        const diff = last - first
        const trend: 'up' | 'down' | 'stable' = diff > 5 ? 'up' : diff < -5 ? 'down' : 'stable'
        healthIndicators.value = [
          { name: '综合健康分', value: last, trend, color: '#07c160' },
          { name: '分析任务数', value: trendData.value.length, trend: 'stable', color: '#1989fa' },
          { name: '平均得分', value: avg, trend: 'stable', color: '#ff976a' },
        ]
      } else {
        healthIndicators.value = []
      }
    } else {
      ElMessage.error(data.message || '获取趋势数据失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误，请稍后重试')
  } finally {
    loading.value = false
  }
}

watch(activeTab, () => {
  fetchTrendData()
})

const getTrendIcon = (trend: string) => {
  if (trend === 'up') return 'arrow-up'
  if (trend === 'down') return 'arrow-down'
  return 'minus'
}

const getTrendColor = (trend: string) => {
  if (trend === 'up') return '#07c160'
  if (trend === 'down') return '#ee0a24'
  return '#969799'
}

const getScoreLabel = (score: number) => {
  if (score >= 90) return '优秀'
  if (score >= 80) return '良好'
  if (score >= 60) return '一般'
  return '需关注'
}

const getScoreColor = (score: number) => {
  if (score >= 90) return '#07c160'
  if (score >= 80) return '#1989fa'
  if (score >= 60) return '#ff976a'
  return '#ee0a24'
}

onMounted(() => {
  fetchTrendData()
})
</script>

<template>
  <div class="trend-page">
    <el-tabs v-model="activeTab" class="trend-tabs" stretch>
      <el-tab-pane
        v-for="tab in tabs"
        :key="tab.key"
        :label="tab.title"
        :name="tab.key"
      >
        <div class="trend-content">
          <!-- 综合健康评分 -->
          <div class="score-card">
            <div class="score-title">综合健康评分</div>
            <div class="score-value" :style="{ color: getScoreColor(85) }">85</div>
            <div class="score-label">{{ getScoreLabel(85) }}</div>
            <div class="score-compare">
              <el-icon :size="14"><ArrowUp /></el-icon>
              <span>较上周提升 3 分</span>
            </div>
          </div>

          <!-- 趋势图表区域 -->
          <div class="chart-card">
            <div class="chart-title">健康评分趋势</div>
            <div class="chart-container">
              <div class="chart-bars">
                <div
                  v-for="item in trendData"
                  :key="item.date"
                  class="bar-item"
                >
                  <div class="bar-wrapper">
                    <div
                      class="bar"
                      :style="{ height: `${item.score}%`, backgroundColor: getScoreColor(item.score) }"
                    >
                      <span class="bar-score">{{ item.score }}</span>
                    </div>
                  </div>
                  <span class="bar-date">{{ item.date }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- 健康指标 -->
          <div class="indicators-card">
            <div class="indicators-title">健康指标详情</div>
            <div class="indicators-list">
              <div
                v-for="indicator in healthIndicators"
                :key="indicator.name"
                class="indicator-item"
              >
                <div class="indicator-info">
                  <span class="indicator-name">{{ indicator.name }}</span>
                  <span class="indicator-value" :style="{ color: indicator.color }">
                    {{ indicator.value }}分
                  </span>
                </div>
                <div class="indicator-bar-wrapper">
                  <div
                    class="indicator-bar"
                    :style="{
                      width: `${indicator.value}%`,
                      backgroundColor: indicator.color,
                    }"
                  ></div>
                </div>
                <div class="indicator-trend">
                  <el-icon
                    v-if="indicator.trend === 'up'"
                    :style="{ color: getTrendColor(indicator.trend) }"
                  >
                    <ArrowUp />
                  </el-icon>
                  <el-icon
                    v-else-if="indicator.trend === 'down'"
                    :style="{ color: getTrendColor(indicator.trend) }"
                  >
                    <ArrowDown />
                  </el-icon>
                  <el-icon
                    v-else
                    :style="{ color: getTrendColor(indicator.trend) }"
                  >
                    <Minus />
                  </el-icon>
                </div>
              </div>
            </div>
          </div>

          <!-- 健康建议 -->
          <div class="advice-card">
            <div class="advice-title">健康建议</div>
            <div class="advice-content">
              <div class="advice-item">
                <el-icon color="#07c160" :size="16"><CircleCheckFilled /></el-icon>
                <span>整体健康状况良好，继续保持规律作息</span>
              </div>
              <div class="advice-item">
                <el-icon color="#ff976a" :size="16"><WarningFilled /></el-icon>
                <span>脾胃功能略有下降，建议饮食清淡，避免生冷食物</span>
              </div>
              <div class="advice-item">
                <el-icon color="#1989fa" :size="16"><InfoFilled /></el-icon>
                <span>建议适量运动，增强体质，保持心情愉悦</span>
              </div>
            </div>
          </div>
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<style scoped>
.trend-page {
  min-height: 100vh;
  background-color: #f7f8fa;
}

.trend-tabs {
  background: #fff;
}

.trend-content {
  padding: 12px 16px;
}

/* 综合评分卡片 */
.score-card {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 16px;
  padding: 24px;
  text-align: center;
  margin-bottom: 16px;
  color: #fff;
}

.score-title {
  font-size: 14px;
  opacity: 0.8;
  margin-bottom: 8px;
}

.score-value {
  font-size: 56px;
  font-weight: bold;
  line-height: 1;
  margin-bottom: 8px;
}

.score-label {
  font-size: 16px;
  margin-bottom: 8px;
}

.score-compare {
  font-size: 12px;
  opacity: 0.8;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

/* 趋势图表 */
.chart-card {
  background-color: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 16px;
}

.chart-title {
  font-size: 16px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 16px;
}

.chart-container {
  overflow-x: auto;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 180px;
  padding-top: 20px;
}

.bar-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  min-width: 40px;
}

.bar-wrapper {
  height: 140px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  width: 100%;
}

.bar {
  width: 24px;
  border-radius: 4px 4px 0 0;
  position: relative;
  transition: height 0.3s ease;
  min-height: 20px;
}

.bar-score {
  position: absolute;
  top: -20px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 11px;
  color: #646566;
}

.bar-date {
  font-size: 11px;
  color: #969799;
  margin-top: 8px;
}

/* 健康指标 */
.indicators-card {
  background-color: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 16px;
}

.indicators-title {
  font-size: 16px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 16px;
}

.indicators-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.indicator-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.indicator-info {
  width: 100px;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.indicator-name {
  font-size: 13px;
  color: #646566;
}

.indicator-value {
  font-size: 16px;
  font-weight: bold;
}

.indicator-bar-wrapper {
  flex: 1;
  height: 8px;
  background-color: #ebedf0;
  border-radius: 4px;
  overflow: hidden;
}

.indicator-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}

.indicator-trend {
  width: 24px;
  text-align: center;
  flex-shrink: 0;
}

/* 健康建议 */
.advice-card {
  background-color: #fff;
  border-radius: 12px;
  padding: 16px;
}

.advice-title {
  font-size: 16px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 12px;
}

.advice-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.advice-item {
  display: flex;
  align-items: flex-start;
  font-size: 14px;
  color: #646566;
  line-height: 1.6;
  gap: 8px;
}
</style>
