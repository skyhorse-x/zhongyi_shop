<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useFullscreen } from '@vueuse/core'
import { useAdminStore } from '@/stores/admin'
import { useTheme } from '@/hooks/useTheme'
import { ElMessageBox } from 'element-plus'
import {
  Expand,
  Fold,
  FullScreen,
  Close,
  Moon,
  Sunny,
  User,
  SwitchButton,
} from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const adminStore = useAdminStore()
const { isDark, toggleDark } = useTheme()
const { isFullscreen, toggle: toggleFullscreen } = useFullscreen()

const sidebarCollapsed = ref(false)

// ====== 面包屑 ======
const breadcrumbs = computed(() => {
  return route.matched
    .filter((m) => m.path && m.path !== '/')
    .map((m) => ({
      title: (m.meta?.title as string) || '',
      path: m.path,
    }))
})

// ====== 多标签页 ======
interface TabItem {
  title: string
  path: string
  name: string
}

const tabs = ref<TabItem[]>([])
const activeTab = computed(() => route.path)

const addTab = (route: any) => {
  const path = route.path
  if (path === '/admin/login' || path === '/admin') return
  const exists = tabs.value.find((t) => t.path === path)
  if (!exists) {
    tabs.value.push({
      title: (route.meta?.title as string) || '',
      path,
      name: route.name as string,
    })
  }
}

const closeTab = (path: string, e: MouseEvent) => {
  e.stopPropagation()
  const idx = tabs.value.findIndex((t) => t.path === path)
  if (idx === -1) return
  // 至少保留一个标签
  if (tabs.value.length <= 1) return
  tabs.value.splice(idx, 1)
  // 如果关闭的是当前页，跳转到前一个或后一个
  if (path === route.path) {
    const target = tabs.value[Math.min(idx, tabs.value.length - 1)]
    if (target) router.push(target.path)
  }
}

const switchTab = (path: string) => {
  if (path !== route.path) {
    router.push(path)
  }
}

// 路由变化时添加标签
watch(
  () => route.path,
  () => addTab(route),
  { immediate: true }
)

// ====== 方法 ======
const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value
}

const handleLogout = async () => {
  try {
    await ElMessageBox.confirm('确认退出登录吗？', '提示', {
      confirmButtonText: '确认',
      cancelButtonText: '取消',
      type: 'warning',
    })
    adminStore.logout()
    router.push('/admin/login')
  } catch {
    // 取消操作
  }
}

const menuSelect = (index: string) => {
  if (index !== route.path) {
    router.push(index)
  }
}

// ====== 页面挂载 ======
onMounted(() => {
  document.body.classList.add('admin-page')
  const appEl = document.getElementById('app')
  if (appEl) appEl.classList.add('admin-app')
})
</script>

