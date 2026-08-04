<script setup lang="ts">
import { ref, h, onMounted, defineComponent, markRaw } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Avatar, User, ChatLineRound, FirstAidKit, Money, Wallet, Star } from '@element-plus/icons-vue'
import type { Component } from 'vue'
import { safeFetch } from '@/utils/fetch'
import { getToken } from '@/utils/auth'

const router = useRouter()

interface FeatureItem {
  icon: Component
  title: string
  desc: string
  path: string
}

const features = ref<FeatureItem[]>(markRaw([
  { icon: FirstAidKit, title: '舌诊分析', desc: 'AI智能舌诊，了解身体状况', path: '/analysis/tongue' },
  { icon: User, title: '面诊分析', desc: '面色面诊，洞察健康密码', path: '/analysis/face' },
  { icon: Star, title: '体质分析', desc: '中医体质辨识，个性化调理', path: '/constitution/test' },
  { icon: ChatLineRound, title: '健康问答', desc: 'AI在线问答，专业指导', path: '/qa/chat' },
]))

const goToFeature = (path: string) => {
  router.push(path)
}

// 当前剩余分析次数（未登录时为 null，不显示）
const analysisTimes = ref<number | null>(null)
const siteName = ref<string>('AI 中医健康助手') // 默认标题

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
  try {
    const res = await safeFetch('/api/v1/user/info', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      analysisTimes.value = data.data?.analysis_times ?? 0
    }
  } catch (e) {
    // 未登录等情况忽略
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
    if (data.code === 0 && data.data?.site_name) {
      siteName.value = data.data.site_name
    }
  } catch (e) {
    // 使用默认标题
  }
}

const goRecharge = () => {
  router.push('/recharge')
}

onMounted(() => {
  fetchAnalysisTimes()
  fetchSiteConfig()
})
</script>

<template>
  <div class="home-page">
    <!-- 顶部横幅 -->
    <div class="banner">
      <div class="banner-title">{{ siteName }}</div>
      <div class="banner-subtitle">智能分析 · 科学养生 · 守护健康</div>
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
          <template v-else>充值分析次数，畅享 AI 健康分析</template>
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
