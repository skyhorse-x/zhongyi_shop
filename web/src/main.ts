import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'
import App from './App.vue'
import router from './router'
import { setupPermissionGuard } from '@/permission'
import '@/styles/index.css'

// 仅 dev: 允许通过 ?_dev_token= 注入 token（E2E 测试用）
if (import.meta.env.DEV) {
  try {
    const url = new URL(location.href)
    const devToken = url.searchParams.get('_dev_token')
    console.log('[E2E] main.ts DEV block, _dev_token=', devToken)
    if (devToken) {
      localStorage.setItem('token', devToken)
      localStorage.setItem('user_info', JSON.stringify({
        id: 5, mobile: '13947427806', nickname: 'test',
        balance: 100, analysis_times: 5, is_promoter: 1
      }))
      url.searchParams.delete('_dev_token')
      history.replaceState({}, '', url.pathname + url.search + url.hash)
      console.log('[E2E] token injected:', devToken.substring(0, 20) + '...')
    } else if (!localStorage.getItem('token') && url.searchParams.get('_dev_auto') === '1') {
      // E2E 自动登录模式：没有 token 时自动调登录接口
      console.log('[E2E] auto login...')
      fetch('/api/v1/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ account: '13947427806', password: '123456' }),
      })
        .then((r) => r.json())
        .then((j) => {
          if (j.code === 0 && j.data?.token) {
            localStorage.setItem('token', j.data.token)
            localStorage.setItem('user_info', JSON.stringify(j.data.user))
            console.log('[E2E] auto login ok:', j.data.token.substring(0, 15) + '...')
            // 重新触发当前路由
            location.reload()
          } else {
            console.error('[E2E] auto login fail:', j)
          }
        })
        .catch((e) => console.error('[E2E] auto login err:', e))
    } else {
      // DEBUG: 报告当前 localStorage token 状态
      const t = localStorage.getItem('token')
      console.log('[E2E] current token in localStorage:', t ? t.substring(0, 15) + '...' : 'EMPTY')
      ;(window as any).__e2eToken = t
      ;(window as any).__e2eTokenCheck = new Date().toISOString()
    }
    const devAdminToken = url.searchParams.get('_dev_admin_token')
    if (devAdminToken) {
      localStorage.setItem('admin_token', devAdminToken)
      url.searchParams.delete('_dev_admin_token')
      history.replaceState({}, '', url.pathname + url.search + url.hash)
      console.log('[E2E] admin_token injected')
    }
  } catch (e) {
    console.error('[E2E] dev token inject error:', e)
  }
}

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(ElementPlus)

// 注册所有图标
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component)
}

// 设置权限守卫
setupPermissionGuard()

app.mount('#app')
