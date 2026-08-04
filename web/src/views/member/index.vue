<script setup lang="ts">
import { ref, onMounted, defineComponent, h } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Document, FirstAidKit, ShoppingBag, Promotion, Headset, Wallet, Money, Present, Bell, Coin } from '@element-plus/icons-vue'
import type { Component } from 'vue'
import { useUserStore } from '@/stores/user'
import { safeFetch } from '@/utils/fetch'

const router = useRouter()
const userStore = useUserStore()

const userInfo = ref(userStore.userInfo)

interface MenuItem {
  icon: Component
  title: string
  path: string
}

const menuItems = ref<MenuItem[]>([
  { icon: Wallet, title: '余额明细', path: '/member/balance' },
  { icon: Money, title: '提现', path: '/promoter/withdraw' },
  { icon: Document, title: '我的订单', path: '/member/orders' },
  { icon: FirstAidKit, title: '健康档案', path: '/health/history' },
  { icon: ShoppingBag, title: '购买次数包', path: '/packages' },
  { icon: Promotion, title: '推广中心', path: '/promoter' },
  { icon: Headset, title: '联系客服', path: '/messages/customer-service' },
])

// 充值积分
const goRecharge = () => {
  router.push('/recharge')
}

// 图标组件包装器（避免 reactive 警告）
const IconWrapper = defineComponent({
  props: {
    icon: { type: Object as () => Component, required: true },
  },
  render() {
    return h(this.icon)
  },
})

const formatBalance = (v: any): string => {
  const n = Number(v ?? 0)
  return n.toFixed(2)
}

const handleLogout = () => {
  userStore.logoutAction()
  router.push('/auth/login')
}

// ===== 邀请播报滚动条（前台会员中心） =====
interface MarqueeItem {
  promoter_name: string
  invite_count: number
  commission: number
}

const marqueeList = ref<MarqueeItem[]>([])
const marqueeLoading = ref(false)

// 虚拟兜底数据
const defaultMarqueeData: MarqueeItem[] = [
  { promoter_name: '李健康', invite_count: 8, commission: 45.60 },
  { promoter_name: '王养生', invite_count: 12, commission: 78.90 },
  { promoter_name: '张中医', invite_count: 5, commission: 28.00 },
  { promoter_name: '刘调理', invite_count: 15, commission: 102.30 },
  { promoter_name: '陈艾灸', invite_count: 3, commission: 15.50 },
  { promoter_name: '杨体质', invite_count: 20, commission: 156.80 },
  { promoter_name: '赵经络', invite_count: 7, commission: 38.90 },
  { promoter_name: '孙方剂', invite_count: 10, commission: 67.20 },
  { promoter_name: '周推拿', invite_count: 6, commission: 33.00 },
  { promoter_name: '吴养生', invite_count: 9, commission: 52.40 },
]

const loadInviteMarquee = async () => {
  marqueeLoading.value = true
  try {
    // 公开接口，无需 Authorization
    const res = await safeFetch('/api/v1/invite-marquee')
    const data = await res.json()
    if (data.code === 0 && data.data) {
      const realItems = data.data.recent || []
      const topList = data.data.top_list || []
      if (realItems.length > 0) {
        marqueeList.value = realItems.map((item: any) => ({
          promoter_name: item.promoter_name,
          invite_count: item.invite_count,
          commission: item.commission,
        }))
      } else if (topList.length > 0) {
        marqueeList.value = topList.map((item: any) => ({
          promoter_name: item.promoter_name,
          invite_count: item.invite_count,
          commission: item.commission,
        }))
      } else {
        marqueeList.value = defaultMarqueeData
      }
    } else {
      marqueeList.value = defaultMarqueeData
    }
  } catch (e) {
    marqueeList.value = defaultMarqueeData
  } finally {
    marqueeLoading.value = false
  }
}

