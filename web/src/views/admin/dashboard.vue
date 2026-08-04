<script setup lang="ts">
import { shallowRef, ref, onMounted } from 'vue'
import { UserFilled, Plus, List, Coin, TrendCharts, Cpu, Money } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'
import { buildAdminHeaders } from '@/utils/auth'

const stats = shallowRef([
  { title: '今日访问', value: '--', color: '#1989fa', icon: TrendCharts },
  { title: '今日注册', value: '--', color: '#07c160', icon: Plus },
  { title: '今日付费', value: '--', color: '#ff976a', icon: List },
  { title: '今日收入', value: '-- 元', color: '#ee0a24', icon: Coin },
  { title: '今日佣金', value: '-- 元', color: '#7232dd', icon: Money },
  { title: 'AI调用', value: '--', color: '#07c160', icon: Cpu },
  { title: 'AI成本', value: '-- 元', color: '#f7a35c', icon: Coin },
  { title: '今日利润', value: '-- 元', color: '#ee0a24', icon: TrendCharts },
])

const recentItems = shallowRef([
  { label: '暂无数据', desc: '系统运行正常，等待用户注册和订单产生' },
])

// 用户总数、总订单数、总佣金等长期统计数据
const totalStats = shallowRef([
  { title: '用户总数', value: '--', color: '#1989fa', icon: UserFilled },
  { title: '总订单数', value: '--', color: '#7232dd', icon: List },
  { title: '总佣金', value: '-- 元', color: '#07c160', icon: Money },
  { title: '总利润', value: '-- 元', color: '#ee0a24', icon: TrendCharts },
])

const loading = ref(false)

// 加载仪表盘数据
const loadDashboardData = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/admin/dashboard', {
      headers: {
        ...buildAdminHeaders(),
        'Accept': 'application/json',
      },
    })
    const data = await res.json()

    if (data.code === 0) {
      const dashboardData = data.data

      // 更新今日数据
      stats.value = [
        { title: '今日访问', value: dashboardData.today_visits || 0, color: '#1989fa', icon: TrendCharts },
        { title: '今日注册', value: dashboardData.today_register || 0, color: '#07c160', icon: Plus },
        { title: '今日付费', value: dashboardData.today_paid || 0, color: '#ff976a', icon: List },
        { title: '今日收入', value: (dashboardData.today_income || 0) + ' 元', color: '#ee0a24', icon: Coin },
        { title: '今日佣金', value: (dashboardData.today_commission || 0) + ' 元', color: '#7232dd', icon: Money },
        { title: 'AI调用', value: dashboardData.today_ai_calls || 0, color: '#07c160', icon: Cpu },
        { title: 'AI成本', value: (dashboardData.today_ai_cost || 0) + ' 元', color: '#f7a35c', icon: Coin },
        { title: '今日利润', value: (dashboardData.today_profit || 0) + ' 元', color: '#ee0a24', icon: TrendCharts },
      ]

      // 更新累计数据
      totalStats.value = [
        { title: '用户总数', value: dashboardData.total_users || 0, color: '#1989fa', icon: UserFilled },
        { title: '总订单数', value: dashboardData.total_orders || 0, color: '#7232dd', icon: List },
        { title: '总佣金', value: (dashboardData.total_commission || 0) + ' 元', color: '#07c160', icon: Money },
        { title: '总利润', value: (dashboardData.total_profit || 0) + ' 元', color: '#ee0a24', icon: TrendCharts },
      ]
    }
  } catch (error) {
    console.error('加载仪表盘数据失败:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadDashboardData()
})
</script>

<template>
  <div class="dashboard-page">
    <div class="page-header">
      <h2 class="page-title">仪表盘</h2>
      <p class="page-desc">数据概览</p>
    </div>

    <!-- 今日数据 -->
    <div class="section-label">今日数据</div>
    <div class="stats-grid" v-loading="loading">
      <div v-for="item in stats" :key="item.title" class="stat-card" :style="{ borderTopColor: item.color }">
        <div class="stat-info">
          <div class="stat-value" :style="{ color: item.color }">{{ item.value }}</div>
          <div class="stat-title">{{ item.title }}</div>
        </div>
        <el-icon class="stat-icon" :style="{ color: item.color }" :size="28">
          <component :is="item.icon" />
        </el-icon>
      </div>
    </div>

    <!-- 累计数据 -->
    <div class="section-label">累计数据</div>
    <div class="stats-grid mini" v-loading="loading">
      <div v-for="item in totalStats" :key="item.title" class="stat-card" :style="{ borderTopColor: item.color }">
        <div class="stat-info">
          <div class="stat-value" :style="{ color: item.color }">{{ item.value }}</div>
          <div class="stat-title">{{ item.title }}</div>
        </div>
        <el-icon class="stat-icon" :style="{ color: item.color }" :size="24">
          <component :is="item.icon" />
        </el-icon>
      </div>
    </div>

    <!-- 最近动态 -->
    <div class="section-label">最近动态</div>
    <el-card shadow="never">
      <div v-for="(item, i) in recentItems" :key="i" class="timeline-item">
        <div class="timeline-dot" />
        <div class="timeline-content">
          <div class="timeline-label">{{ item.label }}</div>
          <div class="timeline-desc">{{ item.desc }}</div>
        </div>
      </div>
    </el-card>
  </div>
</template>

<style scoped>
.dashboard-page { max-width: 100%; }

.page-header { margin-bottom: 20px; }
.page-title { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 4px; }
.page-desc { font-size: 14px; color: #999; }

.section-label {
  font-size: 15px;
  font-weight: 500;
  color: #555;
  margin-bottom: 12px;
  margin-top: 20px;
}
.section-label:first-of-type { margin-top: 0; }

/* 统计卡片 */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 4px;
}

.stats-grid.mini {
  grid-template-columns: repeat(4, 1fr);
}

.stat-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px 18px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-top: 3px solid;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.stat-value {
  font-size: 26px;
  font-weight: bold;
  margin-bottom: 4px;
}

.stat-title {
  font-size: 13px;
  color: #999;
}

.stat-icon { opacity: 0.5; }

/* 时间线 */
.timeline-item {
  display: flex;
  gap: 12px;
  padding-bottom: 12px;
}

.timeline-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #1989fa;
  margin-top: 6px;
  flex-shrink: 0;
}

.timeline-content { flex: 1; }
.timeline-label { font-size: 14px; color: #333; font-weight: 500; }
.timeline-desc { font-size: 12px; color: #999; margin-top: 2px; }

/* 响应式 */
@media (max-width: 768px) {
  .stats-grid,
  .stats-grid.mini {
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
  }

  .stat-card {
    padding: 12px;
  }

  .stat-value {
    font-size: 18px;
  }

  .stat-title {
    font-size: 11px;
  }

  .stat-icon {
    font-size: 20px !important;
  }

  .section-label {
    font-size: 14px;
    margin-bottom: 8px;
    margin-top: 16px;
  }

  .page-title {
    font-size: 18px;
  }

  .page-desc {
    font-size: 12px;
  }
}

@media (max-width: 380px) {
  .stats-grid,
  .stats-grid.mini {
    grid-template-columns: 1fr;
  }
}
</style>
