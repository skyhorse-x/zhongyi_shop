<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Edit, Refresh, Wallet, Promotion } from '@element-plus/icons-vue'

const form = ref({
  phone: '',
  nickname: '',
  status: '',
})

const tableData = ref<any[]>([])
const pageSize = ref(10)
const total = ref(0)
const currentPage = ref(1)
const loading = ref(false)

const getToken = (): string => localStorage.getItem('admin_token') || ''

// 加载用户列表
const loadUsers = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      per_page: pageSize.value.toString(),
    })
    if (form.value.phone) params.append('phone', form.value.phone)
    if (form.value.nickname) params.append('nickname', form.value.nickname)
    if (form.value.status) params.append('status', form.value.status)

    const res = await fetch(`/api/v1/admin/users?${params}`, {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data.data || data.data
      tableData.value = list.map((user: any) => ({
        id: user.id,
        username: user.name || user.email || '-',
        phone: user.phone || user.mobile || '-',
        mobile: user.mobile || '',
        nickname: user.nickname || user.name || '-',
        email: user.email || '',
        avatar: user.avatar || '',
        gender: user.gender ?? 0,
        genderText: genderText(user.gender),
        status: user.status === 1 ? '正常' : '禁用',
        statusValue: user.status ?? 1,
        is_promoter: !!user.is_promoter,
        birthday: user.birthday || '',
        registerTime: user.created_at || '-',
        balance: Number(user.balance ?? 0),
      }))
      total.value = data.data.total || list.length
    } else {
      ElMessage.error(data.message || '加载用户列表失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载用户列表失败')
  } finally {
    loading.value = false
  }
}

const genderText = (g: any) => {
  if (g === 1) return '男'
  if (g === 2) return '女'
  return '未知'
}

const handleSearch = () => {
  currentPage.value = 1
  loadUsers()
}

const handleReset = () => {
  form.value = { phone: '', nickname: '', status: '' }
  currentPage.value = 1
  loadUsers()
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  loadUsers()
}

const handleSizeChange = (size: number) => {
  pageSize.value = size
  currentPage.value = 1
  loadUsers()
}

// 查看弹窗
const viewDialogVisible = ref(false)
const viewData = ref<any>({})

const handleView = (row: any) => {
  viewData.value = row
  viewDialogVisible.value = true
}

