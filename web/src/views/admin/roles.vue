<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { Plus, Edit, Delete, Key } from '@element-plus/icons-vue'
import { getAdminToken } from '@/utils/auth'

const getToken = (): string => getAdminToken() || ''

interface Role {
  id: number
  name: string
  code: string
  description: string
  status: number
  admins_count?: number
  permissions?: Permission[]
}

interface Permission {
  id: number
  name: string
  code: string
  module: string
  description: string
}

const tableData = ref<Role[]>([])
const loading = ref(false)
const pageSize = ref(10)
const total = ref(0)
const currentPage = ref(1)

// 加载角色列表
const loadRoles = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      per_page: pageSize.value.toString(),
    })
    const res = await safeFetch(`/api/v1/admin/roles?${params}`, {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data.data || data.data
      tableData.value = list
      total.value = data.data.total || list.length
    } else {
      ElMessage.error(data.message || '加载角色列表失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载角色列表失败')
  } finally {
    loading.value = false
  }
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  loadRoles()
}

const handleSizeChange = (size: number) => {
  pageSize.value = size
  currentPage.value = 1
  loadRoles()
}

// 新增角色
const addDialogVisible = ref(false)
const addForm = ref({ name: '', code: '', description: '', permissions: [] as number[] })

const resetAddForm = () => {
  addForm.value = { name: '', code: '', description: '', permissions: [] }
}

const handleAdd = () => {
  resetAddForm()
  addDialogVisible.value = true
  loadPermissions()
}