<template>
  <div class="admin-layout flex h-screen overflow-hidden">
    <!-- ====== 侧边栏 ====== -->
    <aside
      class="sidebar flex-shrink-0 flex flex-col transition-all duration-300 bg-[#1a1a2e] text-white"
      :class="sidebarCollapsed ? 'w-[64px]' : 'w-[240px]'"
    >
      <!-- Logo -->
      <div class="sidebar-header flex items-center h-14 px-4 border-b border-white/10 gap-3 flex-shrink-0">
        <div class="text-2xl flex-shrink-0">⚕</div>
        <transition name="fade">
          <span v-if="!sidebarCollapsed" class="text-base font-bold whitespace-nowrap">管理后台</span>
        </transition>
      </div>

      <!-- Menu -->
      <el-menu
        :default-active="route.path"
        :collapse="sidebarCollapsed"
        background-color="#1a1a2e"
        text-color="rgba(255,255,255,0.7)"
        active-text-color="#409eff"
        class="border-none flex-1 overflow-y-auto"
        @select="menuSelect"
      >
        <el-menu-item v-for="item in adminStore.menuItems" :key="item.path" :index="item.path">
          <el-icon><component :is="item.icon" /></el-icon>
          <template #title>
            <span>{{ item.title }}</span>
          </template>
        </el-menu-item>
      </el-menu>

      <!-- Logout -->
      <div class="sidebar-footer border-t border-white/10 flex-shrink-0">
        <el-menu
          :collapse="sidebarCollapsed"
          background-color="#1a1a2e"
          text-color="rgba(255,255,255,0.7)"
          class="border-none"
          @select="handleLogout"
        >
          <el-menu-item index="logout">
            <el-icon><SwitchButton /></el-icon>
            <template #title>
              <span>退出登录</span>
            </template>
          </el-menu-item>
        </el-menu>
      </div>
    </aside>

    <!-- ====== 右侧区域 ====== -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- ====== 顶部栏 Header ====== -->
      <header
        class="header sticky top-0 z-50 flex items-center h-14 px-4 bg-white dark:bg-[#1d1e1f] border-b border-gray-200 dark:border-gray-700 shadow-sm"
      >
        <!-- 折叠按钮 -->
        <button
          class="flex items-center justify-center w-8 h-8 mr-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300"
          @click="toggleSidebar"
        >
          <el-icon :size="18">
            <Fold v-if="!sidebarCollapsed" />
            <Expand v-else />
          </el-icon>
        </button>

        <!-- 面包屑 -->
        <el-breadcrumb separator="/" class="hidden sm:flex">
          <el-breadcrumb-item
            v-for="(crumb, idx) in breadcrumbs"
            :key="idx"
            :to="idx < breadcrumbs.length - 1 ? { path: crumb.path } : undefined"
          >
            {{ crumb.title }}
          </el-breadcrumb-item>
        </el-breadcrumb>

        <div class="flex-1" />

        <!-- 右侧操作区 -->
        <div class="flex items-center gap-1">
          <!-- 全屏切换 -->
          <button
            class="flex items-center justify-center w-8 h-8 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300"
            :title="isFullscreen ? '退出全屏' : '全屏'"
            @click="toggleFullscreen"
          >
            <el-icon :size="18"><FullScreen /></el-icon>
          </button>

          <!-- 主题切换 -->
          <button
            class="flex items-center justify-center w-8 h-8 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300"
            :title="isDark ? '切换亮色' : '切换暗色'"
            @click="toggleDark()"
          >
            <el-icon :size="18">
              <Moon v-if="!isDark" />
              <Sunny v-else />
            </el-icon>
          </button>

          <!-- 用户下拉 -->
          <el-dropdown trigger="click" @command="handleLogout">
            <button
              class="flex items-center gap-1 px-2 h-8 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300"
            >
              <el-icon :size="18"><User /></el-icon>
              <span class="text-sm hidden sm:inline">管理员</span>
            </button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="logout">
                  <el-icon :size="16"><SwitchButton /></el-icon>
                  退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </header>

      <!-- ====== Tab 标签栏 ====== -->
      <div
        v-if="tabs.length > 0"
        class="tabs-bar flex items-center px-2 bg-white dark:bg-[#1d1e1f] border-b border-gray-200 dark:border-gray-700 flex-shrink-0"
      >
        <div
          v-for="tab in tabs"
          :key="tab.path"
          class="tab-item flex items-center gap-1 px-3 h-9 text-sm cursor-pointer border-r border-gray-200 dark:border-gray-700 select-none transition-colors"
          :class="
            activeTab === tab.path
              ? 'text-[#409eff] bg-blue-50 dark:bg-blue-900/20 border-b-2 border-b-[#409eff] mb-0'
              : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'
          "
          @click="switchTab(tab.path)"
        >
          <span class="whitespace-nowrap">{{ tab.title }}</span>
          <el-icon
            v-if="tabs.length > 1"
            :size="14"
            class="close-icon ml-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600"
            @click.stop="closeTab(tab.path, $event)"
          >
            <Close />
          </el-icon>
        </div>
      </div>

      <!-- ====== 主内容区 ====== -->
      <main class="main-content flex-1 overflow-y-auto p-6 bg-gray-50 dark:bg-[#141414]">
        <router-view />
      </main>

      <!-- ====== 底部 Footer ====== -->
      <footer
        class="footer flex items-center justify-center h-10 text-xs text-gray-400 dark:text-gray-500 bg-white dark:bg-[#1d1e1f] border-t border-gray-200 dark:border-gray-700 flex-shrink-0"
      >
        AI中医健康管理平台 &copy; {{ new Date().getFullYear() }} All Rights Reserved.
      </footer>
    </div>
  </div>
</template>

<style scoped>
.sidebar {
  overflow: hidden;
}

.sidebar :deep(.el-menu) {
  border-right: none;
}

.sidebar :deep(.el-menu-item) {
  height: 44px;
  line-height: 44px;
  border-radius: 0;
  margin: 2px 0;
}

.sidebar :deep(.el-menu-item.is-active) {
  background: rgba(64, 158, 255, 0.15) !important;
}

/* 折叠动画 */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Tab 标签过渡 */
.tab-item {
  transition: color 0.2s, background-color 0.2s;
}

.close-icon {
  opacity: 0;
  transition: opacity 0.2s;
}

.tab-item:hover .close-icon {
  opacity: 1;
}
</style>

<!-- 管理后台全局样式 -->
<style>
body.admin-page {
  font-size: 14px !important;
  margin: 0;
  padding: 0;
}

body.admin-page .el-breadcrumb__inner.is-link {
  font-weight: 400;
}

body.admin-page ::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

body.admin-page ::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

body.admin-page ::-webkit-scrollbar-track {
  background: transparent;
}

/* 暗色模式滚动条 */
body.admin-page .dark ::-webkit-scrollbar-thumb {
  background: #555;
}
</style>
