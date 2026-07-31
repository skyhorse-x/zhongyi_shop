<script setup lang="ts">
import { ref, onMounted, computed, markRaw } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox, ElDialog, ElRadioGroup, ElRadioButton, ElButton } from 'element-plus'
import { List, Check, InfoFilled, ShoppingBag, Fire, Star, Trophy } from '@element-plus/icons-vue'

const router = useRouter()
const loading = ref(false)
const packages = ref<any[]>([])
const selectedId = ref<number | null>(null)
const paying = ref(false)
const payType = ref<'wechat' | 'alipay'>('wechat')
const userInfo = ref<any>(null)

const getToken = (): string => localStorage.getItem('token') || ''

// 剩余分析次数（从用户信息获取）
const remainingTimes = computed(() => Number(userInfo.value?.analysis_times ?? 0))

// icon 名称 → 组件映射（后端只传 icon 名称，前端解析）
const iconMap: Record<string, any> = {
  Fire: markRaw(Fire),
  Star: markRaw(Star),
  Trophy: markRaw(Trophy),
  ShoppingBag: markRaw(ShoppingBag),
}

// 从后端加载套餐（不带任何兜底）
const fetchPackages = async () => {
  loading.value = true
  try {
    const res = await fetch('/api/v1/packages', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      packages.value = (data.data || []).map((p: any) => ({
        id: p.id,
        name: p.name,
        type: p.type,
        count: p.times,
        days: p.days,
        price: p.price,
        originalPrice: p.original_price,
        isRecommend: p.is_recommend,
        iconName: p.icon || 'Star',
        iconComp: iconMap[p.icon] || iconMap.Star,
        discount: p.discount || 0,
      }))
      // 默认选中推荐套餐或第一个
      const recommend = packages.value.find(p => p.isRecommend)
      selectedId.value = recommend?.id ?? packages.value[0]?.id ?? null
    } else {
      ElMessage.error(data.message || '加载套餐失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误，请稍后重试')
  } finally {
    loading.value = false
  }
}

const selectPackage = (id: number) => {
  selectedId.value = id
}

const getTagStyle = (isRecommend: boolean) => {
  return isRecommend
    ? { bg: 'linear-gradient(135deg, #07c160, #04a152)', text: '#fff' }
    : { bg: 'linear-gradient(135deg, #ff976a, #ff6b35)', text: '#fff' }
}

const getDiscount = (pkg: any) => pkg.discount || 0

// 购买流程：选择套餐 → 确认支付方式 → 调用后端
const handlePurchase = async (pkg: any) => {
  if (paying.value) return

  // 1. 先选择支付方式
  payType.value = await selectPayType().catch(() => null)
  if (!payType.value) return

  // 2. 确认订单
  try {
    await ElMessageBox.confirm(
      `您选择购买：${pkg.name}，金额：¥${pkg.price}，支付方式：${payType.value === 'wechat' ? '微信支付' : '支付宝'}`,
      '确认订单',
      {
        confirmButtonText: '立即支付',
        cancelButtonText: '取消',
        type: 'info',
      }
    )
  } catch {
    return
  }

  // 3. 发起支付
  paying.value = true
  try {
    const res = await fetch('/api/v1/packages/buy', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        package_id: pkg.id,
        pay_type: payType.value,
      }),
    })
    const data = await res.json()

    if (data.code === 0) {
      ElMessage.success('订单已创建，正在跳转支付...')
      setTimeout(() => {
        router.push(`/member/orders?highlight=${data.data.order_no}`)
      }, 800)
    } else {
      ElMessage.error(data.message || '购买失败')
    }
  } catch (e: any) {
    if (e?.message) ElMessage.error(e.message)
  } finally {
    paying.value = false
  }
}

const selectPayType = (): Promise<'wechat' | 'alipay' | null> => {
  return new Promise((resolve) => {
    payType.value = 'wechat'
    showPayDialog.value = true
    payDialogResolve = resolve
  })
}

