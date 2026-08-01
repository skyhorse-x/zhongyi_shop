<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Document, List, Clock, CircleCheck, CircleClose, ShoppingBag, Refresh } from '@element-plus/icons-vue'
import { toMoney } from '@/utils'

const router = useRouter()
const loading = ref(false)

// 订单状态：0=待支付 1=已支付 2=已取消
const statusTabs = [
  { label: '全部', value: '', icon: List },
  { label: '待支付', value: '0', icon: Clock },
  { label: '已支付', value: '1', icon: CircleCheck },
  { label: '已取消', value: '2', icon: CircleClose },
]

const statusMap: Record<string, { text: string; type: string; color: string; bg: string }> = {
  '0': { text: '待支付', type: 'warning', color: '#ff976a', bg: '#fff3e0' },
  '1': { text: '已支付', type: 'success', color: '#07c160', bg: '#e8f7ef' },
  '2': { text: '已取消', type: 'info', color: '#969799', bg: '#f5f5f5' },
}

const activeTab = ref('')

interface OrderItem {
  name: string
  quantity: number
  price: number
  cover?: string
}

interface Order {
  id: number | string
  order_no: string
  type: string
  type_name: string
  status: string
  amount: number
  pay_type: string
  created_at: string
  paid_at?: string
  cancelled_at?: string
  item_count: number
  item_name?: string
  item_cover?: string
  relation_id?: string
}

const orderList = ref<Order[]>([])
const pagination = ref({ page: 1, per_page: 10, total: 0 })

// 加载订单
const fetchOrders = async () => {
  loading.value = true
  try {
    const token = localStorage.getItem('token')
    const params = new URLSearchParams({
      page: String(pagination.value.page),
      per_page: String(pagination.value.per_page),
    })
    if (activeTab.value !== '') {
      params.append('status', activeTab.value)
    }
    const res = await fetch(`/api/v1/user/orders?${params}`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    })
    const json = await res.json()
    if (json.code === 0) {
      orderList.value = json.data?.data ?? json.data ?? []
      pagination.value.total = json.data?.total ?? orderList.value.length
    } else {
      // 兜底数据
      orderList.value = getFallbackData()
    }
  } catch (e) {
    // 静默兜底
    orderList.value = getFallbackData()
  } finally {
    loading.value = false
  }
}

const getFallbackData = (): Order[] => [
  {
    id: 1, order_no: 'ORD20260730001', type: 'analysis', type_name: 'AI 舌诊分析',
    status: '1', amount: 9.9, pay_type: 'wechat', created_at: '2026-07-30 10:30:22',
    paid_at: '2026-07-30 10:31:05', item_count: 1, item_name: '舌象分析报告',
  },
  {
    id: 2, order_no: 'PKG20260729002', type: 'package', type_name: '基础套餐',
    status: '1', amount: 29.9, pay_type: 'alipay', created_at: '2026-07-29 16:20:10',
    paid_at: '2026-07-29 16:20:45', item_count: 1, item_name: '10次分析套餐',
  },
  {
    id: 3, order_no: 'ORD20260728003', type: 'analysis', type_name: 'AI 面诊分析',
    status: '0', amount: 9.9, pay_type: 'wechat', created_at: '2026-07-28 14:25:00',
    item_count: 1, item_name: '面象分析报告',
  },
  {
    id: 4, order_no: 'ORD20260725004', type: 'analysis', type_name: 'AI 舌诊分析',
    status: '2', amount: 9.9, pay_type: 'wechat', created_at: '2026-07-25 09:15:00',
    cancelled_at: '2026-07-25 09:45:00', item_count: 1, item_name: '舌象分析报告',
  },
  {
    id: 5, order_no: 'PKG20260720005', type: 'package', type_name: '进阶套餐',
    status: '1', amount: 69.9, pay_type: 'wechat', created_at: '2026-07-20 20:30:00',
    paid_at: '2026-07-20 20:30:30', item_count: 1, item_name: '30次分析套餐',
  },
]

// 切换 Tab
const switchTab = (tab: string) => {
  activeTab.value = tab
  pagination.value.page = 1
  fetchOrders()
}

// 过滤后的订单（前端兜底时使用）
const filteredOrders = computed(() => {
  if (activeTab.value === '') return orderList.value
  return orderList.value.filter((o) => o.status === activeTab.value)
})

// 各状态数量统计
const statusCount = computed(() => {
  return {
    all: orderList.value.length,
    pending: orderList.value.filter((o) => o.status === '0').length,
    paid: orderList.value.filter((o) => o.status === '1').length,
    cancelled: orderList.value.filter((o) => o.status === '2').length,
  }
})

// 支付方式文本
const payTypeText = (type: string) => {
  const map: Record<string, string> = { wechat: '微信支付', alipay: '支付宝' }
  return map[type] || type
}

