<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'

const router = useRouter()
const loading = ref(false)
const checking = ref(true)
const isPromoter = ref(false)

const getToken = (): string => localStorage.getItem('token') || ''

// 检查是否已是推广员
const checkPromoter = async () => {
  checking.value = true
  try {
    const res = await safeFetch('/api/v1/promoter/info', {
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      isPromoter.value = true
    }
  } catch (e) {
    // 静默失败
  } finally {
    checking.value = false
  }
}

// 直接调用开通接口（幂等：已开通也会返回成功）
const handleActivate = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/promoter/activate', {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${getToken()}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({}),
    })
    const data = await res.json()

    if (data.code === 0) {
      ElMessage.success('您已成为推广员')
      router.push('/promoter')
    } else {
      ElMessage.error(data.message || '激活失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '激活失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const goToPromoter = () => {
  router.push('/promoter')
}

onMounted(() => {
  checkPromoter()
})
</script>

<template>
  <div class="activate-page">
    <div class="banner">
      <div class="banner-title">推广员中心</div>
      <div class="banner-desc">分享健康，赚取佣金</div>
    </div>

    <!-- 已是推广员 -->
    <div v-if="!checking && isPromoter" class="activated-card">
      <div class="activated-icon">✓</div>
      <div class="activated-title">您已是推广员</div>
      <div class="activated-desc">注册即自动开通，无需审核</div>
      <el-button
        type="primary"
        round
        class="activated-btn"
        @click="goToPromoter"
      >
        进入推广中心
      </el-button>
    </div>

    <!-- 未开通（兼容历史数据） -->
    <div v-else-if="!checking && !isPromoter" class="form-card">
      <div class="form-tip">完善信息即可激活</div>
      <div class="form-actions">
        <el-button
          round
          type="primary"
          :loading="loading"
          class="activate-btn"
          @click="handleActivate"
        >
          一键激活推广员
        </el-button>
      </div>
    </div>

    <div v-else class="loading-block">
      <div class="loading-text">加载中...</div>
    </div>

    <div class="tips">
      <div class="tips-title">推广员权益</div>
      <div class="tips-item">1. 专属推广链接和二维码</div>
      <div class="tips-item">2. 推广订单可获得高额佣金</div>
      <div class="tips-item">3. 佣金可随时提现至微信/支付宝</div>
    </div>
  </div>
</template>

<style scoped>
.activate-page {
  min-height: 100vh;
  background: #f7f8fa;
}

.banner {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  padding: 32px 24px;
  text-align: center;
  color: #fff;
}

.banner-title {
  font-size: 22px;
  font-weight: bold;
  margin-bottom: 8px;
}

.banner-desc {
  font-size: 14px;
  opacity: 0.85;
}

/* 已激活卡片 */
.activated-card {
  margin: 24px 16px;
  padding: 32px 24px;
  background: #fff;
  border-radius: 16px;
  text-align: center;
  box-shadow: 0 4px 16px rgba(7, 193, 96, 0.08);
}

.activated-icon {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  color: #fff;
  font-size: 40px;
  font-weight: bold;
  line-height: 72px;
  margin: 0 auto 16px;
}

.activated-title {
  font-size: 20px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 8px;
}

.activated-desc {
  font-size: 14px;
  color: #969799;
  margin-bottom: 24px;
}

.activated-btn {
  width: 100%;
  height: 48px;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border: none;
  font-size: 16px;
}

/* 未激活卡片（兼容） */
.form-card {
  margin: 16px;
  padding: 24px 16px;
  background: #fff;
  border-radius: 12px;
}

.form-tip {
  font-size: 14px;
  color: #646566;
  text-align: center;
  margin-bottom: 20px;
}

.form-actions {
  margin-top: 8px;
}

.activate-btn {
  width: 100%;
  height: 48px;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border: none;
  font-size: 16px;
}

.loading-block {
  margin: 16px;
  padding: 48px 16px;
  text-align: center;
  background: #fff;
  border-radius: 12px;
}

.loading-text {
  color: #969799;
  font-size: 14px;
}

.tips {
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
