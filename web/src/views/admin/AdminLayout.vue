<script setup lang="ts">
import { ref, shallowRef, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { getAdminToken, clearAdminToken } from '@/utils/auth'
import { Operation, ArrowRight, ArrowLeft, SwitchButton, TrendCharts, UserFilled, Tickets, Document, Setting, Cpu, Promotion, Money, Goods, EditPen, Service, FirstAidKit, Wallet, CreditCard, RefreshLeft, Close, List, Message } from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()

const sidebarCollapsed = ref(false)
const mobileSidebarOpen = ref(false)
const activeCount = ref(0) // 正在服务的人数

// 客服通知相关
interface CustomerNotification {
  id: number
  sessionNo: string
  userId: number
  nickname: string
  mobile: string
  content: string
  created_at: string
}
const notifications = ref<CustomerNotification[]>([])
const notificationTimers = ref<Map<number, ReturnType<typeof setTimeout>>>(new Map())
const lastCheckTime = ref<string>(new Date().toISOString())
const pollingInterval = ref<ReturnType<typeof setInterval> | null>(null)
const unreadMessageCount = ref(0) // 未读消息总数
const showNotificationPanel = ref(false) // 通知面板是否显示

// 使用 shallowRef 避免图标组件被 reactive 包裹，消除 Vue 警告
const menuItems = shallowRef([
  { title: '仪表盘', icon: TrendCharts, path: '/admin/dashboard' },
  { title: '客服管理', icon: Service, path: '/admin/customer-service', badge: () => activeCount.value },
  { title: '用户管理', icon: UserFilled, path: '/admin/users' },
  { title: '订单管理', icon: Tickets, path: '/admin/orders' },
  { title: '套餐管理', icon: Goods, path: '/admin/packages' },
  { title: '闲鱼商品管理', icon: Goods, path: '/admin/xianyu-products' },
  { title: 'AI调用记录', icon: Cpu, path: '/admin/ai' },
  { title: '队列任务管理', icon: List, path: '/admin/queue' },
  { title: '健康管理档案', icon: FirstAidKit, path: '/admin/health-archives' },
  { title: '推广管理', icon: Promotion, path: '/admin/promoters' },
  { title: '提现审核', icon: Money, path: '/admin/withdraws' },
  { title: '用户余额', icon: Wallet, path: '/admin/user-balances' },
  { title: '支付流水', icon: CreditCard, path: '/admin/payment-logs' },
  { title: '退款流水', icon: RefreshLeft, path: '/admin/refund-logs' },
  { title: '文章管理', icon: Document, path: '/admin/articles' },
  { title: '体质题目', icon: EditPen, path: '/admin/constitution' },
  { title: '管理员管理', icon: UserFilled, path: '/admin/admins' },
  { title: '角色管理', icon: Operation, path: '/admin/roles' },
  { title: '操作日志', icon: Document, path: '/admin/operation-logs' },
  { title: 'API日志', icon: Document, path: '/admin/api-logs' },
  { title: '系统设置', icon: Setting, path: '/admin/settings' },
])

// 加载正在服务的人数
const loadActiveCount = async () => {
  try {
    const res = await safeFetch('/api/v1/admin/customer-service/statistics', {
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      activeCount.value = data.data.active || 0
    }
  } catch (e) {
    // 忽略错误
  }
}

// 播放通知提示音
const playNotificationSound = () => {
  try {
    const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)()
    const oscillator = audioContext.createOscillator()
    const gainNode = audioContext.createGain()
    oscillator.connect(gainNode)
    gainNode.connect(audioContext.destination)
    oscillator.frequency.value = 800
    oscillator.type = 'sine'
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime)
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5)
    oscillator.start(audioContext.currentTime)
    oscillator.stop(audioContext.currentTime + 0.5)
  } catch (e) {
    // 忽略音频播放错误
  }
}

