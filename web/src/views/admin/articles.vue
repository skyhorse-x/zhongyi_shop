<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { safeFetch } from '@/utils/fetch'

const form = ref({
  title: '',
  category: ''
})

const tableData = ref<any[]>([])
const pageSize = ref(10)
const total = ref(0)
const currentPage = ref(1)
const loading = ref(false)

import { getAdminToken } from '@/utils/auth'

const getToken = (): string => getAdminToken() || ''

// 加载文章列表
const loadArticles = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      limit: pageSize.value.toString(),
    })
    if (form.value.title) params.append('title', form.value.title)

    const res = await safeFetch(`/api/v1/admin/articles?${params}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data.data || data.data
      tableData.value = list.map((article: any) => ({
        id: article.id,
        title: article.title || '-',
        category: article.category || '-',
        cover: article.cover || '',
        views: article.views || 0,
        status: article.status === 1 ? '发布' : '草稿',
        publishTime: article.published_at || article.created_at || '-',
      }))
      total.value = data.data.total || list.length
    } else {
      ElMessage.error(data.message || '加载文章列表失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载文章列表失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadArticles()
}

const handleReset = () => {
  form.value = { title: '', category: '' }
  currentPage.value = 1
  loadArticles()
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  loadArticles()
}

const handleSizeChange = (size: number) => {
  pageSize.value = size
  currentPage.value = 1
  loadArticles()
}

// 新增/编辑弹窗
const dialogVisible = ref(false)
const isEdit = ref(false)
const editForm = ref({
  id: 0,
  title: '',
  category: '',
  cover: '',
  content: '',
  views: 0,
  status: '草稿',
  publishTime: ''
})

const handleAdd = () => {
  isEdit.value = false
  editForm.value = { id: 0, title: '', category: '', cover: '', content: '', views: 0, status: '草稿', publishTime: '' }
  dialogVisible.value = true
}

const handleEdit = (row: any) => {
  isEdit.value = true
  editForm.value = { ...row }
  dialogVisible.value = true
}

const handleSave = async () => {
  try {
    const url = isEdit.value
      ? `/api/v1/admin/articles/${editForm.value.id}`
      : '/api/v1/admin/articles'
    const method = isEdit.value ? 'PUT' : 'POST'

    const res = await safeFetch(url, {
      method,
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        title: editForm.value.title,
        category: editForm.value.category,
        cover: editForm.value.cover,
        content: editForm.value.content,
        status: editForm.value.status === '发布' ? 1 : 0,
      }),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(isEdit.value ? '更新成功' : '添加成功')
      dialogVisible.value = false
      loadArticles()
    } else {
      ElMessage.error(data.message || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '保存失败')
  }
}

const handleDelete = (row: any) => {
  ElMessageBox.confirm(`确认删除文章「${row.title}」吗？`, '删除确认', {
    confirmButtonText: '确认',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      const res = await safeFetch(`/api/v1/admin/articles/${row.id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${getToken()}`,
          'Accept': 'application/json',
        },
      })
      const data = await res.json()
      if (data.code === 0) {
        ElMessage.success('删除成功')
        loadArticles()
      } else {
        ElMessage.error(data.message || '删除失败')
      }
    } catch (e: any) {
      ElMessage.error(e.message || '删除失败')
    }
  }).catch(() => {})
}

onMounted(() => {
  loadArticles()
})
</script>

<template>
  <div class="admin-page-wrapper">
    <div class="page-header">
      <h2>文章管理</h2>
    </div>

    <el-form :model="form" inline class="search-form">
      <el-row :gutter="16">
        <el-col :span="8">
          <el-form-item label="标题">
            <el-input v-model="form.title" placeholder="请输入文章标题" clearable />
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="分类">
            <el-select v-model="form.category" placeholder="全部" clearable style="width: 100%">
              <el-option label="全部" value="" />
              <el-option label="养生" value="养生" />
              <el-option label="中医" value="中医" />
              <el-option label="健康" value="健康" />
              <el-option label="食疗" value="食疗" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item>
            <el-button type="primary" @click="handleSearch">搜索</el-button>
            <el-button @click="handleReset">重置</el-button>
            <el-button type="primary" plain @click="handleAdd">新增</el-button>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>

    <el-table :data="tableData" border stripe style="width: 100%" v-loading="loading">
      <el-table-column prop="id" label="ID" width="60" align="center" />
      <el-table-column label="标题" min-width="200">
        <template #default="scope">
          <el-link type="primary" :underline="false">{{ scope.row.title }}</el-link>
        </template>
      </el-table-column>
      <el-table-column label="分类" width="80" align="center">
        <template #default="scope">
          <el-tag size="small">{{ scope.row.category }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="封面图" width="100" align="center">
        <template #default="scope">
          <el-image
            :src="scope.row.cover"
            style="width: 60px; height: 38px"
            fit="cover"
          >
            <template #error>
              <div style="font-size: 12px; color: #999;">暂无</div>
            </template>
          </el-image>
        </template>
      </el-table-column>
      <el-table-column prop="views" label="浏览量" width="80" align="center" />
      <el-table-column label="状态" width="80" align="center">
        <template #default="scope">
          <el-tag :type="scope.row.status === '发布' ? 'success' : 'warning'" size="small">
            {{ scope.row.status }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="publishTime" label="发布时间" width="170" />
      <el-table-column label="操作" width="130" align="center">
        <template #default="scope">
          <el-button type="text" size="small" @click="handleEdit(scope.row)">编辑</el-button>
          <el-button type="text" size="small" style="color: #f56c6c;" @click="handleDelete(scope.row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      v-model:current-page="currentPage"
      v-model:page-size="pageSize"
      :page-sizes="[10, 20, 50, 100]"
      :total="total"
      layout="total, sizes, prev, pager, next, jumper"
      background
      @current-change="handlePageChange"
      @size-change="handleSizeChange"
    />

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑文章' : '新增文章'" width="600px">
      <el-form :model="editForm" label-width="100px">
        <el-form-item label="标题" required>
          <el-input v-model="editForm.title" placeholder="请输入文章标题" />
        </el-form-item>
        <el-form-item label="分类" required>
          <el-select v-model="editForm.category" placeholder="请选择分类" style="width: 100%">
            <el-option label="养生" value="养生" />
            <el-option label="中医" value="中医" />
            <el-option label="健康" value="健康" />
            <el-option label="食疗" value="食疗" />
          </el-select>
        </el-form-item>
        <el-form-item label="封面图">
          <el-input v-model="editForm.cover" placeholder="请输入封面图URL" />
        </el-form-item>
        <el-form-item label="内容">
          <el-input v-model="editForm.content" type="textarea" :rows="6" placeholder="请输入文章内容" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="editForm.status" style="width: 100%">
            <el-option label="发布" value="发布" />
            <el-option label="草稿" value="草稿" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSave">确认</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.admin-page-wrapper {
  max-width: 100%;
}

.page-header h2 {
  margin: 0 0 16px 0;
  font-size: 20px;
  font-weight: 600;
  color: #303133;
}

.search-form {
  margin-bottom: 16px;
  padding: 16px;
  background: #fff;
  border-radius: 6px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.el-table {
  margin-bottom: 16px;
}

/* 手机端适配 */
@media (max-width: 768px) {
  .search-form { padding: 12px; }

  .el-form--inline .el-form-item {
    margin-right: 0;
    margin-bottom: 8px;
    width: 100%;
  }

  .el-form--inline .el-form-item .el-input,
  .el-form--inline .el-form-item .el-select {
    width: 100% !important;
  }

  .el-table { font-size: 12px; }

  .el-pagination { flex-wrap: wrap; justify-content: center; }

  .el-dialog { width: 90% !important; max-width: 400px; }

  .el-row { flex-direction: column; }

  .el-row .el-col {
    width: 100% !important;
    max-width: 100% !important;
    flex: 0 0 100% !important;
  }
}
</style>
