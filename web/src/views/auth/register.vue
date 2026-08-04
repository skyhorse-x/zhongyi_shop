<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'

import { useUserStore } from '@/stores/user'
import { sendSmsCode } from '@/api/auth'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

// 注册类型: 'account' | 'mobile'
const registerType = ref<'account' | 'mobile'>('account')

const form = ref({
  username: '',
  mobile: '',
  sms_code: '',
  password: '',
  password_confirmation: '',
  invite_code: '',
})

// 从URL获取邀请码
onMounted(() => {
  const inviteCode = route.query.code as string
  if (inviteCode) {
    form.value.invite_code = inviteCode
  }
})

const smsSending = ref(false)
const smsCountdown = ref(0)
const loading = ref(false)

let countdownTimer: ReturnType<typeof setInterval> | null = null

const isAccountMode = computed(() => registerType.value === 'account')
const isMobileMode = computed(() => registerType.value === 'mobile')

const switchToAccount = () => {
  registerType.value = 'account'
}

const switchToMobile = () => {
  registerType.value = 'mobile'
}

const sendSms = async () => {
  if (!form.value.mobile) {
    ElMessage.info('请输入手机号')
    return
  }
  if (!/^1[3-9]\d{9}$/.test(form.value.mobile)) {
    ElMessage.info('手机号格式不正确')
    return
  }

  smsSending.value = true
  try {
    await sendSmsCode({ mobile: form.value.mobile, type: 'register' })
    ElMessage.success('验证码已发送')
    smsCountdown.value = 60
    countdownTimer = setInterval(() => {
      smsCountdown.value--
      if (smsCountdown.value <= 0) {
        if (countdownTimer) clearInterval(countdownTimer)
      }
    }, 1000)
  } catch (e: any) {
    ElMessage.error(e.message || '发送失败')
  } finally {
    smsSending.value = false
  }
}

const handleRegister = async () => {
  if (isAccountMode.value) {
    if (!form.value.username) {
      ElMessage.info('请输入账号')
      return
    }
    if (form.value.username.length < 3) {
      ElMessage.info('账号至少3位字符')
      return
    }
    if (!/^[a-zA-Z0-9_]+$/.test(form.value.username)) {
      ElMessage.info('账号只能包含字母、数字和下划线')
      return
    }
  } else {
    if (!form.value.mobile) {
      ElMessage.info('请输入手机号')
      return
    }
    if (!/^1[3-9]\d{9}$/.test(form.value.mobile)) {
      ElMessage.info('手机号格式不正确')
      return
    }
  }

  if (!form.value.password) {
    ElMessage.info('请输入密码')
    return
  }
  if (form.value.password.length < 6) {
    ElMessage.info('密码至少6位')
    return
  }
  if (form.value.password !== form.value.password_confirmation) {
    ElMessage.info('两次密码不一致')
    return
  }

  loading.value = true
  try {
    const data: any = {
      type: registerType.value,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    }
    if (isAccountMode.value) {
      data.username = form.value.username
    } else {
      data.mobile = form.value.mobile
    }

    // 添加邀请码（如果有）
    if (form.value.invite_code) {
      data.invite_code = form.value.invite_code
    }

    await userStore.registerAction(data)
    ElMessage.success('注册成功')
    // 清除邀请码，防止返回注册页时重复使用
    form.value.invite_code = ''
    router.replace('/')
  } catch (e: any) {
    ElMessage.error(e.message || '注册失败')
  } finally {
    loading.value = false
  }
}

onUnmounted(() => {
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }
})
</script>

