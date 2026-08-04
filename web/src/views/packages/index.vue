<script setup lang="ts">
import { ref, onMounted, computed, markRaw } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox, ElDialog, ElRadioGroup, ElRadio, ElButton } from 'element-plus'
import { List, Check, InfoFilled, ShoppingBag, Sunny, Star, Trophy, Wallet, Money, ChatLineSquare, CreditCard, Iphone, FirstAidKit } from '@element-plus/icons-vue'
import { toMoney } from '@/utils'
import { safeFetch } from '@/utils/fetch'
import { getToken } from '@/utils/auth'

const router = useRouter()
const loading = ref(false)
const packages = ref<any[]>([])
const selectedId = ref<number | null>(null)
const paying = ref(false)
const userInfo = ref<any>(null)
const userBalance = ref(0)

const getAuthToken = (): string => getToken() || ''

// icon 名称 → 组件映射（后端只传 icon 名称，前端解析）
const iconMap: Record<string, any> = {
  Sunny: markRaw(Sunny),
  Star: markRaw(Star),
  Trophy: markRaw(Trophy),
  ShoppingBag: markRaw(ShoppingBag),
}

// 剩余分析次数
const remainingTimes = computed(() => Number(userInfo.value?.analysis_times ?? 0))

// 从后端加载套餐
const fetchPackages = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/packages', {
      headers: { Authorization: `Bearer ${getToken()}`, Accept: 'application/json' },
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

// ===== 支付方式选择弹窗 =====
const showPayDialog = ref(false)
let payDialogResolve: ((value: string | null) => void) | null = null
let currentPkg: any = null

// 支付方式列表（从后端拉取，含余额）
const paymentMethods = ref<{ code: string; name: string; icon: string; is_enabled: boolean }[]>([])
const selectedPayType = ref('')

