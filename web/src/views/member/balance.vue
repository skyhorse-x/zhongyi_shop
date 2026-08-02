<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { safeFetch } from '@/utils/fetch'
import { useRouter } from 'vue-router'
import { ArrowLeft } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const userStore = useUserStore()

const balance = ref(0)
const logs = ref<any[]>([])
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(15)
const total = ref(0)

const load = async () => {
  loading.value = true
  try {
    const res = await safeFetch(
      `/api/v1/user/balance-logs?page=${currentPage.value}&per_page=${pageSize.value}`,
      {
        headers: {
          Authorization: `Bearer ${userStore.token}`,
          Accept: 'application/json',
        },
      }
    )
    const data = await res.json()
    if (data.code === 0) {
      balance.value = data.data.balance ?? 0
      logs.value = data.data.logs?.data ?? []
      total.value = data.data.logs?.total ?? logs.value.length
    }
  } finally {
    loading.value = false
  }
}

const handlePage = (p: number) => {
  currentPage.value = p
  load()
}

const typeName = (t: string) =>
  ({
    recharge: '后台充值',
    consume: '消费扣减',
    refund: '退款返还',
    reward: '系统奖励',
    admin_deduct: '后台扣减',
  }[t] || t)

const typeColor = (t: string) => {
  if (t === 'recharge' || t === 'refund' || t === 'reward') return '#07c160'
  return '#fa5151'
}

const format = (n: any) => Number(n ?? 0).toFixed(2)
const formatTime = (s: string) => (s ? s.replace('T', ' ').slice(0, 19) : '-')

onMounted(load)
</script>

<template>
  <div class="balance-page">
    <div class="page-header">
      <el-icon class="back-icon" @click="router.back()"><ArrowLeft /></el-icon>
      <span class="page-title">余额明细</span>
    </div>

    <div class="balance-card">
      <div class="balance-label">当前余额（元）</div>
      <div class="balance-value">¥{{ format(balance) }}</div>
    </div>

    <div class="logs-section">
      <div class="section-title">变动记录</div>
      <el-empty v-if="!loading && logs.length === 0" description="暂无记录" :image-size="80" />
      <div v-else class="logs-list">
        <div v-for="log in logs" :key="log.id" class="log-item">
          <div class="log-top">
            <span class="log-type" :style="{ color: typeColor(log.type) }">
              {{ typeName(log.type) }}
            </span>
            <span class="log-change" :style="{ color: typeColor(log.type) }">
              {{ Number(log.change) > 0 ? '+' : '' }}{{ format(log.change) }}
            </span>
          </div>
          <div class="log-bottom">
            <span class="log-time">{{ formatTime(log.created_at) }}</span>
            <span class="log-remark">{{ log.remark || '-' }}</span>
          </div>
          <div class="log-meta">
            余额：¥{{ format(log.before) }} → ¥{{ format(log.after) }}
          </div>
        </div>
      </div>

      <el-pagination
        v-if="total > pageSize"
        v-model:current-page="currentPage"
        :page-size="pageSize"
        :total="total"
        layout="prev, pager, next"
        background
        @current-change="handlePage"
        class="pagination"
      />
    </div>
  </div>
</template>

<style scoped>
.balance-page {
  padding: 0 12px 16px;
  min-height: 100vh;
  background: #f5f6f8;
}

.page-header {
  display: flex;
  align-items: center;
  padding: 14px 0;
  position: sticky;
  top: 0;
  background: #f5f6f8;
  z-index: 10;
}

.back-icon {
  font-size: 20px;
  color: #323233;
  cursor: pointer;
  padding: 4px;
}

.page-title {
  font-size: 16px;
  font-weight: 600;
  margin-left: 8px;
}

.balance-card {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 16px;
  padding: 28px 20px;
  color: #fff;
  margin-bottom: 16px;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.balance-card::before {
  content: '';
  position: absolute;
  top: -30px;
  right: -30px;
  width: 120px;
  height: 120px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
}

.balance-label {
  font-size: 13px;
  opacity: 0.85;
  margin-bottom: 6px;
  position: relative;
}

.balance-value {
  font-size: 36px;
  font-weight: 700;
  position: relative;
}

.logs-section {
  background: #fff;
  border-radius: 12px;
  padding: 12px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #323233;
  padding: 6px 4px 12px;
  border-bottom: 0.5px solid #f5f5f5;
  margin-bottom: 8px;
}

.logs-list {
  display: flex;
  flex-direction: column;
}

.log-item {
  padding: 12px 4px;
  border-bottom: 0.5px solid #f5f5f5;
}

.log-item:last-child {
  border-bottom: none;
}

.log-top {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 4px;
}

.log-type {
  font-size: 14px;
  font-weight: 600;
}

.log-change {
  font-size: 16px;
  font-weight: 700;
}

.log-bottom {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #969799;
  margin-bottom: 4px;
}

.log-remark {
  max-width: 60%;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.log-meta {
  font-size: 11px;
  color: #c8c9cc;
}

.pagination {
  margin-top: 12px;
  justify-content: center;
}
</style>
