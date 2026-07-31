<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'

interface Promoter {
  id: number
  user_id: number
  user?: { name: string; nickname: string; mobile: string }
  invite_count: number
  consume_count: number
  total_commission: number
  withdrawable_commission: number
  frozen_commission: number
  status: number
  created_at: string
}

const searchName = ref('')
const searchStatus = ref('')
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const loading = ref(false)

const detailVisible = ref(false)
const detailLoading = ref(false)
const detailData = ref<any | null>(null)

const statusOptions = [
  { value: '', label: '全部' },
  { value: '1', label: '启用' },
  { value: '0', label: '禁用' },
]

const tableData = ref<Promoter[]>([])

// 统计数据（从列表计算）
const stats = computed(() => {
  const totalCount = total.value
  const todayNew = tableData.value.filter(item => {
    const today = new Date().toISOString().split('T')[0]
    return item.created_at?.startsWith(today)
  }).length
  const totalCommission = tableData.value.reduce((sum, item) => sum + (item.total_commission || 0), 0)
  const pendingWithdraw = tableData.value.reduce((sum, item) => sum + (item.frozen_commission || 0), 0)
  
  return {
    totalCount,
    todayNew,
    totalCommission: parseFloat(totalCommission.toFixed(2)),
    pendingWithdraw: parseFloat(pendingWithdraw.toFixed(2)),
  }
})

const getToken = (): string => localStorage.getItem('admin_token') || ''

// 加载推广员列表
const loadPromoters = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      limit: pageSize.value.toString(),
    })
    if (searchName.value) params.append('nickname', searchName.value)
    if (searchStatus.value) params.append('status', searchStatus.value)

    const res = await fetch(`/api/v1/admin/promoters?${params}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data.data || data.data
      tableData.value = list
      total.value = data.data.total || list.length
    } else {
      ElMessage.error(data.message || '加载推广员列表失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载推广员列表失败')
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  currentPage.value = 1
  loadPromoters()
}

function handleReset() {
  searchName.value = ''
  searchStatus.value = ''
  currentPage.value = 1
  loadPromoters()
}

function handlePageChange(page: number) {
  currentPage.value = page
  loadPromoters()
}

function handleSizeChange(size: number) {
  pageSize.value = size
  currentPage.value = 1
  loadPromoters()
}

async function viewDetail(row: Promoter) {
  detailVisible.value = true
  detailLoading.value = true
  try {
    const res = await fetch(`/api/v1/admin/promoters/${row.id}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      detailData.value = data.data
    } else {
      ElMessage.error(data.message || '加载详情失败')
      // 兜底：仍显示列表基础信息
      detailData.value = { promoter: row, stats: {}, recent_commissions: [] }
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误')
    detailData.value = { promoter: row, stats: {}, recent_commissions: [] }
  } finally {
    detailLoading.value = false
  }
}