onMounted(async () => {
  if (userStore.isLoggedIn) {
    try {
      userInfo.value = await userStore.fetchUserInfo()
    } catch (e) {
      console.error(e)
    }
  }
  loadInviteMarquee()
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
        <div class="badges">
          <div v-if="userInfo?.is_new_user_gift" class="gift-badge">
            <el-icon class="gift-icon"><Present /></el-icon>
            <span>新人礼 · 已赠送 {{ userInfo?.gift_times ?? 0 }} 次</span>
          </div>
          <div class="badge-item">
            <span class="badge-num">¥{{ formatBalance(userInfo?.balance) }}</span>
            <span class="badge-label">余额</span>
          </div>
          <div class="badge-item badge-item--alt">
            <span class="badge-num">{{ userInfo?.analysis_times ?? 0 }}</span>
            <span class="badge-label">次分析</span>
          </div>
        </div>
        <!-- 充值按钮 -->
        <button type="button" class="recharge-btn" @click="goRecharge">
          <el-icon class="recharge-icon"><Coin /></el-icon>
          充值积分
        </button>
      </div>
    </div>

    <!-- 邀请播报滚动条 -->
    <div class="invite-marquee">
      <el-icon class="marquee-icon"><Bell /></el-icon>
      <div class="marquee-wrapper">
        <div class="marquee-content" :class="{ 'marquee-paused': marqueeLoading }">
          <span v-for="(item, idx) in marqueeList" :key="idx" class="marquee-item">
            <span class="promoter-name">{{ item.promoter_name }}</span>
            邀请了
            <span class="highlight-num">{{ item.invite_count }}</span>
            人，返利
            <span class="highlight-money">¥{{ item.commission.toFixed(2) }}</span>
            <span class="marquee-divider">|</span>
          </span>
          <!-- 无缝滚动复制一份 -->
          <span v-for="(item, idx) in marqueeList" :key="'copy-' + idx" class="marquee-item">
            <span class="promoter-name">{{ item.promoter_name }}</span>
            邀请了
            <span class="highlight-num">{{ item.invite_count }}</span>
            人，返利
            <span class="highlight-money">¥{{ item.commission.toFixed(2) }}</span>
            <span class="marquee-divider">|</span>
          </span>
        </div>
      </div>
    </div>

    <div class="menu-group">
      <button
        v-for="item in menuItems"
        :key="item.title"
        type="button"
        class="menu-item"
        @click="item.path && router.push(item.path)"
      >
        <el-icon class="menu-item-icon"><IconWrapper :icon="item.icon" /></el-icon>
        <span class="menu-item-title">{{ item.title }}</span>
        <el-icon class="menu-item-arrow"><ArrowRight /></el-icon>
      </button>
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
  flex: 1;
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

.badges {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
}

.badge-item {
  display: inline-flex;
  align-items: baseline;
  gap: 4px;
  padding: 4px 12px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 16px;
  width: fit-content;
}

.badge-item--alt {
  background: rgba(255, 255, 255, 0.12);
}

.badge-num {
  font-size: 20px;
  font-weight: 700;
  line-height: 1;
}

.badge-label {
  font-size: 11px;
  opacity: 0.85;
}

/* 新人礼徽章 */
.gift-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  background: linear-gradient(135deg, #fff5a0 0%, #ffd700 100%);
  color: #8b6914;
  border-radius: 16px;
  font-size: 11px;
  font-weight: 600;
  box-shadow: 0 2px 6px rgba(255, 215, 0, 0.3);
  animation: gift-pulse 2s ease-in-out infinite;
}

.gift-icon {
  font-size: 13px;
}

@keyframes gift-pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
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
  background: transparent;
  border-left: none;
  border-right: none;
  border-top: none;
  width: 100%;
  text-align: left;
  font-family: inherit;
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

/* ===== 邀请播报滚动条 ===== */
.invite-marquee {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  padding: 10px 14px;
  background: linear-gradient(135deg, #f6ffed 0%, #fcffe6 100%);
  border: 1px solid #b7eb8f;
  border-radius: 12px;
  overflow: hidden;
}

.marquee-icon {
  flex-shrink: 0;
  font-size: 18px;
  animation: marquee-bounce 1s ease-in-out infinite;
}

@keyframes marquee-bounce {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.2); }
}

.marquee-wrapper {
  flex: 1;
  overflow: hidden;
  white-space: nowrap;
}

.marquee-content {
  display: inline-block;
  animation: marquee-scroll 25s linear infinite;
}

.marquee-content.marquee-paused {
  animation-play-state: paused;
}

@keyframes marquee-scroll {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

.marquee-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #333;
}

.promoter-name {
  font-weight: 600;
  color: #52c41a;
}

.highlight-num {
  font-weight: 700;
  color: #1890ff;
}

.highlight-money {
  font-weight: 700;
  color: #fa8c16;
}

.marquee-divider {
  margin: 0 10px;
  color: #b7eb8f;
}

.invite-marquee:hover .marquee-content {
  animation-play-state: paused;
}

/* 充值按钮 */
.recharge-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  margin-top: 14px;
  padding: 10px 0;
  background: linear-gradient(135deg, #ffb800 0%, #ff976a 100%);
  color: #fff;
  border: none;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(255, 184, 0, 0.3);
}

.recharge-btn:active {
  transform: scale(0.96);
  box-shadow: 0 2px 6px rgba(255, 184, 0, 0.3);
}

.recharge-icon {
  font-size: 16px;
}
</style>
