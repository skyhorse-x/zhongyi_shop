import dayjs from 'dayjs'
import { cloneDeep, debounce, throttle, omit, pick, isEmpty } from 'lodash-es'

export { cloneDeep, debounce, throttle, omit, pick, isEmpty, dayjs }

// 格式化日期
export function formatDate(date: string | Date | undefined, format = 'YYYY-MM-DD'): string {
  if (!date) return '-'
  return dayjs(date).format(format)
}

// 格式化日期时间
export function formatDateTime(date: string | Date | undefined): string {
  return formatDate(date, 'YYYY-MM-DD HH:mm:ss')
}

// 格式化金额
export function formatMoney(amount: number | string, prefix = '¥'): string {
  const num = Number(amount)
  if (isNaN(num)) return `${prefix}0.00`
  return `${prefix}${num.toFixed(2)}`
}

// 脱敏手机号
export function maskMobile(mobile: string): string {
  if (!mobile || mobile.length < 7) return mobile
  return mobile.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2')
}

// 延迟
export function sleep(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms))
}

// 树形数据转换
export function buildTree(data: any[], idKey = 'id', parentKey = 'parent_id', childrenKey = 'children'): any[] {
  const map = new Map()
  data.forEach(item => map.set(item[idKey], { ...item, [childrenKey]: [] }))
  const tree: any[] = []
  data.forEach(item => {
    if (item[parentKey] && map.has(item[parentKey])) {
      map.get(item[parentKey])[childrenKey].push(map.get(item[idKey]))
    } else {
      tree.push(map.get(item[idKey]))
    }
  })
  return tree
}
