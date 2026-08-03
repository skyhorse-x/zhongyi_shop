<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Money, Ticket, Wallet, ChatLineRound, Link, Shop } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'
import { getToken } from '@/utils/auth'

const router = useRouter()

interface XianyuProduct {
  id: number
  title: string
  link: string
  amount: number
  times: number
  description: string
}

const loading = ref(false)
const products = ref<XianyuProduct[]>([])
const analysisTimes = ref(0)
const systemLink = ref('')

// 获取当前剩余分析次数
const fetchUserInfo = async () => {
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
    console.error('获取用户信息失败:', e)
  }
}

// 获取闲鱼充值商品列表
const fetchProducts = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/xianyu/products', {
      headers: { 'Accept': 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) {
      systemLink.value = data.data?.system_link || ''
      products.value = data.data?.products || []
    } else {
      ElMessage.error(data.message || '加载商品失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误')
  } finally {
    loading.value = false
  }
}

// 跳转后台配置的闲鱼商品链接（商品列表为空时的兜底入口）
const goSystemLink = () => {
  if (!systemLink.value) {
    ElMessage.warning('闲鱼商品链接暂未配置')
    return
  }
  window.open(systemLink.value, '_blank', 'noopener')
}

// 跳转闲鱼购买：优先跳转后台【系统设置 → 基本设置】配置的闲鱼商品链接，
// 未配置时才回退到商品自身链接
const goBuy = (item: XianyuProduct) => {
  const target = systemLink.value || item.link
  if (!target) {
    ElMessage.warning('闲鱼商品链接暂未配置')
    return
  }
  window.open(target, '_blank', 'noopener')
}

onMounted(() => {
  fetchUserInfo()
  fetchProducts()
})
</script>

<template>
  <div class="recharge-page">
    <!-- 顶部余额卡片 -->
    <div class="balance-card">
      <div class="balance-left">
        <div class="balance-label">当前剩余分析次数</div>
        <div class="balance-num">
          {{ analysisTimes }}
          <span class="balance-unit">次</span>
        </div>
      </div>
      <div class="balance-icon">
        <el-icon :size="40"><Ticket /></el-icon>
      </div>
    </div>

    <!-- 充值商品列表 -->
    <div class="section-title">
      <el-icon><Shop /></el-icon>
      <span>选择充值档位</span>
    </div>

    <div v-loading="loading" class="product-list">
      <div v-for="item in products" :key="item.id" class="product-card">
        <div class="product-info">
          <div class="product-title">{{ item.title }}</div>
          <div class="product-desc">{{ item.description || '闲鱼拍下付款后，联系客服审核到账' }}</div>
          <div class="product-meta">
            <span class="product-price">¥{{ Number(item.amount).toFixed(2) }}</span>
            <span v-if="item.times > 0" class="product-times">赠送 {{ item.times }} 次</span>
          </div>
        </div>
        <div class="product-action">
          <el-button
            round
            type="primary"
            size="small"
            @click="goBuy(item)"
          >
            去闲鱼购买
          </el-button>
        </div>
      </div>

      <el-empty v-if="!loading && products.length === 0 && !systemLink" description="暂无可充值的商品，敬请期待" />

      <!-- 后台配置链接兜底入口（商品列表为空时展示） -->
      <div v-if="products.length === 0 && systemLink" class="system-link-card" @click="goSystemLink">
        <div class="system-link-icon">
          <el-icon :size="28"><Shop /></el-icon>
        </div>
        <div class="system-link-info">
          <div class="system-link-title">闲鱼充值</div>
          <div class="system-link-desc">前往闲鱼购买并完成付款</div>
        </div>
        <el-button round type="primary" size="small">去闲鱼购买</el-button>
      </div>
    </div>

    <!-- 购买流程说明 -->
    <div class="help-card">
      <div class="help-title">
        <el-icon><ChatLineRound /></el-icon>
        <span>购买流程</span>
      </div>
      <div class="help-steps">
        <div class="help-step">
          <span class="step-num">1</span>
          <span>点击「去闲鱼购买」跳转到闲鱼商品页</span>
        </div>
        <div class="help-step">
          <span class="step-num">2</span>
          <span>在闲鱼拍下并完成付款</span>
        </div>
        <div class="help-step">
          <span class="step-num">3</span>
          <span>付款后联系客服，提供闲鱼订单号</span>
        </div>
        <div class="help-step">
          <span class="step-num">4</span>
          <span>管理员核对到账后，分析次数自动到账</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.recharge-page {
  padding: 16px;
}

/* 余额卡片 */
.balance-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 16px;
  padding: 20px;
  color: #fff;
  margin-bottom: 20px;
  box-shadow: 0 4px 16px rgba(7, 193, 96, 0.3);
}

.balance-label {
  font-size: 13px;
  opacity: 0.9;
  margin-bottom: 6px;
}

.balance-num {
  font-size: 36px;
  font-weight: 700;
  line-height: 1;
}

.balance-unit {
  font-size: 14px;
  font-weight: 400;
  opacity: 0.9;
}

.balance-icon {
  opacity: 0.35;
}

/* 标题 */
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 12px;
}

.section-title .el-icon {
  color: #07c160;
}

/* 商品列表 */
.product-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 120px;
}

.product-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  border-radius: 14px;
  padding: 16px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.product-info {
  flex: 1;
  min-width: 0;
  padding-right: 12px;
}

.product-title {
  font-size: 15px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 4px;
}

.product-desc {
  font-size: 12px;
  color: #969799;
  margin-bottom: 8px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-meta {
  display: flex;
  align-items: center;
  gap: 10px;
}

.product-price {
  color: #f56c6c;
  font-size: 18px;
  font-weight: 700;
}

.product-times {
  font-size: 12px;
  color: #07c160;
  background: #e8f7ef;
  padding: 2px 8px;
  border-radius: 10px;
  font-weight: 500;
}

/* 后台配置链接兜底卡片 */
.system-link-card {
  display: flex;
  align-items: center;
  gap: 12px;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 14px;
  padding: 18px 16px;
  color: #fff;
  box-shadow: 0 2px 10px rgba(7, 193, 96, 0.25);
  cursor: pointer;
}

.system-link-icon {
  flex-shrink: 0;
  opacity: 0.9;
}

.system-link-info {
  flex: 1;
  min-width: 0;
}

.system-link-title {
  font-size: 15px;
  font-weight: 600;
}

.system-link-desc {
  font-size: 12px;
  opacity: 0.85;
  margin-top: 2px;
}

/* 帮助 */
.help-card {
  background: #fff;
  border-radius: 14px;
  padding: 16px;
  margin-top: 20px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
}

.help-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 12px;
}

.help-title .el-icon {
  color: #07c160;
}

.help-steps {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.help-step {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: #646566;
}

.step-num {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #e8f7ef;
  color: #07c160;
  font-size: 12px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
</style>