// 拉取支付方式
const fetchPaymentMethods = async () => {
  try {
    const res = await safeFetch('/api/v1/payment/methods', {
      headers: { Authorization: `Bearer ${getToken()}`, Accept: 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) {
      paymentMethods.value = data.data?.list || []
      userBalance.value = Number(data.data?.user_balance ?? 0)
    }
  } catch (e) {
    console.error('拉取支付方式失败', e)
  }
}

// 可点击的支付方式（admin 关闭 + 余额不足的不能选）
const usableMethods = computed(() => {
  const price = Number(currentPkg?.price ?? 0)
  return paymentMethods.value.filter((m) => {
    if (!m.is_enabled) return false
    if (m.code === 'balance' && userBalance.value < price) return false
    return true
  })
})

const payTypeName = (code: string) =>
  paymentMethods.value.find((m) => m.code === code)?.name || code

const balanceEnough = (price: number) => userBalance.value >= price

// 打开支付方式选择
const openPayDialog = (pkg: any): Promise<string | null> => {
  currentPkg = pkg
  // 预选第一个可用方式
  const first = usableMethods.value[0]
  selectedPayType.value = first?.code || ''
  showPayDialog.value = true
  return new Promise((resolve) => {
    payDialogResolve = resolve
  })
}

const confirmPayType = () => {
  if (!selectedPayType.value) {
    ElMessage.warning('请选择支付方式')
    return
  }
  showPayDialog.value = false
  payDialogResolve?.(selectedPayType.value)
  payDialogResolve = null
}

const cancelPayType = () => {
  showPayDialog.value = false
  payDialogResolve?.(null)
  payDialogResolve = null
}

// ===== 购买主流程 =====
const handlePurchase = async (pkg: any) => {
  if (paying.value) return

  // 1. 选择支付方式
  const chosen = await openPayDialog(pkg)
  if (!chosen) return

  // 2. 余额支付：直接下单
  if (chosen === 'balance') {
    if (!balanceEnough(Number(pkg.price))) {
      ElMessage.error('余额不足')
      return
    }
    try {
      paying.value = true
      const res = await safeFetch('/api/v1/packages/buy', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${getToken()}`,
          Accept: 'application/json',
        },
        body: JSON.stringify({ package_id: pkg.id, pay_type: 'balance' }),
      })
      const data = await res.json()
      if (data.code === 0) {
        // 刷新余额
        await fetchPaymentMethods()
        await fetchUserInfo()
        ElMessage.success(`余额支付成功！购买 ${pkg.count} 次分析`)
        setTimeout(() => {
          router.push(`/member/orders?highlight=${data.data.order_no}`)
        }, 600)
      } else {
        ElMessage.error(data.message || '支付失败')
      }
    } catch (e: any) {
      ElMessage.error(e?.message || '支付失败')
    } finally {
      paying.value = false
    }
    return
  }

  // 3. 第三方支付：弹确认 → 创建订单 → 走支付参数
  try {
    await ElMessageBox.confirm(
      `您选择购买：${pkg.name}，金额：¥${toMoney(pkg.price)}，支付方式：${payTypeName(chosen)}`,
      '确认订单',
      { confirmButtonText: '立即支付', cancelButtonText: '取消', type: 'info' }
    )
  } catch {
    return
  }

  paying.value = true
  try {
    const res = await safeFetch('/api/v1/packages/buy', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
      body: JSON.stringify({ package_id: pkg.id, pay_type: chosen }),
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

// 拉取用户信息
const fetchUserInfo = async () => {
  try {
    const res = await safeFetch('/api/v1/user/info', {
      headers: { Authorization: `Bearer ${getToken()}`, Accept: 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) {
      userInfo.value = data.data
      userBalance.value = Number(data.data?.balance ?? userBalance.value)
    }
  } catch {
    // 静默失败
  }
}

onMounted(() => {
  fetchPackages()
  fetchUserInfo()
  fetchPaymentMethods()
})
</script>

<template>
  <div class="packages-page">
    <!-- 顶部横幅 -->
    <div class="header-banner">
      <div class="banner-content">
        <el-icon class="banner-icon"><FirstAidKit /></el-icon>
        <div class="banner-text">
          <h1 class="banner-title">购买次数包</h1>
          <p class="banner-subtitle">选择适合您的分析套餐，享受智能中医健康服务</p>
          <div class="banner-times">
            <span class="times-label">剩余分析次数</span>
            <span class="times-value">{{ remainingTimes }}</span>
            <span class="times-unit">次</span>
            <span class="times-divider">·</span>
            <span class="times-label">余额</span>
            <span class="times-value">¥{{ toMoney(userBalance) }}</span>
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
        <div
          v-if="pkg.isRecommend"
          class="package-tag"
          :style="{ background: getTagStyle(pkg.isRecommend).bg }"
        >
          推荐
        </div>
        <div class="package-check" v-if="selectedId === pkg.id">
          <el-icon><Check /></el-icon>
        </div>
        <div class="package-content">
          <div class="package-info">
            <div class="package-header">
              <div class="package-icon">
                <el-icon><component :is="pkg.iconComp" /></el-icon>
              </div>
              <div class="package-title">
                <div class="package-name">{{ pkg.name }}</div>
                <div class="package-desc">含 {{ pkg.count }} 次分析 · 永久有效</div>
              </div>
            </div>
            <div class="package-count">
              <el-icon class="count-icon"><List /></el-icon>
              <span>共 {{ pkg.count }} 次分析机会</span>
            </div>
          </div>
          <div class="package-price">
            <div class="price-row">
              <span class="price-symbol">¥</span>
              <span class="price-value">{{ pkg.price }}</span>
            </div>
            <div class="price-original">¥{{ pkg.originalPrice }}</div>
            <div v-if="getDiscount(pkg) > 0" class="price-discount">省{{ getDiscount(pkg) }}%</div>
          </div>
        </div>
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
      <el-empty v-if="!loading && packages.length === 0" description="暂无可用套餐" />
    </div>

    <div class="info-card">
      <div class="info-header">
        <el-icon class="info-icon"><InfoFilled /></el-icon>
        <span>购买须知</span>
      </div>
      <div class="info-list">
        <div class="info-item"><span class="info-dot"></span><span>购买后次数永久有效，不会过期</span></div>
        <div class="info-item"><span class="info-dot"></span><span>可用于舌诊、面诊、体质测试等所有分析服务</span></div>
        <div class="info-item"><span class="info-dot"></span><span>支持余额/微信/支付宝支付，安全便捷</span></div>
        <div class="info-item"><span class="info-dot"></span><span>如有问题，请联系客服获取帮助</span></div>
      </div>
    </div>

    <div class="security-tips">
      <el-icon class="shield-icon"><Check /></el-icon>
      <span>支付安全有保障 · 放心购买</span>
    </div>

    <!-- 支付方式选择对话框（修复：单 RadioGroup + 后端拉取） -->
    <ElDialog
      v-model="showPayDialog"
      title="选择支付方式"
      width="360px"
      :close-on-click-modal="false"
      @close="cancelPayType"
    >
      <div v-if="currentPkg" class="pay-summary">
        <div class="summary-label">订单金额</div>
        <div class="summary-amount">¥{{ toMoney(currentPkg.price) }}</div>
        <div class="summary-desc">{{ currentPkg.name }} · {{ currentPkg.count }} 次</div>
      </div>

      <ElRadioGroup v-model="selectedPayType" class="pay-options">
        <template v-for="m in paymentMethods" :key="m.code">
          <!-- 余额选项 -->
          <div
            v-if="m.code === 'balance'"
            class="pay-option"
            :class="{
              active: selectedPayType === m.code,
              disabled: !m.is_enabled || !balanceEnough(Number(currentPkg?.price ?? 0))
            }"
            @click="(m.is_enabled && balanceEnough(Number(currentPkg?.price ?? 0))) && (selectedPayType = m.code)"
          >
            <ElRadio :value="m.code" :disabled="!m.is_enabled || !balanceEnough(Number(currentPkg?.price ?? 0))">
              <div class="pay-option-content">
                <div class="pay-icon-box pay-icon-balance">
                  <el-icon :size="22"><Wallet /></el-icon>
                </div>
                <div class="pay-info">
                  <div class="pay-name-row">
                    <span class="pay-name">{{ m.name }}</span>
                    <span class="pay-tag pay-tag-success" v-if="balanceEnough(Number(currentPkg?.price ?? 0))">推荐</span>
                  </div>
                  <div class="pay-desc">
                    当前余额：<span class="balance-num">¥{{ toMoney(userBalance) }}</span>
                    <span v-if="!balanceEnough(Number(currentPkg?.price ?? 0))" class="pay-tag-warn">
                      余额不足
                    </span>
                  </div>
                </div>
                <el-icon class="pay-check"><Check /></el-icon>
              </div>
            </ElRadio>
          </div>
          <!-- 微信选项 -->
          <div
            v-else-if="m.code === 'wechat'"
            class="pay-option"
            :class="{ active: selectedPayType === m.code, disabled: !m.is_enabled }"
            @click="m.is_enabled && (selectedPayType = m.code)"
          >
            <ElRadio :value="m.code" :disabled="!m.is_enabled">
              <div class="pay-option-content">
                <div class="pay-icon-box pay-icon-wechat">
                  <el-icon :size="22"><ChatLineSquare /></el-icon>
                </div>
                <div class="pay-info">
                  <div class="pay-name-row">
                    <span class="pay-name">{{ m.name }}</span>
                    <span v-if="!m.is_enabled" class="pay-tag-disabled">已关闭</span>
                  </div>
                  <div class="pay-desc">推荐使用微信支付</div>
                </div>
                <el-icon class="pay-check"><Check /></el-icon>
              </div>
            </ElRadio>
          </div>
          <!-- 支付宝选项 -->
          <div
            v-else-if="m.code === 'alipay'"
            class="pay-option"
            :class="{ active: selectedPayType === m.code, disabled: !m.is_enabled }"
            @click="m.is_enabled && (selectedPayType = m.code)"
          >
            <ElRadio :value="m.code" :disabled="!m.is_enabled">
              <div class="pay-option-content">
                <div class="pay-icon-box pay-icon-alipay">
                  <el-icon :size="22"><CreditCard /></el-icon>
                </div>
                <div class="pay-info">
                  <div class="pay-name-row">
                    <span class="pay-name">{{ m.name }}</span>
                    <span v-if="!m.is_enabled" class="pay-tag-disabled">已关闭</span>
                  </div>
                  <div class="pay-desc">安全快捷</div>
                </div>
                <el-icon class="pay-check"><Check /></el-icon>
              </div>
            </ElRadio>
          </div>
          <!-- 其他支付方式 -->
          <div
            v-else
            class="pay-option"
            :class="{ active: selectedPayType === m.code, disabled: !m.is_enabled }"
            @click="m.is_enabled && (selectedPayType = m.code)"
          >
            <ElRadio :value="m.code" :disabled="!m.is_enabled">
              <div class="pay-option-content">
                <div class="pay-icon-box pay-icon-other">
                  <el-icon :size="22"><Iphone /></el-icon>
                </div>
                <div class="pay-info">
                  <div class="pay-name-row">
                    <span class="pay-name">{{ m.name }}</span>
                    <span v-if="!m.is_enabled" class="pay-tag-disabled">已关闭</span>
                  </div>
                  <div class="pay-desc">安全便捷</div>
                </div>
                <el-icon class="pay-check"><Check /></el-icon>
              </div>
            </ElRadio>
          </div>
        </template>
      </ElRadioGroup>

      <div v-if="usableMethods.length === 0" class="pay-empty">
        暂无可用支付方式，请联系客服
      </div>

      <template #footer>
        <ElButton @click="cancelPayType">取消</ElButton>
        <ElButton type="primary" :loading="paying" @click="confirmPayType">确认支付</ElButton>
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
.banner-content { display: flex; align-items: center; gap: 16px; position: relative; z-index: 1; }
.banner-icon { font-size: 48px; flex-shrink: 0; }
.banner-text { flex: 1; }
.banner-title { font-size: 22px; font-weight: 700; margin: 0 0 6px; letter-spacing: 1px; }
.banner-subtitle { font-size: 13px; opacity: 0.9; margin: 0; line-height: 1.5; }
.banner-times {
  display: flex; align-items: baseline; gap: 6px; margin-top: 10px;
  padding: 6px 14px; background: rgba(255, 255, 255, 0.2);
  border-radius: 20px; width: fit-content; backdrop-filter: blur(4px); flex-wrap: wrap;
}
.times-label { font-size: 12px; opacity: 0.85; }
.times-value { font-size: 24px; font-weight: 700; line-height: 1; }
.times-unit { font-size: 12px; opacity: 0.85; }
.times-divider { font-size: 14px; opacity: 0.6; margin: 0 2px; }

/* 支付弹窗 */
.pay-summary {
  text-align: center;
  padding: 12px 0 16px;
  border-bottom: 1px solid #f0f0f0;
  margin-bottom: 12px;
}
.summary-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.summary-amount { font-size: 28px; font-weight: 700; color: #ee0a24; }
.summary-desc { font-size: 12px; color: #969799; margin-top: 4px; }

.pay-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}

.pay-option {
  display: block;
  padding: 14px;
  border: 2px solid #e9ecef;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  background: #fff;
}
.pay-option.active {
  border-color: #07c160;
  background: #f6ffed;
}
.pay-option.disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #f5f5f5;
}

.pay-option-content {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
}

.pay-option :deep(.el-radio) {
  width: 100%;
  margin-right: 0;
}
.pay-option :deep(.el-radio__label) {
  flex: 1;
  padding-left: 8px;
}

.pay-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}
.pay-icon-balance {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  color: #fff;
}
.pay-icon-wechat {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  color: #fff;
}
.pay-icon-alipay {
  background: linear-gradient(135deg, #1677ff 0%, #0958d9 100%);
  color: #fff;
}
.pay-icon-other {
  background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
  color: #fff;
}

.pay-info { flex: 1; min-width: 0; }
.pay-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 2px;
}
.pay-name { font-weight: 600; font-size: 15px; color: #323233; }
.pay-tag {
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 8px;
  font-weight: 500;
}
.pay-tag-success { background: #e8f7ef; color: #07c160; }
.pay-tag-warn { background: #fef0f0; color: #ee0a24; font-size: 10px; padding: 1px 6px; border-radius: 8px; margin-left: 6px; }
.pay-tag-disabled { background: #f0f0f0; color: #969799; font-size: 10px; padding: 1px 6px; border-radius: 8px; }
.pay-desc { font-size: 12px; color: #646566; }
.balance-num { color: #07c160; font-weight: 600; }

.pay-check {
  color: #c8c9cc;
  font-size: 18px;
  flex-shrink: 0;
}
.pay-option.active .pay-check { color: #07c160; }

.pay-empty {
  text-align: center;
  padding: 20px;
  color: #969799;
  font-size: 13px;
}

/* 套餐列表 */
.package-list { display: flex; flex-direction: column; gap: 14px; margin-bottom: 20px; }
.package-card {
  background: #fff; border-radius: 16px; padding: 18px;
  position: relative; border: 2px solid transparent;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  transition: all 0.3s ease; overflow: hidden;
}
.package-card:active { transform: scale(0.98); }
.package-card--active { border-color: #07c160; box-shadow: 0 4px 20px rgba(7, 193, 96, 0.15); }

.package-tag {
  position: absolute; top: 0; right: 0; font-size: 11px; font-weight: 600;
  padding: 4px 12px; border-bottom-left-radius: 12px; color: #fff; letter-spacing: 0.5px;
}
.package-check {
  position: absolute; top: 14px; left: 14px; width: 24px; height: 24px;
  border-radius: 50%; background: #07c160; color: #fff;
  display: flex; align-items: center; justify-content: center; font-size: 14px;
  box-shadow: 0 2px 8px rgba(7, 193, 96, 0.3);
}
.package-content { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.package-info { flex: 1; margin-right: 16px; }
.package-header { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.package-icon {
  width: 44px; height: 44px; border-radius: 12px;
  background: linear-gradient(135deg, #e8f7ef 0%, #d4f0e0 100%);
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; color: #07c160; flex-shrink: 0;
}
.package-title { flex: 1; }
.package-name { font-size: 18px; font-weight: 700; color: #323233; margin-bottom: 4px; }
.package-desc { font-size: 12px; color: #969799; line-height: 1.4; }
.package-count {
  display: flex; align-items: center; gap: 6px; font-size: 13px; color: #646566;
  padding: 8px 12px; background: #f7f8fa; border-radius: 8px; width: fit-content;
}
.count-icon { color: #07c160; font-size: 14px; }

.package-price { text-align: right; flex-shrink: 0; }
.price-row { display: flex; align-items: baseline; justify-content: flex-end; }
.price-symbol { font-size: 16px; font-weight: 600; color: #ee0a24; }
.price-value { font-size: 32px; font-weight: 700; color: #ee0a24; line-height: 1; }
.price-original { font-size: 12px; color: #c8c9cc; text-decoration: line-through; margin-top: 2px; }
.price-discount {
  display: inline-block; font-size: 11px; color: #ee0a24; background: #fef0f0;
  padding: 2px 8px; border-radius: 10px; margin-top: 4px; font-weight: 500;
}

.package-footer { display: flex; justify-content: flex-end; }
.buy-btn { min-width: 120px; height: 38px; font-weight: 500; }
.buy-btn.el-button--primary { background: linear-gradient(135deg, #07c160 0%, #04a152 100%); border: none; }
.buy-btn.el-button--primary:hover { box-shadow: 0 4px 12px rgba(7, 193, 96, 0.3); }

.info-card {
  background: #fff; border-radius: 16px; padding: 18px;
  margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}
.info-header { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #323233; margin-bottom: 14px; }
.info-icon { color: #07c160; font-size: 18px; }
.info-list { display: flex; flex-direction: column; gap: 10px; }
.info-item { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #646566; line-height: 1.5; }
.info-dot { width: 6px; height: 6px; border-radius: 50%; background: #07c160; margin-top: 6px; flex-shrink: 0; }

.security-tips {
  display: flex; align-items: center; justify-content: center;
  gap: 6px; font-size: 12px; color: #969799; padding: 12px;
}
.shield-icon { font-size: 14px; }

/* 横幅装饰 */
.banner-decoration { position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; overflow: hidden; }
.deco-circle { position: absolute; border-radius: 50%; background: rgba(255, 255, 255, 0.1); }
.deco-circle-1 { width: 100px; height: 100px; top: -30px; right: -20px; }
.deco-circle-2 { width: 60px; height: 60px; bottom: -15px; right: 40px; }
</style>