async function toggleStatus(row: Promoter) {
  try {
    const res = await fetch(`/api/v1/admin/promoters/${row.id}/toggle`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(data.message || '操作成功')
      loadPromoters()
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误')
  }
}

onMounted(() => {
  loadPromoters()
})
</script>

<template>
  <div class="admin-page-wrapper">
    <div class="page-header">
      <h2 class="page-title">推广管理</h2>
      <p class="page-desc">推广员列表与佣金管理</p>
    </div>

    <el-card class="stats-card">
      <template #header><span>推广统计概览</span></template>
      <el-descriptions :column="4" border>
        <el-descriptions-item label="推广员总数" label-align="center">
          <span class="stat-number">{{ stats.totalCount }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="今日新增" label-align="center">
          <span class="stat-number stat-green">{{ stats.todayNew }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="累计佣金" label-align="center">
          <span class="stat-number">¥{{ stats.totalCommission.toFixed(2) }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="待审核提现" label-align="center">
          <span class="stat-number stat-orange">¥{{ stats.pendingWithdraw.toFixed(2) }}</span>
        </el-descriptions-item>
      </el-descriptions>
    </el-card>

    <el-card class="list-card">
      <template #header><span>推广员列表</span></template>

      <el-form :model="{ name: searchName, status: searchStatus }" inline>
        <el-form-item label="昵称">
          <el-input v-model="searchName" placeholder="请输入昵称" clearable style="width: 200px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchStatus" placeholder="全部" clearable style="width: 120px">
            <el-option
              v-for="opt in statusOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="tableData" border stripe style="width: 100%" v-loading="loading">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="昵称" min-width="120">
          <template #default="{ row }">
            {{ row.user?.nickname || row.user?.name || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="手机号" width="120">
          <template #default="{ row }">
            {{ row.user?.mobile || '-' }}
          </template>
        </el-table-column>
        <el-table-column prop="invite_count" label="邀请人数" width="90" align="center" />
        <el-table-column prop="consume_count" label="消费人数" width="90" align="center" />
        <el-table-column label="累计佣金" width="110" align="right">
          <template #default="{ row }">¥{{ (row.total_commission || 0).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column label="可提现金额" width="110" align="right">
          <template #default="{ row }">¥{{ (row.withdrawable_commission || 0).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
              {{ row.status === 1 ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="开通时间" width="170">
          <template #default="{ row }">{{ row.created_at || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="140" align="center" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="viewDetail(row)">查看</el-button>
            <el-button size="small" :type="row.status === 1 ? 'warning' : 'success'" link @click="toggleStatus(row)">
              {{ row.status === 1 ? '禁用' : '启用' }}
            </el-button>
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
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </el-card>

    <el-dialog v-model="detailVisible" title="推广员详情" width="680px" v-loading="detailLoading">
      <template v-if="detailData">
        <!-- 基本信息 -->
        <h4 class="detail-section-title">基本信息</h4>
        <el-descriptions :column="2" border>
          <el-descriptions-item label="推广员ID" label-align="center">{{ detailData.promoter.id }}</el-descriptions-item>
          <el-descriptions-item label="用户ID" label-align="center">{{ detailData.promoter.user_id }}</el-descriptions-item>
          <el-descriptions-item label="昵称" label-align="center">
            {{ detailData.promoter.user?.nickname || detailData.promoter.user?.name || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="手机号" label-align="center">
            {{ detailData.promoter.user?.mobile || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="邀请码" label-align="center">
            <code>{{ detailData.promoter.invite_code || '-' }}</code>
          </el-descriptions-item>
          <el-descriptions-item label="状态" label-align="center">
            <el-tag :type="(detailData.promoter.is_enabled ?? 1) === 1 ? 'success' : 'danger'" size="small">
              {{ (detailData.promoter.is_enabled ?? 1) === 1 ? '启用' : '禁用' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="开通时间" label-align="center" :span="2">
            {{ detailData.promoter.created_at || '-' }}
          </el-descriptions-item>
        </el-descriptions>

        <!-- 佣金统计 -->
        <h4 class="detail-section-title">佣金统计</h4>
        <el-descriptions :column="3" border>
          <el-descriptions-item label="累计佣金" label-align="center">
            <span class="stat-money">¥{{ Number(detailData.stats.total_commission || 0).toFixed(2) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="可提现" label-align="center">
            <span class="stat-money stat-green">¥{{ Number(detailData.stats.available_commission || 0).toFixed(2) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="冻结中" label-align="center">
            <span class="stat-money stat-orange">¥{{ Number(detailData.stats.frozen_commission || 0).toFixed(2) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="已提现" label-align="center">
            <span class="stat-money">¥{{ Number(detailData.stats.withdrawn_commission || 0).toFixed(2) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="直属用户" label-align="center">
            <span class="stat-number">{{ detailData.stats.direct_users || 0 }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="消费订单" label-align="center">
            <span class="stat-number">{{ detailData.stats.paid_orders || 0 }}</span>
          </el-descriptions-item>
        </el-descriptions>

        <!-- 最近佣金 -->
        <h4 class="detail-section-title">最近佣金明细</h4>
        <el-table :data="detailData.recent_commissions || []" size="small" border>
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column prop="order_no" label="订单号" min-width="160" />
          <el-table-column label="佣金金额" width="120" align="right">
            <template #default="{ row }">
              <span class="stat-money stat-green">¥{{ Number(row.amount || 0).toFixed(2) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="100" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'warning'" size="small">
                {{ row.status === 1 ? '已结算' : '冻结中' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="时间" width="160" />
          <template #empty>
            <span style="color: #c8c9cc">暂无佣金记录</span>
          </template>
        </el-table>
      </template>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.admin-page-wrapper { max-width: 100%; width: 100%; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 4px; }
.page-desc { font-size: 14px; color: #999; }

.stats-card { margin-bottom: 16px; }
.list-card { margin-bottom: 24px; }

.stat-number { font-size: 20px; font-weight: 700; color: #409eff; }
.stat-money { font-size: 16px; font-weight: 700; color: #409eff; }
.stat-green { color: #67c23a; }
.stat-orange { color: #e6a23c; }

.detail-section-title {
  font-size: 14px;
  font-weight: 600;
  color: #323233;
  margin: 16px 0 8px;
  padding-left: 8px;
  border-left: 3px solid #07c160;
}
.detail-section-title:first-of-type { margin-top: 0; }

.pagination-wrapper { margin-top: 20px; display: flex; justify-content: flex-end; }
</style>