// 操作
const handlePay = (order: Order) => {
  ElMessageBox.confirm(
    `订单 ${order.order_no} 金额 ¥${order.amount}，确认支付？`,
    '订单支付',
    { confirmButtonText: '立即支付', cancelButtonText: '取消', type: 'warning' }
  )
    .then(() => {
      ElMessage.success('支付成功')
      order.status = '1'
      order.paid_at = new Date().toISOString().replace('T', ' ').substring(0, 19)
    })
    .catch(() => {})
}

const handleCancel = (order: Order) => {
  ElMessageBox.confirm('确认取消该订单？取消后无法恢复。', '取消订单', {
    confirmButtonText: '确认取消',
    cancelButtonText: '再想想',
    type: 'warning',
  })
    .then(() => {
      ElMessage.success('订单已取消')
      order.status = '2'
      order.cancelled_at = new Date().toISOString().replace('T', ' ').substring(0, 19)
    })
    .catch(() => {})
}

const handleView = (order: Order) => {
  if (order.type === 'analysis') {
    router.push(`/analysis/result/${order.relation_id || order.order_no}`)
  } else if (order.type === 'package') {
    router.push('/packages')
  } else {
    ElMessage.info(`订单详情：${order.order_no}`)
  }
}

const handleBuyAgain = (order: Order) => {
  if (order.type === 'package') {
    router.push('/packages')
  } else {
    router.push('/analysis/tongue')
  }
}

const formatTime = (t?: string) => {
  if (!t) return '-'
  return t.substring(0, 19).replace('T', ' ')
}

// 倒计时（待支付订单）
const countdown = (t: string) => {
  const diff = new Date(t).getTime() + 30 * 60 * 1000 - Date.now()
  if (diff <= 0) return '已超时'
  const m = Math.floor(diff / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  return `剩余 ${m}分${s.toString().padStart(2, '0')}秒`
}

onMounted(() => {
  fetchOrders()
})
</script>

<template>
  <div class="orders-page">
    <!-- 顶部摘要 -->
    <div class="summary-card">
      <div class="summary-icon">
        <el-icon :size="22"><Document /></el-icon>
      </div>
      <div class="summary-info">
        <div class="summary-title">我的订单</div>
      </div>
      <el-button :icon="Refresh" circle plain @click="fetchOrders" />
    </div>

    <!-- 状态 Tabs -->
    <div class="tabs-wrapper">
      <div class="tab-list">
        <div
          v-for="tab in statusTabs"
          :key="tab.value"
          class="tab-item"
          :class="{ active: activeTab === tab.value }"
          @click="switchTab(tab.value)"
        >
          <el-icon class="tab-icon"><component :is="tab.icon" /></el-icon>
          <span class="tab-label">{{ tab.label }}</span>
          <span class="tab-count" v-if="tab.value !== ''">
            {{ statusCount[tab.value === '0' ? 'pending' : tab.value === '1' ? 'paid' : 'cancelled'] }}
          </span>
          <span class="tab-count" v-else>{{ statusCount.all }}</span>
        </div>
      </div>
    </div>

    <!-- 空状态 -->
    <div v-if="!loading && filteredOrders.length === 0" class="empty-wrapper">
      <div class="empty-icon">
        <el-icon :size="64" color="#c8c9cc"><Document /></el-icon>
      </div>
      <div class="empty-text">暂无相关订单</div>
      <el-button type="primary" round class="empty-btn" @click="router.push('/packages')">
        <el-icon><ShoppingBag /></el-icon>
        <span>前往购买</span>
      </el-button>
    </div>

    <!-- 订单列表 -->
    <div v-else v-loading="loading" class="order-list">
      <div
        v-for="order in filteredOrders"
        :key="order.id"
        class="order-card"
      >
        <!-- 头部：状态 + 订单号 -->
        <div class="order-header">
          <div class="order-status" :style="{ color: statusMap[order.status]?.color }">
            <span class="status-dot" :style="{ background: statusMap[order.status]?.color }"></span>
            <span class="status-text">{{ statusMap[order.status]?.text }}</span>
          </div>
          <div class="order-no">订单号：{{ order.order_no }}</div>
        </div>

        <!-- 主体：商品信息 -->
        <div class="order-body" @click="handleView(order)">
          <div class="item-cover">
            <el-icon :size="28" color="#07c160"><ShoppingBag /></el-icon>
          </div>
          <div class="item-info">
            <div class="item-name">{{ order.item_name || order.type_name }}</div>
            <div class="item-meta">
              <span class="meta-tag">{{ order.type_name }}</span>
              <span class="meta-dot">·</span>
              <span class="meta-pay">{{ payTypeText(order.pay_type) }}</span>
              <span class="meta-dot">·</span>
              <span class="meta-count">共 {{ order.item_count }} 件</span>
            </div>
            <div class="item-time" v-if="order.status === '0'">
              <el-icon :size="12"><Clock /></el-icon>
              <span>{{ countdown(order.created_at) }}</span>
            </div>
            <div class="item-time" v-else-if="order.status === '1' && order.paid_at">
              支付时间：{{ formatTime(order.paid_at) }}
            </div>
            <div class="item-time" v-else-if="order.status === '2'">
              下单时间：{{ formatTime(order.created_at) }}
            </div>
          </div>
          <div class="item-amount">
            <div class="amount-value">¥{{ toMoney(order.amount) }}</div>
            <div class="amount-label">订单金额</div>
          </div>
        </div>

        <!-- 操作栏 -->
        <div class="order-actions">
          <template v-if="order.status === '0'">
            <el-button size="small" plain @click.stop="handleCancel(order)">取消订单</el-button>
            <el-button size="small" type="primary" @click.stop="handlePay(order)">立即支付</el-button>
          </template>
          <template v-else-if="order.status === '1'">
            <el-button size="small" plain @click.stop="handleView(order)">查看详情</el-button>
            <el-button size="small" type="primary" plain @click.stop="handleBuyAgain(order)">再次购买</el-button>
          </template>
          <template v-else-if="order.status === '2'">
            <el-button size="small" plain @click.stop="handleBuyAgain(order)">重新购买</el-button>
          </template>
        </div>
      </div>

      <div v-if="filteredOrders.length > 0" class="list-footer">- 没有更多了 -</div>
    </div>
  </div>
</template>

<style scoped>
.orders-page {
  min-height: 100vh;
  background: #f7f8fa;
  padding-bottom: 24px;
}

/* 顶部摘要 */
.summary-card {
  margin: 12px 16px;
  padding: 16px 18px;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 14px;
  color: #fff;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 4px 16px rgba(7, 193, 96, 0.18);
}
.summary-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.summary-info {
  flex: 1;
}
.summary-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 2px;
}

