<script setup lang="ts">
interface Props {
  visible: boolean
  title: string
  width?: string
  confirmText?: string
  cancelText?: string
  confirmLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  width: '600px',
  confirmText: '确认',
  cancelText: '取消',
  confirmLoading: false,
})

const emit = defineEmits<{
  'update:visible': [value: boolean]
  confirm: []
  cancel: []
}>()

const onCancel = () => {
  emit('update:visible', false)
  emit('cancel')
}
</script>

<template>
  <el-dialog
    :model-value="visible"
    :title="title"
    :width="width"
    :close-on-click-modal="false"
    @update:model-value="emit('update:visible', $event)"
  >
    <slot />
    <template #footer>
      <slot name="footer">
        <el-button @click="onCancel">{{ cancelText }}</el-button>
        <el-button type="primary" :loading="confirmLoading" @click="emit('confirm')">
          {{ confirmText }}
        </el-button>
      </slot>
    </template>
  </el-dialog>
</template>
