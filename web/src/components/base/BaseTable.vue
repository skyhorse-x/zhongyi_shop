<script setup lang="ts">
interface Props {
  data: any[]
  loading?: boolean
  stripe?: boolean
  border?: boolean
  maxHeight?: string | number
  pagination?: {
    currentPage: number
    pageSize: number
    total: number
    pageSizes?: number[]
  }
}

const props = withDefaults(defineProps<Props>(), {
  stripe: true,
  border: true,
  loading: false,
})

const emit = defineEmits<{
  'page-change': [page: number]
  'size-change': [size: number]
}>()
</script>

<template>
  <div class="base-table">
    <el-table
      :data="data"
      :stripe="stripe"
      :border="border"
      :loading="loading"
      :max-height="maxHeight"
      v-bind="$attrs"
      style="width: 100%"
    >
      <slot />
      <template #empty>
        <el-empty :description="loading ? '加载中...' : '暂无数据'" />
      </template>
    </el-table>

    <div v-if="pagination" class="table-pagination">
      <el-pagination
        v-model:current-page="pagination.currentPage"
        v-model:page-size="pagination.pageSize"
        :page-sizes="pagination.pageSizes || [10, 20, 50, 100]"
        :total="pagination.total"
        layout="total, sizes, prev, pager, next, jumper"
        background
        small
        @current-change="emit('page-change', $event)"
        @size-change="emit('size-change', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.base-table { width: 100%; }
.table-pagination { display: flex; justify-content: flex-end; margin-top: 16px; }
</style>
