<template>
  <div class="health-archives">
    <div class="page-header">
      <h2 class="page-title">健康管理档案</h2>
      <p class="page-desc">用户舌诊、面诊、体质分析记录</p>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-value">{{ statsData.total_reports || 0 }}</div>
        <div class="stat-label">总报告数</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ statsData.today_reports || 0 }}</div>
        <div class="stat-label">今日新增</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ statsData.week_reports || 0 }}</div>
        <div class="stat-label">本周新增</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ statsData.avg_health_score || 0 }}</div>
        <div class="stat-label">平均健康分</div>
      </div>
    </div>

    <!-- 类型统计 -->
    <div class="type-stats-bar">
      <span
        v-for="(count, type) in statsData.type_stats"
        :key="type"
        class="type-stat-item"
      >
        <span class="type-dot" :class="`type-${type}`"></span>
        {{ typeLabels[type] || type }}: {{ count }}
      </span>
    </div>

    <!-- 筛选栏 -->
    <div class="filter-bar">
      <el-select
        v-model="filter.type"
        placeholder="分析类型"
        clearable
        size="small"
        style="width: 140px"
        @change="loadData(1)"
      >
        <el-option label="舌诊分析" value="tongue" />
        <el-option label="面诊分析" value="face" />
      </el-select>
      <el-input
        v-model="filter.keyword"
        placeholder="搜索用户名/邮箱/手机号"
        clearable
        size="small"
        style="width: 240px"
        @keyup.enter="loadData(1)"
        @clear="loadData(1)"
      >
        <template #prefix><el-icon><Search /></el-icon></template>
      </el-input>
      <el-date-picker
        v-model="filter.dateRange"
        type="daterange"
        range-separator="至"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        size="small"
        value-format="YYYY-MM-DD"
        @change="loadData(1)"
      />
      <el-button type="primary" size="small" @click="loadData(1)">
        <el-icon><Search /></el-icon> 搜索
      </el-button>
      <el-button size="small" @click="resetFilter">重置</el-button>
    </div>

    <!-- 数据表格 -->
    <div class="table-wrapper">
      <el-table
        :data="tableData"
        v-loading="loading"
        stripe
        border
        style="width: 100%"
        @row-click="viewDetail"
        row-key="id"
        highlight-current-row
      >
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column label="用户" min-width="180">
          <template #default="{ row }">
            <div class="user-cell">
              <el-avatar :size="32" :src="row.user?.avatar">
                {{ row.user?.username?.charAt(0)?.toUpperCase() || 'U' }}
              </el-avatar>
              <div class="user-info">
                <div class="user-name">{{ row.user?.username || '-' }}</div>
                <div class="user-meta">
                  <span>{{ genderLabels[row.user?.gender] || '-' }}</span>
                  <span class="divider">|</span>
                  <span>{{ row.user?.birthday ? calculateAge(row.user.birthday) + '岁' : '-' }}</span>
                </div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="分析类型" width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="typeTagType(row.type)" size="small">
              {{ typeLabels[row.type] || row.type }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="图片" width="100" align="center">
          <template #default="{ row }">
            <div v-if="getReportImages(row).length > 0" class="image-preview-cell">
              <el-image
                :src="getReportImages(row)[0]"
                :preview-src-list="getReportImages(row)"
                fit="cover"
                style="width: 50px; height: 50px; border-radius: 4px; cursor: pointer"
                :preview-teleported="true"
              >
                <template #error>
                  <div class="image-error">
                    <el-icon><Picture /></el-icon>
                  </div>
                </template>
              </el-image>
              <span v-if="getReportImages(row).length > 1" class="image-count">
                +{{ getReportImages(row).length - 1 }}
              </span>
            </div>
            <span v-else class="no-image">无图片</span>
          </template>
        </el-table-column>
        <el-table-column label="健康评分" width="120" align="center">
          <template #default="{ row }">
            <span v-if="row.health_score" :class="scoreClass(row.health_score)">
              {{ row.health_score }}分
            </span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="摘要" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            {{ row.summary || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="分析时间" width="180" align="center">
          <template #default="{ row }">
            {{ formatDate(row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="viewDetail(row)">
              查看详情
            </el-button>
            <el-button type="danger" link size="small" @click="handleDelete(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :page-sizes="[15, 30, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          background
          @size-change="loadData(1)"
          @current-change="loadData"
        />
      </div>
    </div>

    <!-- 详情弹窗 -->
    <el-dialog
      v-model="detailDialogVisible"
      title="健康档案详情"
      width="900px"
      :close-on-click-modal="false"
    >
      <div v-if="currentDetail" class="detail-content">
        <!-- 基本信息 -->
        <div class="detail-section">
          <h4 class="section-title">基本信息</h4>
          <el-descriptions :column="3" border size="small">
            <el-descriptions-item label="报告ID">{{ currentDetail.id }}</el-descriptions-item>
            <el-descriptions-item label="任务编号">{{ currentDetail.task?.task_no || '-' }}</el-descriptions-item>
            <el-descriptions-item label="分析类型">
              <el-tag :type="typeTagType(currentDetail.type)" size="small">
                {{ typeLabels[currentDetail.type] || currentDetail.type }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="用户名">{{ currentDetail.user?.username || '-' }}</el-descriptions-item>
            <el-descriptions-item label="邮箱">{{ currentDetail.user?.email || '-' }}</el-descriptions-item>
            <el-descriptions-item label="手机号">{{ currentDetail.user?.mobile || '-' }}</el-descriptions-item>
            <el-descriptions-item label="性别">
              {{ currentDetail.task?.gender === 1 ? '男' : currentDetail.task?.gender === 2 ? '女' : '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="年龄">{{ currentDetail.task?.age ? currentDetail.task.age + '岁' : '-' }}</el-descriptions-item>
            <el-descriptions-item label="健康评分">
              <span v-if="currentDetail.health_score" :class="scoreClass(currentDetail.health_score)">
                {{ currentDetail.health_score }}分
              </span>
              <span v-else>-</span>
            </el-descriptions-item>
            <el-descriptions-item label="分析时间">{{ formatDate(currentDetail.created_at) }}</el-descriptions-item>
            <el-descriptions-item label="完成时间">
              {{ currentDetail.task?.completed_at ? formatDate(currentDetail.task.completed_at) : '-' }}
            </el-descriptions-item>
          </el-descriptions>
        </div>

        <!-- 用户输入 -->
        <div v-if="currentDetail.task?.text" class="detail-section">
          <h4 class="section-title">用户输入</h4>
          <div class="result-content">
            <pre>{{ currentDetail.task.text }}</pre>
          </div>
        </div>

        <!-- AI提示词 -->
        <div v-if="currentDetail.task?.prompt" class="detail-section">
          <h4 class="section-title">AI提示词</h4>
          <div class="result-content prompt-content">
            <pre>{{ currentDetail.task.prompt }}</pre>
          </div>
        </div>

        <!-- 上传图片 -->
        <div v-if="getImageUrls(currentDetail.task).length > 0" class="detail-section">
          <h4 class="section-title">上传图片 ({{ getImageUrls(currentDetail.task).length }}张)</h4>
          <div class="image-gallery">
            <el-image
              v-for="(img, idx) in getImageUrls(currentDetail.task)"
              :key="idx"
              :src="img"
              :preview-src-list="getImageUrls(currentDetail.task)"
              fit="cover"
              class="analysis-image"
              preview-teleported
            />
          </div>
        </div>

        <!-- 分析结果 -->
        <div class="detail-section">
          <h4 class="section-title">分析结果</h4>
          <div class="result-content">
            <pre v-if="currentDetail.content?.text">{{ currentDetail.content.text }}</pre>
            <pre v-else-if="currentDetail.tongue_analysis">{{ currentDetail.tongue_analysis }}</pre>
            <pre v-else-if="currentDetail.face_analysis">{{ currentDetail.face_analysis }}</pre>
            <pre v-else-if="currentDetail.task?.result?.content">{{ currentDetail.task.result.content }}</pre>
            <div v-else class="empty-content">暂无分析结果</div>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="detailDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Picture } from '@element-plus/icons-vue'
import { formatDate } from '@/utils'
import { safeFetch } from '@/utils/fetch'
import { getAdminToken } from '@/utils/auth'

// 类型标签
const typeLabels: Record<string, string> = {
  tongue: '舌诊分析',
  face: '面诊分析',
}

// 性别标签
const genderLabels: Record<number, string> = {
  1: '男',
  2: '女',
}

// 根据生日计算年龄
function calculateAge(birthday: string): number {
  if (!birthday) return 0
  const birthDate = new Date(birthday)
  const today = new Date()
  let age = today.getFullYear() - birthDate.getFullYear()
  const monthDiff = today.getMonth() - birthDate.getMonth()
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
    age--
  }
  return age
}

// 获取报告图片列表
function getReportImages(row: any): string[] {
  const images: string[] = []
  
  // 从任务的 image_urls 字段获取图片
  if (row.task?.image_urls && Array.isArray(row.task.image_urls)) {
    images.push(...row.task.image_urls)
  }
  
  // 从报告的图片字段获取
  if (row.images && Array.isArray(row.images)) {
    images.push(...row.images)
  }
  
  return images
}

// 统计数据
const statsData = ref<any>({})

// 筛选条件
const filter = reactive({
  type: '',
  keyword: '',
  dateRange: null as null | string[],
})

// 表格数据
const tableData = ref<any[]>([])
const loading = ref(false)
const pagination = reactive({
  page: 1,
  per_page: 15,
  total: 0,
})

// 详情弹窗
const detailDialogVisible = ref(false)
const currentDetail = ref<any>(null)

// 类型标签样式
const typeTagType = (type: string) => {
  const map: Record<string, any> = {
    tongue: 'success',
    face: 'warning',
  }
  return map[type] || 'info'
}

// 评分样式
const scoreClass = (score: number) => {
  if (score >= 80) return 'score-high'
  if (score >= 60) return 'score-medium'
  return 'score-low'
}

// 获取统计数据
const loadStats = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/health-archives/stats', {
      headers: {
        Authorization: `Bearer ${getAdminToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      statsData.value = data.data
    }
  } catch (e) {
    // ignore
  }
}

// 加载数据
const loadData = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    params.set('per_page', String(pagination.per_page))
    if (filter.type) params.set('type', filter.type)
    if (filter.keyword) params.set('keyword', filter.keyword)
    if (filter.dateRange && filter.dateRange.length === 2) {
      params.set('start_date', filter.dateRange[0])
      params.set('end_date', filter.dateRange[1])
    }

    const res = await safeFetch(`/api/v1/admin/health-archives?${params.toString()}`, {
      headers: {
        Authorization: `Bearer ${getAdminToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      tableData.value = data.data.data
      pagination.total = data.data.total
      pagination.page = page
    } else {
      ElMessage.error(data.message || '加载失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

// 重置筛选
const resetFilter = () => {
  filter.type = ''
  filter.keyword = ''
  filter.dateRange = null
  loadData(1)
}

// 查看详情
const viewDetail = async (row: any) => {
  try {
    const res = await safeFetch(`/api/v1/admin/health-archives/${row.id}`, {
      headers: {
        Authorization: `Bearer ${getAdminToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      currentDetail.value = data.data
      detailDialogVisible.value = true
    } else {
      ElMessage.error(data.message || '加载详情失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '加载详情失败')
  }
}

// 删除
const handleDelete = async (row: any) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除该健康档案吗？此操作不可恢复。`,
      '删除确认',
      {
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        type: 'warning',
      }
    )

    const res = await safeFetch(`/api/v1/admin/health-archives/${row.id}`, {
      method: 'DELETE',
      headers: {
        Authorization: `Bearer ${getAdminToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('删除成功')
      loadData(pagination.page)
      loadStats()
    } else {
      ElMessage.error(data.message || '删除失败')
    }
  } catch (e: any) {
    if (e !== 'cancel') {
      ElMessage.error(e?.message || '删除失败')
    }
  }
}

// 格式化详情
const formatDetail = (detail: any) => {
  if (typeof detail === 'string') return detail
  try {
    // 如果是数组或对象，尝试提取content
    if (Array.isArray(detail)) {
      return detail.map((item: any) => {
        if (typeof item === 'string') return item
        return item.content || item.text || JSON.stringify(item, null, 2)
      }).join('\n\n')
    }
    if (detail.content) return detail.content
    return JSON.stringify(detail, null, 2)
  } catch {
    return String(detail)
  }
}

// 获取图片URL列表
const getImageUrls = (task: any) => {
  if (!task) return []
  if (task.image_urls && task.image_urls.length > 0) {
    return task.image_urls
  }
  if (task.image_url) {
    return [task.image_url]
  }
  return []
}

onMounted(() => {
  loadData()
  loadStats()
})
</script>

<style scoped>
.health-archives {
  padding: 20px;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 20px;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  margin: 0 0 4px;
  color: #333;
}

.page-desc {
  font-size: 13px;
  color: #999;
  margin: 0;
}

/* 统计卡片 */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.stat-card {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  border: 1px solid #f0f0f0;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #409eff;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 13px;
  color: #999;
}

/* 类型统计条 */
.type-stats-bar {
  display: flex;
  gap: 20px;
  padding: 12px 16px;
  background: #fff;
  border-radius: 8px;
  margin-bottom: 16px;
  border: 1px solid #f0f0f0;
  flex-wrap: wrap;
}

.type-stat-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #666;
}

.type-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.type-dot.type-tongue { background: #67c23a; }
.type-dot.type-face { background: #e6a23c; }

/* 筛选栏 */
.filter-bar {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #fff;
  border-radius: 8px;
  margin-bottom: 16px;
  border: 1px solid #f0f0f0;
  flex-wrap: wrap;
  align-items: center;
}

/* 表格 */
.table-wrapper {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  border: 1px solid #f0f0f0;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-size: 14px;
  font-weight: 500;
  color: #333;
}

.user-meta {
  font-size: 12px;
  color: #999;
  display: flex;
  align-items: center;
  gap: 4px;
}

.user-meta .divider {
  color: #ddd;
}

/* 图片预览 */
.image-preview-cell {
  position: relative;
  display: inline-block;
}

.image-count {
  position: absolute;
  top: -6px;
  right: -10px;
  background: #409eff;
  color: #fff;
  font-size: 10px;
  padding: 1px 5px;
  border-radius: 8px;
  line-height: 1.2;
}

.image-error {
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f5f5;
  border-radius: 4px;
  color: #ccc;
  font-size: 20px;
}

.no-image {
  color: #ccc;
  font-size: 12px;
}

/* 评分样式 */
.score-high {
  color: #67c23a;
  font-weight: 600;
}

.score-medium {
  color: #e6a23c;
  font-weight: 600;
}

.score-low {
  color: #f56c6c;
  font-weight: 600;
}

/* 分页 */
.pagination-wrapper {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}

/* 详情弹窗 */
.detail-content {
  max-height: 70vh;
  overflow-y: auto;
}

.detail-section {
  margin-bottom: 20px;
}

.section-title {
  font-size: 15px;
  font-weight: 600;
  color: #333;
  margin: 0 0 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #eee;
}

.result-content {
  background: #f8f9fa;
  border-radius: 6px;
  padding: 16px;
  max-height: 400px;
  overflow-y: auto;
}

.result-content pre {
  white-space: pre-wrap;
  word-wrap: break-word;
  font-family: inherit;
  font-size: 13px;
  line-height: 1.6;
  color: #333;
  margin: 0;
}

.prompt-content {
  background: #f0f9f4;
  border-left: 3px solid #67c23a;
}

.prompt-content pre {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-size: 12px;
  color: #333;
}

.empty-content {
  text-align: center;
  color: #999;
  padding: 40px 0;
}

.image-gallery {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.analysis-image {
  width: 150px;
  height: 150px;
  border-radius: 8px;
  border: 1px solid #eee;
  cursor: pointer;
}
</style>
