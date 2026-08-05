<script setup lang="ts">
import { computed, ref, markRaw, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  ArrowRight,
  HomeFilled,
  User,
  Document,
  ChatLineRound,
  Bell,
  Share,
  MoreFilled,
  Back,
  Search,
  Setting,
} from '@element-plus/icons-vue'
import { useUnreadCount } from '@/composables/useUnreadCount'
import { safeFetch } from '@/utils/fetch'

const router = useRouter()
const route = useRoute()

// 站点名称
const siteName = ref<string>('AI中医健康管理')
const siteUrl = ref<string>(window.location.origin)

// 当前页面标题
const pageTitle = computed(() => route.meta.title as string || siteName.value)

// 是否显示返回按钮
const showBack = computed(() => route.path !== '/')

// 胶囊菜单是否展开
const menuOpen = ref(false)

// 加载站点配置
const loadSiteConfig = async () => {
  try {
    const res = await safeFetch('/api/v1/analysis/config', {
      headers: {
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      if (data.data?.site_name) {
        siteName.value = data.data.site_name
      }
      if (data.data?.site_url) {
        siteUrl.value = data.data.site_url
      }
    }
  } catch (e) {
    // 使用默认标题
  }
}

onMounted(() => {
  loadSiteConfig()
})

// 返回上一页
const goBack = () => {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/')
  }
}

// 胶囊菜单项（使用 markRaw 防止图标组件被 Vue 转为响应式对象）
const menuItems = markRaw([
  { icon: HomeFilled, label: '首页', path: '/' },
  { icon: User, label: '个人中心', path: '/member' },
  { icon: Document, label: '我的订单', path: '/member/orders' },
  { icon: ChatLineRound, label: '健康问答', path: '/qa/chat' },
  { icon: Setting, label: '设置', path: '/member' },
])

// 跳转页面
const navigateTo = (path: string) => {
  menuOpen.value = false
  router.push(path)
}

// 分享
const handleShare = () => {
  menuOpen.value = false
  if (navigator.share) {
    navigator.share({
      title: 'ai 中医健康助手',
      text: '智能分析 · 科学养生 · 守护健康',
      url: window.location.origin,
    })
  }
}

// 底部导航项
const tabItems = [
  { icon: HomeFilled, label: '首页', path: '/' },
  { icon: Document, label: '订单', path: '/member/orders' },
  { icon: ChatLineRound, label: '问答', path: '/qa/chat' },
  { icon: Bell, label: '消息', path: '/messages' },
  { icon: User, label: '我的', path: '/member' },
]

// 当前激活的tab
const activeTab = computed(() => {
  const path = route.path
  if (path === '/') return '/'
  if (path.startsWith('/member/orders')) return '/member/orders'
  if (path.startsWith('/qa')) return '/qa/chat'
  if (path.startsWith('/messages')) return '/messages'
  if (path.startsWith('/member')) return '/member'
  return '/'
})

// 切换tab
const switchTab = (path: string) => {
  router.push(path)
}

// 获取未读消息数（从API获取真实数据）
const { unreadCount, fetchUnreadCount } = useUnreadCount()

// 进入消息页时刷新未读数量
watch(() => route.path, (newPath) => {
  if (newPath.startsWith('/messages')) {
    fetchUnreadCount()
  }
})

// 检测是否为微信内置浏览器
const isWeChat = computed(() => {
  const ua = navigator.userAgent.toLowerCase()
  return ua.includes('micromessenger')
})

// 是否显示顶部导航栏（微信环境下隐藏）
const showNavbar = computed(() => !isWeChat.value)

// 是否显示底部Tab栏（始终显示）
const showTabBar = computed(() => true)
</script>

<template>
  <div class="mini-program">
    <!-- 模拟小程序顶部导航栏（微信环境下隐藏） -->
    <div class="mini-navbar" v-if="showNavbar">
      <!-- 左侧返回按钮 -->
      <div class="nav-left" v-if="showBack" @click="goBack">
        <el-icon class="nav-icon"><Back /></el-icon>
      </div>
      <div class="nav-left" v-else></div>

      <!-- 中间标题 -->
      <div class="nav-title">{{ pageTitle }}</div>

      <!-- 右侧胶囊按钮 -->
      <div class="nav-right">
        <div class="capsule-btn" :class="{ active: menuOpen }" @click="menuOpen = !menuOpen">
          <span class="capsule-dot"></span>
          <span class="capsule-dot"></span>
          <span class="capsule-dot"></span>
        </div>
      </div>
    </div>

    <!-- 胶囊菜单弹出层 -->
    <transition name="fade">
      <div class="menu-overlay" v-if="menuOpen" @click="menuOpen = false">
        <div class="menu-popup" @click.stop>
          <div class="menu-arrow"></div>
          <div class="menu-list">
            <div
              v-for="item in menuItems"
              :key="item.path"
              class="menu-item"
              @click="navigateTo(item.path)"
            >
              <el-icon class="menu-item-icon"><component :is="item.icon" /></el-icon>
              <span class="menu-item-label">{{ item.label }}</span>
            </div>
            <div class="menu-item" @click="handleShare">
              <el-icon class="menu-item-icon"><Share /></el-icon>
              <span class="menu-item-label">分享</span>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- 页面内容区域 -->
    <div class="mini-content">
      <slot />
    </div>

    <!-- 底部Tab导航栏（微信环境下隐藏） -->
    <div class="tab-bar" v-if="showTabBar">
      <div
        v-for="tab in tabItems"
        :key="tab.path"
        class="tab-item"
        :class="{ active: activeTab === tab.path }"
        @click="switchTab(tab.path)"
      >
        <div class="tab-icon-wrap">
          <el-icon class="tab-icon"><component :is="tab.icon" /></el-icon>
          <span class="tab-badge" v-if="tab.path === '/messages' && unreadCount > 0">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
        </div>
        <span class="tab-label">{{ tab.label }}</span>
      </div>
    </div>

    <!-- 底部安全区域（微信环境下隐藏） -->
    <div class="safe-area-bottom" v-if="showTabBar"></div>
  </div>
</template>

<style scoped>
.mini-program {
  min-height: 100vh;
  background: #f7f8fa;
  display: flex;
  flex-direction: column;
}

/* 顶部导航栏 */
.mini-navbar {
  position: sticky;
  top: 0;
  z-index: 100;
  height: 44px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 12px;
  border-bottom: 0.5px solid rgba(0, 0, 0, 0.05);
}

.nav-left {
  min-width: 40px;
  height: 100%;
  display: flex;
  align-items: center;
}

.nav-icon {
  font-size: 18px;
  color: #323233;
  cursor: pointer;
}

.nav-title {
  font-size: 17px;
  font-weight: 600;
  color: #323233;
  text-align: center;
  flex: 1;
}

.nav-right {
  min-width: 40px;
  display: flex;
  justify-content: flex-end;
  align-items: center;
}

/* 胶囊按钮 */
.capsule-btn {
  width: 28px;
  height: 28px;
  border-radius: 14px;
  background: rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2.5px;
  cursor: pointer;
  transition: background 0.2s;
  padding: 0 6px;
}

.capsule-btn.active {
  background: rgba(0, 0, 0, 0.1);
}

.capsule-dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: #323233;
}

