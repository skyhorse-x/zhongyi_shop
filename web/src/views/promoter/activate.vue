<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

// 注册时已自动开通推广员，直接跳转到推广中心
router.replace('/promoter')
</script>

<template>
  <div class="activate-page">
    <div class="loading-block">
      <div class="loading-text">正在跳转...</div>
    </div>
  </div>
</template>

<style scoped>
.activate-page {
  min-height: 100vh;
  background: #f7f8fa;
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
</style>
