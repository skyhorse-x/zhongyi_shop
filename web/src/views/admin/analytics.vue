<script setup lang="ts">
/**
 * 运营 BI 看板
 * 收入、用户、漏斗、留存
 */
import { ref, onMounted, computed } from 'vue'
import request from '@/api/request'
import dayjs from 'dayjs'

const loading = ref(false)
const overview = ref<any>({})
const funnel = ref<any>({})
const retention = ref<any[]>([])
const revenue = ref<any[]>([])
const userGrowth = ref<any[]>([])
const topPromoters = ref<any[]>([])
const distribution = ref<Record<string, number>>({})

const load = async () => {
  loading.value = true
  try {
    const [o, f, r, rev, ug, tp, d] = await Promise.all([
      request.get('/admin/analytics/overview'),
      request.get('/admin/analytics/funnel', { params: { days: 30 } }),
      request.get('/admin/analytics/retention', { params: { days: 7 } }),
      request.get('/admin/analytics/revenue', { params: { days: 30 } }),
      request.get('/admin/analytics/user-growth', { params: { days: 30 } }),
      request.get('/admin/analytics/top-promoters', { params: { limit: 10 } }),
      request.get('/admin/analytics/analysis-distribution', { params: { days: 30 } }),
    ])
    overview.value = (o as any).today || o
    funnel.value = f
    retention.value = r as any
    revenue.value = rev as any
    userGrowth.value = ug as any
    topPromoters.value = (tp as any).data || tp || []
    distribution.value = (d as any).data || d || {}
  } finally {
    loading.value = false
  }
}

const formatMoney = (v: number) => `¥${Number(v || 0).toFixed(2)}`
const formatPct = (v: number) => `${Number(v || 0)}%`
</script>

<template>
  <div class="analytics-page" v-loading="loading">
    <!-- 核心指标 -->
    <el-row :gutter="16">
      <el-col :span="6"><el-card><div class="metric-label">今日收入</div><div class="metric-value">{{ formatMoney(overview.today_revenue) }}</div></el-card></el-col>
      <el-col :span="6"><el-card><div class="metric-label">今日订单</div><div class="metric-value">{{ overview.today_orders || 0 }}</div></el-card></el-col>
      <el-col :span="6"><el-card><div class="metric-label">今日新增</div><div class="metric-value">{{ overview.today_new_users || 0 }}</div></el-card></el-col>
      <el-col :span="6"><el-card><div class="metric-label">今日活跃</div><div class="metric-value">{{ overview.today_active || 0 }}</div></el-card></el-col>
    </el-row>

    <el-row :gutter="16" class="mt-3">
      <el-col :span="6"><el-card><div class="metric-label">本月收入</div><div class="metric-value">{{ formatMoney(overview.month_revenue) }}</div></el-card></el-col>
      <el-col :span="6"><el-card><div class="metric-label">累计收入</div><div class="metric-value">{{ formatMoney(overview.total_revenue) }}</div></el-card></el-col>
      <el-col :span="6"><el-card><div class="metric-label">付费用户</div><div class="metric-value">{{ overview.total_paying || 0 }}</div></el-card></el-col>
      <el-col :span="6"><el-card><div class="metric-label">总用户数</div><div class="metric-value">{{ overview.total_users || 0 }}</div></el-card></el-col>
    </el-row>

    <el-row :gutter="16" class="mt-3">
      <!-- 漏斗 -->
      <el-col :span="12">
        <el-card>
          <template #header>转化漏斗（30天）</template>
          <div v-for="step in funnel.steps" :key="step.name" class="funnel-step">
            <div class="funnel-name">{{ step.name }}</div>
            <div class="funnel-bar" :style="{ width: (step.count / (funnel.steps[0]?.count || 1) * 100) + '%' }">
              {{ step.count }}
            </div>
          </div>
        </el-card>
      </el-col>

      <!-- 留存 -->
      <el-col :span="12">
        <el-card>
          <template #header>留存分析（最近 7 天）</template>
          <el-table :data="retention" size="small">
            <el-table-column prop="date" label="日期" width="100" />
            <el-table-column prop="registered" label="注册" width="80" />
            <el-table-column label="次日">
              <template #default="{ row }">{{ formatPct(row.d1) }}</template>
            </el-table-column>
            <el-table-column label="7日">
              <template #default="{ row }">{{ formatPct(row.d7) }}</template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="mt-3">
      <!-- 收入趋势 -->
      <el-col :span="12">
        <el-card>
          <template #header>收入趋势（30天）</template>
          <el-table :data="revenue" size="small" max-height="300">
            <el-table-column prop="date" label="日期" width="100" />
            <el-table-column label="收入">
              <template #default="{ row }">{{ formatMoney(row.revenue) }}</template>
            </el-table-column>
            <el-table-column prop="orders" label="订单数" width="80" />
          </el-table>
        </el-card>
      </el-col>

      <!-- 推广员 TOP10 -->
      <el-col :span="12">
        <el-card>
          <template #header>推广员 TOP 10</template>
          <el-table :data="topPromoters" size="small" max-height="300">
            <el-table-column label="排名" width="60">
              <template #default="{ $index }">{{ $index + 1 }}</template>
            </el-table-column>
            <el-table-column label="用户">
              <template #default="{ row }">{{ row.user?.nickname || row.invite_code }}</template>
            </el-table-column>
            <el-table-column label="总佣金">
              <template #default="{ row }">{{ formatMoney(row.total_commission) }}</template>
            </el-table-column>
            <el-table-column label="可提现">
              <template #default="{ row }">{{ formatMoney(row.available_commission) }}</template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <el-card class="mt-3">
      <template #header>AI 使用分布</template>
      <div v-for="(count, type) in distribution" :key="type" class="dist-item">
        <span>{{ type === 'tongue' ? '舌诊' : type === 'face' ? '面诊' : type === 'constitution' ? '体质' : type }}</span>
        <el-progress :percentage="count / Math.max(...Object.values(distribution).map(Number)) * 100" :format="() => count" />
      </div>
    </el-card>
  </div>
</template>

<style scoped>
.analytics-page { padding: 16px; }
.metric-label { color: #909399; font-size: 13px; }
.metric-value { font-size: 26px; font-weight: 500; margin-top: 6px; color: #303133; }
.mt-3 { margin-top: 12px; }
.funnel-step { display: flex; align-items: center; margin-bottom: 8px; }
.funnel-name { width: 80px; color: #606266; }
.funnel-bar {
  background: linear-gradient(90deg, #1989fa, #67c23a);
  color: #fff;
  padding: 6px 12px;
  border-radius: 4px;
  min-width: 60px;
  text-align: right;
}
.dist-item { display: flex; align-items: center; gap: 12px; margin-bottom: 6px; }
.dist-item span { width: 60px; }
</style>