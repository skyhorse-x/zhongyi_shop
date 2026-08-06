<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { Edit, Refresh, Wallet, Promotion, MoreFilled, View, Close, Lock, Plus, Minus, List, Document, ShoppingBag, Coin, Gift, Timer, Remove } from '@element-plus/icons-vue'

const form = ref({
  phone: '',
  username: '',
  status: '',
})

const tableData = ref<any[]>([])
const pageSize = ref(10)
const total = ref(0)
const currentPage = ref(1)
const loading = ref(false)
const sortField = ref('id')
const sortOrder = ref('desc')

import { getAdminToken } from '@/utils/auth'

const getToken = (): string => getAdminToken() || ''

// ===== 新增用户 =====
const createDialogVisible = ref(false)
const createLoading = ref(false)
const createForm = reactive({
  mobile: '',
  password: '',
  username: '',
  name: '',
  email: '',
  gender: 0 as 0 | 1 | 2,
  birthday: '',
  is_promoter: false,
  status: 1 as 0 | 1,
})

const openCreate = () => {
  Object.assign(createForm, {
    mobile: '',
    password: '',
    username: '',
    name: '',
    email: '',
    gender: 0,
    birthday: '',
    is_promoter: false,
    status: 1,
  })
  createDialogVisible.value = true
}

const generateCreatePassword = () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'
  let pwd = ''
  for (let i = 0; i < 10; i++) {
    pwd += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  createForm.password = pwd
}

const submitCreate = async () => {
  if (!createForm.mobile) {
    ElMessage.warning('请输入手机号')
    return
  }
  if (!/^1[3-9]\d{9}$/.test(createForm.mobile)) {
    ElMessage.warning('手机号格式不正确')
    return
  }
  if (!createForm.password) {
    ElMessage.warning('请输入密码')
    return
  }
  if (createForm.password.length < 6) {
    ElMessage.warning('密码长度不能少于 6 位')
    return
  }
  if (!createForm.username?.trim()) {
    ElMessage.warning('请输入用户名')
    return
  }
  if (createForm.email && !/^[\w.+-]+@[\w-]+\.[\w.-]+$/.test(createForm.email)) {
    ElMessage.warning('邮箱格式不正确')
    return
  }

  createLoading.value = true
  try {
    const res = await safeFetch('/api/v1/admin/users', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        mobile: createForm.mobile,
        password: createForm.password,
        username: createForm.username,
        name: createForm.name || null,
        email: createForm.email || null,
        gender: createForm.gender,
        birthday: createForm.birthday || null,
        is_promoter: createForm.is_promoter,
        status: createForm.status,
      }),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('用户创建成功')
      createDialogVisible.value = false
      loadUsers()
    } else {
      ElMessage.error(data.message || '创建失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '创建失败')
  } finally {
    createLoading.value = false
  }
}

