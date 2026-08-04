<script setup lang="ts">
import { ref, shallowRef, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { getAdminToken, clearAdminToken } from '@/utils/auth'
import { Operation, ArrowRight, ArrowLeft, SwitchButton, TrendCharts, UserFilled, Tickets, Document, Setting, Cpu, Promotion, Money, Goods, EditPen, Service, FirstAidKit } from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()

const sidebarCollapsed = ref(false)
const mobileSidebarOpen = ref(false)
const waitingCount = ref(0)

// 使用 shallowRef 避免图标组件被 reactive 包裹，消除 Vue 警告
const menuItems = shallowRef([
  { title: '仪表盘', icon: TrendCharts, path: '/admin/dashboard' },
  { title: '客服管理', icon: Service, path: '/admin/customer-service', badge: () => waitingCount.value },
  { title: '用户管理', icon: UserFilled, path: '/admin/users' },
  { title: '订单管理', icon: Tickets, path: '/admin/orders' },
  { title: '次数包管理', icon: Goods, path: '/admin/packages' },
  { title: '闲鱼商品管理', icon: Goods, path: '/admin/xianyu-products' },
  { title: 'AI管理', icon: Cpu, path: '/admin/ai' },
  { title: '推广管理', icon: Promotion, path: '/admin/promoters' },
  { title: '提现审核', icon: Money, path: '/admin/withdraws' },
  { title: '文章管理', icon: Document, path: '/admin/articles' },
  { title: '体质题目', icon: EditPen, path: '/admin/constitution' },
  { title: '管理员管理', icon: UserFilled, path: '/admin/admins' },
  { title: '角色管理', icon: Operation, path: '/admin/roles' },
  { title: '系统设置', icon: Setting, path: '/admin/settings' },
])

// 加载待接入客服数量
const loadWaitingCount = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/statistics', {
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      waitingCount.value = data.data.waiting || 0
    }
  } catch (e) {
    // 忽略错误
  }
}

const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value
}

const toggleMobileSidebar = () => {
  mobileSidebarOpen.value = !mobileSidebarOpen.value
}

const navigateTo = (path: string) => {
  router.push(path)
  mobileSidebarOpen.value = false
}

const handleLogout = () => {
  clearAdminToken()
  router.push('/admin/login')
}

// 修改密码
const showChangePasswordDialog = ref(false)
const passwordForm = ref({
  old_password: '',
  new_password: '',
  new_password_confirmation: ''
})
const passwordLoading = ref(false)

const handleCommand = (command: string) => {
  if (command === 'changePassword') {
    showChangePasswordDialog.value = true
  } else if (command === 'logout') {
    handleLogout()
  }
}

const handleChangePassword = async () => {
  if (!passwordForm.value.old_password || !passwordForm.value.new_password) {
    ElMessage.warning('请填写完整密码信息')
    return
  }
  if (passwordForm.value.new_password !== passwordForm.value.new_password_confirmation) {
    ElMessage.warning('两次输入的新密码不一致')
    return
  }
  if (passwordForm.value.new_password.length < 6) {
    ElMessage.warning('新密码长度不能少于6位')
    return
  }
  
  passwordLoading.value = true
  try {
    const res = await safeFetch('/api/v1/admin/auth/change-password', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        old_password: passwordForm.value.old_password,
        new_password: passwordForm.value.new_password,
        new_password_confirmation: passwordForm.value.new_password_confirmation,
      }),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('密码修改成功，请重新登录')
      showChangePasswordDialog.value = false
      clearAdminToken()
      router.push('/admin/login')
    } else {
      ElMessage.error(data.message || '密码修改失败')
    }
  } catch (e) {
    ElMessage.error('密码修改失败，请稍后重试')
  } finally {
    passwordLoading.value = false
  }
}

// 页面加载时给 body 添加 admin 类
onMounted(() => {
  document.body.classList.add('admin-page')
  const appEl = document.getElementById('app')
  if (appEl) appEl.classList.add('admin-app')
  
  // 加载客服待接入数量
  loadWaitingCount()
  // 每30秒刷新一次
  setInterval(loadWaitingCount, 30000)
})
</script>