// 支付对话框状态（安全替代 dangerouslyUseHTMLString）
const showPayDialog = ref(false)
let payDialogResolve: ((value: 'wechat' | 'alipay' | null) => void) | null = null

const confirmPayType = () => {
  showPayDialog.value = false
  if (payDialogResolve) {
    payDialogResolve(payType.value)
    payDialogResolve = null
  }
}

const cancelPayType = () => {
  showPayDialog.value = false
  if (payDialogResolve) {
    payDialogResolve(null)
    payDialogResolve = null
  }
}

onMounted(() => {
  fetchPackages()
  fetchUserInfo()
})

// 获取用户信息（用于显示剩余次数）
const fetchUserInfo = async () => {
  try {
    const res = await fetch('/api/v1/user/info', {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      userInfo.value = data.data
    }
  } catch {
    // 静默失败
  }
}
</script>

<template>
  <div class="packages-page">
    <!-- 顶部横幅 -->
    <div class="header-banner">
      <div class="banner-content">
        <div class="banner-icon">🌿</div>
        <div class="banner-text">
        <h1 class="banner-title">购买次数包</h1>
        <p class="banner-subtitle">选择适合您的分析套餐，享受智能中医健康服务</p>
        <div class="banner-times">
          <span class="times-label">剩余分析次数</span>
          <span class="times-value">{{ remainingTimes }}</span>
          <span class="times-unit">次</span>
        </div>
      </div>
      </div>
      <div class="banner-decoration">
        <div class="deco-circle deco-circle-1"></div>
        <div class="deco-circle deco-circle-2"></div>
      </div>
    </div>

    <!-- 套餐列表 -->
    <div v-loading="loading" class="package-list">
      <div
        v-for="pkg in packages"
        :key="pkg.id"
        class="package-card"
        :class="{ 'package-card--active': selectedId === pkg.id }"
        @click="selectPackage(pkg.id)"
      >
        <!-- 标签 -->
        <div
          v-if="pkg.isRecommend"
          class="package-tag"
          :style="{ background: getTagStyle(pkg.isRecommend).bg }"
        >
          推荐
        </div>

        <!-- 选中标记 -->
        <div class="package-check" v-if="selectedId === pkg.id">
          <el-icon><Check /></el-icon>
        </div>

        <div class="package-content">
          <!-- 左侧信息 -->
          <div class="package-info">
            <div class="package-header">
              <div class="package-icon">
                <el-icon><component :is="pkg.iconComp" /></el-icon>
              </div>
              <div class="package-title">
                <div class="package-name">{{ pkg.name }}</div>
                <div class="package-desc">
                  含 {{ pkg.count }} 次分析 · 永久有效
                </div>
              </div>
            </div>
            <div class="package-count">
              <el-icon class="count-icon"><List /></el-icon>
              <span>共 {{ pkg.count }} 次分析机会</span>
            </div>
          </div>

          <!-- 右侧价格 -->
          <div class="package-price">
            <div class="price-row">
              <span class="price-symbol">¥</span>
              <span class="price-value">{{ pkg.price }}</span>
            </div>
            <div class="price-original">¥{{ pkg.originalPrice }}</div>
            <div v-if="getDiscount(pkg) > 0" class="price-discount">省{{ getDiscount(pkg) }}%</div>
          </div>
        </div>

        <!-- 购买按钮 -->
        <div class="package-footer">
          <el-button
            class="buy-btn"
            :type="selectedId === pkg.id ? 'primary' : 'default'"
            size="large"
            round
            @click.stop="handlePurchase(pkg)"
          >
            <span v-if="selectedId === pkg.id">立即购买</span>
            <span v-else>选择套餐</span>
          </el-button>
        </div>
      </div>

      <!-- 空状态 -->
      <el-empty
        v-if="!loading && packages.length === 0"
        description="暂无可用套餐"
      />
    </div>

    <!-- 底部说明卡片 -->
    <div class="info-card">
      <div class="info-header">
        <el-icon class="info-icon"><InfoFilled /></el-icon>
        <span>购买须知</span>
      </div>
      <div class="info-list">
        <div class="info-item">
          <span class="info-dot"></span>
          <span>购买后次数永久有效，不会过期</span>
        </div>
        <div class="info-item">
          <span class="info-dot"></span>
          <span>可用于舌诊、面诊、体质测试等所有分析服务</span>
        </div>
        <div class="info-item">
          <span class="info-dot"></span>
          <span>支持多种支付方式，安全便捷</span>
        </div>
        <div class="info-item">
          <span class="info-dot"></span>
          <span>如有问题，请联系客服获取帮助</span>
        </div>
      </div>
    </div>

    <!-- 底部安全提示 -->
    <div class="security-tips">
      <span class="shield-icon">🛡️</span>
      <span>支付安全有保障 · 放心购买</span>
    </div>

    <!-- 支付方式选择对话框（安全替代 dangerouslyUseHTMLString） -->
    <ElDialog v-model="showPayDialog" title="选择支付方式" width="90%" :max-width="400" :close-on-click-modal="false">
      <div class="pay-options">
        <label class="pay-option" :class="{ active: payType === 'wechat' }">
          <ElRadioGroup v-model="payType" style="width: 100%">
            <ElRadioButton value="wechat">
              <span class="pay-icon">💚</span>
              <div class="pay-info">
                <div class="pay-name">微信支付</div>
                <div class="pay-desc">推荐使用</div>
              </div>
            </ElRadioButton>
          </ElRadioGroup>
        </label>
        <label class="pay-option" :class="{ active: payType === 'alipay' }">
          <ElRadioGroup v-model="payType" style="width: 100%">
            <ElRadioButton value="alipay">
              <span class="pay-icon">💙</span>
              <div class="pay-info">
                <div class="pay-name">支付宝</div>
                <div class="pay-desc">快捷安全</div>
              </div>
            </ElRadioButton>
          </ElRadioGroup>
        </label>
      </div>
      <template #footer>
        <ElButton @click="cancelPayType">取消</ElButton>
        <ElButton type="primary" @click="confirmPayType">下一步</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<style scoped>
.packages-page {
  padding: 16px;
  padding-bottom: 32px;
  min-height: 100vh;
  background: #f7f8fa;
}

/* 顶部横幅 */
.header-banner {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 16px;
  padding: 24px 20px;
  color: #fff;
  margin-bottom: 20px;
  position: relative;
  overflow: hidden;
}

.banner-content {
  display: flex;
  align-items: center;
  gap: 16px;
  position: relative;
  z-index: 1;
}

.banner-icon {
  font-size: 48px;
  flex-shrink: 0;
}

.banner-text {
  flex: 1;
}

.banner-title {
  font-size: 22px;
  font-weight: 700;
  margin: 0 0 6px;
  letter-spacing: 1px;
}

.banner-subtitle {
  font-size: 13px;
  opacity: 0.9;
  margin: 0;
  line-height: 1.5;
}

.banner-times {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin-top: 10px;
  padding: 6px 14px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  width: fit-content;
  backdrop-filter: blur(4px);
}

.times-label {
  font-size: 12px;
  opacity: 0.85;
}

/* 支付对话框样式 */
.pay-options {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 8px 0;
}

.pay-option {
  display: flex;
  padding: 14px;
  border: 2px solid #e9ecef;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
}

.pay-option.active {
  border-color: #07c160;
  background: #f6ffed;
}

.pay-option .el-radio-button__inner {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 0;
  border: none;
  background: transparent;
  box-shadow: none;
}

.pay-icon {
  font-size: 28px;
}

.pay-info {
  text-align: left;
}

.pay-name {
  font-weight: 600;
  font-size: 15px;
}

.pay-desc {
  font-size: 12px;
  color: #999;
}

.times-value {
  font-size: 24px;
  font-weight: 700;
  line-height: 1;
}

.times-unit {
  font-size: 12px;
  opacity: 0.85;
}

.banner-decoration {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  pointer-events: none;
  overflow: hidden;
}

.deco-circle {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
}

.deco-circle-1 {
  width: 100px;
  height: 100px;
  top: -30px;
  right: -20px;
}

.deco-circle-2 {
  width: 60px;
  height: 60px;
  bottom: -15px;
  right: 40px;
}

/* 套餐列表 */
.package-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 20px;
}

