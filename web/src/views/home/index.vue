<script setup lang="ts">
import { ref, h, onMounted, defineComponent, markRaw } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessageBox } from 'element-plus'
import { ArrowRight, Avatar, User, ChatLineRound, FirstAidKit, Money, Wallet, Star, Pointer, View } from '@element-plus/icons-vue'
import type { Component } from 'vue'
import { safeFetch } from '@/utils/fetch'
import { getToken } from '@/utils/auth'

const router = useRouter()
const route = useRoute()

interface FeatureItem {
  icon: Component
  title: string
  desc: string
  path: string
}

const features = ref<FeatureItem[]>(markRaw([
  { icon: FirstAidKit, title: '舌诊分析', desc: 'AI智能舌诊，了解身体状况', path: '/analysis/tongue' },
  { icon: User, title: '面诊分析', desc: '面色面诊，洞察健康密码', path: '/analysis/face' },
  { icon: Pointer, title: '手相分析', desc: '中医手相诊健康，五脏六腑一目了然', path: '/analysis/palm' },
  { icon: View, title: '眼部分析', desc: '目诊观健康，肝窍明状态', path: '/analysis/eye' },
  { icon: Star, title: '体质分析', desc: '中医体质辨识，个性化调理', path: '/constitution/test' },
  { icon: ChatLineRound, title: '健康问答', desc: 'AI在线问答，专业指导', path: '/qa/chat' },
]))

// 需要消耗积分的分析路径
const creditRequiredPaths = ['/analysis/tongue', '/analysis/face', '/analysis/palm', '/analysis/eye']
const creditsPerAnalysis = 1

const goToFeature = (path: string) => {
  // 检查是否需要消耗积分
  if (creditRequiredPaths.includes(path)) {
    // 未登录用户跳转到登录页
    const token = getToken()
    if (!token) {
      router.push('/login')
      return
    }
    
    // 检查积分是否充足
    if (analysisTimes.value !== null && analysisTimes.value < creditsPerAnalysis) {
      ElMessageBox.confirm(
        `积分不足，本次分析需要 ${creditsPerAnalysis} 积分。快去充值解锁更多分析次数吧！`,
        '积分不足',
        {
          confirmButtonText: '去充值',
          cancelButtonText: '取消',
          type: 'warning',
        }
      ).then(() => {
        router.push('/recharge')
      }).catch(() => {})
      return
    }
  }
  
  router.push(path)
}

// 当前剩余分析积分（未登录时为 null，不显示）
const analysisTimes = ref<number | null>(null)
const siteName = ref<string>('AI 中医健康助手') // 默认标题
const siteUrl = ref<string>(window.location.origin) // 网站域名

// 图标组件包装器（避免 reactive 警告）
const IconWrapper = defineComponent({
  props: {
    icon: { type: Object as () => Component, required: true },
  },
  render() {
    return h(this.icon)
  },
})

const fetchAnalysisTimes = async () => {
  // 仅在已登录时获取用户信息
  const token = getToken()
  if (!token) return

  try {
    const res = await safeFetch('/api/v1/user/info', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      analysisTimes.value = data.data?.analysis_times ?? 0
    }
  } catch (e) {
    // 忽略错误
  }
}

