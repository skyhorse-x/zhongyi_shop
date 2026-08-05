<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { Plus, Edit, Delete, Search, Refresh, Link } from '@element-plus/icons-vue'

interface XianyuProduct {
  id: number
  title: string
  link: string
  amount: number
  times: number
  description: string
  sort_order: number
  is_enabled: boolean
  created_at: string
}

const loading = ref(false)
const tableData = ref<XianyuProduct[]>([])
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
  title: '',
  link: '',
  amount: 0,
  times: 0,
  description: '',
  sort_order: 0,
  is_enabled: true,
})

import { getAdminToken } from '@/utils/auth'

const getToken = (): string => getAdminToken() || ''

const fetchProducts = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: String(currentPage.value),
      per_page: String(pageSize.value),
    })
    if (searchKeyword.value) params.append('keyword', searchKeyword.value)
    const res = await safeFetch(`/api/v1/admin/xianyu-products?${params}`, {
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
      ElMessage.error(data.message || '加载商品失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  fetchProducts()
}

const resetForm = () => {
  Object.assign(form, {
    id: 0,
    title: '',
    link: '',
    amount: 0,
    times: 0,
    description: '',
    sort_order: 0,
    is_enabled: true,
  })
}

const openCreate = () => {
  resetForm()
  dialogMode.value = 'create'
  dialogVisible.value = true
}

const openEdit = (row: XianyuProduct) => {
  Object.assign(form, row)
  dialogMode.value = 'edit'
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!form.title) {
    ElMessage.warning('请输入商品名称')
    return
  }
  if (!form.link) {
    ElMessage.warning('请输入闲鱼商品链接')
    return
  }
  if (form.amount <= 0) {
    ElMessage.warning('请输入正确的售价')
    return
  }
  submitting.value = true
  try {
    const url = dialogMode.value === 'create'
      ? '/api/v1/admin/xianyu-products'
      : `/api/v1/admin/xianyu-products/${form.id}`
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
      ElMessage.success(dialogMode.value === 'create' ? '添加成功' : '更新成功')
      dialogVisible.value = false
      fetchProducts()
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误')
  } finally {
    submitting.value = false
  }
}

const handleDelete = async (row: XianyuProduct) => {
  try {
    await ElMessageBox.confirm(
      `确认删除商品「${row.title}」？删除后无法恢复。`,
      '删除确认',
      { type: 'warning' }
    )
    const res = await safeFetch(`/api/v1/admin/xianyu-products/${row.id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('删除成功')
      fetchProducts()
    } else {
      ElMessage.error(data.message || '删除失败')
    }
  } catch (e: any) {
    if (e !== 'cancel') ElMessage.error(e?.message || '操作失败')
  }
}

const handleToggle = async (row: XianyuProduct) => {
  try {
    const res = await safeFetch(`/api/v1/admin/xianyu-products/${row.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        title: row.title,
        link: row.link,
        amount: row.amount,
        times: row.times,
        description: row.description,
        sort_order: row.sort_order,
        is_enabled: !row.is_enabled,
      }),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('操作成功')
      fetchProducts()
    } else {
      ElMessage.error(data.message || '操作失败')
      fetchProducts()
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '操作失败')
    fetchProducts()
  }
}

onMounted(() => {
  fetchProducts()
})
</script>

<template>
  <div class="page-container">
    <el-card shadow="never" class="search-card">
      <div class="toolbar">
        <el-input
          v-model="searchKeyword"
          placeholder="商品名称"
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
        <el-button @click="fetchProducts">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
        <div class="spacer" />
        <el-button type="primary" @click="openCreate">
          <el-icon><Plus /></el-icon>
          新增闲鱼商品
        </el-button>
      </div>
    </el-card>

    <el-card shadow="never" style="margin-top: 16px">
      <div class="table-scroll-wrapper">
      <el-table :data="tableData" stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="商品名称" min-width="140">
          <template #default="{ row }">
            <span>{{ row.title }}</span>
          </template>
        </el-table-column>
        <el-table-column label="售价" width="110">
          <template #default="{ row }">
            <span class="price">¥{{ Number(row.amount).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="赠送次数" width="110" align="center">
          <template #default="{ row }">
            <span class="num">{{ row.times }}</span> 次
          </template>
        </el-table-column>
        <el-table-column prop="description" label="说明" min-width="140" show-overflow-tooltip />
        <el-table-column label="闲鱼链接" min-width="180">
          <template #default="{ row }">
            <a :href="row.link" target="_blank" rel="noopener" class="link-cell">
              <el-icon><Link /></el-icon>
              <span class="link-text">打开闲鱼</span>
            </a>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90" align="center">
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
        <el-table-column label="操作" width="150" fixed="right">
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
          <el-empty description="暂无闲鱼商品，点击右上角新增" />
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
          @size-change="fetchProducts"
          @current-change="fetchProducts"
        />
      </div>
    </el-card>

    <!-- 新增/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogMode === 'create' ? '新增闲鱼商品' : '编辑闲鱼商品'"
      width="540px"
      :close-on-click-modal="false"
    >
      <el-form :model="form" label-width="100px">
        <el-form-item label="商品名称" required>
          <el-input v-model="form.title" maxlength="100" placeholder="如：AI分析次数 10次充值" />
        </el-form-item>
        <el-form-item label="闲鱼链接" required>
          <el-input v-model="form.link" maxlength="500" placeholder="闲鱼商品分享链接，点击后直接跳转闲鱼">
            <template #prefix>
              <el-icon><Link /></el-icon>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item label="售价(元)" required>
          <el-input-number v-model="form.amount" :min="0.01" :precision="2" :step="1" style="width: 200px" />
        </el-form-item>
        <el-form-item label="赠送次数">
          <el-input-number v-model="form.times" :min="0" :max="100000" style="width: 200px" />
          <span class="form-tip">次</span>
        </el-form-item>
        <el-form-item label="商品说明">
          <el-input v-model="form.description" maxlength="255" type="textarea" :rows="2" placeholder="展示在前台商品卡片上的说明文字" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" :max="9999" style="width: 200px" />
          <span class="form-tip">数值越小越靠前</span>
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="form.is_enabled" active-color="#07c160" />
          <span class="form-tip">关闭后前台不展示该商品</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">
          确定
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-container {
  padding: 4px;
}

.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
}

.spacer {
  flex: 1;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.price {
  color: #f56c6c;
  font-weight: 600;
}

.num {
  color: #07c160;
  font-weight: 600;
}

.link-cell {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #1989fa;
  text-decoration: none;
}

.link-cell:hover {
  text-decoration: underline;
}

.form-tip {
  margin-left: 8px;
  font-size: 12px;
  color: #969799;
}
</style>