<template>
  <div class="register-page">
    <div class="register-form">
      <div class="register-title">创建账号</div>
      <div class="register-subtitle">加入ai 中医健康助手平台</div>

      <!-- 注册方式切换 -->
      <div class="register-type-switch">
        <div
          class="type-tab"
          :class="{ active: isAccountMode }"
          @click="switchToAccount"
        >
          账号注册
        </div>
        <div
          class="type-tab"
          :class="{ active: isMobileMode }"
          @click="switchToMobile"
        >
          手机注册
        </div>
      </div>

      <el-form :model="form" label-width="auto" @submit.prevent="handleRegister">
        <!-- 账号注册模式 -->
        <template v-if="isAccountMode">
          <el-form-item
            label="账号"
            prop="username"
            :rules="[
              { required: true, message: '请输入账号', trigger: 'blur' },
              { pattern: /^[a-zA-Z0-9_]+$/, message: '账号只能包含字母、数字和下划线', trigger: 'blur' },
              { validator: (rule: any, value: string, callback: any) => value.length >= 3 ? callback() : callback(new Error('账号至少3位字符')), trigger: 'blur' },
            ]"
          >
            <el-input v-model="form.username" placeholder="请输入账号（字母、数字、下划线）" />
          </el-form-item>
        </template>

        <!-- 手机注册模式 -->
        <template v-if="isMobileMode">
          <el-form-item
            label="手机号"
            prop="mobile"
            :rules="[
              { required: true, message: '请输入手机号', trigger: 'blur' },
              { pattern: /^1[3-9]\d{9}$/, message: '手机号格式不正确', trigger: 'blur' },
            ]"
          >
            <el-input v-model="form.mobile" placeholder="请输入手机号">
              <template #suffix>
                <el-button
                  size="small"
                  type="primary"
                  :disabled="smsCountdown > 0"
                  :loading="smsSending"
                  @click="sendSms"
                  style="margin-right: -8px;"
                >
                  {{ smsCountdown > 0 ? `${smsCountdown}s` : '获取验证码' }}
                </el-button>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item
            label="验证码"
            prop="sms_code"
            :rules="[{ required: true, message: '请输入验证码', trigger: 'blur' }]"
          >
            <el-input v-model="form.sms_code" placeholder="请输入验证码" maxlength="6" />
          </el-form-item>
        </template>

        <el-form-item
          label="密码"
          prop="password"
          :rules="[
            { required: true, message: '请输入密码', trigger: 'blur' },
            { validator: (rule: any, value: string, callback: any) => value.length >= 6 ? callback() : callback(new Error('密码至少6位')), trigger: 'blur' },
          ]"
        >
          <el-input v-model="form.password" type="password" placeholder="请输入密码（至少6位）" show-password />
        </el-form-item>
        <el-form-item
          label="确认密码"
          prop="password_confirmation"
          :rules="[
            { required: true, message: '请确认密码', trigger: 'blur' },
            { validator: (rule: any, value: string, callback: any) => value === form.password ? callback() : callback(new Error('两次密码不一致')), trigger: 'blur' },
          ]"
        >
          <el-input v-model="form.password_confirmation" type="password" placeholder="请再次输入密码" show-password />
        </el-form-item>

        <!-- 邀请码（选填） -->
        <el-form-item label="邀请码" prop="invite_code">
          <el-input v-model="form.invite_code" placeholder="请输入邀请码（选填）" />
        </el-form-item>

        <div class="form-actions">
          <el-button round type="primary" native-type="submit" :loading="loading" style="width: 100%">
            注册
          </el-button>
        </div>
      </el-form>

      <div class="form-footer">
        <span @click="router.push('/auth/login')">已有账号？立即登录</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.register-page {
  min-height: 100vh;
  background: #f7f8fa;
}

.register-form {
  padding: 32px 16px;
}

.register-title {
  font-size: 24px;
  font-weight: bold;
  color: #323233;
  margin-bottom: 8px;
}

.register-subtitle {
  font-size: 14px;
  color: #969799;
  margin-bottom: 24px;
}

/* 注册方式切换 */
.register-type-switch {
  display: flex;
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 16px;
  border: 1px solid #ebedf0;
}

.type-tab {
  flex: 1;
  text-align: center;
  padding: 12px 0;
  font-size: 14px;
  color: #969799;
  cursor: pointer;
  transition: all 0.3s;
}

.type-tab.active {
  color: #fff;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  font-weight: bold;
}

.form-actions {
  margin-top: 24px;
}

.form-footer {
  margin-top: 16px;
  text-align: center;
  font-size: 14px;
  color: #07c160;
  cursor: pointer;
}
</style>
