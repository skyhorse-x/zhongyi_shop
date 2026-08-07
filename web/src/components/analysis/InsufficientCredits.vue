<script setup lang="ts">
interface Props {
  /** 所需积分数量 */
  credits?: number
  /** 当前剩余积分 */
  currentCredits?: number
}

withDefaults(defineProps<Props>(), {
  credits: 1,
  currentCredits: 0,
})
</script>

<template>
  <div class="locked-overlay">
    <div class="locked-content">
      <div class="locked-icon">🔒</div>
      <div class="locked-title">积分不足，无法解锁</div>
      <div class="locked-desc">
        本次分析需要 {{ credits }} 积分<br />
        请充值后解锁
      </div>
      <el-button round type="primary" class="unlock-btn" @click="$router.push('/recharge')">
        去充值解锁
      </el-button>
    </div>
  </div>
</template>

<style scoped>
.locked-overlay {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-radius: 16px;
  padding: 32px 24px;
  margin: 24px 0;
  text-align: center;
  border: 2px dashed #dcdee0;
}

.locked-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.locked-title {
  font-size: 18px;
  font-weight: 600;
  color: #323233;
  margin-bottom: 12px;
}

.locked-desc {
  font-size: 14px;
  color: #969799;
  line-height: 1.8;
  margin-bottom: 24px;
}

.unlock-btn {
  padding: 10px 32px;
}
</style>
