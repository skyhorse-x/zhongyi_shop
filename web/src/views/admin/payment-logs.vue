<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { getAdminToken } from '@/utils/auth'
import { Search, Refresh, CreditCard } from '@element-plus/icons-vue'

const loading = ref(false)
const keyword = ref('')
const payType = ref('')
const actionType = ref('')
const status = ref('')
const startDate = ref('')
const endDate = ref('')
const tableData = ref<any[]>([])
const pagination = ref({
  current: 1,
  pageSize: 20,
  total: 0,
})

const payTypeOptions = [
  { label: '微信支付', value: 'wechat' },
  { label: '支付宝', value: 'alipay' },
  { label: '余额支付', value: 'balance' },
]

const actionOptions = [
  { label: '支付', value: '0' },
  { label: '退款', value: '1' },
]

const statusOptions = [
  { label: '待处理', value: '0' },
  { label: '成功', value: '1' },
  { label: '失败', value: '2' },
  { label: '处理中', value: '3' },
]

const fetchData = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: pagination.value.current.toString(),
      per_page: pagination.value.pageSize.toString(),
    })
    if (keyword.value) params.set('keyword', keyword.value)
    if (payType.value) params.set('pay_type', payType.value)
    if (actionType.value !== '') params.set('action', actionType.value)
    if (status.value !== '') params.set('status', status.value)
    if (startDate.value) params.set('start_date', startDate.value)
    if (endDate.value) params.set('end_date', endDate.value)

    const res = await safeFetch(`/api/v1/admin/payment-logs?${params}`, {
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      tableData.value = data.data.data
      pagination.value.total = data.data.total
    } else {
      ElMessage.error(data.message || '获取数据失败')
    }
  } catch (e) {
    console.error('获取支付流水失败:', e)
    ElMessage.error('获取数据失败')
  } finally {
    loading.value = false
  }
}

const resetSearch = () => {
  keyword.value = ''
  payType.value = ''
  actionType.value = ''
  status.value = ''
  startDate.value = ''
  endDate.value = ''
  pagination.value.current = 1
  fetchData()
}

const handlePageChange = (page: number) => {
  pagination.value.current = page
  fetchData()
}

const handleSizeChange = (size: number) => {
  pagination.value.pageSize = size
  pagination.value.current = 1
  fetchData()
}

const formatDate = (date: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('zh-CN')
}

const getStatusColor = (status: number) => {
  const colors: Record<number, string> = {
    0: 'warning',
    1: 'success',
    2: 'danger',
    3: 'info',
  }
  return colors[status] || 'info'
}

const getStatusText = (status: number) => {
  const texts: Record<number, string> = {
    0: '待处理',
    1: '成功',
    2: '失败',
    3: '处理中',
  }
  return texts[status] || '未知'
}

const getPayTypeColor = (type: string) => {
  const colors: Record<string, string> = {
    wechat: 'success',
    alipay: 'primary',
    balance: 'warning',
  }
  return colors[type] || 'info'
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="payment-logs-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2>支付流水</h2>
      <p class="page-desc">查看所有支付渠道的交易记录</p>
    </div>

    <!-- 搜索区域 -->
    <div class="search-section">
      <el-input
        v-model="keyword"
        placeholder="搜索订单号/交易号/用户"
        style="width: 240px"
        clearable
        :prefix-icon="Search"
        @keyup.enter="fetchData"
      />
      <el-select v-model="payType" placeholder="支付渠道" clearable style="width: 130px">
        <el-option
          v-for="item in payTypeOptions"
          :key="item.value"
          :label="item.label"
          :value="item.value"
        />
      </el-select>
      <el-select v-model="actionType" placeholder="类型" clearable style="width: 100px">
        <el-option
          v-for="item in actionOptions"
          :key="item.value"
          :label="item.label"
          :value="item.value"
        />
      </el-select>
      <el-select v-model="status" placeholder="状态" clearable style="width: 100px">
        <el-option
          v-for="item in statusOptions"
          :key="item.value"
          :label="item.label"
          :value="item.value"
        />
      </el-select>
      <el-date-picker
        v-model="startDate"
        type="date"
        placeholder="开始日期"
        value-format="YYYY-MM-DD"
        style="width: 140px"
      />
      <span class="range-separator">-</span>
      <el-date-picker
        v-model="endDate"
        type="date"
        placeholder="结束日期"
        value-format="YYYY-MM-DD"
        style="width: 140px"
      />
      <el-button type="primary" @click="fetchData">
        <el-icon><Search /></el-icon>
        搜索
      </el-button>
      <el-button @click="resetSearch">
        <el-icon><Refresh /></el-icon>
        重置
      </el-button>
    </div>

    <!-- 数据表格 -->
    <div class="table-section" v-loading="loading">
      <el-table :data="tableData" stripe border>
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="订单号" min-width="180">
          <template #default="{ row }">
            <span class="order-no">{{ row.order_no }}</span>
          </template>
        </el-table-column>
        <el-table-column label="用户" width="120">
          <template #default="{ row }">
            {{ row.user?.username || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="渠道" width="100">
          <template #default="{ row }">
            <el-tag :type="getPayTypeColor(row.pay_type)" size="small">
              {{ row.pay_type_name }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.action === 0 ? 'primary' : 'warning'" size="small">
              {{ row.action_name }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="金额" width="120" align="right">
          <template #default="{ row }">
            <span class="amount-text">¥{{ Number(row.amount).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="渠道交易号" min-width="200">
          <template #default="{ row }">
            <span class="transaction-id">{{ row.transaction_id || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusColor(row.status)" size="small">
              {{ row.status_name }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="完成时间" width="170">
          <template #default="{ row }">
            {{ formatDate(row.paid_at) }}
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="170">
          <template #default="{ row }">
            {{ formatDate(row.created_at) }}
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-section">
        <el-pagination
          v-model:current-page="pagination.current"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.payment-logs-page {
  padding: 20px;
}

.page-header {
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0 0 8px;
  font-size: 20px;
  color: #303133;
}

.page-desc {
  margin: 0;
  font-size: 14px;
  color: #909399;
}

.search-section {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding: 16px;
  background: #fff;
  border-radius: 8px;
  flex-wrap: wrap;
}

.range-separator {
  color: #909399;
}

.table-section {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
}

.order-no {
  font-family: monospace;
  color: #409eff;
}

.amount-text {
  font-weight: bold;
  color: #67c23a;
  font-size: 15px;
}

.transaction-id {
  font-family: monospace;
  font-size: 12px;
  color: #606266;
}

.pagination-section {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
