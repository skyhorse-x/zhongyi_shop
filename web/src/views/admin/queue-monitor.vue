<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { getAdminToken } from '@/utils/auth'
import {
  Cpu, Loading, CircleCheck, CircleClose, Clock,
  Warning, Refresh, Monitor, DataLine
} from '@element-plus/icons-vue'

// 监控数据
const monitorData = ref<any>(null)
const loading = ref(false)
const autoRefresh = ref(true)
const refreshInterval = ref(5000) // 5秒刷新一次
let timer: ReturnType<typeof setInterval> | null = null

// 工作者状态类型
const workerStatusType = computed(() => {
  if (!monitorData.value?.worker) return 'info'
  return monitorData.value.worker.is_running ? 'success' : 'danger'
})

const workerStatusText = computed(() => {
  if (!monitorData.value?.worker) return '未知'
  return monitorData.value.worker.is_running ? '运行中' : '已停止'
})

// 格式化秒数
const formatDuration = (seconds: number) => {
  if (!seconds || seconds <= 0) return '-'
  if (seconds < 60) return `${seconds}秒`
  if (seconds < 3600) return `${Math.round(seconds / 60)}分钟`
  return `${(seconds / 3600).toFixed(1)}小时`
}

// 格式化速率
const formatRate = (count: number) => {
  if (!count || count <= 0) return '0/分钟'
  return `${count}/5分钟`
}

// 加载监控数据
const loadMonitorData = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/admin/queue/monitor', {
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      monitorData.value = data.data
    } else {
      ElMessage.error(data.message || '加载监控数据失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载监控数据失败')
  } finally {
    loading.value = false
  }
}

// 切换自动刷新
const toggleAutoRefresh = () => {
  if (autoRefresh.value) {
    startAutoRefresh()
  } else {
    stopAutoRefresh()
  }
}

// 开始自动刷新
const startAutoRefresh = () => {
  if (timer) clearInterval(timer)
  timer = setInterval(loadMonitorData, refreshInterval.value)
}

