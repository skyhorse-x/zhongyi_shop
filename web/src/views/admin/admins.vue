<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { Refresh } from '@element-plus/icons-vue'

const form = ref({
  username: '',
  name: '',
})

const tableData = ref<any[]>([])
const pageSize = ref(10)
const total = ref(0)
const currentPage = ref(1)
const loading = ref(false)

import { getAdminToken } from '@/utils/auth'

const getAuthToken = (): string => getAdminToken() || ''

// 加载管理员列表
const loadAdmins = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      per_page: pageSize.value.toString(),
    })
    if (form.value.username) params.append('username', form.value.username)
    if (form.value.name) params.append('name', form.value.name)

    const res = await safeFetch(`/api/v1/admin/admins?${params}`, {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data.data || data.data
      tableData.value = list.map((admin: any) => ({
        id: admin.id,
        username: admin.username,
        name: admin.name || '-',
        email: admin.email || '-',
        avatar: admin.avatar || '',
        status: admin.status,
        statusText: admin.status === 1 ? '正常' : '禁用',
        lastLoginAt: admin.last_login_at || '从未登录',
        lastLoginIp: admin.last_login_ip || '-',
        createdAt: admin.created_at || '-',
        isSuper: admin.id === 1,
      }))
      total.value = data.data.total || list.length
    } else {
      ElMessage.error(data.message || '加载管理员列表失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载管理员列表失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadAdmins()
}

const handleReset = () => {
  form.value = { username: '', name: '' }
  currentPage.value = 1
  loadAdmins()
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  loadAdmins()
}

const handleSizeChange = (size: number) => {
  pageSize.value = size
  currentPage.value = 1
  loadAdmins()
}

// 新增管理员
const addDialogVisible = ref(false)
const addForm = ref({ username: '', password: '', name: '', email: '', status: 1 })
const addFormRef = ref()

const resetAddForm = () => {
  addForm.value = { username: '', password: '', name: '', email: '', status: 1 }
}

const handleAdd = () => {
  resetAddForm()
  addDialogVisible.value = true
}

const generatePassword = (target: 'add' | 'reset') => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'
  let pwd = ''
  for (let i = 0; i < 10; i++) {
    pwd += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  if (target === 'add') {
    addForm.value.password = pwd
  } else {
    resetPwdData.value.password = pwd
  }
}

const submitAdd = async () => {
  if (!addForm.value.username || addForm.value.username.length < 3) {
    ElMessage.warning('用户名至少 3 位')
    return
  }
  if (!addForm.value.password || addForm.value.password.length < 6) {
    ElMessage.warning('密码至少 6 位')
    return
  }
  try {
    const res = await safeFetch('/api/v1/admin/admins', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(addForm.value),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('创建成功')
      addDialogVisible.value = false
      loadAdmins()
    } else {
      ElMessage.error(data.message || '创建失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  }
}

// 编辑
const editDialogVisible = ref(false)
const editForm = ref({ id: 0, name: '', email: '', status: 1 })

const handleEdit = (row: any) => {
  if (row.isSuper) {
    ElMessage.warning('系统超级管理员信息不可修改')
    return
  }
  editForm.value = { id: row.id, name: row.name, email: row.email, status: row.status }
  editDialogVisible.value = true
}

const submitEdit = async () => {
  try {
    const res = await safeFetch(`/api/v1/admin/admins/${editForm.value.id}`, {
      method: 'PUT',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        name: editForm.value.name,
        email: editForm.value.email,
        status: editForm.value.status,
      }),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('更新成功')
      editDialogVisible.value = false
      loadAdmins()
    } else {
      ElMessage.error(data.message || '更新失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '更新失败')
  }
}

// 重置密码
const resetPwdDialogVisible = ref(false)
const resetPwdData = ref({ admin: null as any, password: '' })

const handleResetPassword = (row: any) => {
  if (row.isSuper) {
    ElMessage.warning('系统超级管理员密码不可重置')
    return
  }
  resetPwdData.value = { admin: row, password: '' }
  resetPwdDialogVisible.value = true
}

const submitResetPassword = async () => {
  if (!resetPwdData.value.password || resetPwdData.value.password.length < 6) {
    ElMessage.warning('密码至少 6 位')
    return
  }
  try {
    const res = await safeFetch(`/api/v1/admin/admins/${resetPwdData.value.admin.id}/reset-password`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ password: resetPwdData.value.password }),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('密码已重置，该账号需重新登录')
      resetPwdDialogVisible.value = false
    } else {
      ElMessage.error(data.message || '重置失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '重置失败')
  }
}

// 删除
const handleDelete = (row: any) => {
  if (row.isSuper) {
    ElMessage.warning('系统超级管理员不可删除')
    return
  }
  ElMessageBox.confirm(
    `确定要删除管理员「${row.username}」吗？该操作不可恢复。`,
    '删除确认',
    {
      confirmButtonText: '确定删除',
      cancelButtonText: '取消',
      type: 'warning',
    }
  ).then(async () => {
    try {
      const res = await safeFetch(`/api/v1/admin/admins/${row.id}`, {
        method: 'DELETE',
        headers: {
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
        },
      })
      const data = await res.json()
      if (data.code === 0) {
        ElMessage.success('删除成功')
        loadAdmins()
      } else {
        ElMessage.error(data.message || '删除失败')
      }
    } catch (e: any) {
      ElMessage.error(e.message || '删除失败')
    }
  }).catch(() => {})
}

// 切换状态
const handleToggleStatus = async (row: any) => {
  if (row.isSuper) {
    ElMessage.warning('系统超级管理员不可禁用')
    return
  }
  const newStatus = row.status === 1 ? 0 : 1
  const actionText = newStatus === 1 ? '启用' : '禁用'
  try {
    await ElMessageBox.confirm(
      `确定要${actionText}管理员「${row.username}」吗？`,
      `${actionText}确认`,
      { type: 'warning' }
    )
  } catch {
    return
  }
  try {
    const res = await safeFetch(`/api/v1/admin/admins/${row.id}`, {
      method: 'PUT',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ status: newStatus }),
    })
    const data = await res.json()
    if (data.code === 0) {
      row.status = newStatus
      row.statusText = newStatus === 1 ? '正常' : '禁用'
      ElMessage.success(actionText + '成功')
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  }
}

onMounted(() => {
  loadAdmins()
})
</script>

<template>
  <div class="admin-page-wrapper">
    <div class="page-header">
      <h2>管理员管理</h2>
      <span class="page-tip">管理系统后台账号，包括子管理员的增删改查与密码重置</span>
    </div>

    <el-form :model="form" inline class="search-form">
      <el-row :gutter="16">
        <el-col :span="6">
          <el-form-item label="用户名">
            <el-input v-model="form.username" placeholder="请输入用户名" clearable />
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="姓名">
            <el-input v-model="form.name" placeholder="请输入姓名" clearable />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item>
            <el-button type="primary" @click="handleSearch">搜索</el-button>
            <el-button @click="handleReset">重置</el-button>
            <el-button type="primary" plain @click="handleAdd">
              <el-icon><Plus /></el-icon>
              <span style="margin-left: 4px">新增管理员</span>
            </el-button>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>

    <el-table :data="tableData" border stripe style="width: 100%" v-loading="loading">
      <el-table-column prop="id" label="ID" width="60" align="center" />
      <el-table-column prop="username" label="用户名" width="140">
        <template #default="scope">
          {{ scope.row.username }}
          <el-tag v-if="scope.row.isSuper" type="danger" size="small" effect="dark" style="margin-left: 6px">
            超级
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="name" label="姓名" width="100" />
      <el-table-column prop="email" label="邮箱" min-width="180" />
      <el-table-column label="状态" width="80" align="center">
        <template #default="scope">
          <el-tag :type="scope.row.status === 1 ? 'success' : 'danger'" size="small">
            {{ scope.row.statusText }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="lastLoginAt" label="最后登录" width="170" />
      <el-table-column prop="lastLoginIp" label="登录IP" width="130" />
      <el-table-column prop="createdAt" label="创建时间" min-width="170" />
      <el-table-column label="操作" width="280" align="center" fixed="right">
        <template #default="scope">
          <el-button
            type="primary"
            link
            size="small"
            :disabled="scope.row.isSuper"
            @click="handleEdit(scope.row)"
          >
            编辑
          </el-button>
          <el-button
            type="primary"
            link
            size="small"
            :disabled="scope.row.isSuper"
            :style="{ color: scope.row.status === 1 ? '#e6a23c' : '#67c23a' }"
            @click="handleToggleStatus(scope.row)"
          >
            {{ scope.row.status === 1 ? '禁用' : '启用' }}
          </el-button>
          <el-button
            type="warning"
            link
            size="small"
            :disabled="scope.row.isSuper"
            @click="handleResetPassword(scope.row)"
          >
            重置密码
          </el-button>
          <el-button
            type="danger"
            link
            size="small"
            :disabled="scope.row.isSuper"
            @click="handleDelete(scope.row)"
          >
            删除
          </el-button>
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

    <!-- 新增弹窗 -->
    <el-dialog v-model="addDialogVisible" title="新增管理员" width="500px" :close-on-click-modal="false">
      <el-form :model="addForm" label-width="100px">
        <el-form-item label="用户名" required>
          <el-input v-model="addForm.username" placeholder="登录用户名（至少 3 位）" />
        </el-form-item>
        <el-form-item label="密码" required>
          <el-input v-model="addForm.password" placeholder="登录密码（至少 6 位）" show-password />
          <div style="margin-top: 6px">
            <el-button size="small" @click="generatePassword('add')">
              <el-icon><Refresh /></el-icon>
              <span style="margin-left: 4px">随机生成</span>
            </el-button>
          </div>
        </el-form-item>
        <el-form-item label="姓名">
          <el-input v-model="addForm.name" placeholder="管理员姓名" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="addForm.email" placeholder="邮箱地址" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="addForm.status">
            <el-radio :value="1">正常</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="addDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAdd">确认创建</el-button>
      </template>
    </el-dialog>

    <!-- 编辑弹窗 -->
    <el-dialog v-model="editDialogVisible" title="编辑管理员" width="500px" :close-on-click-modal="false">
      <el-form :model="editForm" label-width="100px">
        <el-form-item label="姓名">
          <el-input v-model="editForm.name" placeholder="管理员姓名" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="editForm.email" placeholder="邮箱地址" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="editForm.status">
            <el-radio :value="1">正常</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitEdit">保存</el-button>
      </template>
    </el-dialog>

    <!-- 重置密码弹窗 -->
    <el-dialog
      v-model="resetPwdDialogVisible"
      title="重置管理员密码"
      width="480px"
      :close-on-click-modal="false"
    >
      <el-alert
        :title="`即将重置管理员「${resetPwdData.admin?.username}」的密码`"
        type="warning"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <el-form label-width="80px">
        <el-form-item label="新密码" required>
          <el-input v-model="resetPwdData.password" placeholder="至少 6 位" show-password />
        </el-form-item>
        <el-form-item>
          <el-button size="small" @click="generatePassword('reset')">
            <el-icon><Refresh /></el-icon>
            <span style="margin-left: 4px">随机生成</span>
          </el-button>
          <span class="form-tip">重置后该账号需重新登录</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resetPwdDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitResetPassword">确认重置</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.admin-page-wrapper {
  max-width: 100%;
}

.page-header {
  display: flex;
  align-items: baseline;
  gap: 12px;
  margin-bottom: 16px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #303133;
}

.page-tip {
  font-size: 13px;
  color: #909399;
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

.form-tip {
  margin-left: 12px;
  font-size: 12px;
  color: #909399;
}
</style>
