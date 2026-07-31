import { ref, watch } from 'vue'
import { useDark, useToggle } from '@vueuse/core'
import { appConfig } from '@/config'

export function useTheme() {
  const isDark = useDark({
    storageKey: appConfig.themeKey,
  })
  
  const toggleDark = useToggle(isDark)
  
  const currentTheme = ref(isDark.value ? 'dark' : 'light')
  
  watch(isDark, (val) => {
    currentTheme.value = val ? 'dark' : 'light'
    document.documentElement.classList.toggle('dark', val)
  })

  return { isDark, toggleDark, currentTheme }
}