// 停止自动刷新
const stopAutoRefresh = () => {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

onMounted(() => {
  loadMonitorData()
  if (autoRefresh.value) {
    startAutoRefresh()
  }
})

onUnmounted(() => {
  stopAutoRefresh()
})
</script>

<template>
  <div class="queue-monitor-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">
          <el-icon :size="24"><Monitor /></el-icon>
          队列监控
        </h2>
        <p class="page-desc">实时监控队列工作者状态和任务处理情况</p>
      </div>
      <div class="header-right">
        <el-switch
          v-model="autoRefresh"
          active-text="自动刷新"
          inactive-text="手动"
          @change="toggleAutoRefresh"
        />
        <el-select v-model="refreshInterval" style="width: 100px; margin-left: 12px;">
          <el-option :value="3000" label="3秒" />
          <el-option :value="5000" label="5秒" />
          <el-option :value="10000" label="10秒" />
          <el-option :value="30000" label="30秒" />
        </el-select>
        <el-button type="primary" :loading="loading" @click="loadMonitorData" style="margin-left: 12px;">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
      </div>
    </div>

    <!-- 工作者状态卡片 -->
    <div class="status-cards">
      <div class="status-card" :class="workerStatusType">
        <div class="card-icon">
          <el-icon :size="32"><Cpu /></el-icon>
        </div>
        <div class="card-content">
          <div class="card-label">工作者状态</div>
          <div class="card-value">
            <el-tag :type="workerStatusType" size="large" effect="dark">
              {{ workerStatusText }}
            </el-tag>
          </div>
          <div class="card-detail" v-if="monitorData?.worker">
            进程数: {{ monitorData.worker.process_count }}
          </div>
        </div>
      </div>

      <div class="status-card info">
        <div class="card-icon">
          <el-icon :size="32"><DataLine /></el-icon>
        </div>
        <div class="card-content">
          <div class="card-label">队列连接</div>
          <div class="card-value">{{ monitorData?.connection || '-' }}</div>
          <div class="card-detail">队列: {{ monitorData?.queue_name || 'default' }}</div>
        </div>
      </div>

      <div class="status-card success">
        <div class="card-icon">
          <el-icon :size="32"><CircleCheck /></el-icon>
        </div>
        <div class="card-content">
          <div class="card-label">已完成任务</div>
          <div class="card-value">{{ monitorData?.jobs?.completed?.toLocaleString() || 0 }}</div>
          <div class="card-detail">总计完成</div>
        </div>
      </div>

      <div class="status-card warning">
        <div class="card-icon">
          <el-icon :size="32"><Warning /></el-icon>
        </div>
        <div class="card-content">
          <div class="card-label">失败任务</div>
          <div class="card-value">{{ monitorData?.jobs?.failed?.toLocaleString() || 0 }}</div>
          <div class="card-detail">需要关注</div>
        </div>
      </div>
    </div>

    <!-- 任务状态概览 -->
    <div class="monitor-section">
      <div class="section-header">
        <el-icon><DataLine /></el-icon>
        <span>任务状态概览</span>
      </div>
      <div class="section-content">
        <div class="task-stats-grid">
          <div class="task-stat-item pending">
            <div class="stat-icon"><el-icon><Clock /></el-icon></div>
            <div class="stat-info">
              <div class="stat-label">排队中</div>
              <div class="stat-value">{{ monitorData?.jobs?.pending || 0 }}</div>
            </div>
          </div>
          <div class="task-stat-item processing">
            <div class="stat-icon"><el-icon><Loading /></el-icon></div>
            <div class="stat-info">
              <div class="stat-label">处理中</div>
              <div class="stat-value">{{ monitorData?.jobs?.processing || 0 }}</div>
            </div>
          </div>
          <div class="task-stat-item completed">
            <div class="stat-icon"><el-icon><CircleCheck /></el-icon></div>
            <div class="stat-info">
              <div class="stat-label">已完成</div>
              <div class="stat-value">{{ monitorData?.jobs?.completed || 0 }}</div>
            </div>
          </div>
          <div class="task-stat-item failed">
            <div class="stat-icon"><el-icon><CircleClose /></el-icon></div>
            <div class="stat-info">
              <div class="stat-label">失败</div>
              <div class="stat-value">{{ monitorData?.jobs?.failed || 0 }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 处理速率和性能 -->
    <div class="monitor-section">
      <div class="section-header">
        <el-icon><Loading /></el-icon>
        <span>处理速率与性能</span>
      </div>
      <div class="section-content">
        <div class="performance-grid">
          <div class="performance-item">
            <div class="perf-label">最近5分钟完成</div>
            <div class="perf-value success-text">
              {{ monitorData?.throughput?.completed_5min || 0 }} 个任务
            </div>
          </div>
          <div class="performance-item">
            <div class="perf-label">最近5分钟失败</div>
            <div class="perf-value danger-text">
              {{ monitorData?.throughput?.failed_5min || 0 }} 个任务
            </div>
          </div>
          <div class="performance-item">
            <div class="perf-label">平均处理时长</div>
            <div class="perf-value">
              {{ formatDuration(monitorData?.throughput?.avg_duration_seconds || 0) }}
            </div>
          </div>
          <div class="performance-item">
            <div class="perf-label">最近1小时任务</div>
            <div class="perf-value">
              {{ monitorData?.hourly?.total || 0 }} 个任务
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 最近1小时统计 -->
    <div class="monitor-section">
      <div class="section-header">
        <el-icon><DataLine /></el-icon>
        <span>最近1小时统计</span>
      </div>
      <div class="section-content">
        <div class="hourly-stats">
          <div class="hourly-stat-item">
            <div class="hourly-label">新增任务</div>
            <div class="hourly-value">{{ monitorData?.hourly?.total || 0 }}</div>
          </div>
          <div class="hourly-stat-item">
            <div class="hourly-label">已完成</div>
            <div class="hourly-value success-text">{{ monitorData?.hourly?.completed || 0 }}</div>
          </div>
          <div class="hourly-stat-item">
            <div class="hourly-label">失败</div>
            <div class="hourly-value danger-text">{{ monitorData?.hourly?.failed || 0 }}</div>
          </div>
          <div class="hourly-stat-item">
            <div class="hourly-label">成功率</div>
            <div class="hourly-value">
              {{ monitorData?.hourly?.total > 0
                ? Math.round(((monitorData.hourly.completed || 0) / monitorData.hourly.total) * 100)
                : 100 }}%
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 数据库队列状态 -->
    <div class="monitor-section" v-if="monitorData?.queue_table?.available">
      <div class="section-header">
        <el-icon><DataLine /></el-icon>
        <span>数据库队列状态 (jobs表)</span>
      </div>
      <div class="section-content">
        <div class="queue-table-stats">
          <div class="table-stat-item">
            <div class="table-label">待处理 (pending)</div>
            <div class="table-value">{{ monitorData.queue_table.pending_in_table || 0 }}</div>
          </div>
          <div class="table-stat-item">
            <div class="table-label">已预留 (reserved)</div>
            <div class="table-value">{{ monitorData.queue_table.reserved_in_table || 0 }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 工作者进程详情 -->
    <div class="monitor-section" v-if="monitorData?.worker?.processes?.length > 0">
      <div class="section-header">
        <el-icon><Cpu /></el-icon>
        <span>工作者进程详情</span>
      </div>
      <div class="section-content">
        <div class="process-list">
          <div
            v-for="(proc, index) in monitorData.worker.processes"
            :key="index"
            class="process-item"
          >
            <code class="process-cmd">{{ proc }}</code>
          </div>
        </div>
      </div>
    </div>

    <!-- 操作提示 -->
    <div class="monitor-section">
      <div class="section-content">
        <el-alert
          v-if="monitorData?.worker && !monitorData.worker.is_running"
          title="队列工作者未运行"
          type="error"
          :closable="false"
          show-icon
        >
          <template #default>
            <p>请执行以下命令启动队列工作者：</p>
            <code class="command-code">php artisan queue:work</code>
          </template>
        </el-alert>
        <el-alert
          v-if="monitorData?.jobs?.pending > 10"
          title="排队任务较多"
          type="warning"
          :closable="false"
          show-icon
        >
          <template #default>
            <p>当前有 {{ monitorData.jobs.pending }} 个任务排队中，建议增加工作者进程以提高处理速度。</p>
          </template>
        </el-alert>
        <el-alert
          v-if="monitorData?.jobs?.failed > 0"
          title="存在失败任务"
          type="warning"
          :closable="false"
          show-icon
        >
          <template #default>
            <p>有 {{ monitorData.jobs.failed }} 个任务失败，请前往队列任务管理查看详情。</p>
          </template>
        </el-alert>
      </div>
    </div>

    <!-- 更新时间 -->
    <div class="update-time" v-if="monitorData?.updated_at">
      最后更新: {{ monitorData.updated_at }}
    </div>
  </div>
</template>

<style scoped>
.queue-monitor-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.header-left {
  display: flex;
  flex-direction: column;
}

.page-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 20px;
  font-weight: 600;
  color: #303133;
  margin: 0;
}

.page-desc {
  font-size: 13px;
  color: #909399;
  margin: 4px 0 0;
}

.header-right {
  display: flex;
  align-items: center;
}

/* 状态卡片 */
.status-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.status-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  border-left: 4px solid #909399;
}

.status-card.success {
  border-left-color: #67c23a;
}

.status-card.danger {
  border-left-color: #f56c6c;
}

.status-card.warning {
  border-left-color: #e6a23c;
}

.status-card.info {
  border-left-color: #409eff;
}

.card-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f7fa;
  color: #606266;
}

