<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { getAdminToken } from '@/utils/auth'
import { Search, Wallet, Refresh } from '@element-plus/icons-vue'

const router = useRouter()

const loading = ref(false)
const keyword = ref('')
const minBalance = ref('')
const maxBalance = ref('')
const tableData = ref<any[]>([])
const pagination = ref({
  current: 1,
  pageSize: 20,
  total: 0,
})

// 余额详情弹窗
const logsVisible = ref(false)
const currentUser = ref<any>(null)
const logsLoading = ref(false)
const logsData = ref<any[]>([])
const logsPagination = ref({
  current: 1,
  pageSize: 20,
  total: 0,
})

const fetchData = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: pagination.value.current.toString(),
      per_page: pagination.value.pageSize.toString(),
    })
    if (keyword.value) params.set('keyword', keyword.value)
    if (minBalance.value) params.set('min_balance', minBalance.value)
    if (maxBalance.value) params.set('max_balance', maxBalance.value)

    const res = await safeFetch(`/api/v1/admin/user-balances?${params}`, {
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
    console.error('获取用户余额失败:', e)
    ElMessage.error('获取数据失败')
  } finally {
    loading.value = false
  }
}

const resetSearch = () => {
  keyword.value = ''
  minBalance.value = ''
  maxBalance.value = ''
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

// 查看余额流水
const viewLogs = async (row: any) => {
  currentUser.value = row
  logsVisible.value = true
  logsPagination.value.current = 1
  fetchLogs()
}

const fetchLogs = async () => {
  if (!currentUser.value) return
  logsLoading.value = true
  try {
    const params = new URLSearchParams({
      page: logsPagination.value.current.toString(),
      per_page: logsPagination.value.pageSize.toString(),
    })

    const res = await safeFetch(`/api/v1/admin/user-balances/${currentUser.value.id}/logs?${params}`, {
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      logsData.value = data.data.data
      logsPagination.value.total = data.data.total
    }
  } catch (e) {
    console.error('获取余额流水失败:', e)
  } finally {
    logsLoading.value = false
  }
}

const handleLogsPageChange = (page: number) => {
  logsPagination.value.current = page
  fetchLogs()
}

const formatDate = (date: string) => {
  if (!date) return '-'
  return new Date(date).toLocaleString('zh-CN')
}

const getBalanceTypeColor = (type: string) => {
  const colors: Record<string, string> = {
    recharge: 'success',
    consume: 'warning',
    refund: 'info',
    reward: 'success',
    admin_deduct: 'danger',
  }
  return colors[type] || 'info'
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="user-balances-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2>用户余额管理</h2>
      <p class="page-desc">查看用户余额、分析次数和变动流水</p>
    </div>

    <!-- 搜索区域 -->
    <div class="search-section">
      <el-input
        v-model="keyword"
        placeholder="搜索用户名/手机号/邮箱"
        style="width: 240px"
        clearable
        :prefix-icon="Search"
        @keyup.enter="fetchData"
      />
      <el-input
        v-model="minBalance"
        placeholder="最小余额"
        style="width: 140px"
        type="number"
        clearable
      />
      <span class="range-separator">-</span>
      <el-input
        v-model="maxBalance"
        placeholder="最大余额"
        style="width: 140px"
        type="number"
        clearable
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
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column prop="username" label="用户名" min-width="120" />
        <el-table-column prop="mobile" label="手机号" width="130" />
        <el-table-column label="余额" width="120" align="right">
          <template #default="{ row }">
            <span class="balance-text">¥{{ Number(row.balance).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="分析次数" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small" type="primary">{{ row.analysis_times }}次</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="注册时间" width="170">
          <template #default="{ row }">
            {{ formatDate(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="viewLogs(row)">
              <el-icon><Wallet /></el-icon>
              查看流水
            </el-button>
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

    <!-- 余额流水弹窗 -->
    <el-dialog
      v-model="logsVisible"
      :title="`${currentUser?.username || ''} - 余额变动流水`"
      width="800px"
      destroy-on-close
    >
      <div v-loading="logsLoading">
        <el-table :data="logsData" stripe border max-height="500">
          <el-table-column prop="id" label="ID" width="70" align="center" />
          <el-table-column label="类型" width="100">
            <template #default="{ row }">
              <el-tag :type="getBalanceTypeColor(row.type)" size="small">
                {{ row.type_name }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="变动金额" width="120" align="right">
            <template #default="{ row }">
              <span :class="row.change > 0 ? 'text-success' : 'text-danger'">
                {{ row.change > 0 ? '+' : '' }}¥{{ Number(row.change).toFixed(2) }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="变动前" width="100" align="right">
            <template #default="{ row }">
              ¥{{ Number(row.before).toFixed(2) }}
            </template>
          </el-table-column>
          <el-table-column label="变动后" width="100" align="right">
            <template #default="{ row }">
              ¥{{ Number(row.after).toFixed(2) }}
            </template>
          </el-table-column>
          <el-table-column prop="remark" label="备注" min-width="150" show-overflow-tooltip />
          <el-table-column label="操作人" width="100">
            <template #default="{ row }">
              {{ row.operator?.name || '-' }}
            </template>
          </el-table-column>
          <el-table-column label="时间" width="170">
            <template #default="{ row }">
              {{ formatDate(row.created_at) }}
            </template>
          </el-table-column>
        </el-table>

        <!-- 分页 -->
        <div class="pagination-section">
          <el-pagination
            v-model:current-page="logsPagination.current"
            v-model:page-size="logsPagination.pageSize"
            :total="logsPagination.total"
            :page-sizes="[20, 50, 100]"
            layout="total, sizes, prev, pager, next"
            @current-change="handleLogsPageChange"
          />
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<style scoped>
.user-balances-page {
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

.balance-text {
  font-weight: bold;
  color: #67c23a;
  font-size: 15px;
}

.pagination-section {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.text-success {
  color: #67c23a;
  font-weight: bold;
}

.text-danger {
  color: #f56c6c;
  font-weight: bold;
}
</style>