.package-card {
  background: #fff;
  border-radius: 16px;
  padding: 18px;
  position: relative;
  border: 2px solid transparent;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  transition: all 0.3s ease;
  overflow: hidden;
}

.package-card:active {
  transform: scale(0.98);
}

.package-card--active {
  border-color: #07c160;
  box-shadow: 0 4px 20px rgba(7, 193, 96, 0.15);
}

/* 标签 */
.package-tag {
  position: absolute;
  top: 0;
  right: 0;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 12px;
  border-bottom-left-radius: 12px;
  color: #fff;
  letter-spacing: 0.5px;
}

/* 选中标记 */
.package-check {
  position: absolute;
  top: 14px;
  left: 14px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #07c160;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  box-shadow: 0 2px 8px rgba(7, 193, 96, 0.3);
}

/* 套餐内容 */
.package-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.package-info {
  flex: 1;
  margin-right: 16px;
}

.package-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.package-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #e8f7ef 0%, #d4f0e0 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: #07c160;
  flex-shrink: 0;
}

.package-title {
  flex: 1;
}

.package-name {
  font-size: 18px;
  font-weight: 700;
  color: #323233;
  margin-bottom: 4px;
}

.package-desc {
  font-size: 12px;
  color: #969799;
  line-height: 1.4;
}