.status-card.success .card-icon {
  background: #f0f9eb;
  color: #67c23a;
}

.status-card.danger .card-icon {
  background: #fef0f0;
  color: #f56c6c;
}

.status-card.warning .card-icon {
  background: #fdf6ec;
  color: #e6a23c;
}

.status-card.info .card-icon {
  background: #ecf5ff;
  color: #409eff;
}

.card-content {
  flex: 1;
}

.card-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 4px;
}

.card-value {
  font-size: 24px;
  font-weight: 700;
  color: #303133;
  margin-bottom: 4px;
}

.card-detail {
  font-size: 12px;
  color: #c0c4cc;
}

/* 监控区块 */
.monitor-section {
  background: #fff;
  border-radius: 12px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.section-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px 20px;
  border-bottom: 1px solid #ebeef5;
  font-size: 15px;
  font-weight: 500;
  color: #303133;
}

.section-content {
  padding: 20px;
}

/* 任务统计网格 */
.task-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.task-stat-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-radius: 8px;
  background: #f5f7fa;
}

.task-stat-item.pending {
  background: #f4f4f5;
}

.task-stat-item.processing {
  background: #fdf6ec;
}

.task-stat-item.completed {
  background: #f0f9eb;
}

.task-stat-item.failed {
  background: #fef0f0;
}

