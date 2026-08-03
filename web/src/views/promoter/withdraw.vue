<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { getAuthToken } from '@/utils/auth'

const router = useRouter()

const balance = ref(0)
const loading = ref(false)
const fetchingBalance = ref(false)

const form = ref({
  amount: '',
  withdrawType: 'wechat',
  payAccount: '',
})

const withdrawTypes = [
  { value: 'wechat', label: '微信' },
  { value: 'alipay', label: '支付宝' },
]

const canWithdraw = computed(() => {
  const amount = parseFloat(form.value.amount)
  return amount > 0 && amount <= balance.value && form.value.payAccount
})

// 加载推广员信息（获取可提现余额）
const loadPromoterInfo = async () => {
  fetchingBalance.value = true
  try {
    const res = await safeFetch('/api/v1/promoter/info', {
      headers: {
        'Authorization': `Bearer ${getAuthToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      balance.value = data.data.available_commission || 0
    } else {
      ElMessage.error(data.message || '加载推广信息失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载推广信息失败')
  } finally {
    fetchingBalance.value = false
  }
}

const handleWithdraw = async () => {
  if (!form.value.amount) {
    ElMessage.info('请输入提现金额')
    return
  }
  const amount = parseFloat(form.value.amount)
  if (amount <= 0) {
    ElMessage.info('提现金额必须大于0')
    return
  }
  if (amount > balance.value) {
    ElMessage.info('提现金额不能超过可提现余额')
    return
  }
  if (amount < 1) {
    ElMessage.info('最低提现金额为1元')
    return
  }
  if (!form.value.payAccount) {
    ElMessage.info('请输入收款账号')
    return
  }

  loading.value = true
  try {
    const res = await safeFetch('/api/v1/promoter/withdraw', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getAuthToken()}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        amount: amount,
        pay_type: form.value.withdrawType,
        pay_account: form.value.payAccount,
      }),
    })
    const data = await res.json()

    if (data.code === 0) {
      ElMessage.success('提现申请已提交，请等待审核')
      router.push('/promoter')
    } else {
      ElMessage.error(data.message || '提现失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '提现失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const setAllAmount = () => {
  form.value.amount = balance.value.toString()
}

onMounted(() => {
  loadPromoterInfo()
})
</script>

<template>
  <div class="withdraw-page">
    <div class="balance-card">
      <div class="balance-label">可提现佣金</div>
      <div class="balance-amount">¥{{ balance.toFixed(2) }}</div>
    </div>

    <el-form :model="form" label-width="auto" @submit.prevent="handleWithdraw">
      <div class="form-card">
        <el-form-item
          label="提现金额"
          :rules="[{ required: true, message: '请输入提现金额' }]"
        >
          <el-input
            v-model="form.amount"
            placeholder="请输入提现金额"
            type="number"
          >
            <template #suffix>
              <span class="all-btn" @click="setAllAmount">全部提现</span>
            </template>
          </el-input>
        </el-form-item>

        <el-form-item label="提现方式">
          <el-radio-group v-model="form.withdrawType">
            <el-radio
              v-for="item in withdrawTypes"
              :key="item.value"
              :value="item.value"
            >
              {{ item.label }}
            </el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item
          label="收款账号"
          :rules="[{ required: true, message: '请输入收款账号' }]"
        >
          <el-input
            v-model="form.payAccount"
            :placeholder="form.withdrawType === 'wechat' ? '请输入微信号' : '请输入支付宝账号'"
          />
        </el-form-item>
      </div>

      <div class="form-actions">
        <el-button
          round
          type="primary"
          native-type="submit"
          :loading="loading"
          :disabled="!canWithdraw"
          style="width: 100%"
        >
          确认提现
        </el-button>
      </div>
    </el-form>

    <div class="withdraw-tips">
      <div class="tips-title">提现说明</div>
      <div class="tips-item">1. 最低提现金额为1元</div>
      <div class="tips-item">2. 提现申请将在1-3个工作日内处理</div>
      <div class="tips-item">3. 提现金额将转入您选择的账户</div>
      <div class="tips-item">4. 如有疑问请联系客服</div>
    </div>
  </div>
</template>

<style scoped>
.withdraw-page {
  min-height: 100vh;
  background: #f7f8fa;
}

.balance-card {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  margin: 16px;
  border-radius: 16px;
  padding: 24px;
  text-align: center;
  color: #fff;
}

.balance-label {
  font-size: 14px;
  opacity: 0.85;
  margin-bottom: 8px;
}

.balance-amount {
  font-size: 36px;
  font-weight: bold;
}

.all-btn {
  color: #409eff;
  font-size: 14px;
  cursor: pointer;
}

.form-card {
  margin: 16px;
  padding: 16px;
  background: #fff;
  border-radius: 12px;
}

.form-actions {
  margin: 24px 16px;
}

.withdraw-tips {
  margin: 24px 16px;
  padding: 16px;
  background: #fff;
  border-radius: 12px;
}

.tips-title {
  font-size: 16px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 12px;
}

.tips-item {
  font-size: 14px;
  color: #646566;
  line-height: 2;
}
</style>
