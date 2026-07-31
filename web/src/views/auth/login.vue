<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'

import { useUserStore } from '@/stores/user'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

const form = ref({
  account: '',
  password: '',
})

const loading = ref(false)

const handleLogin = async () => {
  if (!form.value.account) {
    ElMessage.info('请输入账号或手机号')
    return
  }
  if (!form.value.password) {
    ElMessage.info('请输入密码')
    return
  }

  loading.value = true
  try {
    await userStore.loginAction(form.value.account, form.value.password)
    ElMessage.success('登录成功')
    const redirect = (route.query.redirect as string) || '/'
    router.push(redirect)
  } catch (e: any) {
    ElMessage.error(e.message || '登录失败')
  } finally {
    loading.value = false
  }
}

const goRegister = () => {
  router.push('/auth/register')
}
</script>

<template>
  <div class="login-page">
    <div class="login-form">
      <div class="login-title">欢迎登录</div>
      <div class="login-subtitle">AI中医健康管理平台</div>

      <el-form :model="form" label-width="auto" @submit.prevent="handleLogin">
        <el-form-item
          label="账号"
          prop="account"
          :rules="[{ required: true, message: '请输入账号或手机号', trigger: 'blur' }]"
        >
          <el-input v-model="form.account" placeholder="请输入账号或手机号" />
        </el-form-item>
        <el-form-item
          label="密码"
          prop="password"
          :rules="[{ required: true, message: '请输入密码', trigger: 'blur' }]"
        >
          <el-input v-model="form.password" type="password" placeholder="请输入密码" show-password />
        </el-form-item>

        <div class="form-actions">
          <el-button round type="primary" native-type="submit" :loading="loading" style="width: 100%">
            登录
          </el-button>
        </div>
      </el-form>

      <div class="form-footer">
        <span @click="goRegister">还没有账号？立即注册</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  background: #f7f8fa;
}

.login-form {
  padding: 32px 16px;
}

.login-title {
  font-size: 24px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 8px;
}

.login-subtitle {
  font-size: 14px;
  color: #969799;
  margin-bottom: 32px;
}

.form-actions {
  margin-top: 24px;
}

.form-footer {
  margin-top: 16px;
  text-align: center;
  font-size: 14px;
  color: #1989fa;
  cursor: pointer;
}
</style>
