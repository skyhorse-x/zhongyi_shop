<script setup lang="ts">
import type { SearchField } from '@/types'

interface Props {
  fields: SearchField[]
  model: Record<string, any>
}

defineProps<Props>()
const emit = defineEmits<{ search: []; reset: [] }>()
</script>

<template>
  <div class="base-search">
    <el-form :model="model" inline>
      <el-row :gutter="16">
        <el-col v-for="field in fields" :key="field.prop" :span="field.span || 6">
          <el-form-item :label="field.label">
            <el-input
              v-if="field.type === 'input'"
              v-model="model[field.prop]"
              :placeholder="field.placeholder || `请输入${field.label}`"
              clearable
              style="width: 100%"
            />
            <el-select
              v-else-if="field.type === 'select'"
              v-model="model[field.prop]"
              :placeholder="field.placeholder || '全部'"
              clearable
              style="width: 100%"
            >
              <el-option label="全部" value="" />
              <el-option v-for="opt in field.options" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="6">
          <el-form-item>
            <el-button type="primary" @click="emit('search')">搜索</el-button>
            <el-button @click="emit('reset')">重置</el-button>
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>
  </div>
</template>

<style scoped>
.base-search {
  padding: 16px;
  background: #fff;
  border-radius: 6px;
  margin-bottom: 16px;
}
</style>
