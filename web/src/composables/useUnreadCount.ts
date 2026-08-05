import { ref, onMounted, onUnmounted } from 'vue'
import { safeFetch } from '@/utils/fetch'
import { getToken } from '@/utils/auth'

const unreadCount = ref(0)
let timer: ReturnType<typeof setInterval> | null = null

// 获取未读消息总数（系统消息 + 客服消息）
const fetchUnreadCount = async () => {
  if (!getToken()) {
    unreadCount.value = 0
    return
  }

  try {
    // 获取系统消息未读数量
    const systemRes = await safeFetch('/api/v1/system-messages/unread-count', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const systemData = await systemRes.json()
    const systemUnread = systemData.code === 0 ? systemData.data.unread_count : 0

    // 获取客服消息未读数量
    const csRes = await safeFetch('/api/v1/customer-service/sessions', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const csData = await csRes.json()
    let csUnread = 0
    if (csData.code === 0 && csData.data?.data) {
      // 累加所有会话的用户未读数
      csData.data.data.forEach((session: any) => {
        csUnread += session.user_unread || 0
      })
    }

    unreadCount.value = systemUnread + csUnread
  } catch {
    // 获取失败时不更新
  }
}

// 启动定时刷新
const startPolling = () => {
  fetchUnreadCount()
  timer = setInterval(fetchUnreadCount, 30000) // 每30秒刷新一次
}

// 停止定时刷新
const stopPolling = () => {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

// 手动减少未读数量（用于本地操作后立即更新）
const decrementUnread = (count = 1) => {
  unreadCount.value = Math.max(0, unreadCount.value - count)
}

// 手动设置未读数量
const setUnreadCount = (count: number) => {
  unreadCount.value = Math.max(0, count)
}

export const useUnreadCount = () => {
  onMounted(() => {
    startPolling()
  })

  onUnmounted(() => {
    stopPolling()
  })

  return {
    unreadCount,
    fetchUnreadCount,
    decrementUnread,
    setUnreadCount,
  }
}

// 导出独立的响应式引用（供非组件使用）
export { unreadCount }
