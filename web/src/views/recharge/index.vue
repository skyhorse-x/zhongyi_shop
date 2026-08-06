<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Ticket, ChatLineRound, Shop, Star, Trophy } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'

const router = useRouter()

interface Package {
  id: number
  name: string
  type: string
  times: number
  days: number
  price: number
  original_price: number
  is_recommend: boolean
  icon: string
  discount: number
  sort_order: number
}

const loading = ref(false)
const packages = ref<Package[]>([])
const analysisTimes = ref(0)
const wechatService = ref('') // 微信客服

// 获取当前剩余分析次数
const fetchUserInfo = async () => {
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
    console.error('获取用户信息失败:', e)
  }
}

// 获取微信客服配置
const fetchWechatService = async () => {
  try {
    const res = await safeFetch('/api/v1/analysis/config', {
      headers: { 'Accept': 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) {
      wechatService.value = data.data?.wechat_service || ''
    }
  } catch (e) {
    console.error('获取微信客服配置失败:', e)
  }
}

// 获取套餐列表
const fetchPackages = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/packages', {
      headers: { 'Accept': 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) {
      packages.value = data.data || []
    } else {
      ElMessage.error(data.message || '加载套餐失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误')
  } finally {
    loading.value = false
  }
}

// 复制客服微信号
const copyWechat = async () => {
  const text = wechatService.value || '暂未配置'
  try {
    await navigator.clipboard.writeText(text)
    ElMessage.success('微信号已复制')
  } catch {
    const input = document.createElement('input')
    input.value = text
    document.body.appendChild(input)
    input.select()
    document.execCommand('copy')
    document.body.removeChild(input)
    ElMessage.success('微信号已复制')
  }
}

// 弹出微信号对话框
const showWechatDialog = (pkg: Package) => {
  const wechat = wechatService.value || '暂未配置'
  ElMessageBox({
    title: '加微信购买积分',
    message: `
      <div style="text-align: center; padding: 10px 0;">
        <div style="font-size: 16px; color: #333; margin-bottom: 16px;">
          购买「${pkg.name}」请添加微信客服
        </div>
        <div style="background: #e8f7ef; border-radius: 8px; padding: 16px; margin-bottom: 16px; border: 1px solid #b7eb8f;">
          <div style="font-size: 14px; color: #52c41a; margin-bottom: 8px;">微信号</div>
          <div style="font-size: 28px; font-weight: bold; color: #07c160; letter-spacing: 1px;">${wechat}</div>
        </div>
        <div style="font-size: 14px; color: #666; margin-bottom: 8px;">
          请向客服说明要购买的套餐：<b style="color: #333;">${pkg.name}</b>
        </div>
        <div style="font-size: 12px; color: #999;">
          点击下方按钮复制微信号
        </div>
      </div>
    `,
    dangerouslyUseHTMLString: true,
    showCancelButton: true,
    confirmButtonText: '复制微信号',
    cancelButtonText: '取消',
    confirmButtonClass: 'el-button--success',
    cancelButtonClass: 'el-button--default',
    callback: (action: string) => {
      if (action === 'confirm') {
        copyWechat()
      }
    },
  })
}

onMounted(() => {
  fetchUserInfo()
  fetchPackages()
  fetchWechatService()
})
</script>

<template>
  <div class="recharge-page">
    <!-- 顶部余额卡片 -->
    <div class="balance-card">
      <div class="balance-left">
        <div class="balance-label">当前剩余积分</div>
        <div class="balance-num">
          {{ analysisTimes }}
          <span class="balance-unit">次</span>
        </div>
      </div>
      <div class="balance-icon">
        <el-icon :size="40"><Ticket /></el-icon>
      </div>
    </div>

    <!-- 微信客服（顶部显示） -->
    <div class="contact-card">
      <div class="contact-header">
        <el-icon><ChatLineRound /></el-icon>
        <span>微信客服</span>
      </div>
      <div class="contact-body">
        <div class="contact-tip">余额充值和积分购买，请加微信：</div>
        <div v-if="wechatService" class="wechat-id">
          <el-icon><ChatLineRound /></el-icon>
          <span>{{ wechatService }}</span>
          <el-button link type="primary" size="small" @click="copyWechat">复制</el-button>
        </div>
        <div v-else class="wechat-id wechat-empty">
          <el-icon><ChatLineRound /></el-icon>
          <span>客服暂未配置，请联系管理员</span>
        </div>
      </div>
    </div>

    <!-- 选择套餐 -->
    <div class="section-title">
      <el-icon><Shop /></el-icon>
      <span>积分购买</span>
    </div>

    <div v-loading="loading" class="package-list">
      <div 
        v-for="pkg in packages" 
        :key="pkg.id" 
        class="package-card"
        :class="{ 'recommended': pkg.is_recommend }"
      >
        <div class="package-badge" v-if="pkg.is_recommend">推荐</div>
        <div class="package-icon">
          <el-icon :size="32"><Trophy v-if="pkg.is_recommend" /><Star v-else /></el-icon>
        </div>
        <div class="package-info">
          <div class="package-name">{{ pkg.name }}</div>
          <div class="package-desc">{{ pkg.times }} 次分析 · {{ pkg.days }} 天有效</div>
          <div class="package-meta">
            <span class="package-price">¥{{ pkg.price.toFixed(2) }}</span>
            <span v-if="pkg.original_price > pkg.price" class="package-original">¥{{ pkg.original_price.toFixed(2) }}</span>
            <span v-if="pkg.discount > 0" class="package-discount">-{{ pkg.discount }}%</span>
          </div>
        </div>
        <div class="package-action">
          <el-button
            round
            type="success"
            size="small"
            @click="showWechatDialog(pkg)"
          >
            加微信购买积分
          </el-button>
        </div>
      </div>

      <el-empty v-if="!loading && packages.length === 0" description="暂无可购买的套餐，敬请期待" />
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

/* 套餐列表 */
.package-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  min-height: 120px;
}

