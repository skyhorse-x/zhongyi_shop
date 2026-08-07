<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Delete, Search } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'
import { getAdminToken } from '@/utils/auth'

interface ApiLog {
  id: number
  method: string
  url: string
  route_name: string
  module: string
  request_params: any
  response_status: number
  success: boolean
  duration_ms: number
  ip: string
  user_id: number
  user_type: string
  requested_at: string
}

const logs = ref<ApiLog[]>([])
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const stats = ref({
  total_requests: 0,
  today_requests: 0,
  success_rate: 100,
  avg_duration: 0,
})

// 筛选条件
const filters = ref({
  module: '',
  status: '',
  method: '',
  keyword: '',
  date_from: '',
  date_to: '',
})

const modules = ref<string[]>([])
const showDetail = ref(false)
const currentLog = ref<ApiLog | null>(null)

const getToken = (): string => getAdminToken() || ''

// 方法标签类型
const methodType = (method: string) => {
  const types: Record<string, string> = {
    GET: 'success',
    POST: 'primary',
    PUT: 'warning',
    DELETE: 'danger',
    PATCH: 'warning',
  }
  return types[method] || 'info'
}

// 状态标签类型
const statusType = (status: number) => {
  if (status >= 200 && status < 300) return 'success'
  if (status >= 400 && status < 500) return 'warning'
  if (status >= 500) return 'danger'
  return 'info'
}

// 加载日志列表
const loadLogs = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      limit: pageSize.value.toString(),
    })

    if (filters.value.module) params.set('module', filters.value.module)
    if (filters.value.status) params.set('status', filters.value.status)
    if (filters.value.method) params.set('method', filters.value.method)
    if (filters.value.keyword) params.set('keyword', filters.value.keyword)
    if (filters.value.date_from) params.set('date_from', filters.value.date_from)
    if (filters.value.date_to) params.set('date_to', filters.value.date_to)

    const res = await safeFetch(`/api/v1/admin/api-logs?${params}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      logs.value = data.data.data || []
      total.value = data.data.total || 0
      if (data.stats) {
        stats.value = data.stats
      }
    } else {
      ElMessage.error(data.message || '加载日志失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载日志失败')
  } finally {
    loading.value = false
  }
}

// 加载模块列表
const loadModules = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/api-logs/modules', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      modules.value = data.data
    }
  } catch (e) {
    console.error('加载模块列表失败:', e)
  }
}

// 查看详情
const viewDetail = (log: ApiLog) => {
  currentLog.value = log
  showDetail.value = true
}

// 删除日志
const deleteLog = async (id: number) => {
  try {
    await ElMessageBox.confirm('确定要删除这条日志吗？', '提示', {
      type: 'warning',
    })

    const res = await safeFetch(`/api/v1/admin/api-logs/${id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('删除成功')
      loadLogs()
    } else {
      ElMessage.error(data.message || '删除失败')
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '删除失败')
    }
  }
}

// 清空所有日志
const clearAllLogs = async () => {
  try {
    await ElMessageBox.confirm('确定要清空所有日志吗？此操作不可恢复！', '警告', {
      type: 'warning',
      confirmButtonText: '确定清空',
      cancelButtonText: '取消',
    })

    const res = await safeFetch('/api/v1/admin/api-logs/clean', {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('清空成功')
      loadLogs()
    } else {
      ElMessage.error(data.message || '清空失败')
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '清空失败')
    }
  }
}

// 筛选
const handleFilter = () => {
  currentPage.value = 1
  loadLogs()
}

// 重置筛选
const resetFilter = () => {
  filters.value = {
    module: '',
    status: '',
    method: '',
    keyword: '',
    date_from: '',
    date_to: '',
  }
  currentPage.value = 1
  loadLogs()
}

// 分页
const handlePageChange = (page: number) => {
  currentPage.value = page
  loadLogs()
}

const handleSizeChange = (size: number) => {
  pageSize.value = size
  currentPage.value = 1
  loadLogs()
}

// 格式化JSON
const formatJson = (obj: any) => {
  if (!obj) return '-'
  try {
    return typeof obj === 'string' ? JSON.stringify(JSON.parse(obj), null, 2) : JSON.stringify(obj, null, 2)
  } catch {
    return String(obj)
  }
}

onMounted(() => {
  loadLogs()
  loadModules()
})
</script>