// 加载用户列表
const loadUsers = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      per_page: pageSize.value.toString(),
    })
    if (form.value.phone) params.append('phone', form.value.phone)
    if (form.value.username) params.append('username', form.value.username)
    if (form.value.status !== '' && form.value.status !== null && form.value.status !== undefined) {
      params.append('status', form.value.status)
    }
    params.append('sort_field', sortField.value)
    params.append('sort_order', sortOrder.value)

    const res = await safeFetch(`/api/v1/admin/users?${params}`, {
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
        username: user.username || user.name || user.email || '-',
        phone: user.phone || user.mobile || '-',
        mobile: user.mobile || '',
        email: user.email || '',
        avatar: user.avatar || '',
        gender: user.gender ?? 0,
        genderText: genderText(user.gender),
        status: user.status === 1 ? '正常' : '禁用',
        statusValue: user.status ?? 1,
        is_promoter: !!user.is_promoter,
        birthday: user.birthday || '',
        registerTime: user.created_at || '-',
        register_ip: user.register_ip || '',
        last_visit_at: user.last_visit_at || '',
        last_visit_ip: user.last_visit_ip || '',
        last_login_at: user.last_login_at || '',
        last_login_ip: user.last_login_ip || '',
        balance: Number(user.balance ?? 0),
        analysis_times: Number(user.analysis_times ?? 0),
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

const handleSortChange = ({ prop, order }: { prop: string; order: string }) => {
  sortField.value = prop
  sortOrder.value = order === 'ascending' ? 'asc' : 'desc'
  currentPage.value = 1
  loadUsers()
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
  form.value = { phone: '', username: '', status: '' }
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
    await ElMessageBox.confirm(`确定要${actionText}用户「${row.username}」吗？`, `${actionText}确认`, {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch {
    return
  }

  try {
    const res = await safeFetch(`/api/v1/admin/users/${row.id}/status`, {
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
    const res = await safeFetch(`/api/v1/admin/users/${resetPwdData.value.user.id}/reset-password`, {
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
  username: '',
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
    const res = await safeFetch(`/api/v1/admin/users/${row.id}`, {
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
        username: u.username || u.name || '',
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
        username: row.username === '-' ? '' : row.username,
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
      username: row.username === '-' ? '' : row.username,
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
  if (!editForm.username?.trim()) {
    ElMessage.warning('请输入用户名')
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
    const res = await safeFetch(`/api/v1/admin/users/${editForm.id}`, {
      method: 'PUT',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        username: editForm.username,
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
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
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
      )} 给用户「${adjustForm.user?.username}」？`,
      `${adjustForm.type === 'recharge' ? '充值' : '扣减'}确认`,
      { type: 'warning', confirmButtonText: '确定', cancelButtonText: '取消' }
    )
  } catch {
    return
  }

  adjustLoading.value = true
  try {
    const res = await safeFetch(
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

// ===== 积分管理 =====
const adjustCreditsDialogVisible = ref(false)
const adjustCreditsLoading = ref(false)
const adjustCreditsForm = reactive({
  user: null as any,
  type: 'recharge' as 'recharge' | 'admin_deduct',
  amount: 0 as number | string,
  remark: '',
})
const presetCreditsAmounts = [1, 5, 10, 20, 50, 100]

const openAdjustCredits = (row: any, type: 'recharge' | 'admin_deduct') => {
  adjustCreditsForm.user = row
  adjustCreditsForm.type = type
  adjustCreditsForm.amount = 0
  adjustCreditsForm.remark = type === 'recharge' ? '管理员后台充值积分' : '管理员后台扣减积分'
  adjustCreditsDialogVisible.value = true
}

const submitAdjustCredits = async () => {
  const amt = Number(adjustCreditsForm.amount)
  if (!amt || amt <= 0) {
    ElMessage.warning('请输入大于 0 的积分数量')
    return
  }
  if (!Number.isInteger(amt)) {
    ElMessage.warning('积分必须为整数')
    return
  }
  if (amt > 99999) {
    ElMessage.warning('单次积分不能超过 99,999')
    return
  }
  if (
    adjustCreditsForm.type === 'admin_deduct' &&
    amt > Number(adjustCreditsForm.user?.analysis_times ?? 0)
  ) {
    ElMessage.warning('扣减积分不能超过用户当前积分')
    return
  }
  try {
    await ElMessageBox.confirm(
      `确认要「${adjustCreditsForm.type === 'recharge' ? '充值' : '扣减'}」${amt} 积分给用户「${adjustCreditsForm.user?.username}」？`,
      `${adjustCreditsForm.type === 'recharge' ? '充值' : '扣减'}积分确认`,
      { type: 'warning', confirmButtonText: '确定', cancelButtonText: '取消' }
    )
  } catch {
    return
  }

  adjustCreditsLoading.value = true
  try {
    const res = await safeFetch(
      `/api/v1/admin/users/${adjustCreditsForm.user.id}/credits`,
      {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          type: adjustCreditsForm.type,
          amount: amt,
          remark: adjustCreditsForm.remark,
        }),
      }
    )
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(data.message || '操作成功')
      adjustCreditsDialogVisible.value = false
      // 刷新列表里的积分显示
      const newCredits = data.data?.analysis_times ?? 0
      const target = tableData.value.find((u) => u.id === adjustCreditsForm.user.id)
      if (target) target.analysis_times = Number(newCredits)
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '操作失败')
  } finally {
    adjustCreditsLoading.value = false
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

// 移动端检测
const isMobile = ref(window.innerWidth <= 768)
const checkMobile = () => {
  isMobile.value = window.innerWidth <= 768
}

const logTypeName = (t: string) =>
  ({
    recharge: '官方充值',
    consume: '消费扣减',
    refund: '退款返还',
    reward: '系统奖励',
    admin_deduct: '系统扣减',
  }[t] || t)

// ===== 积分流水弹窗 =====
const creditsLogsDialogVisible = ref(false)
const creditsLogsLoading = ref(false)
const creditsLogsList = ref<any[]>([])
const creditsLogsTotal = ref(0)
const creditsLogsPage = ref(1)
const creditsLogsPageSize = ref(15)
const creditsLogsFilterType = ref('')
const creditsLogsUser = ref<any>(null)

const creditsLogTypeName = (t: string) =>
  ({
    recharge: '官方充值',
    use: '分析消费',
    refund: '退款返还',
    reward: '系统奖励',
    admin_deduct: '系统扣减',
    register_grant: '注册赠送',
    purchase: '购买',
  }[t] || t)

const getCreditsDotClass = (type: string) => {
  const map: Record<string, string> = {
    recharge: 'dot-green',
    use: 'dot-orange',
    refund: 'dot-blue',
    reward: 'dot-purple',
    admin_deduct: 'dot-red',
    register_grant: 'dot-cyan',
    purchase: 'dot-green',
  }
  return map[type] || 'dot-gray'
}

const getCreditsIcon = (type: string) => {
  const map: Record<string, any> = {
    recharge: Plus,
    use: Coin,
    refund: Refresh,
    reward: Gift,
    admin_deduct: Remove,
    register_grant: ShoppingBag,
    purchase: ShoppingBag,
  }
  return map[type] || Coin
}

const openCreditsLogs = async (row: any) => {
  creditsLogsDialogVisible.value = true
  creditsLogsPage.value = 1
  creditsLogsList.value = []
  creditsLogsUser.value = row
  await loadCreditsLogs()
}

const loadCreditsLogs = async () => {
  if (!creditsLogsUser.value) return
  creditsLogsLoading.value = true
  try {
    const params = new URLSearchParams({
      page: creditsLogsPage.value.toString(),
      per_page: creditsLogsPageSize.value.toString(),
    })
    if (creditsLogsFilterType.value) params.append('type', creditsLogsFilterType.value)
    const res = await safeFetch(
      `/api/v1/admin/users/${creditsLogsUser.value.id}/credits-logs?${params}`,
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
      creditsLogsList.value = data.data.logs?.data ?? []
      creditsLogsTotal.value = data.data.logs?.total ?? creditsLogsList.value.length
    } else {
      ElMessage.error(data.message || '加载积分流水失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '加载积分流水失败')
  } finally {
    creditsLogsLoading.value = false
  }
}
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
    const res = await safeFetch(
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
      <div style="margin-left: auto">
        <el-button type="primary" @click="openCreate">+ 新增用户</el-button>
      </div>
    </div>

    <el-form :model="form" inline class="search-form">
      <el-row :gutter="16">
        <el-col :xs="24" :sm="12" :md="6">
          <el-form-item label="手机号">
            <el-input v-model="form.phone" placeholder="请输入手机号" clearable />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :sm="12" :md="6">
          <el-form-item label="用户名">
            <el-input v-model="form.username" placeholder="请输入用户名" clearable />
          </el-form-item>
        </el-col>
        <el-col :xs="24" :sm="12" :md="6">
          <el-form-item label="状态">
            <el-select v-model="form.status" placeholder="全部" clearable style="width: 100%">
              <el-option label="正常" :value="1" />
              <el-option label="禁用" :value="0" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :xs="24" :sm="12" :md="6">
          <el-form-item>
            <el-button type="primary" @click="handleSearch">搜索</el-button>
            <el-button @click="handleReset">重置</el-button>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>

    <div class="table-scroll-wrapper">
      <el-table :data="tableData" border stripe @sort-change="handleSortChange">
        <el-table-column prop="id" label="ID" width="80" align="center" sortable="custom" />
        <el-table-column label="用户信息" min-width="140">
          <template #default="scope">
            <div class="user-info-cell">
              <div class="user-name">{{ scope.row.username }}</div>
              <div class="user-phone">{{ scope.row.phone !== '-' ? scope.row.phone : '未绑定手机' }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="头像" width="60" align="center" class-name="hidden-mobile">
          <template #default="scope">
            <el-avatar :size="32" :src="scope.row.avatar" />
          </template>
        </el-table-column>
        <el-table-column prop="gender" label="性别" width="60" align="center" class-name="hidden-mobile">
          <template #default="scope">
            <el-tag :type="scope.row.genderText === '男' ? 'primary' : 'danger'" size="small">
              {{ scope.row.genderText }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="余额" width="90" align="center">
          <template #default="scope">
            <span :style="{ color: scope.row.balance > 0 ? '#fa8c16' : '#999', fontWeight: 600 }">
              ¥{{ formatMoney(scope.row.balance) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="积分" width="80" align="center">
          <template #default="scope">
            <span :style="{ color: scope.row.analysis_times > 0 ? '#67c23a' : '#999', fontWeight: 600 }">
              {{ scope.row.analysis_times || 0 }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="70" align="center">
          <template #default="scope">
            <el-tag :type="scope.row.statusValue === 1 ? 'success' : 'danger'" size="small">
              {{ scope.row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="registerTime" label="注册时间" min-width="160" class-name="hidden-mobile">
          <template #default="scope">
            {{ formatTime(scope.row.created_at) }}
          </template>
        </el-table-column>
        <el-table-column label="注册IP" width="130" class-name="hidden-mobile">
          <template #default="scope">
            {{ scope.row.register_ip || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="最后访问" width="160" class-name="hidden-mobile">
          <template #default="scope">
            {{ formatTime(scope.row.last_visit_at) }}
          </template>
        </el-table-column>
        <el-table-column label="访问IP" width="130" class-name="hidden-mobile">
          <template #default="scope">
            {{ scope.row.last_visit_ip || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" align="center" :fixed="isMobile ? false : 'right'">
          <template #default="scope">
            <el-dropdown
              trigger="click"
              @command="(cmd: string) => {
                if (cmd === 'view') handleView(scope.row)
                else if (cmd === 'edit') openEdit(scope.row)
                else if (cmd === 'toggle') handleToggleStatus(scope.row)
                else if (cmd === 'resetpwd') handleResetPassword(scope.row)
                else if (cmd === 'recharge') openAdjust(scope.row, 'recharge')
                else if (cmd === 'admin_deduct') openAdjust(scope.row, 'admin_deduct')
                else if (cmd === 'credits_recharge') openAdjustCredits(scope.row, 'recharge')
                else if (cmd === 'credits_deduct') openAdjustCredits(scope.row, 'admin_deduct')
                else if (cmd === 'credits_logs') openCreditsLogs(scope.row)
                else if (cmd === 'logs') openLogs(scope.row)
              }"
            >
              <el-button type="primary" size="small" circle :icon="MoreFilled" />
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="view">
                    <el-icon><View /></el-icon> 查看
                  </el-dropdown-item>
                  <el-dropdown-item command="edit" divided>
                    <el-icon><Edit /></el-icon> 编辑
                  </el-dropdown-item>
                  <el-dropdown-item command="toggle" divided>
                    <el-icon><Close /></el-icon> {{ scope.row.statusValue === 1 ? '禁用' : '启用' }}
                  </el-dropdown-item>
                  <el-dropdown-item command="resetpwd" divided>
                    <el-icon><Lock /></el-icon> 重置密码
                  </el-dropdown-item>
                  <el-dropdown-item command="recharge">
                    <el-icon><Plus /></el-icon> 充值
                  </el-dropdown-item>
                  <el-dropdown-item command="admin_deduct">
                    <el-icon><Minus /></el-icon> 扣减
                  </el-dropdown-item>
                  <el-dropdown-item command="credits_recharge" divided>
                    <el-icon><Plus /></el-icon> 充值积分
                  </el-dropdown-item>
                  <el-dropdown-item command="credits_deduct">
                    <el-icon><Minus /></el-icon> 扣减积分
                  </el-dropdown-item>
                  <el-dropdown-item command="credits_logs">
                    <el-icon><List /></el-icon> 查看积分流水
                  </el-dropdown-item>
                  <el-dropdown-item command="logs" divided>
                    <el-icon><List /></el-icon> 查看余额流水
                  </el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
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

    <!-- 查看弹窗 -->
    <el-dialog v-model="viewDialogVisible" title="用户详情" width="600px">
      <el-descriptions :column="2" border>
        <el-descriptions-item label="ID">{{ viewData.id }}</el-descriptions-item>
        <el-descriptions-item label="用户名">{{ viewData.username }}</el-descriptions-item>
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
            <el-form-item label="用户名" required>
              <el-input v-model="editForm.username" placeholder="请输入用户名" maxlength="50" show-word-limit />
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
        :title="`即将重置用户「${resetPwdData.user?.username}」的密码`"
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
    >
      <el-alert
        :title="`用户「${adjustForm.user?.username}」当前余额：¥${formatMoney(adjustForm.user?.balance)}`"
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

    <!-- 积分充值/扣减 弹窗 -->
    <el-dialog
      v-model="adjustCreditsDialogVisible"
      :title="adjustCreditsForm.type === 'recharge' ? '积分充值' : '积分扣减'"
      :width="isMobile ? '95%' : '480px'"
      :close-on-click-modal="false"
      class="credits-dialog"
    >
      <el-alert
        :title="`用户「${adjustCreditsForm.user?.username}」当前积分：${adjustCreditsForm.user?.analysis_times || 0}`"
        :type="adjustCreditsForm.type === 'recharge' ? 'success' : 'warning'"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <el-form :label-width="isMobile ? '60px' : '80px'">
        <el-form-item label="操作类型">
          <el-radio-group v-model="adjustCreditsForm.type">
            <el-radio value="recharge">充值（增加）</el-radio>
            <el-radio value="admin_deduct">扣减（减少）</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="积分" required>
          <el-input-number
            v-model="adjustCreditsForm.amount"
            :min="1"
            :max="99999"
            :step="1"
            style="width: 100%"
            placeholder="请输入积分数量"
          />
        </el-form-item>
        <el-form-item label="快捷">
          <div class="preset-buttons">
            <el-button
              v-for="p in presetCreditsAmounts"
              :key="p"
              size="small"
              @click="adjustCreditsForm.amount = p"
            >
              {{ p }} 积分
            </el-button>
          </div>
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="adjustCreditsForm.remark"
            type="textarea"
            :rows="2"
            maxlength="200"
            show-word-limit
            placeholder="如：活动赠送、补偿等"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="adjustCreditsDialogVisible = false">取消</el-button>
        <el-button
          :type="adjustCreditsForm.type === 'recharge' ? 'primary' : 'danger'"
          :loading="adjustCreditsLoading"
          @click="submitAdjustCredits"
        >
          确认{{ adjustCreditsForm.type === 'recharge' ? '充值' : '扣减' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 新增用户弹窗 -->
    <el-dialog
      v-model="createDialogVisible"
      title="新增用户"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-alert
        title="创建新用户账号，手机号将作为登录账号"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <el-form :model="createForm" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="手机号" required>
              <el-input v-model="createForm.mobile" placeholder="11位手机号" maxlength="11" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="用户名" required>
              <el-input v-model="createForm.username" placeholder="请输入用户名" maxlength="50" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="密码" required>
              <el-input
                v-model="createForm.password"
                placeholder="至少 6 位"
                show-password
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="姓名">
              <el-input v-model="createForm.name" placeholder="可选" maxlength="50" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="邮箱">
              <el-input v-model="createForm.email" placeholder="可选" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="性别">
              <el-radio-group v-model="createForm.gender">
                <el-radio :value="0">未知</el-radio>
                <el-radio :value="1">男</el-radio>
                <el-radio :value="2">女</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="生日">
              <el-date-picker
                v-model="createForm.birthday"
                type="date"
                value-format="YYYY-MM-DD"
                placeholder="可选"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="账号状态">
              <el-radio-group v-model="createForm.status">
                <el-radio :value="1">正常</el-radio>
                <el-radio :value="0">禁用</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="推广员身份">
              <el-switch
                v-model="createForm.is_promoter"
                active-color="#07c160"
                inline-prompt
                active-text="是"
                inactive-text="否"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="随机密码">
              <el-button size="small" @click="generateCreatePassword">生成随机密码</el-button>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="createLoading" @click="submitCreate">创建用户</el-button>
      </template>
    </el-dialog>

    <!-- 余额流水弹窗 -->
    <el-dialog
      v-model="logsDialogVisible"
      :title="`用户「${adjustForm.user?.username}」余额流水`"
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
          <el-option label="官方充值" value="recharge" />
          <el-option label="消费扣减" value="consume" />
          <el-option label="退款返还" value="refund" />
          <el-option label="系统奖励" value="reward" />
          <el-option label="系统扣减" value="admin_deduct" />
        </el-select>
      </div>

      <el-table :data="logsList" border stripe max-height="420">
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

    <!-- 积分流水弹窗 -->
    <el-dialog
      v-model="creditsLogsDialogVisible"
      :title="`用户「${creditsLogsUser?.username}」积分流水`"
      :width="isMobile ? '95%' : '680px'"
      :close-on-click-modal="false"
      class="credits-logs-dialog"
    >
      <div class="credits-logs-header">
        <div class="credits-logs-stat">
          <div class="credits-stat-icon">
            <el-icon :size="24"><Coin /></el-icon>
          </div>
          <div class="credits-stat-info">
            <span class="credits-logs-stat-label">当前积分</span>
            <span class="credits-logs-stat-value">{{ creditsLogsUser?.analysis_times || 0 }}</span>
          </div>
        </div>
        <el-select
          v-model="creditsLogsFilterType"
          placeholder="全部类型"
          clearable
          size="small"
          style="width: 140px"
          @change="() => { creditsLogsPage = 1; loadCreditsLogs() }"
        >
          <el-option label="分析消费" value="use" />
          <el-option label="退款返还" value="refund" />
          <el-option label="系统奖励" value="reward" />
          <el-option label="注册赠送" value="register_grant" />
          <el-option label="购买" value="purchase" />
          <el-option label="官方充值" value="recharge" />
          <el-option label="系统扣减" value="admin_deduct" />
        </el-select>
      </div>

      <!-- 时间线样式流水列表 -->
      <div v-loading="creditsLogsLoading" class="credits-timeline">
        <div v-if="creditsLogsList.length === 0 && !creditsLogsLoading" class="credits-empty">
          <el-icon :size="48" color="#ccc"><Document /></el-icon>
          <p>暂无积分流水记录</p>
        </div>
        
        <div
          v-for="(item, index) in creditsLogsList"
          :key="item.id"
          class="timeline-item"
        >
          <div class="timeline-dot" :class="getCreditsDotClass(item.type)">
            <el-icon :size="14"><component :is="getCreditsIcon(item.type)" /></el-icon>
          </div>
          <div class="timeline-content">
            <div class="timeline-header">
              <span class="timeline-type">{{ creditsLogTypeName(item.type) }}</span>
              <span
                class="timeline-change"
                :class="Number(item.change) > 0 ? 'positive' : 'negative'"
              >
                {{ Number(item.change) > 0 ? '+' : '' }}{{ item.change }}
              </span>
            </div>
            <div class="timeline-remark">{{ item.mark || item.remark || '-' }}</div>
            <div class="timeline-footer">
              <span class="timeline-balance">余额：{{ item.before }} → {{ item.after }}</span>
              <span class="timeline-time">{{ formatTime(item.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>

      <el-pagination
        v-if="creditsLogsTotal > creditsLogsPageSize"
        v-model:current-page="creditsLogsPage"
        :page-size="creditsLogsPageSize"
        :total="creditsLogsTotal"
        layout="prev, pager, next, total"
        background
        @current-change="() => loadCreditsLogs()"
        style="margin-top: 16px; justify-content: flex-end"
      />

      <template #footer>
        <el-button @click="creditsLogsDialogVisible = false">关闭</el-button>
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

/* 用户信息单元格 */
.user-info-cell {
  line-height: 1.5;
}

.user-name {
  font-weight: 500;
  color: #303133;
}

.user-phone {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}

/* 积分流水弹窗样式 */
.credits-logs-dialog .credits-logs-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 4px 16px;
  border-bottom: 1px solid #ebeef5;
  margin-bottom: 16px;
}

.credits-logs-stat {
  display: flex;
  align-items: center;
  gap: 12px;
}

.credits-stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, #67c23a 0%, #85ce61 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  box-shadow: 0 4px 12px rgba(103, 194, 58, 0.3);
}

.credits-stat-info {
  display: flex;
  flex-direction: column;
}

.credits-logs-stat-label {
  font-size: 13px;
  color: #909399;
  line-height: 1.2;
}

.credits-logs-stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #67c23a;
  line-height: 1.2;
}

.credits-timeline {
  max-height: 420px;
  overflow-y: auto;
  padding: 0 8px;
}

.credits-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 0;
  color: #999;
}

.credits-empty p {
  margin-top: 12px;
  font-size: 14px;
}

.timeline-item {
  display: flex;
  gap: 12px;
  padding: 16px 0;
  border-bottom: 1px solid #f5f5f5;
  position: relative;
  transition: background-color 0.2s;
}

.timeline-item:hover {
  background-color: #fafafa;
  margin: 0 -8px;
  padding-left: 8px;
  padding-right: 8px;
  border-radius: 8px;
}

.timeline-item:last-child {
  border-bottom: none;
}

.timeline-dot {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #fff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.dot-green { background: linear-gradient(135deg, #67c23a 0%, #85ce61 100%); }
.dot-orange { background: linear-gradient(135deg, #e6a23c 0%, #f0c78a 100%); }
.dot-blue { background: linear-gradient(135deg, #409eff 0%, #79bbff 100%); }
.dot-purple { background: linear-gradient(135deg, #9c27b0 0%, #c689d4 100%); }
.dot-red { background: linear-gradient(135deg, #f56c6c 0%, #f89898 100%); }
.dot-cyan { background: linear-gradient(135deg, #13c2c2 0%, #5cdbd3 100%); }
.dot-gray { background: linear-gradient(135deg, #909399 0%, #b1b3b8 100%); }

.timeline-content {
  flex: 1;
  min-width: 0;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.timeline-type {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}

.timeline-change {
  font-size: 18px;
  font-weight: 700;
  font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
}

.timeline-change.positive {
  color: #67c23a;
}

.timeline-change.negative {
  color: #f56c6c;
}

.timeline-remark {
  font-size: 13px;
  color: #606266;
  margin-bottom: 8px;
  padding: 6px 10px;
  background: #f5f7fa;
  border-radius: 4px;
}

.timeline-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: #909399;
}

.timeline-balance {
  font-weight: 500;
}

/* 手机端积分流水适配 */
@media (max-width: 768px) {
  .credits-logs-stat-value {
    font-size: 22px;
  }
  
  .credits-stat-icon {
    width: 40px;
    height: 40px;
  }
  
  .credits-stat-icon .el-icon {
    font-size: 20px;
  }
  
  .timeline-dot {
    width: 32px;
    height: 32px;
  }
  
  .timeline-type {
    font-size: 13px;
  }
  
  .timeline-change {
    font-size: 16px;
  }
  
  .timeline-footer {
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
  }
}

/* 手机端适配 */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }

  .page-header h2 {
    font-size: 18px;
  }

  .search-form {
    padding: 12px;
  }

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

  .el-table .el-button {
    padding: 4px 6px;
    font-size: 11px;
  }

  .el-pagination {
    flex-wrap: wrap;
    justify-content: center;
  }

  .el-dialog {
    width: 90% !important;
    max-width: 400px !important;
    margin: 0 auto !important;
  }

  .el-dialog__body {
    padding: 12px !important;
    overflow-x: hidden;
  }

  .el-dialog__header {
    padding: 12px 12px 8px !important;
  }

  .el-dialog__footer {
    padding: 8px 12px 12px !important;
  }

  .el-row {
    flex-direction: column;
  }

  .el-row .el-col {
    width: 100% !important;
    max-width: 100% !important;
    flex: 0 0 100% !important;
  }

  .el-form-item {
    margin-bottom: 12px;
  }

  .logs-header {
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
  }

  .logs-stat-value {
    font-size: 16px;
  }

  .preset-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .preset-buttons .el-button {
    margin: 0 !important;
  }

  .credits-dialog .el-form-item {
    margin-bottom: 12px;
  }

  .credits-dialog .el-radio-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
  }

  .credits-dialog .el-input-number {
    width: 100% !important;
  }

  .credits-dialog .el-textarea {
    font-size: 12px;
  }

  .credits-dialog .el-button {
    padding: 6px 10px !important;
    font-size: 12px !important;
  }

  /* 用户信息单元格 */
  .user-info-cell {
    line-height: 1.4;
  }

  .user-name {
    font-weight: 500;
    font-size: 12px;
  }

  .user-phone {
    font-size: 11px;
    color: #999;
  }

  /* 余额为零时灰色显示 */
  .el-table .cell span {
    font-size: 12px;
  }

  /* 表格滚动提示 */
  .table-scroll-wrapper {
    position: relative;
  }

  .table-scroll-wrapper::after {
    content: '← 左右滑动查看更多 →';
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
    pointer-events: none;
    opacity: 0;
    animation: fadeInOut 3s ease-in-out;
  }

  @keyframes fadeInOut {
    0%, 100% { opacity: 0; }
    20%, 80% { opacity: 1; }
  }
}
</style>
