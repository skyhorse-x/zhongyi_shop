/** 通用分页参数 */
export interface PaginationParams {
  page: number
  pageSize: number
}

/** 通用分页响应 */
export interface PaginatedResponse<T> {
  list: T[]
  total: number
  page: number
  pageSize: number
}

/** 通用 API 响应 */
export interface ApiResponse<T = any> {
  code: number
  message: string
  data: T
}

/** 搜索表单字段定义 */
export interface SearchField {
  prop: string
  label: string
  type: 'input' | 'select'
  span?: number
  placeholder?: string
  options?: { label: string; value: string | number }[]
}

/** 菜单项 */
export interface MenuItem {
  title: string
  icon: string
  path: string
}

/** 用户信息 */
export interface UserInfo {
  id: number
  username: string
  nickname: string
  mobile: string
  avatar: string
  gender: number
  status: number
  created_at: string
}

/** 订单 */
export interface Order {
  id: number
  order_no: string
  amount: string
  pay_method: string
  status: number
  pay_time: string
  product_name: string
  created_at: string
}
