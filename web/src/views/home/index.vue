<script setup lang="ts">
import { ref, h } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, Avatar, User, List, ChatLineRound, FirstAidKit, Money } from '@element-plus/icons-vue'
import type { FunctionalComponent } from 'vue'

const router = useRouter()

interface FeatureItem {
  icon: FunctionalComponent
  title: string
  desc: string
  path: string
}

const features = ref<FeatureItem[]>([
  { icon: FirstAidKit, title: '舌诊分析', desc: 'AI智能舌诊，了解身体状况', path: '/analysis/tongue' },
  { icon: User, title: '面诊分析', desc: '面色面诊，洞察健康密码', path: '/analysis/face' },
  { icon: List, title: '体质测试', desc: '中医体质辨识，个性化调理', path: '/constitution/test' },
  { icon: ChatLineRound, title: '健康问答', desc: 'AI在线问答，专业指导', path: '/qa/chat' },
])

const goToFeature = (path: string) => {
  router.push(path)
}
</script>

<template>
  <div class="home-page">
    <!-- 顶部横幅 -->
    <div class="banner">
      <div class="banner-title">AI中医健康管理</div>
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
          <el-icon><component :is="item.icon" /></el-icon>
        </div>
        <div class="feature-title">{{ item.title }}</div>
        <div class="feature-desc">{{ item.desc }}</div>
      </div>
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
    <div class="promote-entry" @click="router.push('/promoter/activate')">
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
.promote-entry {
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
.promote-entry:active {
  transform: scale(0.99);
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
