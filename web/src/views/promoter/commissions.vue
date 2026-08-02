<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'

const router = useRouter()

const activeTab = ref('all')
const loading = ref(false)

interface CommissionItem {
  id: number
  commission_no: string
  amount: number
  rate: number
  status: number
  created_at: string
  order_amount?: number
  user_name?: string
  user_mobile?: string
}

const commissionList = ref<CommissionItem[]>([])

// 状态映射：0=冻结中 1=已结算 2=已取消
const statusMap: Record<number, { label: string; class: string }> = {
  0: { label: '冻结中', class: 'frozen' },
  1: { label: '已结算', class: 'settled' },
  2: { label: '已取消', class: 'cancelled' },
}

const getToken = (): string => localStorage.getItem('token') || ''

const loadCommissions = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/promoter/commissions?limit=50', {
      headers: { Authorization: `Bearer ${getToken()}`, Accept: 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) {
      commissionList.value = (data.data?.data ?? data.data ?? []) as CommissionItem[]
    } else if (data.code === 404) {
      ElMessage.warning('请先开通推广员')
      router.push('/promoter/activate')
    } else {
      ElMessage.error(data.message || '加载佣金明细失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载佣金明细失败')
  } finally {
    loading.value = false
  }
}

const filteredList = computed(() => {
  if (activeTab.value === 'all') return commissionList.value
  const status = activeTab.value === 'settled' ? 1 : activeTab.value === 'frozen' ? 0 : 2
  return commissionList.value.filter((c) => c.status === status)
})

// 统计
const totalCommission = computed(() =>
  commissionList.value
    .filter((c) => c.status === 1)
    .reduce((sum, c) => sum + Number(c.amount || 0), 0)
    .toFixed(2)
)
const frozenCommission = computed(() =>
  commissionList.value
    .filter((c) => c.status === 0)
    .reduce((sum, c) => sum + Number(c.amount || 0), 0)
    .toFixed(2)
)

const formatTime = (t?: string) => {
  if (!t) return '-'
  return t.substring(0, 16).replace('T', ' ')
}

const maskMobile = (m?: string) => {
  if (!m) return ''
  return m.length === 11 ? m.slice(0, 3) + '****' + m.slice(-4) : m
}

onMounted(() => {
  loadCommissions()
})
</script>

<template>
  <div class="commissions-page">

    <!-- 顶部统计卡 -->
    <div class="stats-card">
      <div class="stats-row">
        <div class="stats-item">
          <div class="stats-value">¥{{ totalCommission }}</div>
          <div class="stats-label">累计已结算</div>
        </div>
        <div class="stats-divider"></div>
        <div class="stats-item">
          <div class="stats-value frozen-text">¥{{ frozenCommission }}</div>
          <div class="stats-label">冻结中</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tab-bar">
      <div
        v-for="tab in [
          { value: 'all', label: '全部' },
          { value: 'settled', label: '已结算' },
          { value: 'frozen', label: '冻结中' },
        ]"
        :key="tab.value"
        class="tab-item"
        :class="{ active: activeTab === tab.value }"
        @click="activeTab = tab.value"
      >
        {{ tab.label }}
      </div>
    </div>

    <!-- 列表 -->
    <div v-loading="loading" class="commission-list">
      <el-empty v-if="!loading && filteredList.length === 0" description="暂无佣金记录" />

      <div
        v-for="item in filteredList"
        :key="item.id"
        class="commission-card"
      >
        <div class="card-top">
          <div class="card-no">订单号：{{ item.commission_no }}</div>
          <div class="card-status" :class="statusMap[item.status]?.class">
            {{ statusMap[item.status]?.label }}
          </div>
        </div>

        <div class="card-mid">
          <div>
            <div class="mid-label">获得佣金</div>
            <div class="mid-amount">+¥{{ Number(item.amount).toFixed(2) }}</div>
          </div>
          <div class="mid-right">
            <div class="mid-rate">佣金比例 {{ item.rate }}%</div>
            <div class="mid-time">{{ formatTime(item.created_at) }}</div>
          </div>
        </div>

        <div class="card-bottom" v-if="item.user_mobile || item.user_name">
          <span>来自用户：{{ item.user_name || maskMobile(item.user_mobile) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.commissions-page {
  min-height: 100vh;
  background: #f7f8fa;
  padding-bottom: 24px;
}

/* 统计卡 */
.stats-card {
  margin: 12px 16px;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 14px;
  padding: 20px;
  color: #fff;
  box-shadow: 0 4px 16px rgba(7, 193, 96, 0.18);
}
.stats-row {
  display: flex;
  align-items: center;
}
.stats-item {
  flex: 1;
  text-align: center;
}
.stats-value {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 4px;
  font-family: ui-monospace, monospace;
}
.stats-value.frozen-text {
  opacity: 0.85;
}
.stats-label {
  font-size: 12px;
  opacity: 0.85;
}
.stats-divider {
  width: 1px;
  height: 32px;
  background: rgba(255, 255, 255, 0.3);
  margin: 0 12px;
}

/* Tabs */
.tab-bar {
  display: flex;
  background: #fff;
  border-bottom: 1px solid #f0f0f0;
  position: sticky;
  top: 0;
  z-index: 10;
}
.tab-item {
  flex: 1;
  text-align: center;
  padding: 12px 0;
  font-size: 14px;
  color: #646566;
  cursor: pointer;
  position: relative;
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
  width: 20px;
  height: 3px;
  background: #07c160;
  border-radius: 2px;
}

/* 列表 */
.commission-list {
  padding: 12px 16px;
}
.commission-card {
  background: #fff;
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 10px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px dashed #f0f0f0;
}
.card-no {
  font-size: 12px;
  color: #969799;
  font-family: ui-monospace, monospace;
}
.card-status {
  font-size: 12px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
}
.card-status.settled {
  color: #07c160;
  background: #e8f7ef;
}
.card-status.frozen {
  color: #ff976a;
  background: #fff3e0;
}
.card-status.cancelled {
  color: #969799;
  background: #f0f0f0;
}
.card-mid {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}
.mid-label {
  font-size: 12px;
  color: #969799;
  margin-bottom: 4px;
}
.mid-amount {
  font-size: 22px;
  font-weight: 700;
  color: #ee0a24;
  font-family: ui-monospace, monospace;
}
.mid-right {
  text-align: right;
}
.mid-rate {
  font-size: 12px;
  color: #646566;
  margin-bottom: 4px;
}
.mid-time {
  font-size: 11px;
  color: #969799;
}
.card-bottom {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #f5f5f5;
  font-size: 12px;
  color: #969799;
}
</style>
