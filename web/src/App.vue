<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { useTheme } from '@/hooks'
import MiniProgramLayout from '@/layouts/MiniProgramLayout.vue'

// 初始化主题
useTheme()

const route = useRoute()

// 判断是否为管理后台页面
const isAdminPage = computed(() => route.path.startsWith('/admin'))
</script>

<template>
  <!-- 管理后台使用原始路由 -->
  <router-view v-if="isAdminPage" />

  <!-- 用户端使用小程序风格布局 -->
  <MiniProgramLayout v-else>
    <router-view />
  </MiniProgramLayout>
</template>
