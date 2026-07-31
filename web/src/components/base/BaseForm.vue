<script setup lang="ts">
import { ref } from 'vue'

interface Props {
  model: Record<string, any>
  labelWidth?: string
  inline?: boolean
}

withDefaults(defineProps<Props>(), {
  labelWidth: 'auto',
  inline: true,
})

const emit = defineEmits<{
  search: []
  reset: []
}>()

const formRef = ref()
defineExpose({ formRef })
</script>

<template>
  <div class="base-form-wrapper">
    <el-form
      ref="formRef"
      :model="model"
      :label-width="labelWidth"
      :inline="inline"
      v-bind="$attrs"
    >
      <slot />
      <el-form-item>
        <slot name="actions">
          <el-button type="primary" @click="emit('search')">搜索</el-button>
          <el-button @click="emit('reset')">重置</el-button>
        </slot>
      </el-form-item>
    </el-form>
  </div>
</template>

<style scoped>
.base-form-wrapper {
  padding: 16px;
  background: #fff;
  border-radius: 6px;
  margin-bottom: 16px;
}
</style>
