<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { getAdminToken } from '@/utils/auth'
import {
  List, Refresh, CircleCheck, CircleClose, Loading, Clock,
  Search, Filter, Histogram, Warning, ChatLineRound, Document
} from '@element-plus/icons-vue'

// 任务状态映射
const statusMap: Record<number, { label: string; type: string; icon: any }> = {
  0: { label: '排队中', type: 'info', icon: Clock },
  1: { label: '处理中', type: 'warning', icon: Loading },
  2: { label: '已完成', type: 'success', icon: CircleCheck },
  3: { label: '失败', type: 'danger', icon: CircleClose },
}

// 分析类型映射
const typeMap: Record<string, string> = {
  tongue: '舌诊分析',
  face: '面诊分析',
}

// 统计数据
const statistics = ref({
  today: { total: 0, pending: 0, processing: 0, completed: 0, failed: 0 },
  yesterday: { total: 0, completed: 0, failed: 0 },
  overall: { total: 0, pending: 0, processing: 0, completed: 0, failed: 0 },
  failure_rate: 0,
})

// 任务列表
const taskList = ref([])
const total = ref(0)
const loading = ref(false)

// 筛选条件
const filters = reactive({
  status: '',
  type: '',
  keyword: '',
  start_date: '',
  end_date: '',
})

// 分页
const currentPage = ref(1)
const pageSize = ref(20)

// 详情对话框
const detailDialogVisible = ref(false)
const currentTask = ref<any>(null)

// 活跃标签
const activeTab = ref('all')

// 加载统计数据
const loadStatistics = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/queue/statistics', {
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      statistics.value = data.data
    }
  } catch (e: any) {
    ElMessage.error('加载统计数据失败')
  }
}

// 加载任务列表
const loadTasks = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      limit: pageSize.value.toString(),
    })
    if (filters.status !== '') params.set('status', filters.status)
    if (filters.type) params.set('type', filters.type)
    if (filters.keyword) params.set('keyword', filters.keyword)
    if (filters.start_date) params.set('start_date', filters.start_date)
    if (filters.end_date) params.set('end_date', filters.end_date)

    const res = await safeFetch(`/api/v1/admin/queue/tasks?${params}`, {
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      taskList.value = data.data.data || []
      total.value = data.data.total || 0
    } else {
      ElMessage.error(data.message || '加载任务列表失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载任务列表失败')
  } finally {
    loading.value = false
  }
}

// 加载失败任务
const loadFailedJobs = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      limit: pageSize.value.toString(),
    })
    const res = await safeFetch(`/api/v1/admin/queue/failed-jobs?${params}`, {
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      taskList.value = data.data.data || []
      total.value = data.data.total || 0
    }
  } catch (e: any) {
    ElMessage.error('加载失败任务失败')
  } finally {
    loading.value = false
  }
}

// 切换标签
const handleTabChange = (tab: string) => {
  activeTab.value = tab
  currentPage.value = 1
  if (tab === 'failed') {
    loadFailedJobs()
  } else {
    loadTasks()
  }
}

// 搜索
const handleSearch = () => {
  currentPage.value = 1
  if (activeTab.value === 'failed') {
    loadFailedJobs()
  } else {
    loadTasks()
  }
}

// 重置筛选
const handleReset = () => {
  filters.status = ''
  filters.type = ''
  filters.keyword = ''
  filters.start_date = ''
  filters.end_date = ''
  handleSearch()
}