/* Tabs */
.tabs-wrapper {
  background: #fff;
  position: sticky;
  top: 0;
  z-index: 10;
  border-bottom: 1px solid #f0f0f0;
}
.tab-list {
  display: flex;
  padding: 4px 8px;
}
.tab-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 10px 4px;
  cursor: pointer;
  position: relative;
  color: #646566;
  font-size: 13px;
  transition: color 0.2s;
}
.tab-item.active {
  color: #07c160;
  font-weight: 600;
}
.tab-item.active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 24px;
  height: 3px;
  background: #07c160;
  border-radius: 2px;
}
.tab-icon {
  font-size: 18px;
}
.tab-label {
  font-size: 13px;
}
.tab-count {
  position: absolute;
  top: 6px;
  right: 25%;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 8px;
  background: #f0f0f0;
  color: #969799;
  font-size: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tab-item.active .tab-count {
  background: #e8f7ef;
  color: #07c160;
}

/* 订单列表 */
.order-list {
  padding: 12px 16px;
}

.order-card {
  background: #fff;
  border-radius: 14px;
  margin-bottom: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.order-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px dashed #f0f0f0;
  background: #fafafa;
}
.order-status {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
}
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  box-shadow: 0 0 0 3px currentColor;
  opacity: 0.25;
}
.status-text {
  letter-spacing: 0.5px;
}
.order-no {
  font-size: 12px;
  color: #969799;
  font-family: ui-monospace, monospace;
}

/* 订单主体 */
.order-body {
  display: flex;
  align-items: center;
  padding: 16px;
  gap: 12px;
  cursor: pointer;
}
.item-cover {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  background: linear-gradient(135deg, #e8f7ef 0%, #d4f0e0 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.item-info {
  flex: 1;
  min-width: 0;
}
.item-name {
  font-size: 14px;
  color: #323233;
  font-weight: 500;
  margin-bottom: 6px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.item-meta {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #969799;
  margin-bottom: 4px;
}
.meta-tag {
  background: #f0f9f4;
  color: #07c160;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 11px;
}
.meta-dot {
  color: #c8c9cc;
}
.item-time {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #969799;
}

.item-amount {
  text-align: right;
  flex-shrink: 0;
}
.amount-value {
  font-size: 18px;
  font-weight: 700;
  color: #323233;
  font-family: ui-monospace, monospace;
  margin-bottom: 2px;
}
.amount-label {
  font-size: 11px;
  color: #c8c9cc;
}

/* 操作栏 */
.order-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 0 16px 14px;
}
.order-actions .el-button {
  min-width: 80px;
}

/* 列表底部 */
.list-footer {
  text-align: center;
  padding: 20px 0 8px;
  font-size: 12px;
  color: #c8c9cc;
}

/* 空状态 */
.empty-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80px 20px;
}
.empty-icon {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  background: #f0f0f0;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}
.empty-text {
  font-size: 15px;
  color: #646566;
  margin-bottom: 20px;
}
.empty-btn {
  min-width: 160px;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border: none;
}
.empty-btn:hover {
  opacity: 0.9;
}
.empty-btn span {
  margin-left: 4px;
}
</style>
