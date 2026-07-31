<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { UserFilled, Lock } from '@element-plus/icons-vue'

const router = useRouter()

const form = reactive({
  username: '',
  password: '',
})

const rules = {
  username: [{ required: true, message: '请输入管理员账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
}

const loading = ref(false)

// 页面加载时给 body 和 #app 添加 admin 类，避免用户端样式污染
onMounted(() => {
  document.body.classList.add('admin-page')
  const appEl = document.getElementById('app')
  if (appEl) appEl.classList.add('admin-app')
})

// 离开页面时清理 admin 类，防止样式污染其他页面
onUnmounted(() => {
  document.body.classList.remove('admin-page')
  const appEl = document.getElementById('app')
  if (appEl) appEl.classList.remove('admin-app')
})

const handleLogin = async () => {
  if (!form.username) {
    ElMessage.warning('请输入管理员账号')
    return
  }
  if (!form.password) {
    ElMessage.warning('请输入密码')
    return
  }

  loading.value = true
  try {
    const res = await fetch('/api/v1/admin/auth/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(form),
    })
    const data = await res.json()

    if (data.code === 0) {
      localStorage.setItem('admin_token', data.data.token)
      ElMessage.success('登录成功')
      router.push('/admin/dashboard')
    } else {
      ElMessage.error(data.message || '登录失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '登录失败')
  } finally {
    loading.value = false
  }
}

const goUserLogin = () => {
  router.push('/auth/login')
}
</script>

<template>
  <div class="admin-login-page">
    <!-- 背景装饰元素 -->
    <div class="bg-decoration">
      <div class="bg-circle bg-circle-1"></div>
      <div class="bg-circle bg-circle-2"></div>
      <div class="bg-circle bg-circle-3"></div>
      <div class="bg-pattern"></div>
    </div>

    <div class="admin-login-container">
      <!-- 左侧品牌区域 -->
      <div class="brand-section">
        <div class="brand-content">
          <div class="logo-wrapper">
            <div class="logo-icon">
              <span class="logo-emoji">⚕</span>
              <div class="logo-glow"></div>
            </div>
          </div>
          <h1 class="brand-title">中医健康管理平台</h1>
          <p class="brand-subtitle">AI 驱动 · 传承千年智慧</p>
          <div class="brand-features">
            <div class="feature-item">
              <span class="feature-icon">🌿</span>
              <span>智能体质辨识</span>
            </div>
            <div class="feature-item">
              <span class="feature-icon">📊</span>
              <span>健康数据分析</span>
            </div>
            <div class="feature-item">
              <span class="feature-icon">💊</span>
              <span>个性化调理方案</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 右侧登录区域 -->
      <div class="login-section">
        <div class="login-header">
          <h2 class="login-title">管理员登录</h2>
          <p class="login-desc">请输入您的管理员账号和密码</p>
        </div>

        <el-form :model="form" :rules="rules" label-width="auto" @submit.prevent="handleLogin" class="login-form">
          <el-form-item prop="username">
            <el-input
              v-model="form.username"
              placeholder="请输入管理员账号"
              size="large"
              clearable
            >
              <template #prefix>
                <el-icon class="input-icon"><UserFilled /></el-icon>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item prop="password">
            <el-input
              v-model="form.password"
              type="password"
              placeholder="请输入密码"
              size="large"
              clearable
              show-password
            >
              <template #prefix>
                <el-icon class="input-icon"><Lock /></el-icon>
              </template>
            </el-input>
          </el-form-item>

          <div class="form-options">
            <el-checkbox>记住我</el-checkbox>
            <a href="javascript:;" class="forgot-link">忘记密码？</a>
          </div>

          <div class="form-actions">
            <el-button
              round
              block
              type="primary"
              size="large"
              native-type="submit"
              :loading="loading"
              class="login-button"
            >
              <span v-if="!loading">登 录</span>
              <span v-else>登录中...</span>
            </el-button>
          </div>
        </el-form>

        <div class="login-footer">
          <div class="footer-divider">
            <span>或</span>
          </div>
          <button class="back-user-btn" @click="goUserLogin">
            <span class="back-icon">←</span>
            返回用户端
          </button>
        </div>

        <div class="admin-tips">
          <el-icon class="tips-icon"><Lock /></el-icon>
          <span>仅限授权管理员访问，所有操作将被记录</span>
        </div>
      </div>
    </div>

    <!-- 底部版权 -->
    <div class="copyright">
      <span>© 2024 AI中医健康管理平台 · 保留所有权利</span>
    </div>
  </div>
</template>

<style scoped>
.admin-login-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  position: relative;
  overflow: hidden;
}

/* 背景装饰 */
.bg-decoration {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  pointer-events: none;
  overflow: hidden;
}

.bg-circle {
  position: absolute;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
}

.bg-circle-1 {
  width: 600px;
  height: 600px;
  top: -200px;
  right: -100px;
  animation: float 8s ease-in-out infinite;
}

.bg-circle-2 {
  width: 400px;
  height: 400px;
  bottom: -100px;
  left: -100px;
  animation: float 10s ease-in-out infinite reverse;
}

.bg-circle-3 {
  width: 300px;
  height: 300px;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  animation: pulse 6s ease-in-out infinite;
}

.bg-pattern {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

@keyframes float {
  0%, 100% { transform: translateY(0) rotate(0deg); }
  50% { transform: translateY(-20px) rotate(5deg); }
}

@keyframes pulse {
  0%, 100% { opacity: 0.3; transform: translate(-50%, -50%) scale(1); }
  50% { opacity: 0.6; transform: translate(-50%, -50%) scale(1.1); }
}

/* 登录容器 */
.admin-login-container {
  width: 100%;
  max-width: 960px;
  min-height: 560px;
  background: rgba(255, 255, 255, 0.98);
  border-radius: 24px;
  display: flex;
  box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
  position: relative;
  z-index: 1;
  overflow: hidden;
}

/* 品牌区域 */
.brand-section {
  flex: 1;
  background: linear-gradient(135deg, #1a5f3f 0%, #2d8f5e 50%, #3cb371 100%);
  padding: 60px 48px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.brand-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
  opacity: 0.5;
}

.brand-content {
  position: relative;
  z-index: 1;
  text-align: center;
}

.logo-wrapper {
  margin-bottom: 32px;
}

.logo-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100px;
  height: 100px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 50%;
  position: relative;
  backdrop-filter: blur(10px);
  border: 2px solid rgba(255, 255, 255, 0.2);
}

.logo-emoji {
  font-size: 48px;
  position: relative;
  z-index: 1;
}

.logo-glow {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 80px;
  height: 80px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
  border-radius: 50%;
  animation: glow 3s ease-in-out infinite;
}

@keyframes glow {
  0%, 100% { opacity: 0.5; transform: translate(-50%, -50%) scale(1); }
  50% { opacity: 1; transform: translate(-50%, -50%) scale(1.2); }
}

.brand-title {
  font-size: 28px;
  font-weight: 700;
  color: #fff;
  margin: 0 0 12px;
  letter-spacing: 2px;
}

.brand-subtitle {
  font-size: 15px;
  color: rgba(255, 255, 255, 0.85);
  margin: 0 0 40px;
  letter-spacing: 1px;
}

.brand-features {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  backdrop-filter: blur(5px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  transition: all 0.3s ease;
}

.feature-item:hover {
  background: rgba(255, 255, 255, 0.18);
  transform: translateX(5px);
}

.feature-icon {
  font-size: 20px;
}

.feature-item span:last-child {
  color: #fff;
  font-size: 14px;
}

/* 登录区域 */
.login-section {
  flex: 1;
  padding: 60px 48px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-header {
  margin-bottom: 32px;
}

.login-title {
  font-size: 26px;
  font-weight: 700;
  color: #1a1a2e;
  margin: 0 0 8px;
}

.login-desc {
  font-size: 14px;
  color: #8c8c8c;
  margin: 0;
}

.login-form {
  margin-bottom: 24px;
}

.login-form :deep(.el-form-item) {
  margin-bottom: 24px;
}

.login-form :deep(.el-form-item__label) {
  font-weight: 500;
  color: #333;
}

.login-form :deep(.el-input__wrapper) {
  border-radius: 12px;
  padding: 4px 16px;
  box-shadow: 0 0 0 1px #e0e0e0 inset;
  transition: all 0.3s ease;
}

.login-form :deep(.el-input__wrapper:hover) {
  box-shadow: 0 0 0 1px #2d8f5e inset;
}

.login-form :deep(.el-input__wrapper.is-focus) {
  box-shadow: 0 0 0 2px #2d8f5e inset !important;
}

.input-icon {
  font-size: 18px;
  color: #2d8f5e;
}

.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.forgot-link {
  color: #2d8f5e;
  font-size: 14px;
  text-decoration: none;
  transition: color 0.3s ease;
}

.forgot-link:hover {
  color: #1a5f3f;
}

.form-actions {
  margin-top: 8px;
}

.login-button {
  height: 48px;
  font-size: 16px;
  font-weight: 600;
  letter-spacing: 4px;
  background: linear-gradient(135deg, #1a5f3f 0%, #2d8f5e 100%);
  border: none;
  transition: all 0.3s ease;
}

.login-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(45, 143, 94, 0.4);
}

.login-button:active {
  transform: translateY(0);
}

/* 底部区域 */
.login-footer {
  margin-top: 24px;
}

.footer-divider {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 16px;
}

.footer-divider::before,
.footer-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, transparent, #e0e0e0, transparent);
}

.footer-divider span {
  font-size: 13px;
  color: #999;
}

.back-user-btn {
  width: 100%;
  padding: 12px;
  background: transparent;
  border: 1px solid #e0e0e0;
  border-radius: 12px;
  color: #666;
  font-size: 14px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.3s ease;
}

.back-user-btn:hover {
  border-color: #2d8f5e;
  color: #2d8f5e;
  background: rgba(45, 143, 94, 0.05);
}

.back-icon {
  font-size: 16px;
  transition: transform 0.3s ease;
}

.back-user-btn:hover .back-icon {
  transform: translateX(-4px);
}

.admin-tips {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 24px;
  padding: 12px 16px;
  background: linear-gradient(135deg, #fff8e1 0%, #fff3cd 100%);
  border-radius: 10px;
  border: 1px solid #ffeaa7;
}

.tips-icon {
  color: #f39c12;
  font-size: 16px;
}

.admin-tips span {
  font-size: 12px;
  color: #856404;
}

/* 版权信息 */
.copyright {
  position: absolute;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 12px;
  color: rgba(255, 255, 255, 0.5);
  white-space: nowrap;
}

/* 响应式设计 */
@media (max-width: 768px) {
  .admin-login-container {
    flex-direction: column;
    min-height: auto;
  }

  .brand-section {
    padding: 40px 24px;
  }

  .brand-features {
    display: none;
  }

  .login-section {
    padding: 40px 24px;
  }

  .copyright {
    position: relative;
    bottom: auto;
    left: auto;
    transform: none;
    margin-top: 24px;
    text-align: center;
  }
}
</style>