// 获取网站名称配置
const fetchSiteConfig = async () => {
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

const goRecharge = () => {
  router.push('/recharge')
}

// 滚动活动数据
interface ActivityItem {
  id: number
  username: string
  type: string
  type_name: string
  health_score: number
  credits: number
  created_at: string
}

const activities = ref<ActivityItem[]>([])
const activityLoading = ref(false)

// 活动类型图标映射
const typeIcons: Record<string, Component> = {
  tongue: FirstAidKit,
  face: User,
  palm: Pointer,
  eye: View,
  constitution: Star,
}

const fetchActivities = async () => {
  activityLoading.value = true
  try {
    const res = await safeFetch('/api/v1/home/activity', {
      headers: {
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      activities.value = data.data
    }
  } catch (e) {
    // 获取失败不显示模拟数据
    activities.value = []
  } finally {
    activityLoading.value = false
  }
}

onMounted(() => {
  // 存储邀请码到 localStorage（如果有）
  const inviteCode = route.query.code as string
  if (inviteCode) {
    localStorage.setItem('invite_code', inviteCode)
    // 清除 URL 中的 code 参数，避免重复存储
    router.replace({ query: {} })
  }
  
  fetchAnalysisTimes()
  fetchSiteConfig()
  fetchActivities()
})
</script>

<template>
  <div class="home-page">
    <!-- 顶部横幅 -->
    <div class="banner">
      <div class="banner-title">{{ siteName }}</div>
      <div class="banner-subtitle">智能分析 · 科学养生 · 守护健康</div>
    </div>

    <!-- 滚动活动播报（仅有数据时显示） -->
    <div v-if="activities.length > 0" class="activity-feed">
      <div class="feed-header">
        <el-icon class="feed-icon"><FirstAidKit /></el-icon>
        <span class="feed-title">实时动态</span>
      </div>
      <div class="feed-list-wrapper">
        <div class="feed-list" :class="{ 'feed-list--animate': activities.length > 0 }">
          <div
            v-for="(item, index) in [...activities, ...activities]"
            :key="`${item.id}-${index}`"
            class="feed-item"
          >
            <span class="feed-username">{{ item.username }}</span>
            <span class="feed-action">完成{{ item.type_name }}</span>
            <span class="feed-credits">消费{{ item.credits }}积分</span>
            <div class="feed-score-ring">
              <svg class="score-ring" viewBox="0 0 36 36">
                <path
                  class="score-ring-bg"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
                <path
                  class="score-ring-fill"
                  :stroke-dasharray="`${item.health_score}, 100`"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
              </svg>
              <span class="score-ring-text">{{ item.health_score }}</span>
            </div>
            <span class="feed-time">{{ item.created_at }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 功能入口 -->
    <div class="features">
      <div
        v-for="item in features"
        :key="item.title"
        class="feature-item"
        @click="goToFeature(item.path)"
      >
        <div class="feature-icon">
          <el-icon><IconWrapper :icon="item.icon" /></el-icon>
        </div>
        <div class="feature-title">{{ item.title }}</div>
        <div class="feature-desc">{{ item.desc }}</div>
      </div>
    </div>

    <!-- 充值积分面板 -->
    <div class="recharge-entry" @click="goRecharge">
      <div class="entry-icon">
        <el-icon><Wallet /></el-icon>
      </div>
      <div class="entry-content">
        <div class="recharge-entry-title">充值积分</div>
        <div class="recharge-entry-desc">
          <template v-if="analysisTimes !== null">当前剩余 {{ analysisTimes }} 积分，点击充值</template>
          <template v-else>充值分析积分，畅享 AI 健康分析</template>
        </div>
      </div>
      <el-button class="recharge-btn" round size="small" type="primary">立即充值</el-button>
    </div>

    <!-- 健康档案入口 -->
    <div class="health-entry" @click="router.push('/health/history')">
      <div class="entry-icon">
        <el-icon><FirstAidKit /></el-icon>
      </div>
      <div class="entry-content">
        <div class="health-entry-title">健康档案</div>
        <div class="health-entry-desc">查看您的分析历史和健康趋势</div>
      </div>
      <el-icon class="entry-arrow"><ArrowRight /></el-icon>
    </div>

    <!-- 推广入口 -->
    <div class="promote-entry" @click="router.push('/promoter')">
      <div class="entry-icon">
        <el-icon><Money /></el-icon>
      </div>
      <div class="entry-content">
        <div class="promote-entry-title">推广赚钱</div>
        <div class="promote-entry-desc">邀请好友，赚取佣金</div>
      </div>
      <el-icon class="entry-arrow"><ArrowRight /></el-icon>
    </div>
  </div>
</template>

<style scoped>
.home-page {
  padding: 0 12px;
  padding-bottom: 24px;
}

/* 小程序风格横幅 */
.banner {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 16px;
  padding: 28px 20px;
  color: #fff;
  margin-bottom: 16px;
  position: relative;
  overflow: hidden;
}

.banner::before {
  content: '';
  position: absolute;
  top: -20px;
  right: -20px;
  width: 100px;
  height: 100px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
}

.banner::after {
  content: '';
  position: absolute;
  bottom: -30px;
  left: -30px;
  width: 80px;
  height: 80px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 50%;
}

.banner-title {
  font-size: 22px;
  font-weight: bold;
  margin-bottom: 6px;
  position: relative;
  z-index: 1;
}

.banner-subtitle {
  font-size: 13px;
  opacity: 0.9;
  position: relative;
  z-index: 1;
}

/* 滚动活动播报 */
.activity-feed {
  background: #fff;
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.feed-header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
}

.feed-icon {
  color: #07c160;
  font-size: 16px;
}

.feed-title {
  font-size: 13px;
  font-weight: 500;
  color: #303133;
}

.feed-list-wrapper {
  height: 120px;
  overflow: hidden;
  position: relative;
}

.feed-list {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.feed-list--animate {
  animation: feedScroll 20s linear infinite;
}

@keyframes feedScroll {
  0% {
    transform: translateY(0);
  }
  100% {
    transform: translateY(-50%);
  }
}

.feed-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 0;
  font-size: 12px;
  color: #606266;
  border-bottom: 1px solid #f5f7fa;
  white-space: nowrap;
}

.feed-username {
  font-weight: 500;
  color: #303133;
  min-width: 40px;
}

.feed-action {
  color: #909399;
  flex: 1;
}

.feed-credits {
  color: #f56c6c;
  font-size: 11px;
  background: #fef0f0;
  padding: 2px 6px;
  border-radius: 4px;
}

.feed-score-ring {
  position: relative;
  width: 32px;
  height: 32px;
  flex-shrink: 0;
}

.score-ring {
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.score-ring-bg {
  fill: none;
  stroke: #ebeef5;
  stroke-width: 3;
}

.score-ring-fill {
  fill: none;
  stroke: #07c160;
  stroke-width: 3;
  stroke-linecap: round;
  transition: stroke-dasharray 0.5s ease;
}

.score-ring-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 10px;
  font-weight: 600;
  color: #07c160;
}

.feed-time {
  color: #c0c4cc;
  font-size: 11px;
}

/* 功能入口网格 */
.features {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 16px;
}

.feature-item {
  background: #fff;
  border-radius: 12px;
  padding: 18px 12px;
  text-align: center;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  transition: transform 0.15s, box-shadow 0.15s;
}

.feature-item:active {
  transform: scale(0.98);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}

.feature-icon {
  font-size: 28px;
  margin-bottom: 6px;
  color: #07c160;
}

.feature-title {
  font-size: 14px;
  font-weight: 600;
  color: #323233;
  margin-bottom: 2px;
}

.feature-desc {
  font-size: 11px;
  color: #969799;
  line-height: 1.4;
}

/* 快捷入口卡片 */
.health-entry,
.promote-entry,
.recharge-entry {
  background: #fff;
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  transition: transform 0.15s;
}

.health-entry:active,
.promote-entry:active,
.recharge-entry:active {
  transform: scale(0.99);
}

/* 充值积分面板 */
.recharge-entry {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  color: #fff;
}

.recharge-entry .entry-icon {
  color: rgba(255, 255, 255, 0.9);
}

.recharge-entry-title {
  font-size: 15px;
  font-weight: 600;
}

.recharge-entry-desc {
  font-size: 12px;
  opacity: 0.85;
  margin-top: 2px;
}

.recharge-btn {
  margin-left: auto;
  flex-shrink: 0;
  background: #fff;
  color: #07c160;
  border: none;
  font-weight: 500;
}

.recharge-btn:hover {
  background: #f0f9f4;
  color: #07c160;
}

.entry-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #e8f7ef 0%, #d4f0e0 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;
  flex-shrink: 0;
}

.entry-icon .el-icon {
  font-size: 20px;
  color: #07c160;
}

.entry-content {
  flex: 1;
  min-width: 0;
}

.health-entry-title,
.promote-entry-title {
  font-size: 15px;
  font-weight: 600;
  color: #323233;
  margin-bottom: 2px;
}

.health-entry-desc,
.promote-entry-desc {
  font-size: 11px;
  color: #969799;
}

.entry-arrow {
  color: #c8c9cc;
  font-size: 14px;
  flex-shrink: 0;
}
</style>