.package-count {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #646566;
  padding: 8px 12px;
  background: #f7f8fa;
  border-radius: 8px;
  width: fit-content;
}

.count-icon {
  color: #07c160;
  font-size: 14px;
}

/* 价格区域 */
.package-price {
  text-align: right;
  flex-shrink: 0;
}

.price-row {
  display: flex;
  align-items: baseline;
  justify-content: flex-end;
}

.price-symbol {
  font-size: 16px;
  font-weight: 600;
  color: #ee0a24;
}

.price-value {
  font-size: 32px;
  font-weight: 700;
  color: #ee0a24;
  line-height: 1;
}

.price-original {
  font-size: 12px;
  color: #c8c9cc;
  text-decoration: line-through;
  margin-top: 2px;
}

.price-discount {
  display: inline-block;
  font-size: 11px;
  color: #ee0a24;
  background: #fef0f0;
  padding: 2px 8px;
  border-radius: 10px;
  margin-top: 4px;
  font-weight: 500;
}

/* 购买按钮 */
.package-footer {
  display: flex;
  justify-content: flex-end;
}

.buy-btn {
  min-width: 120px;
  height: 38px;
  font-weight: 500;
}

.buy-btn.el-button--primary {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border: none;
}

.buy-btn.el-button--primary:hover {
  box-shadow: 0 4px 12px rgba(7, 193, 96, 0.3);
}

/* 信息卡片 */
.info-card {
  background: #fff;
  border-radius: 16px;
  padding: 18px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.info-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 600;
  color: #323233;
  margin-bottom: 14px;
}

.info-icon {
  color: #07c160;
  font-size: 18px;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.info-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
  color: #646566;
  line-height: 1.5;
}

.info-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #07c160;
  margin-top: 6px;
  flex-shrink: 0;
}

/* 安全提示 */
.security-tips {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  color: #969799;
  padding: 12px;
}

.shield-icon {
  font-size: 14px;
}
</style>