/* 菜单遮罩 */
.menu-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 200;
  background: transparent;
}

/* 菜单弹出层 */
.menu-popup {
  position: absolute;
  top: 52px;
  right: 10px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
  overflow: hidden;
  min-width: 160px;
  animation: popup 0.2s ease;
}

@keyframes popup {
  from {
    opacity: 0;
    transform: scale(0.9) translateY(-8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.menu-arrow {
  position: absolute;
  top: -6px;
  right: 18px;
  width: 12px;
  height: 12px;
  background: #fff;
  transform: rotate(45deg);
  box-shadow: -2px -2px 4px rgba(0, 0, 0, 0.03);
}

.menu-list {
  position: relative;
  background: #fff;
  padding: 4px 0;
}

.menu-item {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  cursor: pointer;
  transition: background 0.15s;
}

.menu-item:active {
  background: #f5f5f5;
}

.menu-item-icon {
  font-size: 18px;
  margin-right: 12px;
  color: #323233;
}

.menu-item-label {
  font-size: 15px;
  color: #323233;
}

/* 内容区域 */
.mini-content {
  flex: 1;
  overflow-y: auto;
}

/* 底部Tab导航栏 */
.tab-bar {
  position: sticky;
  bottom: 0;
  z-index: 100;
  height: 56px;
  background: #fff;
  display: flex;
  align-items: center;
  border-top: 0.5px solid rgba(0, 0, 0, 0.08);
  padding-bottom: env(safe-area-inset-bottom);
}

.tab-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  cursor: pointer;
  transition: all 0.15s;
}

.tab-icon {
  font-size: 22px;
  color: #969799;
  margin-bottom: 2px;
  transition: color 0.15s;
}

.tab-label {
  font-size: 11px;
  color: #969799;
  transition: color 0.15s;
}

.tab-item.active .tab-icon,
.tab-item.active .tab-label {
  color: #07c160;
}

.tab-item:active {
  background: rgba(0, 0, 0, 0.02);
}

.tab-icon-wrap {
  position: relative;
}

.tab-badge {
  position: absolute;
  top: -6px;
  right: -12px;
  min-width: 16px;
  height: 16px;
  line-height: 16px;
  text-align: center;
  background: #ee0a24;
  color: #fff;
  font-size: 10px;
  border-radius: 8px;
  padding: 0 4px;
  font-weight: 500;
}

/* 底部安全区域 */
.safe-area-bottom {
  height: env(safe-area-inset-bottom);
  background: #fff;
}

/* 过渡动画 */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
