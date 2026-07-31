<script setup lang="ts">
interface Props {
  currentPage: number
  pageSize: number
  total: number
  pageSizes?: number[]
}

withDefaults(defineProps<Props>(), {
  pageSizes: () => [10, 20, 50, 100],
})

const emit = defineEmits<{
  'update:currentPage': [v: number]
  'update:pageSize': [v: number]
  'change': [page: number, size: number]
}>()

const onCurrentChange = (page: number) => {
  emit('update:currentPage', page)
  emit('change', page, 0)
}
const onSizeChange = (size: number) => {
  emit('update:pageSize', size)
  emit('change', 0, size)
}
</script>

<template>
  <div class="base-pagination">
    <el-pagination
      :model-value="currentPage"
      :page-size="pageSize"
      :page-sizes="pageSizes"
      :total="total"
      layout="total, sizes, prev, pager, next, jumper"
      background
      small
      @update:model-value="onCurrentChange"
      @update:page-size="onSizeChange"
    />
  </div>
</template>

<style scoped>
.base-pagination {
  display: flex;
  justify-content: flex-end;
  padding: 16px 0;
}
</style>