.stat-icon {
  font-size: 24px;
}

.task-stat-item.pending .stat-icon {
  color: #909399;
}

.task-stat-item.processing .stat-icon {
  color: #e6a23c;
}

.task-stat-item.completed .stat-icon {
  color: #67c23a;
}

.task-stat-item.failed .stat-icon {
  color: #f56c6c;
}

.stat-info {
  flex: 1;
}

.stat-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #303133;
}

/* 性能网格 */
.performance-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.performance-item {
  padding: 16px;
  background: #f5f7fa;
  border-radius: 8px;
  text-align: center;
}

.perf-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 8px;
}

.perf-value {
  font-size: 18px;
  font-weight: 600;
  color: #303133;
}

.success-text {
  color: #67c23a;
}

.danger-text {
  color: #f56c6c;
}

/* 小时统计 */
.hourly-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.hourly-stat-item {
  text-align: center;
  padding: 16px;
  background: #f5f7fa;
  border-radius: 8px;
}

.hourly-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 8px;
}

.hourly-value {
  font-size: 24px;
  font-weight: 700;
  color: #303133;
}

/* 队列表统计 */
.queue-table-stats {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.table-stat-item {
  text-align: center;
  padding: 16px;
  background: #f5f7fa;
  border-radius: 8px;
}

.table-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 8px;
}

.table-value {
  font-size: 24px;
  font-weight: 700;
  color: #303133;
}

/* 进程列表 */
.process-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.process-item {
  padding: 12px;
  background: #1e1e1e;
  border-radius: 6px;
  overflow-x: auto;
}

.process-cmd {
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 12px;
  color: #4ec9b0;
  white-space: pre-wrap;
  word-break: break-all;
}

/* 命令代码 */
.command-code {
  display: inline-block;
  padding: 4px 12px;
  background: #1e1e1e;
  color: #4ec9b0;
  border-radius: 4px;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 14px;
  margin-top: 8px;
}

/* 更新时间 */
.update-time {
  text-align: center;
  font-size: 12px;
  color: #c0c4cc;
  padding: 16px;
}

/* 响应式 */
@media (max-width: 1200px) {
  .status-cards {
    grid-template-columns: repeat(2, 1fr);
  }

  .task-stats-grid,
  .performance-grid,
  .hourly-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .header-right {
    width: 100%;
    flex-wrap: wrap;
  }

  .status-cards,
  .task-stats-grid,
  .performance-grid,
  .hourly-stats,
  .queue-table-stats {
    grid-template-columns: 1fr;
  }
}
</style>
