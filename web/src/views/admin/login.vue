<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { setAdminToken } from '@/utils/auth'
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
    const res = await safeFetch('/api/v1/admin/auth/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(form),
    })
    const data = await res.json()

    if (data.code === 0) {
      setAdminToken(data.data.token)
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
</script>

<template>
  <div class="admin-login-page">
    <div class="login-container">
      <div class="login-header">
        <h1 class="login-title">管理后台</h1>
        <p class="login-desc">请输入管理员账号和密码</p>
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

      <div class="admin-tips">
        <el-icon class="tips-icon"><Lock /></el-icon>
        <span>仅限授权管理员访问，所有操作将被记录</span>
      </div>
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
}

.login-container {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 16px;
  padding: 48px 40px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.login-header {
  text-align: center;
  margin-bottom: 32px;
}

.login-title {
  font-size: 28px;
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
  width: 100%;
}

.login-form :deep(.el-form-item) {
  margin-bottom: 24px;
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
  letter-spacing: 8px;
  background: linear-gradient(135deg, #1a5f3f 0%, #2d8f5e 100%);
  border: none;
  transition: all 0.3s ease;
  width: 100%;
  border-radius: 24px;
}

.login-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(45, 143, 94, 0.4);
}

.login-button:active {
  transform: translateY(0);
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

/* 响应式设计 */
@media (max-width: 768px) {
  .admin-login-page {
    padding: 16px;
  }

  .login-container {
    padding: 32px 24px;
    border-radius: 12px;
  }

  .login-title {
    font-size: 24px;
  }

  .login-desc {
    font-size: 13px;
  }

  .login-form :deep(.el-form-item) {
    margin-bottom: 16px;
  }

  .form-options {
    margin-bottom: 16px;
  }

  .login-button {
    height: 44px;
    font-size: 15px;
    letter-spacing: 8px;
  }

  .admin-tips {
    margin-top: 16px;
    padding: 10px 12px;
  }

  .admin-tips span {
    font-size: 11px;
  }
}

@media (max-width: 380px) {
  .login-container {
    padding: 24px 20px;
  }

  .login-title {
    font-size: 22px;
  }
}
</style>
