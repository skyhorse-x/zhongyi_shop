<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'

const router = useRouter()
const loading = ref(false)

interface WithdrawItem {
  id: number
  withdraw_no: string
  amount: number
  pay_type: string
  pay_account: string
  status: number
  remark?: string
  audit_remark?: string
  audited_at?: string
  paid_at?: string
  created_at: string
}

// 状态：0=待审核 1=已通过 2=已拒绝
const statusMap: Record<number, { label: string; class: string; tag: string }> = {
  0: { label: '审核中', class: 'pending', tag: 'warning' },
  1: { label: '已通过', class: 'approved', tag: 'success' },
  2: { label: '已拒绝', class: 'rejected', tag: 'danger' },
}

const payTypeMap: Record<string, string> = {
  wechat: '微信钱包',
  alipay: '支付宝',
  bank: '银行卡',
}

const withdrawList = ref<WithdrawItem[]>([])

const getToken = (): string => localStorage.getItem('token') || ''

const loadWithdraws = async () => {
  loading.value = true
  try {
    const res = await fetch('/api/v1/promoter/withdraw-history?limit=50', {
      headers: { Authorization: `Bearer ${getToken()}`, Accept: 'application/json' },
    })
    const data = await res.json()
    if (data.code === 0) {
      withdrawList.value = (data.data?.data ?? data.data ?? []) as WithdrawItem[]
    } else if (data.code === 404) {
      ElMessage.warning('请先开通推广员')
      router.push('/promoter/activate')
    } else {
      ElMessage.error(data.message || '加载提现记录失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载提现记录失败')
  } finally {
    loading.value = false
  }
}

const formatTime = (t?: string) => {
  if (!t) return '-'
  return t.substring(0, 19).replace('T', ' ')
}

const maskAccount = (acc?: string) => {
  if (!acc) return '-'
  if (acc.length <= 6) return acc
  return acc.slice(0, 3) + '****' + acc.slice(-4)
}

onMounted(() => {
  loadWithdraws()
})
</script>

<template>
  <div class="withdraw-history-page">
    <!-- 空状态 -->
    <div v-if="!loading && withdrawList.length === 0" class="empty-wrapper">
      <div class="empty-icon">💸</div>
      <div class="empty-text">暂无提现记录</div>
      <el-button type="primary" round @click="router.push('/promoter/withdraw')">
        申请提现
      </el-button>
    </div>

    <!-- 列表 -->
    <div v-loading="loading" class="withdraw-list">
      <div
        v-for="item in withdrawList"
        :key="item.id"
        class="withdraw-card"
      >
        <div class="card-top">
          <div class="card-status" :class="statusMap[item.status]?.class">
            <span class="status-dot"></span>
            <span>{{ statusMap[item.status]?.label }}</span>
          </div>
          <div class="card-amount">¥{{ Number(item.amount).toFixed(2) }}</div>
        </div>

        <div class="card-info">
          <div class="info-row">
            <span class="info-label">提现单号</span>
            <span class="info-value mono">{{ item.withdraw_no }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">提现方式</span>
            <span class="info-value">
              {{ payTypeMap[item.pay_type] || item.pay_type }} · {{ maskAccount(item.pay_account) }}
            </span>
          </div>
          <div class="info-row">
            <span class="info-label">申请时间</span>
            <span class="info-value">{{ formatTime(item.created_at) }}</span>
          </div>
          <div class="info-row" v-if="item.audited_at">
            <span class="info-label">审核时间</span>
            <span class="info-value">{{ formatTime(item.audited_at) }}</span>
          </div>
          <div class="info-row" v-if="item.paid_at">
            <span class="info-label">到账时间</span>
            <span class="info-value">{{ formatTime(item.paid_at) }}</span>
          </div>
        </div>

        <div class="card-remark" v-if="item.audit_remark">
          <span class="remark-label">审核备注：</span>
          <span class="remark-text">{{ item.audit_remark }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.withdraw-history-page {
  min-height: 100vh;
  background: #f7f8fa;
  padding: 12px 16px 24px;
}

/* 列表 */
.withdraw-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.withdraw-card {
  background: #fff;
  border-radius: 12px;
  padding: 14px 16px;
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
.card-status {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
}
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.5;
}
.card-status.pending { color: #ff976a; }
.card-status.approved { color: #07c160; }
.card-status.rejected { color: #ee0a24; }

.card-amount {
  font-size: 20px;
  font-weight: 700;
  color: #323233;
  font-family: ui-monospace, monospace;
}

.card-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.info-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}
.info-label {
  color: #969799;
}
.info-value {
  color: #323233;
}
.info-value.mono {
  font-family: ui-monospace, monospace;
  font-size: 12px;
}

.card-remark {
  margin-top: 10px;
  padding: 8px 10px;
  background: #fff8e6;
  border-radius: 6px;
  font-size: 12px;
  color: #8a6d3b;
  border-left: 3px solid #ff976a;
}
.remark-label {
  font-weight: 600;
}

/* 空状态 */
.empty-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80px 20px;
}
.empty-icon {
  font-size: 64px;
  margin-bottom: 16px;
  opacity: 0.6;
}
.empty-text {
  font-size: 15px;
  color: #646566;
  margin-bottom: 20px;
}
</style>
