<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

const goHome = () => {
  router.push('/')
}
</script>

<template>
  <div class="not-found-page">
    <div class="content">
      <el-empty description="抱歉，您访问的页面不存在">
        <el-button round type="primary" class="btn-home" @click="goHome">
          返回首页
        </el-button>
      </el-empty>
    </div>
  </div>
</template>

<style scoped>
.not-found-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background-color: #f7f8fa;
}

.content {
  width: 100%;
  padding: 0 20px;
}

.btn-home {
  width: 160px;
  margin-top: 20px;
}
</style>