const submitAdd = async () => {
  if (!addForm.value.name) {
    ElMessage.warning('请输入角色名称')
    return
  }
  if (!addForm.value.code) {
    ElMessage.warning('请输入角色编码')
    return
  }
  try {
    const res = await safeFetch('/api/v1/admin/roles', {
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
      loadRoles()
    } else {
      ElMessage.error(data.message || '创建失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '创建失败')
  }
}

// 编辑角色
const editDialogVisible = ref(false)
const editForm = ref({ id: 0, name: '', description: '', permissions: [] as number[] })

const handleEdit = (row: Role) => {
  editForm.value = { id: row.id, name: row.name, description: row.description, permissions: [] }
  editDialogVisible.value = true
  loadPermissions(row.id)
}

const submitEdit = async () => {
  if (!editForm.value.name) {
    ElMessage.warning('请输入角色名称')
    return
  }
  try {
    const res = await safeFetch(`/api/v1/admin/roles/${editForm.value.id}`, {
      method: 'PUT',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        name: editForm.value.name,
        description: editForm.value.description,
        permissions: editForm.value.permissions,
      }),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('更新成功')
      editDialogVisible.value = false
      loadRoles()
    } else {
      ElMessage.error(data.message || '更新失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '更新失败')
  }
}

// 删除角色
const handleDelete = (row: Role) => {
  if (row.code === 'super_admin') {
    ElMessage.warning('超级管理员角色不可删除')
    return
  }
  ElMessageBox.confirm(
    `确定要删除角色「${row.name}」吗？该操作不可恢复。`,
    '删除确认',
    {
      confirmButtonText: '确定删除',
      cancelButtonText: '取消',
      type: 'warning',
    }
  ).then(async () => {
    try {
      const res = await safeFetch(`/api/v1/admin/roles/${row.id}`, {
        method: 'DELETE',
        headers: {
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
        },
      })
      const data = await res.json()
      if (data.code === 0) {
        ElMessage.success('删除成功')
        loadRoles()
      } else {
        ElMessage.error(data.message || '删除失败')
      }
    } catch (e: any) {
      ElMessage.error(e.message || '删除失败')
    }
  }).catch(() => {})
}

// 切换状态
const handleToggleStatus = async (row: Role) => {
  if (row.code === 'super_admin') {
    ElMessage.warning('超级管理员角色不可禁用')
    return
  }
  const newStatus = row.status === 1 ? 0 : 1
  const actionText = newStatus === 1 ? '启用' : '禁用'
  try {
    await ElMessageBox.confirm(
      `确定要${actionText}角色「${row.name}」吗？`,
      `${actionText}确认`,
      { type: 'warning' }
    )
  } catch {
    return
  }
  try {
    const res = await safeFetch(`/api/v1/admin/roles/${row.id}/toggle-status`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      row.status = newStatus
      ElMessage.success(actionText + '成功')
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  }
}

// 权限列表
const permissions = ref<Record<string, Permission[]>>({})
const permissionsLoading = ref(false)

const loadPermissions = async (roleId?: number) => {
  permissionsLoading.value = true
  try {
    const res = await safeFetch('/api/v1/admin/roles/permissions', {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      permissions.value = data.data
    }
    // 如果是编辑，加载角色已有权限
    if (roleId) {
      const roleRes = await safeFetch(`/api/v1/admin/roles/${roleId}`, {
        headers: {
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
        },
      })
      const roleData = await roleRes.json()
      if (roleData.code === 0) {
        editForm.value.permissions = roleData.data.permissions?.map((p: Permission) => p.id) || []
      }
    }
  } catch (e) {
    // 忽略错误
  } finally {
    permissionsLoading.value = false
  }
}

onMounted(() => {
  loadRoles()
})
</script>

<template>
  <div class="admin-page-wrapper">
    <div class="page-header">
      <h2>角色管理</h2>
      <span class="page-tip">管理系统角色和权限分组</span>
    </div>

    <div class="toolbar">
      <el-button type="primary" @click="handleAdd">
        <el-icon><Plus /></el-icon>
        <span style="margin-left: 4px">新增角色</span>
      </el-button>
    </div>

    <div class="table-scroll-wrapper">
    <el-table :data="tableData" border stripe style="width: 100%">
      <el-table-column prop="id" label="ID" width="60" align="center" />
      <el-table-column prop="name" label="角色名称" width="150" />
      <el-table-column prop="code" label="角色编码" width="150">
        <template #default="scope">
          {{ scope.row.code }}
          <el-tag v-if="scope.row.code === 'super_admin'" type="danger" size="small" effect="dark" style="margin-left: 6px">
            超级
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="description" label="描述" min-width="200" />
      <el-table-column label="管理员数" width="100" align="center">
        <template #default="scope">
          {{ scope.row.admins_count || 0 }}
        </template>
      </el-table-column>
      <el-table-column label="状态" width="80" align="center">
        <template #default="scope">
          <el-tag :type="scope.row.status === 1 ? 'success' : 'danger'" size="small">
            {{ scope.row.status === 1 ? '正常' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="220" align="center" fixed="right">
        <template #default="scope">
          <el-button
            type="primary"
            link
            size="small"
            :disabled="scope.row.code === 'super_admin'"
            @click="handleEdit(scope.row)"
          >
            编辑
          </el-button>
          <el-button
            type="primary"
            link
            size="small"
            :disabled="scope.row.code === 'super_admin'"
            :style="{ color: scope.row.status === 1 ? '#e6a23c' : '#67c23a' }"
            @click="handleToggleStatus(scope.row)"
          >
            {{ scope.row.status === 1 ? '禁用' : '启用' }}
          </el-button>
          <el-button
            type="danger"
            link
            size="small"
            :disabled="scope.row.code === 'super_admin'"
            @click="handleDelete(scope.row)"
          >
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>
    </div>

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
    <el-dialog v-model="addDialogVisible" title="新增角色" width="600px" :close-on-click-modal="false">
      <el-form :model="addForm" label-width="100px">
        <el-form-item label="角色名称" required>
          <el-input v-model="addForm.name" placeholder="如：运营管理员" />
        </el-form-item>
        <el-form-item label="角色编码" required>
          <el-input v-model="addForm.code" placeholder="如：operation_admin" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="addForm.description" type="textarea" :rows="3" placeholder="角色描述" />
        </el-form-item>
        <el-form-item label="权限">
          <div class="permissions-box">
            <div v-for="(perms, module) in permissions" :key="module" class="permission-group">
              <div class="module-name">{{ module }}</div>
              <el-checkbox-group v-model="addForm.permissions">
                <el-checkbox v-for="perm in perms" :key="perm.id" :value="perm.id">
                  {{ perm.name }}
                </el-checkbox>
              </el-checkbox-group>
            </div>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="addDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAdd">确认创建</el-button>
      </template>
    </el-dialog>

    <!-- 编辑弹窗 -->
    <el-dialog v-model="editDialogVisible" title="编辑角色" width="600px" :close-on-click-modal="false">
      <el-form :model="editForm" label-width="100px">
        <el-form-item label="角色名称" required>
          <el-input v-model="editForm.name" placeholder="角色名称" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="editForm.description" type="textarea" :rows="3" placeholder="角色描述" />
        </el-form-item>
        <el-form-item label="权限">
          <div class="permissions-box">
            <div v-for="(perms, module) in permissions" :key="module" class="permission-group">
              <div class="module-name">{{ module }}</div>
              <el-checkbox-group v-model="editForm.permissions">
                <el-checkbox v-for="perm in perms" :key="perm.id" :value="perm.id">
                  {{ perm.name }}
                </el-checkbox>
              </el-checkbox-group>
            </div>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitEdit">保存</el-button>
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

.toolbar {
  margin-bottom: 16px;
}

.el-table {
  margin-bottom: 16px;
}

.permissions-box {
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  padding: 12px;
  width: 100%;
}

.permission-group {
  margin-bottom: 16px;
}

.permission-group:last-child {
  margin-bottom: 0;
}

.module-name {
  font-weight: 600;
  color: #303133;
  margin-bottom: 8px;
  padding-bottom: 4px;
  border-bottom: 1px solid #ebeef5;
}

.el-checkbox-group {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
</style>