// 查看详情
const handleViewDetail = async (row: any) => {
  try {
    const res = await safeFetch(`/api/v1/admin/queue/tasks/${row.task_no}`, {
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      currentTask.value = data.data
      detailDialogVisible.value = true
    }
  } catch (e: any) {
    ElMessage.error('加载任务详情失败')
  }
}

// 重试任务
const handleRetry = async (taskNo: string) => {
  try {
    await ElMessageBox.confirm('确定要重试该任务吗？', '确认重试', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch {
    return
  }

  try {
    const res = await safeFetch(`/api/v1/admin/queue/retry/${taskNo}`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('任务已重新派发')
      loadTasks()
      loadStatistics()
    } else {
      ElMessage.error(data.message || '重试失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '重试失败')
  }
}

// 批量重试
const handleRetryAll = async () => {
  try {
    await ElMessageBox.confirm(
      '确定要重试所有失败任务吗？这可能会消耗较多资源。',
      '批量重试',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
      }
    )
  } catch {
    return
  }

  try {
    const res = await safeFetch('/api/v1/admin/queue/retry-all', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(data.message)
      loadTasks()
      loadStatistics()
    } else {
      ElMessage.error(data.message || '批量重试失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '批量重试失败')
  }
}

// 分页变化
const handlePageChange = (page: number) => {
  currentPage.value = page
  if (activeTab.value === 'failed') {
    loadFailedJobs()
  } else {
    loadTasks()
  }
}

// 格式化时间
const formatDateTime = (dateStr: string) => {
  if (!dateStr) return '-'
  try {
    const date = new Date(dateStr)
    if (isNaN(date.getTime())) return dateStr
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    const h = String(date.getHours()).padStart(2, '0')
    const min = String(date.getMinutes()).padStart(2, '0')
    return `${y}-${m}-${d} ${h}:${min}`
  } catch {
    return dateStr
  }
}

// 计算处理时长
const getDuration = (start: string, end: string) => {
  if (!start || !end) return '-'
  try {
    const startTime = new Date(start).getTime()
    const endTime = new Date(end).getTime()
    const duration = (endTime - startTime) / 1000
    if (duration < 60) return `${Math.round(duration)}秒`
    if (duration < 3600) return `${Math.round(duration / 60)}分钟`
    return `${(duration / 3600).toFixed(1)}小时`
  } catch {
    return '-'
  }
}

// 失败率颜色
const failureRateColor = computed(() => {
  const rate = statistics.value.failure_rate
  if (rate < 5) return '#67c23a'
  if (rate < 15) return '#e6a23c'
  return '#f56c6c'
})

onMounted(() => {
  loadStatistics()
  loadTasks()
})
</script>

<template>
  <div class="queue-page">
    <!-- 统计卡片 -->
    <div class="stats-grid">
      <div class="stat-card stat-card--blue">
        <div class="stat-icon">
          <el-icon :size="24"><Histogram /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">今日任务总数</div>
          <div class="stat-value">{{ statistics.today.total }}</div>
        </div>
      </div>
      <div class="stat-card stat-card--orange">
        <div class="stat-icon">
          <el-icon :size="24"><Loading /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">处理中</div>
          <div class="stat-value">{{ statistics.today.processing }}</div>
        </div>
      </div>
      <div class="stat-card stat-card--green">
        <div class="stat-icon">
          <el-icon :size="24"><CircleCheck /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">今日完成</div>
          <div class="stat-value">{{ statistics.today.completed }}</div>
        </div>
      </div>
      <div class="stat-card stat-card--red">
        <div class="stat-icon">
          <el-icon :size="24"><Warning /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">今日失败</div>
          <div class="stat-value">{{ statistics.today.failed }}</div>
        </div>
      </div>
      <div class="stat-card stat-card--purple">
        <div class="stat-icon">
          <el-icon :size="24"><CircleClose /></el-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">失败率</div>
          <div class="stat-value" :style="{ color: failureRateColor }">
            {{ statistics.failure_rate }}%
          </div>
        </div>
      </div>
    </div>

    <!-- 筛选和搜索 -->
    <div class="filter-section">
      <el-tabs v-model="activeTab" @tab-change="handleTabChange">
        <el-tab-pane label="全部任务" name="all"></el-tab-pane>
        <el-tab-pane label="失败任务" name="failed"></el-tab-pane>
      </el-tabs>

      <div class="filter-form">
        <el-select
          v-if="activeTab === 'all'"
          v-model="filters.status"
          placeholder="任务状态"
          clearable
          style="width: 140px"
          @change="handleSearch"
        >
          <el-option label="排队中" :value="0"></el-option>
          <el-option label="处理中" :value="1"></el-option>
          <el-option label="已完成" :value="2"></el-option>
          <el-option label="失败" :value="3"></el-option>
        </el-select>
        <el-select
          v-model="filters.type"
          placeholder="分析类型"
          clearable
          style="width: 140px"
          @change="handleSearch"
        >
          <el-option label="舌诊分析" value="tongue"></el-option>
          <el-option label="面诊分析" value="face"></el-option>
        </el-select>
        <el-input
          v-model="filters.keyword"
          placeholder="搜索任务号/用户昵称/手机号"
          clearable
          style="width: 240px"
          @keyup.enter="handleSearch"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>
        <el-date-picker
          v-model="filters.start_date"
          type="date"
          placeholder="开始日期"
          value-format="YYYY-MM-DD"
          style="width: 150px"
          @change="handleSearch"
        />
        <el-date-picker
          v-model="filters.end_date"
          type="date"
          placeholder="结束日期"
          value-format="YYYY-MM-DD"
          style="width: 150px"
          @change="handleSearch"
        />
        <el-button type="primary" @click="handleSearch">
          <el-icon><Search /></el-icon>
          搜索
        </el-button>
        <el-button @click="handleReset">重置</el-button>
        <el-button
          v-if="activeTab === 'failed'"
          type="danger"
          @click="handleRetryAll"
        >
          <el-icon><Refresh /></el-icon>
          批量重试全部
        </el-button>
      </div>
    </div>

    <!-- 任务列表 -->
    <div class="table-section">
      <el-table
        v-loading="loading"
        :data="taskList"
        stripe
        style="width: 100%"
      >
        <el-table-column prop="task_no" label="任务编号" width="180">
          <template #default="{ row }">
            <span class="task-no">{{ row.task_no }}</span>
          </template>
        </el-table-column>
        <el-table-column label="用户" width="140">
          <template #default="{ row }">
            <div v-if="row.user" class="user-info">
              <div class="user-nickname">{{ row.user.nickname || '-' }}</div>
              <div class="user-mobile">{{ row.user.mobile || '-' }}</div>
            </div>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="分析类型" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="row.type === 'tongue' ? 'primary' : 'success'">
              {{ typeMap[row.type] || row.type }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag
              :type="statusMap[row.status]?.type as any"
              size="small"
            >
              <el-icon class="status-icon"><component :is="statusMap[row.status]?.icon" /></el-icon>
              {{ statusMap[row.status]?.label || '未知' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="160">
          <template #default="{ row }">
            {{ formatDateTime(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="处理时长" width="100">
          <template #default="{ row }">
            {{ getDuration(row.started_at, row.completed_at) }}
          </template>
        </el-table-column>
        <el-table-column label="错误信息" min-width="200">
          <template #default="{ row }">
            <span v-if="row.error_message" class="error-message">
              {{ row.error_message }}
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button
              type="primary"
              size="small"
              link
              @click="handleViewDetail(row)"
            >
              详情
            </el-button>
            <el-button
              v-if="row.status === 3"
              type="danger"
              size="small"
              link
              @click="handleRetry(row.task_no)"
            >
              重试
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-section">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next, jumper"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <!-- 详情对话框 -->
    <el-dialog
      v-model="detailDialogVisible"
      title="任务详情"
      width="600px"
      destroy-on-close
    >
      <div v-if="currentTask" class="task-detail">
        <div class="detail-row">
          <div class="detail-label">任务编号：</div>
          <div class="detail-value">{{ currentTask.task_no }}</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">用户信息：</div>
          <div class="detail-value">
            <span v-if="currentTask.user">
              {{ currentTask.user.nickname || '-' }} ({{ currentTask.user.mobile || '-' }})
            </span>
            <span v-else>-</span>
          </div>
        </div>
        <div class="detail-row">
          <div class="detail-label">分析类型：</div>
          <div class="detail-value">{{ typeMap[currentTask.type] || currentTask.type }}</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">任务状态：</div>
          <div class="detail-value">
            <el-tag :type="statusMap[currentTask.status]?.type as any">
              {{ statusMap[currentTask.status]?.label || '未知' }}
            </el-tag>
          </div>
        </div>
        <div class="detail-row">
          <div class="detail-label">创建时间：</div>
          <div class="detail-value">{{ formatDateTime(currentTask.created_at) }}</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">开始时间：</div>
          <div class="detail-value">{{ formatDateTime(currentTask.started_at) }}</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">完成时间：</div>
          <div class="detail-value">{{ formatDateTime(currentTask.completed_at) }}</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">用户性别：</div>
          <div class="detail-value">{{ currentTask.gender === 1 ? '男' : currentTask.gender === 2 ? '女' : '-' }}</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">用户年龄：</div>
          <div class="detail-value">{{ currentTask.age ? `${currentTask.age}岁` : '-' }}</div>
        </div>
        <div v-if="currentTask.error_message" class="detail-row">
          <div class="detail-label">错误信息：</div>
          <div class="detail-value error-text">{{ currentTask.error_message }}</div>
        </div>
        <div v-if="currentTask.result" class="detail-row">
          <div class="detail-label">分析结果：</div>
          <div class="detail-value">
            <div class="result-summary">健康评分：{{ currentTask.result.health_score || '-' }}</div>
            <div class="result-summary">摘要：{{ currentTask.result.summary || '-' }}</div>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="detailDialogVisible = false">关闭</el-button>
        <el-button
          v-if="currentTask && currentTask.status === 3"
          type="danger"
          @click="handleRetry(currentTask.task_no)"
        >
          重试任务
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.queue-page {
  padding: 20px;
}

/* 统计卡片 */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}

.stat-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  border-left: 4px solid;
}

.stat-card--blue { border-left-color: #409eff; }
.stat-card--orange { border-left-color: #e6a23c; }
.stat-card--green { border-left-color: #67c23a; }
.stat-card--red { border-left-color: #f56c6c; }
.stat-card--purple { border-left-color: #9c27b0; }

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f7fa;
  color: #606266;
}

.stat-card--blue .stat-icon { background: #ecf5ff; color: #409eff; }
.stat-card--orange .stat-icon { background: #fdf6ec; color: #e6a23c; }
.stat-card--green .stat-icon { background: #f0f9eb; color: #67c23a; }
.stat-card--red .stat-icon { background: #fef0f0; color: #f56c6c; }
.stat-card--purple .stat-icon { background: #f3e5f5; color: #9c27b0; }

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #303133;
}

/* 筛选区域 */
.filter-section {
  background: #fff;
  border-radius: 12px;
  padding: 16px 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.filter-form {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 12px;
}

.filter-form .el-button {
  margin-left: auto;
}

/* 表格区域 */
.table-section {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.task-no {
  font-family: monospace;
  font-weight: 500;
  color: #409eff;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-nickname {
  font-weight: 500;
  color: #303133;
}

.user-mobile {
  font-size: 12px;
  color: #909399;
}

.status-icon {
  margin-right: 4px;
}

.error-message {
  color: #f56c6c;
  font-size: 12px;
  word-break: break-all;
}

.pagination-section {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

/* 详情对话框 */
.task-detail {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-row {
  display: flex;
  gap: 12px;
}

.detail-label {
  width: 100px;
  color: #909399;
  flex-shrink: 0;
}

.detail-value {
  flex: 1;
  color: #303133;
}

.error-text {
  color: #f56c6c;
  background: #fef0f0;
  padding: 8px 12px;
  border-radius: 4px;
  font-size: 12px;
  word-break: break-all;
}

.result-summary {
  margin-bottom: 4px;
  font-size: 13px;
}

/* 响应式 */
@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .filter-form {
    flex-direction: column;
  }

  .filter-form .el-button {
    margin-left: 0;
    width: 100%;
  }
}
</style>
