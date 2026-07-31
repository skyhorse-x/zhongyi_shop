import request from './request'

// 登录（支持账号或手机号）
export const login = (data: { account: string; password: string }) => {
  return request.post('/auth/login', data)
}

// 注册（手机号）
export const register = (data: {
  type?: 'mobile' | 'account'
  mobile?: string
  username?: string
  password: string
  password_confirmation: string
}) => {
  return request.post('/auth/register', data)
}

// 发送短信验证码
export const sendSmsCode = (data: { mobile: string; type: string }) => {
  return request.post('/auth/sms-code', data)
}

// 获取用户信息
export const getUserInfo = () => {
  return request.get('/user/info')
}

// 更新用户信息
export const updateUserInfo = (data: any) => {
  return request.put('/user/info', data)
}

// 退出登录
export const logout = () => {
  return request.post('/auth/logout')
}

// 微信登录
export const wechatLogin = (code: string) => {
  return request.post('/auth/wechat-login', { code })
}