// 禁用/启用
const handleToggleStatus = async (row: any) => {
  const newStatus = row.statusValue === 1 ? 0 : 1
  const actionText = newStatus === 1 ? '启用' : '禁用'
  try {
    await ElMessageBox.confirm(`确定要${actionText}用户「${row.nickname}」吗？`, `${actionText}确认`, {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch {
    return
  }

  try {
    const res = await fetch(`/api/v1/admin/users/${row.id}/status`, {
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
      row.status = newStatus === 1 ? '正常' : '禁用'
      row.statusValue = newStatus
      ElMessage.success(actionText + '成功')
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '操作失败')
  }
}

// 重置密码弹窗
const resetPwdDialogVisible = ref(false)
const resetPwdData = ref({ user: null as any, password: '' })

const handleResetPassword = (row: any) => {
  resetPwdData.value = { user: row, password: '' }
  resetPwdDialogVisible.value = true
}

const submitResetPassword = async () => {
  if (!resetPwdData.value.password) {
    ElMessage.warning('请输入新密码')
    return
  }
  if (resetPwdData.value.password.length < 6) {
    ElMessage.warning('密码长度不能少于 6 位')
    return
  }
  try {
    const res = await fetch(`/api/v1/admin/users/${resetPwdData.value.user.id}/reset-password`, {
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
      ElMessage.success('密码已重置')
      resetPwdDialogVisible.value = false
    } else {
      ElMessage.error(data.message || '重置失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '重置失败')
  }
}

const generatePassword = () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'
  let pwd = ''
  for (let i = 0; i < 10; i++) {
    pwd += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  resetPwdData.value.password = pwd
}

// ===== 编辑用户 =====
const editDialogVisible = ref(false)
const editLoading = ref(false)
const editForm = reactive({
  id: 0,
  nickname: '',
  name: '',
  mobile: '',
  email: '',
  gender: 0 as 0 | 1 | 2,
  birthday: '',
  is_promoter: false,
  status: 1 as 0 | 1,
})

const openEdit = async (row: any) => {
  // 先从后端拉取最新详情（避免数据陈旧）
  editLoading.value = true
  editDialogVisible.value = true
  try {
    const res = await fetch(`/api/v1/admin/users/${row.id}`, {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const u = data.data
      Object.assign(editForm, {
        id: u.id,
        nickname: u.nickname || '',
        name: u.name || '',
        mobile: u.mobile || '',
        email: u.email || '',
        gender: u.gender ?? 0,
        birthday: u.birthday || '',
        is_promoter: !!u.is_promoter,
        status: u.status ?? 1,
      })
    } else {
      // 失败时回退到列表行数据
      Object.assign(editForm, {
        id: row.id,
        nickname: row.nickname === '-' ? '' : row.nickname,
        name: row.username === '-' ? '' : row.username,
        mobile: row.mobile,
        email: row.email,
        gender: row.gender,
        birthday: row.birthday,
        is_promoter: row.is_promoter,
        status: row.statusValue,
      })
    }
  } catch {
    Object.assign(editForm, {
      id: row.id,
      nickname: row.nickname === '-' ? '' : row.nickname,
      name: row.username === '-' ? '' : row.username,
      mobile: row.mobile,
      email: row.email,
      gender: row.gender,
      birthday: row.birthday,
      is_promoter: row.is_promoter,
      status: row.statusValue,
    })
  } finally {
    editLoading.value = false
  }
}

const submitEdit = async () => {
  if (!editForm.nickname?.trim()) {
    ElMessage.warning('请输入昵称')
    return
  }
  if (editForm.mobile && !/^1[3-9]\d{9}$/.test(editForm.mobile)) {
    ElMessage.warning('手机号格式不正确')
    return
  }
  if (editForm.email && !/^[\w.+-]+@[\w-]+\.[\w.-]+$/.test(editForm.email)) {
    ElMessage.warning('邮箱格式不正确')
    return
  }

  editLoading.value = true
  try {
    const res = await fetch(`/api/v1/admin/users/${editForm.id}`, {
      method: 'PUT',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        nickname: editForm.nickname,
        name: editForm.name || null,
        mobile: editForm.mobile || null,
        email: editForm.email || null,
        gender: editForm.gender,
        birthday: editForm.birthday || null,
        is_promoter: editForm.is_promoter,
        status: editForm.status,
      }),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('保存成功')
      editDialogVisible.value = false
      loadUsers()
    } else {
      ElMessage.error(data.message || '保存失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '保存失败')
  } finally {
    editLoading.value = false
  }
}

onMounted(() => {
  loadUsers()
})

// ===== 余额管理 =====

// 充值/扣减弹窗
const adjustDialogVisible = ref(false)
const adjustLoading = ref(false)
const adjustForm = reactive({
  user: null as any,
  type: 'recharge' as 'recharge' | 'admin_deduct',
  amount: 0 as number | string,
  remark: '',
})
const presetAmounts = [10, 50, 100, 200, 500, 1000]

const openAdjust = (row: any, type: 'recharge' | 'admin_deduct') => {
  adjustForm.user = row
  adjustForm.type = type
  adjustForm.amount = 0
  adjustForm.remark = type === 'recharge' ? '管理员后台充值' : '管理员后台扣减'
  adjustDialogVisible.value = true
}

const submitAdjust = async () => {
  const amt = Number(adjustForm.amount)
  if (!amt || amt <= 0) {
    ElMessage.warning('请输入大于 0 的金额')
    return
  }
  if (amt > 99999.99) {
    ElMessage.warning('单次金额不能超过 99,999.99')
    return
  }
  if (
    adjustForm.type === 'admin_deduct' &&
    amt > Number(adjustForm.user?.balance ?? 0)
  ) {
    ElMessage.warning('扣减金额不能超过用户当前余额')
    return
  }
  try {
    await ElMessageBox.confirm(
      `确认要「${adjustForm.type === 'recharge' ? '充值' : '扣减'}」¥${amt.toFixed(
        2
      )} 给用户「${adjustForm.user?.nickname}」？`,
      `${adjustForm.type === 'recharge' ? '充值' : '扣减'}确认`,
      { type: 'warning', confirmButtonText: '确定', cancelButtonText: '取消' }
    )
  } catch {
    return
  }

  adjustLoading.value = true
  try {
    const res = await fetch(
      `/api/v1/admin/users/${adjustForm.user.id}/balance`,
      {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          type: adjustForm.type,
          amount: amt,
          remark: adjustForm.remark,
        }),
      }
    )
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(data.message || '操作成功')
      adjustDialogVisible.value = false
      // 刷新列表里的余额显示
      const newBal = data.data?.balance ?? 0
      const target = tableData.value.find((u) => u.id === adjustForm.user.id)
      if (target) target.balance = Number(newBal)
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '操作失败')
  } finally {
    adjustLoading.value = false
  }
}

// 流水弹窗
const logsDialogVisible = ref(false)
const logsLoading = ref(false)
const logsList = ref<any[]>([])
const logsTotal = ref(0)
const logsPage = ref(1)
const logsPageSize = ref(15)
const logsFilterType = ref('')

const logTypeName = (t: string) =>
  ({
    recharge: '后台充值',
    consume: '消费扣减',
    refund: '退款返还',
    reward: '系统奖励',
    admin_deduct: '后台扣减',
  }[t] || t)
const formatMoney = (n: any) => Number(n ?? 0).toFixed(2)
const formatTime = (s: string) => (s ? s.replace('T', ' ').slice(0, 19) : '-')

const openLogs = async (row: any) => {
  logsDialogVisible.value = true
  logsPage.value = 1
  logsList.value = []
  await loadLogs(row)
}

const loadLogs = async (row?: any) => {
  const user = row || adjustForm.user
  if (!user) return
  logsLoading.value = true
  try {
    const params = new URLSearchParams({
      page: logsPage.value.toString(),
      per_page: logsPageSize.value.toString(),
    })
    if (logsFilterType.value) params.append('type', logsFilterType.value)
    const res = await fetch(
      `/api/v1/admin/users/${user.id}/balance-logs?${params}`,
      {
        headers: {
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
        },
      }
    )
    const ct = res.headers.get('content-type') || ''
    if (!ct.includes('application/json')) {
      throw new Error(`接口返回非 JSON（status=${res.status}）`)
    }
    const data = await res.json()
    if (data.code === 0) {
      logsList.value = data.data.logs?.data ?? []
      logsTotal.value = data.data.logs?.total ?? logsList.value.length
    } else {
      ElMessage.error(data.message || '加载流水失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '加载流水失败')
  } finally {
    logsLoading.value = false
  }
}
</script>

<template>
  <div class="admin-page-wrapper">
    <div class="page-header">
      <h2>用户管理</h2>
      <span class="page-tip">管理 C 端用户账号、状态与密码</span>
    </div>

    <el-form :model="form" inline class="search-form">
      <el-row :gutter="16">
        <el-col :span="6">
          <el-form-item label="手机号">
            <el-input v-model="form.phone" placeholder="请输入手机号" clearable />
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="昵称">
            <el-input v-model="form.nickname" placeholder="请输入昵称" clearable />
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item label="状态">
            <el-select v-model="form.status" placeholder="全部" clearable style="width: 100%">
              <el-option label="正常" :value="1" />
              <el-option label="禁用" :value="0" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item>
            <el-button type="primary" @click="handleSearch">搜索</el-button>
            <el-button @click="handleReset">重置</el-button>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>

    <el-table :data="tableData" border stripe style="width: 100%" v-loading="loading">
      <el-table-column prop="id" label="ID" width="60" align="center" />
      <el-table-column prop="nickname" label="昵称" width="120" />
      <el-table-column prop="phone" label="手机号" width="130" />
      <el-table-column label="头像" width="80" align="center">
        <template #default="scope">
          <el-avatar :size="36" :src="scope.row.avatar" />
        </template>
      </el-table-column>
      <el-table-column prop="gender" label="性别" width="70" align="center">
        <template #default="scope">
          <el-tag :type="scope.row.genderText === '男' ? 'primary' : 'danger'" size="small">
            {{ scope.row.genderText }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="余额" width="100" align="center">
        <template #default="scope">
          <span style="color: #fa8c16; font-weight: 600">
            ¥{{ formatMoney(scope.row.balance) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="80" align="center">
        <template #default="scope">
          <el-tag :type="scope.row.statusValue === 1 ? 'success' : 'danger'" size="small">
            {{ scope.row.status }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="registerTime" label="注册时间" min-width="170" />
      <el-table-column label="操作" width="460" align="center" fixed="right">
        <template #default="scope">
          <el-button type="primary" link size="small" @click="handleView(scope.row)">查看</el-button>
          <el-button type="primary" link size="small" @click="openEdit(scope.row)">
            <el-icon><Edit /></el-icon>
            <span style="margin-left: 2px">编辑</span>
          </el-button>
          <el-button
            type="primary"
            link
            size="small"
            :style="{ color: scope.row.statusValue === 1 ? '#e6a23c' : '#67c23a' }"
            @click="handleToggleStatus(scope.row)"
          >
            {{ scope.row.statusValue === 1 ? '禁用' : '启用' }}
          </el-button>
          <el-button type="warning" link size="small" @click="handleResetPassword(scope.row)">
            重置密码
          </el-button>
          <el-dropdown
            trigger="click"
            @command="(cmd: string) => {
              if (cmd === 'recharge') openAdjust(scope.row, 'recharge')
              else if (cmd === 'admin_deduct') openAdjust(scope.row, 'admin_deduct')
              else if (cmd === 'logs') openLogs(scope.row)
            }"
          >
            <el-button type="success" link size="small">
              <el-icon><Wallet /></el-icon>
              <span style="margin-left: 2px">余额</span>
            </el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="recharge">充值</el-dropdown-item>
                <el-dropdown-item command="admin_deduct" divided>扣减</el-dropdown-item>
                <el-dropdown-item command="logs" divided>查看流水</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
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

    <!-- 查看弹窗 -->
    <el-dialog v-model="viewDialogVisible" title="用户详情" width="600px">
      <el-descriptions :column="2" border>
        <el-descriptions-item label="ID">{{ viewData.id }}</el-descriptions-item>
        <el-descriptions-item label="昵称">{{ viewData.nickname }}</el-descriptions-item>
        <el-descriptions-item label="手机号">{{ viewData.phone }}</el-descriptions-item>
        <el-descriptions-item label="性别">{{ viewData.gender }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="viewData.statusValue === 1 ? 'success' : 'danger'" size="small">
            {{ viewData.status }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="注册时间" :span="2">{{ viewData.registerTime }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="viewDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 编辑用户弹窗 -->
    <el-dialog
      v-model="editDialogVisible"
      title="编辑用户"
      width="600px"
      :close-on-click-modal="false"
      v-loading="editLoading"
    >
      <el-alert
        :title="`编辑用户 #${editForm.id} 的信息`"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <el-form :model="editForm" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="昵称" required>
              <el-input v-model="editForm.nickname" placeholder="请输入昵称" maxlength="50" show-word-limit />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="姓名">
              <el-input v-model="editForm.name" placeholder="可选" maxlength="50" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="手机号">
              <el-input v-model="editForm.mobile" placeholder="11位手机号" maxlength="11" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="邮箱">
              <el-input v-model="editForm.email" placeholder="可选" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="性别">
              <el-radio-group v-model="editForm.gender">
                <el-radio :value="0">未知</el-radio>
                <el-radio :value="1">男</el-radio>
                <el-radio :value="2">女</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="生日">
              <el-date-picker
                v-model="editForm.birthday"
                type="date"
                value-format="YYYY-MM-DD"
                placeholder="可选"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="账号状态">
              <el-radio-group v-model="editForm.status">
                <el-radio :value="1">正常</el-radio>
                <el-radio :value="0">禁用</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="推广员身份">
              <el-switch
                v-model="editForm.is_promoter"
                active-color="#07c160"
                inline-prompt
                active-text="是"
                inactive-text="否"
              />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="editLoading" @click="submitEdit">保存</el-button>
      </template>
    </el-dialog>

    <!-- 重置密码弹窗 -->
    <el-dialog
      v-model="resetPwdDialogVisible"
      title="重置用户密码"
      width="480px"
      :close-on-click-modal="false"
    >
      <el-alert
        :title="`即将重置用户「${resetPwdData.user?.nickname}」的密码`"
        type="warning"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <el-form label-width="80px">
        <el-form-item label="新密码" required>
          <el-input
            v-model="resetPwdData.password"
            placeholder="请输入新密码（至少 6 位）"
            show-password
          />
        </el-form-item>
        <el-form-item>
          <el-button size="small" @click="generatePassword">
            <el-icon><Refresh /></el-icon>
            <span style="margin-left: 4px">随机生成</span>
          </el-button>
          <span class="form-tip">重置后该用户需重新登录</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resetPwdDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitResetPassword">确认重置</el-button>
      </template>
    </el-dialog>

    <!-- 充值/扣减 弹窗 -->
    <el-dialog
      v-model="adjustDialogVisible"
      :title="adjustForm.type === 'recharge' ? '用户充值' : '用户扣减'"
      width="480px"
      :close-on-click-modal="false"
      v-loading="adjustLoading"
    >
      <el-alert
        :title="`用户「${adjustForm.user?.nickname}」当前余额：¥${formatMoney(adjustForm.user?.balance)}`"
        :type="adjustForm.type === 'recharge' ? 'success' : 'warning'"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <el-form label-width="80px">
        <el-form-item label="操作类型">
          <el-radio-group v-model="adjustForm.type">
            <el-radio value="recharge">充值（增加）</el-radio>
            <el-radio value="admin_deduct">扣减（减少）</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="金额" required>
          <el-input-number
            v-model="adjustForm.amount"
            :min="0.01"
            :max="99999.99"
            :precision="2"
            :step="1"
            style="width: 100%"
            placeholder="请输入金额"
          />
        </el-form-item>
        <el-form-item label="快捷">
          <el-button
            v-for="p in presetAmounts"
            :key="p"
            size="small"
            @click="adjustForm.amount = p"
            style="margin-right: 6px; margin-bottom: 4px"
          >
            ¥{{ p }}
          </el-button>
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="adjustForm.remark"
            type="textarea"
            :rows="2"
            maxlength="200"
            show-word-limit
            placeholder="如：618 活动赠送、订单退款补偿等"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="adjustDialogVisible = false">取消</el-button>
        <el-button
          :type="adjustForm.type === 'recharge' ? 'primary' : 'danger'"
          :loading="adjustLoading"
          @click="submitAdjust"
        >
          确认{{ adjustForm.type === 'recharge' ? '充值' : '扣减' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 余额流水弹窗 -->
    <el-dialog
      v-model="logsDialogVisible"
      :title="`用户「${adjustForm.user?.nickname}」余额流水`"
      width="780px"
      :close-on-click-modal="false"
    >
      <div class="logs-header">
        <div class="logs-stat">
          <span class="logs-stat-label">当前余额：</span>
          <span class="logs-stat-value">¥{{ formatMoney(adjustForm.user?.balance) }}</span>
        </div>
        <el-select
          v-model="logsFilterType"
          placeholder="全部类型"
          clearable
          size="small"
          style="width: 160px"
          @change="() => { logsPage = 1; loadLogs() }"
        >
          <el-option label="后台充值" value="recharge" />
          <el-option label="消费扣减" value="consume" />
          <el-option label="退款返还" value="refund" />
          <el-option label="系统奖励" value="reward" />
          <el-option label="后台扣减" value="admin_deduct" />
        </el-select>
      </div>

      <el-table :data="logsList" border stripe v-loading="logsLoading" max-height="420">
        <el-table-column prop="id" label="ID" width="60" align="center" />
        <el-table-column label="类型" width="100" align="center">
          <template #default="scope">
            <el-tag
              :type="['recharge','refund','reward'].includes(scope.row.type) ? 'success' : 'danger'"
              size="small"
            >
              {{ logTypeName(scope.row.type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="变动金额" width="110" align="right">
          <template #default="scope">
            <span
              :style="{
                color: Number(scope.row.change) > 0 ? '#67c23a' : '#f56c6c',
                fontWeight: 600
              }"
            >
              {{ Number(scope.row.change) > 0 ? '+' : '' }}{{ formatMoney(scope.row.change) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="变动前" width="100" align="right">
          <template #default="scope">¥{{ formatMoney(scope.row.before) }}</template>
        </el-table-column>
        <el-table-column label="变动后" width="100" align="right">
          <template #default="scope">¥{{ formatMoney(scope.row.after) }}</template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip />
        <el-table-column label="时间" width="150" align="center">
          <template #default="scope">{{ formatTime(scope.row.created_at) }}</template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-if="logsTotal > logsPageSize"
        v-model:current-page="logsPage"
        :page-size="logsPageSize"
        :total="logsTotal"
        layout="prev, pager, next, total"
        background
        @current-change="() => loadLogs()"
        style="margin-top: 12px; justify-content: flex-end"
      />

      <template #footer>
        <el-button @click="logsDialogVisible = false">关闭</el-button>
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

.logs-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 4px 12px;
}

.logs-stat-label {
  font-size: 13px;
  color: #909399;
}

.logs-stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #fa8c16;
}

</style>
