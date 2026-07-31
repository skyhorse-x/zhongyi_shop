<script setup lang="ts">
interface Props {
  title?: string
  padding?: string
}

withDefaults(defineProps<Props>(), {
  padding: '20px',
})
</script>

<template>
  <div class="base-card" :style="{ padding }">
    <div v-if="title" class="base-card-header">
      <span class="base-card-title">{{ title }}</span>
      <slot name="extra" />
    </div>
    <slot />
  </div>
</template>

<style scoped>
.base-card {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  margin-bottom: 16px;
}
.base-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 16px;
  border-bottom: 1px solid #f0f0f0;
  margin-bottom: 16px;
}
.base-card-title {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
}
</style>
