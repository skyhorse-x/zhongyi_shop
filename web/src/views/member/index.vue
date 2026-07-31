<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Document, FirstAidKit, ShoppingBag, Promotion, Headset } from '@element-plus/icons-vue'
import type { Component } from 'vue'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const userStore = useUserStore()

const userInfo = ref(userStore.userInfo)

interface MenuItem {
  icon: Component
  title: string
  path: string
}

const menuItems = ref<MenuItem[]>([
  { icon: Document, title: '我的订单', path: '/member/orders' },
  { icon: FirstAidKit, title: '健康档案', path: '/health/history' },
  { icon: ShoppingBag, title: '购买次数包', path: '/packages' },
  { icon: Promotion, title: '推广中心', path: '/promoter' },
  { icon: Headset, title: '联系客服', path: '' },
])

const handleLogout = () => {
  userStore.logoutAction()
  router.push('/auth/login')
}

onMounted(async () => {
  if (userStore.isLoggedIn) {
    try {
      userInfo.value = await userStore.fetchUserInfo()
    } catch (e) {
      console.error(e)
    }
  }
})
</script>

<template>
  <div class="member-page">
    <div class="user-card">
      <div class="avatar">
        <img
          :src="userInfo?.avatar || 'https://img.yzcdn.cn/vant/cat.jpeg'"
          alt="头像"
        />
      </div>
      <div class="user-info">
        <div class="nickname">{{ userInfo?.nickname || '用户' }}</div>
        <div class="mobile">{{ userInfo?.mobile || '' }}</div>
        <div class="times-badge">
          <span class="times-num">{{ userInfo?.analysis_times ?? 0 }}</span>
          <span class="times-label">次分析</span>
        </div>
      </div>
    </div>

    <div class="menu-group">
      <div
        v-for="item in menuItems"
        :key="item.title"
        class="menu-item"
        @click="item.path && router.push(item.path)"
      >
        <el-icon class="menu-item-icon"><component :is="item.icon" /></el-icon>
        <span class="menu-item-title">{{ item.title }}</span>
        <el-icon class="menu-item-arrow"><ArrowRight /></el-icon>
      </div>
    </div>

    <div class="logout-btn">
      <el-button style="width: 100%" @click="handleLogout">退出登录</el-button>
    </div>
  </div>
</template>

<style scoped>
.member-page {
  padding: 0 12px;
}

/* 用户卡片 - 小程序风格 */
.user-card {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 16px;
  padding: 24px 20px;
  color: #fff;
  display: flex;
  align-items: center;
  margin-bottom: 16px;
  position: relative;
  overflow: hidden;
}

.user-card::before {
  content: '';
  position: absolute;
  top: -15px;
  right: -15px;
  width: 80px;
  height: 80px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
}

.avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  overflow: hidden;
  margin-right: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  position: relative;
  z-index: 1;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-info {
  color: #fff;
  position: relative;
  z-index: 1;
}

.nickname {
  font-size: 17px;
  font-weight: 600;
  margin-bottom: 2px;
}

.mobile {
  font-size: 13px;
  opacity: 0.85;
}

.times-badge {
  display: inline-flex;
  align-items: baseline;
  gap: 4px;
  margin-top: 10px;
  padding: 4px 12px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 16px;
  width: fit-content;
}

.times-num {
  font-size: 20px;
  font-weight: 700;
  line-height: 1;
}

.times-label {
  font-size: 11px;
  opacity: 0.85;
}

/* 菜单组 - 小程序风格 */
.menu-group {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 16px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.menu-item {
  display: flex;
  align-items: center;
  padding: 14px 16px;
  cursor: pointer;
  border-bottom: 0.5px solid #f5f5f5;
  transition: background 0.15s;
}

.menu-item:active {
  background: #f9f9f9;
}

.menu-item:last-child {
  border-bottom: none;
}

.menu-item-icon {
  font-size: 18px;
  margin-right: 12px;
  color: #07c160;
}

.menu-item-title {
  flex: 1;
  font-size: 14px;
  color: #323233;
}

.menu-item-arrow {
  color: #c8c9cc;
  font-size: 12px;
}

/* 退出按钮 - 小程序风格 */
.logout-btn {
  margin-top: 24px;
}

.logout-btn :deep(.el-button) {
  height: 44px;
  border-radius: 22px;
  font-size: 15px;
  font-weight: 500;
}
</style>
