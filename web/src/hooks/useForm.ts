import { ref, unref, type Ref } from 'vue'
import type { FormInstance } from 'element-plus'

export function useForm<T extends Record<string, any>>(initialData: T) {
  const formRef = ref<FormInstance>()
  const form = ref<T>({ ...initialData }) as Ref<T>
  const formLoading = ref(false)

  const resetForm = () => {
    form.value = { ...initialData }
    formRef.value?.resetFields()
  }

  const validateForm = async (): Promise<boolean> => {
    if (!formRef.value) return true
    try {
      await formRef.value.validate()
      return true
    } catch {
      return false
    }
  }

  const setFormValues = (values: Partial<T>) => {
    Object.assign(form.value, values)
  }

  return { formRef, form, formLoading, resetForm, validateForm, setFormValues }
}