// 检查新消息
const checkNewMessages = async () => {
  try {
    const res = await safeFetch(`/api/v1/admin/customer-service/sessions?updated_after=${encodeURIComponent(lastCheckTime.value)}`, {
      headers: {
        'Authorization': `Bearer ${getAdminToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0 && data.data.data && data.data.data.length > 0) {
      const sessions = data.data.data

      for (const session of sessions) {
        // 检查是否有新的用户消息（未读 > 0 且不是当前页面）
        if (session.admin_unread > 0 && route.path !== '/admin/customer-service') {
          // 获取最后一条消息
          const messagesRes = await safeFetch(`/api/v1/admin/customer-service/sessions/${session.session_no}/messages?per_page=1`, {
            headers: {
              'Authorization': `Bearer ${getAdminToken()}`,
              'Accept': 'application/json',
            },
          })
          const messagesData = await messagesRes.json()
          if (messagesData.code === 0 && messagesData.data.data && messagesData.data.data.length > 0) {
            const lastMessage = messagesData.data.data[0]
            if (lastMessage.sender_type === 'user') {
              // 显示通知
              showNotification({
                id: session.id,
                sessionNo: session.session_no,
                userId: session.user_id,
                nickname: session.user?.nickname || '未知用户',
                mobile: session.user?.mobile || '',
                content: lastMessage.content,
                created_at: lastMessage.created_at,
              })
            }
          }
        }
      }

      // 更新时间戳
      lastCheckTime.value = new Date().toISOString()
    }
  } catch (e) {
    // 忽略错误
  }
}

// 显示通知
const showNotification = (notification: CustomerNotification) => {
  // 检查是否已存在相同会话的通知
  const existingIndex = notifications.value.findIndex(n => n.sessionNo === notification.sessionNo)
  if (existingIndex !== -1) {
    // 更新现有通知
    notifications.value[existingIndex] = notification
    // 清除旧的定时器
    const oldTimer = notificationTimers.value.get(notification.id)
    if (oldTimer) {
      clearTimeout(oldTimer)
    }
  } else {
    // 添加新通知
    notifications.value.push(notification)
    // 播放提示音
    playNotificationSound()
  }

  // 更新未读消息数
  unreadMessageCount.value = notifications.value.length

  // 设置自动关闭定时器（30秒后）
  const timer = setTimeout(() => {
    dismissNotification(notification.id)
  }, 30000)
  notificationTimers.value.set(notification.id, timer)
}

// 关闭通知
const dismissNotification = (id: number) => {
  const index = notifications.value.findIndex(n => n.id === id)
  if (index !== -1) {
    notifications.value.splice(index, 1)
  }
  const timer = notificationTimers.value.get(id)
  if (timer) {
    clearTimeout(timer)
    notificationTimers.value.delete(id)
  }
  unreadMessageCount.value = notifications.value.length
}

// 清空所有通知
const clearAllNotifications = () => {
  notifications.value = []
  notificationTimers.value.forEach(timer => clearTimeout(timer))
  notificationTimers.value.clear()
  unreadMessageCount.value = 0
}

// 点击通知 - 跳转到客服页面
const handleNotificationClick = (notification: CustomerNotification) => {
  dismissNotification(notification.id)
  router.push('/admin/customer-service')
}

// 格式化通知时间
const formatNotificationTime = (timeStr: string) => {
  if (!timeStr) return ''
  const date = new Date(timeStr)
  const now = new Date()
  const diff = now.getTime() - date.getTime()

  if (diff < 60000) {
    return '刚刚'
  } else if (diff < 3600000) {
    return `${Math.floor(diff / 60000)}分钟前`
  } else if (diff < 86400000) {
    return `${Math.floor(diff / 3600000)}小时前`
  } else {
    return date.toLocaleDateString('zh-CN')
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
  loadActiveCount()
  // 每30秒刷新一次
  setInterval(loadActiveCount, 30000)

  // 启动客服消息轮询（每10秒检查一次）
  pollingInterval.value = setInterval(checkNewMessages, 10000)
  
  // 添加点击外部监听（用于关闭通知面板）
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  // 清理轮询定时器
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
  }
  // 清理通知定时器
  notificationTimers.value.forEach(timer => clearTimeout(timer))
  notificationTimers.value.clear()
  // 移除点击外部监听
  document.removeEventListener('click', handleClickOutside)
})

// 点击外部关闭通知面板
const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement
  if (!target.closest('.notification-bell') && !target.closest('.notification-panel')) {
    showNotificationPanel.value = false
  }
}
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
          <!-- 客服通知铃铛 -->
          <div class="notification-bell" @click="showNotificationPanel = !showNotificationPanel">
            <el-icon :size="20"><Service /></el-icon>
            <span v-if="unreadMessageCount > 0" class="badge">{{ unreadMessageCount > 99 ? '99+' : unreadMessageCount }}</span>
          </div>
          
          <!-- 通知面板 -->
          <div v-if="showNotificationPanel" class="notification-panel" @click.stop>
            <div class="panel-header">
              <span>客服消息</span>
              <el-button v-if="notifications.length > 0" link size="small" @click="clearAllNotifications">清空</el-button>
            </div>
            <div class="panel-content">
              <div v-if="notifications.length === 0" class="empty-text">暂无新消息</div>
              <div
                v-for="notification in notifications"
                :key="notification.id"
                class="panel-item"
                @click="handleNotificationClick(notification)"
              >
                <div class="item-avatar">{{ notification.nickname?.[0] || 'U' }}</div>
                <div class="item-content">
                  <div class="item-header">
                    <span class="item-name">{{ notification.nickname }}</span>
                    <span class="item-time">{{ formatNotificationTime(notification.created_at) }}</span>
                  </div>
                  <div class="item-text">{{ notification.content }}</div>
                </div>
              </div>
            </div>
          </div>
          
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

  <!-- 客服通知弹窗 -->
    <div class="customer-notifications">
      <TransitionGroup name="notification">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          class="notification-card"
          @click="handleNotificationClick(notification)"
        >
          <div class="notification-header">
            <div class="notification-avatar">
              {{ notification.nickname?.[0] || 'U' }}
            </div>
            <div class="notification-info">
              <span class="notification-name">{{ notification.nickname }}</span>
              <span class="notification-mobile">{{ notification.mobile }}</span>
            </div>
            <button class="notification-close" @click.stop="dismissNotification(notification.id)">
              <el-icon><Close /></el-icon>
            </button>
          </div>
          <div class="notification-content">{{ notification.content }}</div>
          <div class="notification-time">{{ formatNotificationTime(notification.created_at) }}</div>
        </div>
      </TransitionGroup>
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
  position: relative;
}

/* 通知铃铛 */
.notification-bell {
  position: relative;
  cursor: pointer;
  padding: 8px;
  border-radius: 50%;
  transition: background 0.2s;
  color: #666;
}

.notification-bell:hover {
  background: rgba(0, 0, 0, 0.05);
  color: #1989fa;
}

.notification-bell .badge {
  position: absolute;
  top: 2px;
  right: 2px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  background: #f56c6c;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: badge-pulse 2s infinite;
}

@keyframes badge-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

/* 通知面板 */
.notification-panel {
  position: absolute;
  top: 100%;
  right: 0;
  width: 360px;
  max-height: 480px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
  z-index: 1000;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: panel-in 0.2s ease;
}

@keyframes panel-in {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  border-bottom: 1px solid #ebeef5;
  font-size: 15px;
  font-weight: 600;
  color: #333;
}

.panel-content {
  flex: 1;
  overflow-y: auto;
  max-height: 400px;
}

.panel-content .empty-text {
  padding: 40px 20px;
  text-align: center;
  color: #999;
  font-size: 14px;
}

.panel-item {
  display: flex;
  gap: 12px;
  padding: 14px 16px;
  cursor: pointer;
  transition: background 0.2s;
  border-bottom: 1px solid #f5f5f5;
}

.panel-item:hover {
  background: #f5f7fa;
}

.panel-item:last-child {
  border-bottom: none;
}

.item-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1989fa, #409eff);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 500;
  flex-shrink: 0;
}

.item-content {
  flex: 1;
  min-width: 0;
}

.item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.item-name {
  font-size: 14px;
  font-weight: 500;
  color: #333;
}

.item-time {
  font-size: 12px;
  color: #999;
}

.item-text {
  font-size: 13px;
  color: #666;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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

/* 客服通知弹窗 */
.customer-notifications {
  position: fixed;
  top: 70px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 12px;
  pointer-events: none;
}

.notification-card {
  width: 320px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15), 0 2px 8px rgba(0, 0, 0, 0.1);
  padding: 16px;
  cursor: pointer;
  pointer-events: auto;
  transition: all 0.3s ease;
  border-left: 4px solid #1989fa;
}

.notification-card:hover {
  transform: translateX(-4px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2), 0 4px 12px rgba(0, 0, 0, 0.12);
}

.notification-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
}

.notification-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1989fa, #409eff);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 600;
  flex-shrink: 0;
}

.notification-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.notification-name {
  font-size: 14px;
  font-weight: 600;
  color: #333;
}

.notification-mobile {
  font-size: 12px;
  color: #999;
}

.notification-close {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  color: #999;
  transition: color 0.2s;
  flex-shrink: 0;
}

.notification-close:hover {
  color: #333;
}

.notification-content {
  font-size: 13px;
  color: #666;
  line-height: 1.5;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  margin-bottom: 8px;
}

.notification-time {
  font-size: 11px;
  color: #999;
}

/* 通知动画 */
.notification-enter-active {
  animation: notification-in 0.4s ease;
}

.notification-leave-active {
  animation: notification-out 0.3s ease;
}

@keyframes notification-in {
  0% {
    opacity: 0;
    transform: translateX(100px) scale(0.8);
  }
  50% {
    transform: translateX(-10px) scale(1.02);
  }
  100% {
    opacity: 1;
    transform: translateX(0) scale(1);
  }
}

@keyframes notification-out {
  0% {
    opacity: 1;
    transform: translateX(0) scale(1);
  }
  100% {
    opacity: 0;
    transform: translateX(100px) scale(0.8);
  }
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

  .customer-notifications {
    top: 60px;
    right: 10px;
    left: 10px;
  }

  .notification-card {
    width: 100%;
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
