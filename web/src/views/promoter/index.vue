<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Money, Wallet, Refresh } from '@element-plus/icons-vue'
import { toMoney } from '@/utils'
import { safeFetch } from '@/utils/fetch'

const router = useRouter()

const loading = ref(false)
const promoterInfo = ref<any>(null)
const isPromoter = ref(false)

const stats = ref({
  totalCommission: '0.00',
  todayCommission: '0.00',
  totalOrders: 0,
  pendingCommission: '0.00',
})

const tools = ref([
  { icon: 'qr', title: '推广二维码', action: 'poster' },
  { icon: 'share', title: '分享链接', action: 'share' },
  { icon: 'commission', title: '佣金明细', action: 'commissions' },
  { icon: 'history', title: '提现记录', action: 'withdraw-history' },
])

// 二维码弹层
const posterVisible = ref(false)
const posterUrl = ref('')

const getToken = (): string => localStorage.getItem('token') || ''

// 工具：安全转字符串数字（@/utils 已有同名函数）
const loadPromoterInfo = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/promoter/info', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()

    if (data.code === 0) {
      isPromoter.value = true
      promoterInfo.value = data.data
      stats.value = {
        totalCommission: toMoney(data.data.available_commission),
        todayCommission: '0.00',
        totalOrders: Number(data.data.total_consume) || 0,
        pendingCommission: toMoney(data.data.frozen_commission),
      }
    } else if (data.code === 404) {
      // 兼容历史数据：老用户未自动开通，调用 activate 补建
      isPromoter.value = false
      try {
        const r2 = await safeFetch('/api/v1/promoter/activate', {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${getToken()}`,
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({}),
        })
        const d2 = await r2.json()
        if (d2.code === 0) {
          isPromoter.value = true
          promoterInfo.value = d2.data
          stats.value = {
            totalCommission: '0.00',
            todayCommission: '0.00',
            totalOrders: 0,
            pendingCommission: '0.00',
          }
        }
      } catch (e) {
        // 静默失败，保持未开通状态
      }
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载推广信息失败')
  } finally {
    loading.value = false
  }
}

const handleWithdraw = () => {
  router.push('/promoter/withdraw')
}

const handleToolClick = (action: string) => {
  switch (action) {
    case 'poster':
      showPoster()
      break
    case 'share':
      handleCopyLink()
      break
    case 'commissions':
      router.push('/promoter/commissions')
      break
    case 'withdraw-history':
      router.push('/promoter/withdraw-history')
      break
  }
}

const showPoster = async () => {
  if (!promoterInfo.value?.invite_code) {
    ElMessage.warning('请先开通推广员')
    return
  }
  // 拼接后端动态生成的海报 URL（无需 token，可直接分享）
  posterUrl.value = `/api/v1/promoter/poster-image?code=${promoterInfo.value.invite_code}&t=${Date.now()}`
  posterVisible.value = true
}

const handleCopyLink = async () => {
  if (!promoterInfo.value?.invite_url) {
    ElMessage.warning('请先开通推广员')
    return
  }
  try {
    await navigator.clipboard.writeText(promoterInfo.value.invite_url)
    ElMessage.success('推广链接已复制')
  } catch {
    // 降级方案
    const input = document.createElement('input')
    input.value = promoterInfo.value.invite_url
    document.body.appendChild(input)
    input.select()
    document.execCommand('copy')
    document.body.removeChild(input)
    ElMessage.success('推广链接已复制')
  }
}

// 下载海报
const handleDownloadPoster = async () => {
  if (!posterUrl.value) return
  try {
    const res = await fetch(posterUrl.value)
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `推广海报-${promoterInfo.value?.invite_code || 'qr'}.png`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
    ElMessage.success('海报已下载')
  } catch (e: any) {
    ElMessage.error('下载失败，请长按图片保存')
  }
}

const goToActivate = () => {
  router.push('/promoter/activate')
}

onMounted(() => {
  loadPromoterInfo()
})
</script>

<template>
  <div class="promoter-page">

    <!-- 未开通推广员 -->
    <div v-if="!isPromoter && !loading" class="not-promoter">
      <div class="not-promoter-icon">🌿</div>
      <div class="not-promoter-title">您还不是推广员</div>
      <div class="not-promoter-desc">开通推广员，分享健康赚取佣金</div>
      <el-button type="primary" round @click="goToActivate">立即开通</el-button>
    </div>

    <!-- 推广员信息 -->
    <template v-else>
      <div class="stats-card">
        <div class="stats-header">
          <div class="stats-title">可提现佣金（元）</div>
          <el-button type="primary" size="small" round @click="handleWithdraw">
            立即提现
          </el-button>
        </div>
        <div class="stats-amount">¥{{ stats.totalCommission }}</div>
        <div class="stats-grid">
          <div class="stats-item">
            <div class="item-value">¥{{ stats.todayCommission }}</div>
            <div class="item-label">今日收益</div>
          </div>
          <div class="stats-item">
            <div class="item-value">{{ stats.totalOrders }}</div>
            <div class="item-label">推广订单</div>
          </div>
          <div class="stats-item">
            <div class="item-value">¥{{ stats.pendingCommission }}</div>
            <div class="item-label">待结算</div>
          </div>
        </div>
      </div>

      <div class="share-section">
        <div class="section-title">分享推广</div>
        <el-button
          class="share-btn"
          type="primary"
          @click="handleCopyLink"
        >
          <el-icon style="margin-right: 4px"><Share /></el-icon>
          复制推广链接
        </el-button>
      </div>

      <div class="tools-group">
        <div
          v-for="item in tools"
          :key="item.title"
          class="tools-item"
          @click="handleToolClick(item.action)"
        >
          <div class="tools-item__left">
            <span class="tools-item__icon">{{ item.icon === 'qr' ? '📱' : item.icon === 'share' ? '🔗' : item.icon === 'commission' ? '💰' : '📋' }}</span>
            <span class="tools-item__title">{{ item.title }}</span>
          </div>
          <span class="tools-item__arrow">›</span>
        </div>
      </div>
    </template>

    <!-- 推广二维码弹层 -->
    <el-dialog v-model="posterVisible" title="推广海报" width="360px" center>
      <div class="poster-dialog">
        <div class="poster-qr">
          <img
            v-if="posterUrl"
            :src="posterUrl"
            alt="推广海报"
            @error="(e: any) => { e.target.style.display='none' }"
          />
        </div>
        <div class="poster-tip">长按图片可保存到相册</div>
        <div class="poster-link">{{ promoterInfo?.invite_url }}</div>
        <div class="poster-actions">
          <el-button
            type="primary"
            round
            class="poster-copy-btn"
            @click="handleCopyLink"
          >
            复制链接
          </el-button>
          <el-button
            type="primary"
            round
            class="poster-download-btn"
            @click="handleDownloadPoster"
          >
            下载海报
          </el-button>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<style scoped>
.promoter-page {
  min-height: 100vh;
  background: #f7f8fa;
  padding-bottom: 24px;
}

.not-promoter {
  text-align: center;
  padding: 60px 20px;
}

.not-promoter-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.not-promoter-title {
  font-size: 18px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 8px;
}

.not-promoter-desc {
  font-size: 14px;
  color: #969799;
  margin-bottom: 24px;
}

.stats-card {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  margin: 16px;
  border-radius: 16px;
  padding: 20px;
  color: #fff;
}

.stats-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.stats-title {
  font-size: 14px;
  opacity: 0.85;
}

.stats-amount {
  font-size: 36px;
  font-weight: bold;
  margin-bottom: 20px;
}

.stats-grid {
  display: flex;
  justify-content: space-between;
}

.stats-item {
  text-align: center;
}

.item-value {
  font-size: 16px;
  font-weight: bold;
  margin-bottom: 4px;
}

.item-label {
  font-size: 12px;
  opacity: 0.75;
}

.share-section {
  margin: 16px;
}

.section-title {
  font-size: 16px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 12px;
}

.share-btn {
  width: 100%;
  border-radius: 8px;
}

.tools-group {
  margin: 0 16px;
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
}

.tools-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  cursor: pointer;
  border-bottom: 1px solid #f5f5f5;
}

.tools-item:last-child {
  border-bottom: none;
}

.tools-item:active {
  background: #f2f3f5;
}

.tools-item__left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tools-item__icon {
  font-size: 18px;
}

.tools-item__title {
  font-size: 14px;
  color: #323233;
}

.tools-item__arrow {
  color: #c8c9cc;
  font-size: 18px;
}

/* 海报弹层 */
.poster-dialog {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 0;
}
.poster-qr {
  width: 280px;
  height: 395px;
  border-radius: 12px;
  background: #f7f8fa;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.poster-qr img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.poster-tip {
  font-size: 12px;
  color: #969799;
  margin-bottom: 12px;
}
.poster-link {
  font-size: 12px;
  color: #646566;
  background: #f7f8fa;
  padding: 8px 12px;
  border-radius: 6px;
  width: 100%;
  text-align: center;
  word-break: break-all;
  margin-bottom: 16px;
}
.poster-actions {
  display: flex;
  gap: 12px;
  width: 100%;
}
.poster-copy-btn,
.poster-download-btn {
  flex: 1;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border: none;
}
</style>

