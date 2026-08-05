import { ref, onMounted, onUnmounted } from 'vue'

export interface NotificationOptions {
  body?: string
  icon?: string
  tag?: string
  data?: any
  requireInteraction?: boolean
  silent?: boolean
}

export function useNotification() {
  const permission = ref<NotificationPermission>('default')
  const isSupported = ref(false)
  const lastNotification = ref<Notification | null>(null)

  // 检查浏览器是否支持 Notification API
  onMounted(() => {
    isSupported.value = 'Notification' in window
    if (isSupported.value) {
      permission.value = Notification.permission
    }
  })

  // 请求通知权限
  async function requestPermission(): Promise<boolean> {
    if (!isSupported.value) {
      console.warn('浏览器不支持 Notification API')
      return false
    }

    if (permission.value === 'granted') {
      return true
    }

    if (permission.value === 'denied') {
      console.warn('通知权限已被拒绝，请在浏览器设置中手动开启')
      return false
    }

    try {
      const result = await Notification.requestPermission()
      permission.value = result
      return result === 'granted'
    } catch (error) {
      console.error('请求通知权限失败:', error)
      return false
    }
  }

  // 显示通知
  async function showNotification(title: string, options?: NotificationOptions): Promise<Notification | null> {
    // 确保有权限
    if (permission.value !== 'granted') {
      const granted = await requestPermission()
      if (!granted) {
        return null
      }
    }

    // 如果页面活跃且获得焦点，可以选择不显示通知
    // 但为了客服响应及时性，仍然显示

    try {
      const notification = new Notification(title, {
        body: options?.body || '',
        icon: options?.icon || '/favicon.ico',
        tag: options?.tag,
        data: options?.data,
        requireInteraction: options?.requireInteraction ?? false,
        silent: options?.silent ?? false,
      })

      lastNotification.value = notification

      // 点击通知
      notification.onclick = (event) => {
        event.preventDefault()
        // 聚焦到窗口
        window.focus()
        // 关闭通知
        notification.close()
        // 触发自定义回调
        if (options?.data?.onClick) {
          options.data.onClick(options.data)
        }
      }

      // 通知显示
      notification.onshow = () => {
        // 自动关闭（除非 requireInteraction 为 true）
        if (!options?.requireInteraction) {
          setTimeout(() => {
            notification.close()
          }, 5000)
        }
      }

      // 通知错误
      notification.onerror = (error) => {
        console.error('通知显示错误:', error)
      }

      return notification
    } catch (error) {
      console.error('创建通知失败:', error)
      return null
    }
  }

  // 关闭所有通知
  function closeAllNotifications() {
    // Notification API 没有直接关闭所有通知的方法
    // 但我们可以通过 tag 来管理
    if (lastNotification.value) {
      lastNotification.value.close()
    }
  }

  // 显示新消息通知
  function showNewMessageNotification(data: {
    senderName: string
    content: string
    sessionId: string
    sessionNo: string
    onClick?: (data: any) => void
  }) {
    return showNotification(`新消息 - ${data.senderName}`, {
      body: data.content.length > 100 ? data.content.substring(0, 100) + '...' : data.content,
      tag: `chat-${data.sessionNo}`,
      data: {
        type: 'chat_message',
        sessionNo: data.sessionNo,
        sessionId: data.sessionId,
        onClick: data.onClick,
      },
      requireInteraction: false,
    })
  }

  // 显示会话通知
  function showSessionNotification(data: {
    title: string
    body: string
    sessionNo: string
    onClick?: (data: any) => void
  }) {
    return showNotification(data.title, {
      body: data.body,
      tag: `session-${data.sessionNo}`,
      data: {
        type: 'new_session',
        sessionNo: data.sessionNo,
        onClick: data.onClick,
      },
      requireInteraction: true,
    })
  }

  return {
    isSupported,
    permission,
    requestPermission,
    showNotification,
    showNewMessageNotification,
    showSessionNotification,
    closeAllNotifications,
  }
}