.package-card {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  background: #fff;
  border-radius: 14px;
  padding: 16px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
  border: 2px solid transparent;
  transition: all 0.3s ease;
}

.package-card.recommended {
  border-color: #ff9800;
  background: linear-gradient(135deg, #fffbf0 0%, #fff8e1 100%);
  box-shadow: 0 4px 16px rgba(255, 152, 0, 0.15);
}

.package-badge {
  position: absolute;
  top: -8px;
  right: 12px;
  background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  padding: 2px 10px;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);
}

.package-icon {
  flex-shrink: 0;
  width: 50px;
  height: 50px;
  border-radius: 12px;
  background: linear-gradient(135deg, #e8f7ef 0%, #d4edda 100%);
  color: #07c160;
  display: flex;
  align-items: center;
  justify-content: center;
}

.package-card.recommended .package-icon {
  background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
  color: #ff9800;
}

.package-info {
  flex: 1;
  min-width: 0;
}

.package-name {
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 4px;
}

.package-desc {
  font-size: 12px;
  color: #969799;
  margin-bottom: 6px;
}

.package-meta {
  display: flex;
  align-items: center;
  gap: 8px;
}

.package-price {
  color: #f56c6c;
  font-size: 20px;
  font-weight: 700;
}

.package-original {
  font-size: 13px;
  color: #969799;
  text-decoration: line-through;
}

.package-discount {
  font-size: 12px;
  color: #fff;
  background: #f56c6c;
  padding: 1px 6px;
  border-radius: 4px;
  font-weight: 600;
}

.package-action {
  flex-shrink: 0;
}

/* 联系客服卡片 */
.contact-card {
  background: linear-gradient(135deg, #fff7e6 0%, #fff2d9 100%);
  border-radius: 14px;
  padding: 16px;
  margin-top: 20px;
  border: 1px solid #ffe4a3;
}

.contact-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 600;
  color: #c8821f;
  margin-bottom: 10px;
}

.contact-header .el-icon {
  color: #e6a23c;
}

.contact-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.contact-tip {
  font-size: 13px;
  color: #8b6914;
  line-height: 1.5;
}

.wechat-id {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fff;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid #ffe4a3;
}

.wechat-id .el-icon {
  color: #07c160;
  font-size: 16px;
}

.wechat-id span {
  flex: 1;
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  letter-spacing: 0.5px;
}

.wechat-empty span {
  color: #969799;
  font-weight: 400;
  font-size: 14px;
}
</style>