<template>
  <div class="admin-layout">
    <!-- 移动端遮罩 -->
    <div v-if="mobileSidebarOpen" class="sidebar-overlay" @click="toggleMobileSidebar" />

    <!-- 侧边栏 -->
    <aside class="sidebar" :class="{ collapsed: sidebarCollapsed, mobile: mobileSidebarOpen }">
      <div class="sidebar-header">
        <el-icon class="sidebar-logo"><FirstAidKit /></el-icon>
        <div v-if="!sidebarCollapsed" class="sidebar-title">管理后台</div>
      </div>

      <nav class="sidebar-nav">
        <div
          v-for="item in menuItems"
          :key="item.path"
          class="nav-item"
          :class="{ active: route.path === item.path || (item.path !== '/admin/dashboard' && route.path.startsWith(item.path)) }"
          @click="navigateTo(item.path)"
        >
          <div class="nav-icon-wrap">
            <el-icon class="nav-icon"><component :is="item.icon" /></el-icon>
            <span v-if="item.badge && item.badge() > 0" class="nav-badge">{{ item.badge() > 99 ? '99+' : item.badge() }}</span>
          </div>
          <span v-if="!sidebarCollapsed" class="nav-label">{{ item.title }}</span>
          <span v-if="sidebarCollapsed && item.badge && item.badge() > 0" class="collapsed-badge">{{ item.badge() > 99 ? '99+' : item.badge() }}</span>
        </div>
      </nav>

      <div class="sidebar-footer">
        <div class="nav-item" @click="handleLogout">
          <el-icon class="nav-icon"><SwitchButton /></el-icon>
          <span v-if="!sidebarCollapsed" class="nav-label">退出登录</span>
        </div>
      </div>
    </aside>

    <!-- 主内容区 -->
    <div class="main-area">
      <!-- 顶栏 -->
      <header class="topbar">
        <div class="topbar-left">
          <button class="menu-btn" @click="toggleMobileSidebar">
            <el-icon :size="20"><Operation /></el-icon>
          </button>
          <button class="menu-btn desktop-only" @click="toggleSidebar">
            <el-icon><ArrowLeft v-if="!sidebarCollapsed" /><ArrowRight v-else /></el-icon>
          </button>
          <span class="topbar-title">{{ route.meta.title }}</span>
        </div>
        <div class="topbar-right">
          <el-dropdown @command="handleCommand">
            <span class="admin-username">
              管理员
              <el-icon class="el-icon--right"><ArrowRight /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="changePassword">修改密码</el-dropdown-item>
                <el-dropdown-item command="logout" divided>退出登录</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </header>

      <!-- 页面内容 -->
      <main class="main-content">
        <router-view />
      </main>
    </div>
  </div>

  <!-- 修改密码对话框 -->
  <el-dialog
    v-model="showChangePasswordDialog"
    title="修改密码"
    width="400px"
    :close-on-click-modal="false"
  >
    <el-form :model="passwordForm" label-width="100px">
      <el-form-item label="原密码" required>
        <el-input
          v-model="passwordForm.old_password"
          type="password"
          placeholder="请输入原密码"
          show-password
        />
      </el-form-item>
      <el-form-item label="新密码" required>
        <el-input
          v-model="passwordForm.new_password"
          type="password"
          placeholder="请输入新密码（至少6位）"
          show-password
        />
      </el-form-item>
      <el-form-item label="确认密码" required>
        <el-input
          v-model="passwordForm.new_password_confirmation"
          type="password"
          placeholder="请再次输入新密码"
          show-password
        />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="showChangePasswordDialog = false">取消</el-button>
      <el-button type="primary" :loading="passwordLoading" @click="handleChangePassword">
        确认修改
      </el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
}

/* 侧边栏 */
.sidebar {
  width: 220px;
  background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
  color: #fff;
  display: flex;
  flex-direction: column;
  transition: width 0.3s;
  position: sticky;
  top: 0;
  height: 100vh;
  z-index: 100;
}

.sidebar.collapsed {
  width: 60px;
}

.sidebar-header {
  display: flex;
  align-items: center;
  padding: 20px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  gap: 10px;
}

.sidebar-logo {
  font-size: 28px;
  flex-shrink: 0;
}

.sidebar-title {
  font-size: 16px;
  font-weight: bold;
  white-space: nowrap;
}

.sidebar-nav {
  flex: 1;
  padding: 12px 0;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  cursor: pointer;
  transition: all 0.2s;
  gap: 12px;
  white-space: nowrap;
  color: rgba(255, 255, 255, 0.7);
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
}

.nav-item.active {
  background: rgba(25, 137, 250, 0.2);
  color: #1989fa;
  border-right: 3px solid #1989fa;
}

.nav-icon {
  font-size: 18px;
  flex-shrink: 0;
  width: 20px;
  text-align: center;
}

.nav-label {
  font-size: 14px;
  flex: 1;
}

.nav-icon-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
}

.nav-badge {
  position: absolute;
  top: -8px;
  right: -12px;
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  text-align: center;
  background: #ee0a24;
  color: #fff;
  font-size: 10px;
  border-radius: 9px;
  padding: 0 5px;
  font-weight: bold;
  z-index: 1;
}

.collapsed-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  min-width: 18px;
  height: 18px;
  line-height: 18px;
  text-align: center;
  background: #ee0a24;
  color: #fff;
  font-size: 10px;
  border-radius: 9px;
  padding: 0 5px;
  font-weight: bold;
}

.sidebar-footer {
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding: 8px 0;
}

/* 主内容区 */
.main-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* 顶栏 */
.topbar {
  height: 56px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  position: sticky;
  top: 0;
  z-index: 50;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.menu-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  color: #666;
  display: none;
}

.menu-btn.desktop-only {
  display: flex;
  align-items: center;
}

.topbar-title {
  font-size: 16px;
  font-weight: 500;
  color: #333;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.admin-username {
  font-size: 14px;
  color: #666;
}

/* 主内容 */
.main-content {
  padding: 20px;
  flex: 1;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

/* 响应式 */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    left: -220px;
    top: 0;
    height: 100vh;
    z-index: 200;
    transition: left 0.3s;
  }

  .sidebar.mobile {
    left: 0;
  }

  .sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 150;
  }

  .menu-btn {
    display: flex;
  }

  .menu-btn.desktop-only {
    display: none;
  }

  .main-content {
    padding: 16px;
  }

  .sidebar.collapsed {
    width: 220px;
  }
}
</style>

<!-- 管理后台全局样式重置 -->
<style>
body.admin-page {
  font-size: 14px !important;
}

body.admin-page .admin-page-wrapper {
  max-width: 100% !important;
  width: 100%;
}

body.admin-page ::-webkit-scrollbar {
  display: block;
  width: 6px;
}

body.admin-page ::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

body.admin-page ::-webkit-scrollbar-track {
  background: transparent;
}
</style>
