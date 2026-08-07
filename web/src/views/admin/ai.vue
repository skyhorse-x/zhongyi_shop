<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'

interface CallRecord {
  id: number
  created_at: string
  model_name: string
  type: string
  duration: string
  cost: number
  status: number
  user?: { name: string }
}

const callRecords = ref<CallRecord[]>([])
const callStats = ref({
  todayCalls: 0,
  todayCost: 0,
  monthCalls: 0,
  monthCost: 0,
})

const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const recordsLoading = ref(false)

import { getAdminToken } from '@/utils/auth'

const getToken = (): string => getAdminToken() || ''

// 加载调用记录
const loadCallRecords = async () => {
  recordsLoading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      limit: pageSize.value.toString(),
    })
    const res = await safeFetch(`/api/v1/admin/ai/logs?${params}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data.data || data.data
      callRecords.value = list.map((item: any) => ({
        id: item.id,
        created_at: item.created_at,
        model_name: item.model_name || '-',
        type: item.type || '-',
        duration: item.duration ? `${item.duration}s` : '-',
        cost: item.cost || 0,
        status: item.status,
        user: item.user,
      }))
      total.value = data.data.total || list.length
    } else {
      ElMessage.error(data.message || '加载调用记录失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载调用记录失败')
  } finally {
    recordsLoading.value = false
  }
}

// 加载统计数据
const loadStats = async () => {
  try {
    const params = new URLSearchParams({ page: '1', limit: '1000' })
    const res = await safeFetch(`/api/v1/admin/ai/logs?${params}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data.data || data.data
      const today = new Date().toISOString().split('T')[0]
      const currentMonth = today.substring(0, 7)

      let todayCalls = 0
      let todayCost = 0
      let monthCalls = 0
      let monthCost = 0

      list.forEach((item: any) => {
        const itemDate = item.created_at?.substring(0, 10)
        const itemMonth = item.created_at?.substring(0, 7)

        if (itemDate === today) {
          todayCalls++
          todayCost += item.cost || 0
        }
        if (itemMonth === currentMonth) {
          monthCalls++
          monthCost += item.cost || 0
        }
      })

      callStats.value = {
        todayCalls,
        todayCost: parseFloat(todayCost.toFixed(2)) || 0,
        monthCalls,
        monthCost: parseFloat(monthCost.toFixed(2)) || 0,
      }
    }
  } catch (e) {
    console.error('加载统计数据失败:', e)
  }
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  loadCallRecords()
}

const handleSizeChange = (size: number) => {
  pageSize.value = size
  currentPage.value = 1
  loadCallRecords()
}

// 分析类型映射
const typeLabels: Record<string, string> = {
  tongue: '舌诊分析',
  face: '面诊分析',
  palm: '手相分析',
  eye: '眼部分析',
  constitution: '体质分析',
  qa: '健康问答',
}

const getTypeLabel = (type: string) => typeLabels[type] || type

onMounted(() => {
  loadCallRecords()
  loadStats()
})
</script>

<template>
  <div class="admin-page-wrapper">
    <div class="page-header">
      <h2 class="page-title">AI 调用记录</h2>
      <p class="page-desc">查看AI模型调用历史和统计</p>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-value">{{ callStats.todayCalls }}</div>
        <div class="stat-label">今日调用次数</div>
      </div>
      <div class="stat-card">
        <div class="stat-value stat-cost">¥{{ callStats.todayCost.toFixed(2) }}</div>
        <div class="stat-label">今日费用</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ callStats.monthCalls }}</div>
        <div class="stat-label">本月调用次数</div>
      </div>
      <div class="stat-card">
        <div class="stat-value stat-cost">¥{{ callStats.monthCost.toFixed(2) }}</div>
        <div class="stat-label">本月费用</div>
      </div>
    </div>

    <!-- 调用记录表格 -->
    <el-card class="card-records">
      <template #header>
        <div class="card-header">
          <span>调用记录</span>
          <el-button size="small" @click="loadCallRecords">
            <el-icon><Refresh /></el-icon> 刷新
          </el-button>
        </div>
      </template>

      <el-table :data="callRecords" v-loading="recordsLoading" border stripe size="small" style="width: 100%">
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="created_at" label="时间" width="170" />
        <el-table-column prop="model_name" label="模型" min-width="120" />
        <el-table-column label="类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small">{{ getTypeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="duration" label="耗时" width="80" align="center" />
        <el-table-column prop="cost" label="费用" width="80" align="right">
          <template #default="{ row }">¥{{ Number(row.cost || 0).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
              {{ row.status === 1 ? '成功' : '失败' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="用户" width="120" align="center">
          <template #default="{ row }">
            {{ row.user?.name || '-' }}
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next, jumper"
          background
          size="small"
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </el-card>
  </div>
</template>

<style scoped>
.admin-page-wrapper { max-width: 100%; width: 100%; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 4px; }
.page-desc { font-size: 14px; color: #999; }

.stats-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #409eff;
  margin-bottom: 8px;
}

.stat-cost {
  color: #f56c6c;
}

.stat-label {
  font-size: 14px;
  color: #909399;
}

.card-records { margin-bottom: 24px; }

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pagination-wrapper {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

/* 手机端适配 */
@media (max-width: 768px) {
  .stats-cards {
    grid-template-columns: repeat(2, 1fr);
  }

  .stat-value { font-size: 20px; }

  .el-table { font-size: 12px; }

  .pagination-wrapper { justify-content: center; }
}
</style>