<template>
  <div class="admin-page-wrapper">
    <div class="page-header">
      <h2 class="page-title">API请求日志</h2>
      <p class="page-desc">查看和监控所有API接口请求记录</p>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-value">{{ stats.total_requests.toLocaleString() }}</div>
        <div class="stat-label">总请求数</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ stats.today_requests.toLocaleString() }}</div>
        <div class="stat-label">今日请求</div>
      </div>
      <div class="stat-card">
        <div class="stat-value stat-success">{{ stats.success_rate }}%</div>
        <div class="stat-label">成功率</div>
      </div>
      <div class="stat-card">
        <div class="stat-value stat-duration">{{ stats.avg_duration }}ms</div>
        <div class="stat-label">平均耗时</div>
      </div>
    </div>

    <!-- 筛选区域 -->
    <el-card class="filter-card">
      <el-form :inline="true" size="small">
        <el-form-item label="模块">
          <el-select v-model="filters.module" placeholder="全部" clearable style="width: 140px">
            <el-option v-for="m in modules" :key="m" :label="m" :value="m" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="成功" value="success" />
            <el-option label="失败" value="failed" />
            <el-option label="2xx" value="200" />
            <el-option label="4xx" value="400" />
            <el-option label="5xx" value="500" />
          </el-select>
        </el-form-item>
        <el-form-item label="方法">
          <el-select v-model="filters.method" placeholder="全部" clearable style="width: 100px">
            <el-option label="GET" value="GET" />
            <el-option label="POST" value="POST" />
            <el-option label="PUT" value="PUT" />
            <el-option label="DELETE" value="DELETE" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model="filters.keyword" placeholder="URL/IP" clearable style="width: 180px" />
        </el-form-item>
        <el-form-item label="时间">
          <el-date-picker
            v-model="filters.date_from"
            type="date"
            placeholder="开始日期"
            value-format="YYYY-MM-DD"
            style="width: 140px"
            size="small"
          />
          <span style="margin: 0 8px">-</span>
          <el-date-picker
            v-model="filters.date_to"
            type="date"
            placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 140px"
            size="small"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleFilter">查询</el-button>
          <el-button @click="resetFilter">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 操作栏 -->
    <div class="toolbar">
      <el-button :icon="Refresh" size="small" @click="loadLogs">刷新</el-button>
      <el-button type="danger" :icon="Delete" size="small" @click="clearAllLogs">清空日志</el-button>
    </div>

    <!-- 日志表格 -->
    <el-card v-loading="loading">
      <el-table :data="logs" border stripe size="small" style="width: 100%">
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column label="方法" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="methodType(row.method)" size="small">{{ row.method }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="url" label="URL" min-width="200" show-overflow-tooltip />
        <el-table-column prop="module" label="模块" width="100" align="center">
          <template #default="{ row }">
            <el-tag size="small" type="info">{{ row.module }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="statusType(row.response_status)" size="small">{{ row.response_status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="duration_ms" label="耗时" width="90" align="right">
          <template #default="{ row }">{{ row.duration_ms }}ms</template>
        </el-table-column>
        <el-table-column prop="ip" label="IP" width="130" />
        <el-table-column prop="requested_at" label="时间" width="170" />
        <el-table-column label="操作" width="120" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="viewDetail(row)">详情</el-button>
            <el-button type="danger" link size="small" @click="deleteLog(row.id)">删除</el-button>
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

    <!-- 详情对话框 -->
    <el-dialog v-model="showDetail" title="API请求详情" width="700px">
      <div v-if="currentLog" class="detail-content">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="ID">{{ currentLog.id }}</el-descriptions-item>
          <el-descriptions-item label="方法">
            <el-tag :type="methodType(currentLog.method)" size="small">{{ currentLog.method }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="URL" :span="2">{{ currentLog.url }}</el-descriptions-item>
          <el-descriptions-item label="路由">{{ currentLog.route_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="模块">{{ currentLog.module }}</el-descriptions-item>
          <el-descriptions-item label="状态码">
            <el-tag :type="statusType(currentLog.response_status)" size="small">{{ currentLog.response_status }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="耗时">{{ currentLog.duration_ms }}ms</el-descriptions-item>
          <el-descriptions-item label="IP">{{ currentLog.ip }}</el-descriptions-item>
          <el-descriptions-item label="用户ID">{{ currentLog.user_id || '-' }}</el-descriptions-item>
          <el-descriptions-item label="用户类型">{{ currentLog.user_type || '-' }}</el-descriptions-item>
          <el-descriptions-item label="时间" :span="2">{{ currentLog.requested_at }}</el-descriptions-item>
        </el-descriptions>

        <div class="detail-section">
          <h4>请求参数</h4>
          <pre class="json-block">{{ formatJson(currentLog.request_params) }}</pre>
        </div>
      </div>
    </el-dialog>
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

.stat-success { color: #67c23a; }
.stat-duration { color: #e6a23c; }

.stat-label {
  font-size: 14px;
  color: #909399;
}

.filter-card { margin-bottom: 16px; }

.toolbar {
  margin-bottom: 16px;
  display: flex;
  gap: 8px;
}

.pagination-wrapper {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

.detail-content { max-height: 60vh; overflow-y: auto; }

.detail-section { margin-top: 16px; }

.detail-section h4 {
  font-size: 14px;
  font-weight: 600;
  color: #333;
  margin-bottom: 8px;
}

.json-block {
  background: #f5f7fa;
  border-radius: 4px;
  padding: 12px;
  font-size: 12px;
  line-height: 1.5;
  overflow-x: auto;
  max-height: 300px;
  overflow-y: auto;
}

/* 手机端适配 */
@media (max-width: 768px) {
  .stats-cards {
    grid-template-columns: repeat(2, 1fr);
  }

  .stat-value { font-size: 20px; }

  .el-form--inline .el-form-item {
    margin-right: 0;
    width: 100%;
  }

  .pagination-wrapper { justify-content: center; }
}
</style>
