<template>
  <div class="operation-logs-container">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2 class="page-title">操作日志</h2>
      <p class="page-desc">查看管理员操作记录和审计日志</p>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-value">{{ statistics.total_operations || 0 }}</div>
        <div class="stat-label">总操作数</div>
      </div>
      <div class="stat-card">
        <div class="stat-value text-success">{{ statistics.success_count || 0 }}</div>
        <div class="stat-label">成功</div>
      </div>
      <div class="stat-card">
        <div class="stat-value text-danger">{{ statistics.fail_count || 0 }}</div>
        <div class="stat-label">失败</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ statistics.active_admins || 0 }}</div>
        <div class="stat-label">活跃管理员</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ statistics.avg_duration_ms || 0 }}ms</div>
        <div class="stat-label">平均耗时</div>
      </div>
    </div>

    <!-- 筛选区域 -->
    <div class="filter-section">
      <el-form :inline="true" :model="filters" class="filter-form">
        <el-form-item label="关键词">
          <el-input
            v-model="filters.keyword"
            placeholder="管理员/操作/IP"
            clearable
            @keyup.enter="handleSearch"
            style="width: 200px"
          />
        </el-form-item>
        <el-form-item label="模块">
          <el-select
            v-model="filters.module"
            placeholder="全部模块"
            clearable
            style="width: 150px"
          >
            <el-option
              v-for="mod in modules"
              :key="mod"
              :label="mod"
              :value="mod"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select
            v-model="filters.status"
            placeholder="全部"
            clearable
            style="width: 120px"
          >
            <el-option label="成功" :value="1" />
            <el-option label="失败" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 240px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">
            <el-icon><Search /></el-icon>搜索
          </el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 操作按钮 -->
    <div class="action-bar">
      <el-button type="danger" @click="handleClean">
        <el-icon><Delete /></el-icon>清理过期日志
      </el-button>
    </div>

    <!-- 日志表格 -->
    <el-table
      :data="logs"
      v-loading="loading"
      stripe
      border
      style="width: 100%"
    >
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="admin_name" label="管理员" width="120">
        <template #default="{ row }">
          <el-tag size="small" type="info">{{ row.admin_name }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="module" label="模块" width="120">
        <template #default="{ row }">
          <el-tag size="small">{{ row.module }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="action" label="操作" min-width="150" />
      <el-table-column prop="method" label="方法" width="80">
        <template #default="{ row }">
          <el-tag
            size="small"
            :type="getMethodType(row.method)"
          >
            {{ row.method }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="url" label="URL" min-width="200" show-overflow-tooltip />
      <el-table-column prop="ip" label="IP" width="130" />
      <el-table-column prop="duration_ms" label="耗时" width="100">
        <template #default="{ row }">
          <span :class="getDurationClass(row.duration_ms)">
            {{ row.duration_ms }}ms
          </span>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="80">
        <template #default="{ row }">
          <el-tag
            size="small"
            :type="row.status === 1 ? 'success' : 'danger'"
          >
            {{ row.status === 1 ? '成功' : '失败' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="时间" width="170">
        <template #default="{ row }">
          {{ formatDate(row.created_at) }}
        </template>
      </el-table-column>
      <el-table-column label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="handleViewDetail(row)">
            详情
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 分页 -->
    <div class="pagination-wrapper">
      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.per_page"
        :page-sizes="[20, 50, 100]"
        :total="pagination.total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handlePageChange"
      />
    </div>

    <!-- 详情对话框 -->
    <el-dialog
      v-model="detailVisible"
      title="操作日志详情"
      width="700px"
    >
      <div v-if="currentLog" class="log-detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="ID">{{ currentLog.id }}</el-descriptions-item>
          <el-descriptions-item label="管理员">{{ currentLog.admin_name }}</el-descriptions-item>
          <el-descriptions-item label="模块">{{ currentLog.module }}</el-descriptions-item>
          <el-descriptions-item label="操作">{{ currentLog.action }}</el-descriptions-item>
          <el-descriptions-item label="请求方法">{{ currentLog.method }}</el-descriptions-item>
          <el-descriptions-item label="IP地址">{{ currentLog.ip }}</el-descriptions-item>
          <el-descriptions-item label="耗时">{{ currentLog.duration_ms }}ms</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="currentLog.status === 1 ? 'success' : 'danger'">
              {{ currentLog.status === 1 ? '成功' : '失败' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="时间" :span="2">{{ formatDate(currentLog.created_at) }}</el-descriptions-item>
          <el-descriptions-item label="URL" :span="2">{{ currentLog.url }}</el-descriptions-item>
        </el-descriptions>

        <div class="detail-section">
          <h4>请求参数</h4>
          <pre class="code-block">{{ formatJson(currentLog.params) }}</pre>
        </div>

        <div v-if="currentLog.error_message" class="detail-section">
          <h4>错误信息</h4>
          <el-alert
            :title="currentLog.error_message"
            type="error"
            :closable="false"
          />
        </div>

        <div v-if="currentLog.user_agent" class="detail-section">
          <h4>浏览器标识</h4>
          <p class="user-agent">{{ currentLog.user_agent }}</p>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Delete } from '@element-plus/icons-vue'
import axios from 'axios'

// 日志数据
const logs = ref<any[]>([])
const loading = ref(false)
const modules = ref<string[]>([])
const dateRange = ref<string[]>([])

// 筛选条件
const filters = reactive({
  keyword: '',
  module: '',
  status: '' as number | '',
  start_date: '',
  end_date: '',
})

// 分页
const pagination = reactive({
  page: 1,
  per_page: 20,
  total: 0,
})

// 统计
const statistics = ref<any>({})

// 详情
const detailVisible = ref(false)
const currentLog = ref<any>(null)

// 获取日志列表
const fetchLogs = async () => {
  loading.value = true
  try {
    const params: any = {
      page: pagination.page,
      per_page: pagination.per_page,
      ...filters,
    }

    if (dateRange.value && dateRange.value.length === 2) {
      params.start_date = dateRange.value[0] + ' 00:00:00'
      params.end_date = dateRange.value[1] + ' 23:59:59'
    }

    // 移除空值
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })

    const { data } = await axios.get('/api/v1/admin/operation-logs', { params })
    if (data.code === 0) {
      logs.value = data.data.data
      pagination.total = data.data.total
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '获取日志失败')
  } finally {
    loading.value = false
  }
}

// 获取模块列表
const fetchModules = async () => {
  try {
    const { data } = await axios.get('/api/v1/admin/operation-logs/modules')
    if (data.code === 0) {
      modules.value = data.data
    }
  } catch {
    // 忽略错误
  }
}

// 获取统计
const fetchStatistics = async () => {
  try {
    const { data } = await axios.get('/api/v1/admin/operation-logs/statistics')
    if (data.code === 0) {
      statistics.value = data.data
    }
  } catch {
    // 忽略错误
  }
}

// 搜索
const handleSearch = () => {
  pagination.page = 1
  fetchLogs()
}

// 重置
const handleReset = () => {
  filters.keyword = ''
  filters.module = ''
  filters.status = ''
  dateRange.value = []
  pagination.page = 1
  fetchLogs()
}

// 分页变化
const handleSizeChange = (size: number) => {
  pagination.per_page = size
  pagination.page = 1
  fetchLogs()
}

const handlePageChange = (page: number) => {
  pagination.page = page
  fetchLogs()
}

// 查看详情
const handleViewDetail = (row: any) => {
  currentLog.value = row
  detailVisible.value = true
}

// 清理日志
const handleClean = async () => {
  try {
    await ElMessageBox.confirm(
      '确定要清理90天前的操作日志吗？此操作不可恢复。',
      '清理日志',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
      }
    )

    const { data } = await axios.delete('/api/v1/admin/operation-logs/clean', {
      params: { days: 90 },
    })

    if (data.code === 0) {
      ElMessage.success(data.message)
      fetchLogs()
      fetchStatistics()
    }
  } catch (error: any) {
    if (error !== 'cancel') {
      ElMessage.error(error.response?.data?.message || '清理失败')
    }
  }
}

// 辅助函数
const getMethodType = (method: string) => {
  const types: Record<string, string> = {
    GET: 'success',
    POST: 'primary',
    PUT: 'warning',
    DELETE: 'danger',
    PATCH: 'warning',
  }
  return types[method] || 'info'
}

const getDurationClass = (ms: number) => {
  if (ms < 100) return 'text-success'
  if (ms < 500) return 'text-warning'
  return 'text-danger'
}

const formatDate = (date: string) => {
  if (!date) return ''
  return new Date(date).toLocaleString('zh-CN')
}

const formatJson = (obj: any) => {
  if (!obj) return '无'
  try {
    if (typeof obj === 'string') {
      obj = JSON.parse(obj)
    }
    return JSON.stringify(obj, null, 2)
  } catch {
    return String(obj)
  }
}

onMounted(() => {
  fetchLogs()
  fetchModules()
  fetchStatistics()
})
</script>

<style scoped>
.operation-logs-container {
  padding: 20px;
}

.stats-cards {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
}

.stat-card {
  flex: 1;
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: #409eff;
}

.stat-value.text-success {
  color: #67c23a;
}

.stat-value.text-danger {
  color: #f56c6c;
}

.stat-label {
  font-size: 14px;
  color: #909399;
  margin-top: 8px;
}

.filter-section {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.action-bar {
  margin-bottom: 16px;
}

.pagination-wrapper {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
}

.log-detail {
  max-height: 600px;
  overflow-y: auto;
}

.detail-section {
  margin-top: 20px;
}

.detail-section h4 {
  margin-bottom: 10px;
  color: #303133;
}

.code-block {
  background: #f5f7fa;
  border-radius: 4px;
  padding: 12px;
  font-size: 12px;
  overflow-x: auto;
  max-height: 300px;
  overflow-y: auto;
}

.user-agent {
  font-size: 12px;
  color: #606266;
  word-break: break-all;
}

.text-success {
  color: #67c23a;
}

.text-warning {
  color: #e6a23c;
}

.text-danger {
  color: #f56c6c;
}
</style>
