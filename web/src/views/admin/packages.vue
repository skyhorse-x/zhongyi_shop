<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { Plus, Edit, Delete, Search, Refresh, View } from '@element-plus/icons-vue'

interface Package {
  id: number
  name: string
  type: string
  type_name: string
  times: number
  days: number
  price: number
  original_price: number
  is_recommend: boolean
  is_enabled: boolean
  sort_order: number
  created_at: string
}

const loading = ref(false)
const tableData = ref<Package[]>([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(10)
const searchKeyword = ref('')

// 弹窗
const dialogVisible = ref(false)
const dialogMode = ref<'create' | 'edit'>('create')
const submitting = ref(false)
const form = reactive({
  id: 0,
  name: '',
  type: 'all' as 'tongue' | 'face' | 'all',
  times: 10,
  days: 90,
  price: 0,
  original_price: 0,
  is_recommend: false,
  is_enabled: true,
  sort_order: 0,
})

const typeOptions = [
  { value: 'all', label: '全部通用' },
  { value: 'tongue', label: '舌诊' },
  { value: 'face', label: '面诊' },
]

import { getAdminToken } from '@/utils/auth'

const getToken = (): string => getAdminToken() || ''

const typeName = (type: string) => typeOptions.find(t => t.value === type)?.label || type

const fetchPackages = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: String(currentPage.value),
      per_page: String(pageSize.value),
    })
    if (searchKeyword.value) params.append('name', searchKeyword.value)
    const res = await safeFetch(`/api/v1/admin/packages?${params}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      tableData.value = data.data.data || []
      total.value = data.data.total || 0
    } else {
      ElMessage.error(data.message || '加载套餐失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  fetchPackages()
}

const resetForm = () => {
  Object.assign(form, {
    id: 0,
    name: '',
    type: 'all',
    times: 10,
    days: 90,
    price: 0,
    original_price: 0,
    is_recommend: false,
    is_enabled: true,
    sort_order: 0,
  })
}

const openCreate = () => {
  resetForm()
  dialogMode.value = 'create'
  dialogVisible.value = true
}

const openEdit = (row: Package) => {
  Object.assign(form, row)
  dialogMode.value = 'edit'
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!form.name) {
    ElMessage.warning('请输入套餐名称')
    return
  }
  if (form.price < 0) {
    ElMessage.warning('价格不能为负')
    return
  }
  submitting.value = true
  try {
    const url = dialogMode.value === 'create'
      ? '/api/v1/admin/packages'
      : `/api/v1/admin/packages/${form.id}`
    const method = dialogMode.value === 'create' ? 'POST' : 'PUT'
    const res = await safeFetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify(form),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(dialogMode.value === 'create' ? '创建成功' : '更新成功')
      dialogVisible.value = false
      fetchPackages()
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误')
  } finally {
    submitting.value = false
  }
}

const handleDelete = async (row: Package) => {
  try {
    await ElMessageBox.confirm(
      `确认删除套餐「${row.name}」？删除后无法恢复。`,
      '删除确认',
      { type: 'warning' }
    )
    const res = await safeFetch(`/api/v1/admin/packages/${row.id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('删除成功')
      fetchPackages()
    } else {
      ElMessage.error(data.message || '删除失败')
    }
  } catch (e: any) {
    if (e !== 'cancel') ElMessage.error(e?.message || '操作失败')
  }
}

