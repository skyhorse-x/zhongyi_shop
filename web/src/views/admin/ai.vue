<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'

interface AiModel {
  id: number
  name: string
  status: number
  cost_per_call: number
  timeout: number
  retry_count: number
  api_url?: string
  api_key?: string
  model_name?: string
}

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

const models = ref<AiModel[]>([])
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
const loading = ref(false)
const recordsLoading = ref(false)

const getToken = (): string => localStorage.getItem('admin_token') || ''

// 加载AI模型列表
const loadModels = async () => {
  try {
    const res = await fetch('/api/v1/admin/ai/models', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      models.value = data.data || []
    } else {
      ElMessage.error(data.message || '加载AI模型失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载AI模型失败')
  }
}

// 加载调用记录
const loadCallRecords = async () => {
  recordsLoading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      limit: pageSize.value.toString(),
    })
    const res = await fetch(`/api/v1/admin/ai/logs?${params}`, {
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

// 加载统计数据（从调用记录计算）
const loadStats = async () => {
  try {
    const params = new URLSearchParams({ page: '1', limit: '1000' })
    const res = await fetch(`/api/v1/admin/ai/logs?${params}`, {
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
        todayCost: parseFloat(todayCost.toFixed(2)),
        monthCalls,
        monthCost: parseFloat(monthCost.toFixed(2)),
      }
    }
  } catch (e) {
    console.error('加载统计数据失败:', e)
  }
}

// 编辑模型
const editDialogVisible = ref(false)
const editForm = ref<AiModel>({
  id: 0,
  name: '',
  status: 1,
  cost_per_call: 0.05,
  timeout: 30,
  retry_count: 3,
  api_url: '',
  api_key: '',
  model_name: '',
})

const handleEdit = (model: AiModel) => {
  editForm.value = { ...model }
  editDialogVisible.value = true
}

const handleAdd = () => {
  editForm.value = {
    id: 0,
    name: '',
    status: 1,
    cost_per_call: 0.05,
    timeout: 30,
    retry_count: 3,
    api_url: '',
    api_key: '',
    model_name: '',
  }
  editDialogVisible.value = true
}

const handleSaveModel = async () => {
  try {
    const url = editForm.value.id
      ? `/api/v1/admin/ai/models/${editForm.value.id}`
      : '/api/v1/admin/ai/models'
    const method = editForm.value.id ? 'PUT' : 'POST'

    const res = await fetch(url, {
      method,
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(editForm.value),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(editForm.value.id ? '更新成功' : '添加成功')
      editDialogVisible.value = false
      loadModels()
    } else {
      ElMessage.error(data.message || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  }
}

const handleToggleStatus = async (model: AiModel) => {
  const newStatus = model.status === 1 ? 0 : 1
  try {
    const res = await fetch(`/api/v1/admin/ai/models/${model.id}`, {
      method: 'PUT',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ status: newStatus }),
    })
    const data = await res.json()
    if (data.code === 0) {
      model.status = newStatus
      ElMessage.success(newStatus === 1 ? '已启用' : '已禁用')
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  }
}

const handleDeleteModel = (model: AiModel) => {
  ElMessageBox.confirm(`确定要删除模型「${model.name}」吗？`, '确认删除', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      const res = await fetch(`/api/v1/admin/ai/models/${model.id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${getToken()}`,
          'Accept': 'application/json',
        },
      })
      const data = await res.json()
      if (data.code === 0) {
        ElMessage.success('删除成功')
        loadModels()
      } else {
        ElMessage.error(data.message || '删除失败')
      }
    } catch (e: any) {
      ElMessage.error(e.message || '删除失败')
    }
  }).catch(() => {})
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

onMounted(() => {
  loadModels()
  loadCallRecords()
  loadStats()
})
</script>

<template>
  <div class="admin-page-wrapper">
    <div class="page-header">
      <h2 class="page-title">AI管理</h2>
      <p class="page-desc">AI模型配置与调用日志</p>
    </div>

    <el-row :gutter="16">
      <el-col :span="12">
        <el-card class="card-model">
          <template #header>
            <div class="card-header">
              <span>AI模型配置</span>
              <el-button type="primary" size="small" @click="handleAdd">添加模型</el-button>
            </div>
          </template>
          <div v-for="(model, index) in models" :key="model.id" class="model-item">
            <div class="model-header">
              <span class="model-name">{{ model.name }}</span>
              <el-tag :type="model.status === 1 ? 'success' : 'danger'" size="small">
                {{ model.status === 1 ? '已启用' : '已禁用' }}
              </el-tag>
            </div>
            <el-descriptions :column="2" border size="small" class="model-desc">
              <el-descriptions-item label="费用">{{ model.cost_per_call }} 元/次</el-descriptions-item>
              <el-descriptions-item label="超时时间">{{ model.timeout }}s</el-descriptions-item>
              <el-descriptions-item label="重试次数">{{ model.retry_count }} 次</el-descriptions-item>
              <el-descriptions-item label="模型">{{ model.model_name || '-' }}</el-descriptions-item>
            </el-descriptions>
            <div class="model-actions">
              <el-button size="small" type="primary" link @click="handleEdit(model)">编辑</el-button>
              <el-button size="small" :type="model.status === 1 ? 'warning' : 'success'" link @click="handleToggleStatus(model)">
                {{ model.status === 1 ? '禁用' : '启用' }}
              </el-button>
              <el-button size="small" type="danger" link @click="handleDeleteModel(model)">删除</el-button>
            </div>
            <el-divider v-if="index < models.length - 1" />
          </div>
          <el-empty v-if="models.length === 0" description="暂无模型配置" />
        </el-card>
      </el-col>

      <el-col :span="12">
        <el-card class="card-stats">
          <template #header><span>调用统计</span></template>

          <el-descriptions :column="2" border size="small" class="stats-summary">
            <el-descriptions-item label="今日调用次数" align="center">
              <span class="stats-num">{{ callStats.todayCalls }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="今日费用" align="center">
              <span class="stats-num stats-cost">¥{{ callStats.todayCost.toFixed(2) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="本月调用次数" align="center">
              <span class="stats-num">{{ callStats.monthCalls }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="本月费用" align="center">
              <span class="stats-num stats-cost">¥{{ callStats.monthCost.toFixed(2) }}</span>
            </el-descriptions-item>
          </el-descriptions>

          <div class="record-section">
            <h4 class="record-title">调用记录</h4>
            <el-table :data="callRecords" border stripe size="small" style="width: 100%" v-loading="recordsLoading">
              <el-table-column prop="created_at" label="时间" width="160" />
              <el-table-column prop="model_name" label="模型" min-width="110" />
              <el-table-column prop="type" label="类型" width="90" />
              <el-table-column prop="duration" label="耗时" width="70" align="center" />
              <el-table-column prop="cost" label="费用" width="70" align="right">
                <template #default="{ row }">¥{{ row.cost.toFixed(2) }}</template>
              </el-table-column>
              <el-table-column prop="status" label="状态" width="70" align="center">
                <template #default="{ row }">
                  <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                    {{ row.status === 1 ? '成功' : '失败' }}
                  </el-tag>
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
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 编辑/新增模型弹窗 -->
    <el-dialog v-model="editDialogVisible" :title="editForm.id ? '编辑模型' : '添加模型'" width="500px">
      <el-form :model="editForm" label-width="100px">
        <el-form-item label="模型名称" required>
          <el-input v-model="editForm.name" placeholder="请输入模型名称" />
        </el-form-item>
        <el-form-item label="模型标识">
          <el-input v-model="editForm.model_name" placeholder="如: gpt-4o-mini" />
        </el-form-item>
        <el-form-item label="API地址">
          <el-input v-model="editForm.api_url" placeholder="请输入API地址" />
        </el-form-item>
        <el-form-item label="API密钥">
          <el-input v-model="editForm.api_key" type="password" placeholder="请输入API密钥" show-password />
        </el-form-item>
        <el-form-item label="单次费用">
          <el-input-number v-model="editForm.cost_per_call" :min="0" :precision="4" style="width: 100%" />
        </el-form-item>
        <el-form-item label="超时时间">
          <el-input-number v-model="editForm.timeout" :min="1" style="width: 100%" />
          <span class="form-unit">秒</span>
        </el-form-item>
        <el-form-item label="重试次数">
          <el-input-number v-model="editForm.retry_count" :min="0" :max="10" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="editForm.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveModel">确认</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.admin-page-wrapper { max-width: 100%; width: 100%; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 4px; }
.page-desc { font-size: 14px; color: #999; }

.card-model,
.card-stats { margin-bottom: 24px; }

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.model-item { margin-bottom: 8px; }
.model-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.model-name { font-size: 15px; font-weight: 600; color: #333; }
.model-desc { margin-bottom: 8px; }
.model-actions { margin-top: 8px; }

.stats-summary { margin-bottom: 20px; }
.stats-num { font-size: 18px; font-weight: 700; color: #409eff; }
.stats-cost { color: #f56c6c; }

.record-section { margin-top: 16px; }
.record-title { font-size: 14px; font-weight: 600; color: #333; margin-bottom: 12px; margin-top: 0; }

.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }

.form-unit { margin-left: 8px; color: #999; }
</style>