const handleToggle = async (row: Package) => {
  const res = await safeFetch(`/api/v1/admin/packages/${row.id}/toggle`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${getToken()}`,
      'Accept': 'application/json',
    },
  })
  const data = await res.json()
  if (data.code === 0) {
    ElMessage.success(data.message || '操作成功')
    fetchPackages()
  } else {
    ElMessage.error(data.message || '操作失败')
  }
}

const discount = (row: Package) => {
  if (!row.original_price || row.original_price <= row.price) return 0
  return Math.round((1 - row.price / row.original_price) * 100)
}

onMounted(() => {
  fetchPackages()
})
</script>

<template>
  <div class="page-container">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2 class="page-title">次数包管理</h2>
      <p class="page-desc">管理 AI 分析次数套餐、价格和有效期</p>
    </div>

    <el-card shadow="never" class="search-card">
      <div class="toolbar">
        <el-input
          v-model="searchKeyword"
          placeholder="套餐名称"
          clearable
          style="width: 240px"
          @keyup.enter="handleSearch"
          @clear="handleSearch"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>
        <el-button type="primary" @click="handleSearch">查询</el-button>
        <el-button @click="fetchPackages">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
        <div class="spacer" />
        <el-button type="primary" @click="openCreate">
          <el-icon><Plus /></el-icon>
          新增套餐
        </el-button>
      </div>
    </el-card>

    <el-card shadow="never" style="margin-top: 16px">
      <div class="table-scroll-wrapper">
      <el-table :data="tableData" stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="套餐名称" min-width="160">
          <template #default="{ row }">
            <div class="pkg-name">
              {{ row.name }}
              <el-tag v-if="row.is_recommend" type="success" size="small" effect="dark">推荐</el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="100">
          <template #default="{ row }">
            <el-tag size="small">{{ typeName(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="次数" width="100" align="center">
          <template #default="{ row }">
            <span class="num">{{ row.times }}</span> 次
          </template>
        </el-table-column>
        <el-table-column label="价格" width="160">
          <template #default="{ row }">
            <div class="price-cell">
              <span class="price">¥{{ Number(row.price).toFixed(2) }}</span>
              <span v-if="row.original_price > row.price" class="original">¥{{ Number(row.original_price).toFixed(2) }}</span>
              <el-tag v-if="discount(row) > 0" type="danger" size="small">省{{ discount(row) }}%</el-tag>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              :model-value="row.is_enabled"
              @change="handleToggle(row)"
              active-color="#07c160"
            />
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="80" align="center" />
        <el-table-column prop="created_at" label="创建时间" width="170" />
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="openEdit(row)">
              <el-icon><Edit /></el-icon>编辑
            </el-button>
            <el-button size="small" type="danger" link @click="handleDelete(row)">
              <el-icon><Delete /></el-icon>删除
            </el-button>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty description="暂无套餐数据" />
        </template>
      </el-table>
      </div>

      <div class="pagination">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="fetchPackages"
          @current-change="fetchPackages"
        />
      </div>
    </el-card>

    <!-- 新增/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogMode === 'create' ? '新增套餐' : '编辑套餐'"
      width="540px"
      :close-on-click-modal="false"
    >
      <el-form :model="form" label-width="100px">
        <el-form-item label="套餐名称" required>
          <el-input v-model="form.name" maxlength="50" placeholder="请输入套餐名称" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="form.type" style="width: 100%">
            <el-option v-for="t in typeOptions" :key="t.value" :value="t.value" :label="t.label" />
          </el-select>
        </el-form-item>
        <el-form-item label="分析次数" required>
          <el-input-number v-model="form.times" :min="1" :max="9999" />
          <span class="form-tip">次</span>
        </el-form-item>
        <el-form-item label="有效期">
          <el-input-number v-model="form.days" :min="0" :max="3650" />
          <span class="form-tip">天（0 或留空表示永久有效）</span>
        </el-form-item>
        <el-form-item label="价格" required>
          <el-input-number v-model="form.price" :min="0" :precision="2" :step="0.1" />
          <span class="form-tip">元</span>
        </el-form-item>
        <el-form-item label="原价">
          <el-input-number v-model="form.original_price" :min="0" :precision="2" :step="0.1" />
          <span class="form-tip">元（用于显示折扣，不参与计算）</span>
        </el-form-item>
        <el-form-item label="是否推荐">
          <el-switch v-model="form.is_recommend" active-color="#07c160" />
        </el-form-item>
        <el-form-item label="是否启用">
          <el-switch v-model="form.is_enabled" active-color="#07c160" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" :max="9999" />
          <span class="form-tip">数字越小越靠前</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">
          {{ dialogMode === 'create' ? '创建' : '保存' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-container {
  padding: 0;
}
.search-card {
  border: none;
}
.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.spacer { flex: 1; }
.pkg-name {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 500;
}
.num { color: #07c160; font-weight: 600; }
.price-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  align-items: flex-start;
}
.price { color: #ee0a24; font-weight: 700; font-size: 16px; }
.original { color: #c8c9cc; text-decoration: line-through; font-size: 12px; }
.pagination {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
.form-tip {
  margin-left: 8px;
  color: #969799;
  font-size: 12px;
}

/* 手机端适配 */
@media (max-width: 768px) {
  .el-form--inline .el-form-item {
    margin-right: 0;
    margin-bottom: 8px;
    width: 100%;
  }

  .el-form--inline .el-form-item .el-input,
  .el-form--inline .el-form-item .el-select {
    width: 100% !important;
  }

  .el-table {
    font-size: 12px;
  }

  .el-pagination {
    flex-wrap: wrap;
    justify-content: center;
  }

  .el-dialog {
    width: 90% !important;
    max-width: 400px;
  }

  .el-row {
    flex-direction: column;
  }

  .el-row .el-col {
    width: 100% !important;
    max-width: 100% !important;
    flex: 0 0 100% !important;
  }

  .price {
    font-size: 14px;
  }
}
</style>
