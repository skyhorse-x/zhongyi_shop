<template>
  <div class="notification-config-container">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2 class="page-title">消息推送</h2>
      <p class="page-desc">配置短信和微信模板消息推送</p>
    </div>

    <el-tabs v-model="activeTab">
      <!-- 短信配置 -->
      <el-tab-pane label="阿里云短信" name="sms">
        <el-form
          ref="smsFormRef"
          :model="smsConfig"
          label-width="140px"
          class="config-form"
        >
          <el-form-item label="启用短信推送">
            <el-switch
              v-model="smsConfig.sms_enabled"
              active-value="1"
              inactive-value="0"
            />
          </el-form-item>

          <el-form-item label="AccessKey ID">
            <el-input
              v-model="smsConfig.sms_access_key_id"
              placeholder="请输入阿里云AccessKey ID"
              maxlength="100"
            />
          </el-form-item>

          <el-form-item label="AccessKey Secret">
            <el-input
              v-model="smsConfig.sms_access_key_secret"
              type="password"
              placeholder="请输入AccessKey Secret（已存在时无需重复填写）"
              maxlength="200"
              show-password
            />
          </el-form-item>

          <el-form-item label="短信签名">
            <el-input
              v-model="smsConfig.sms_sign_name"
              placeholder="例如：中医智能"
              maxlength="50"
            />
          </el-form-item>

          <el-divider content-position="left">短信模板配置</el-divider>

          <el-form-item label="支付成功模板">
            <el-input
              v-model="smsConfig.sms_template_payment"
              placeholder="SMS_xxxxx"
              maxlength="50"
            />
            <div class="form-tip">模板变量：package-商品名, amount-金额</div>
          </el-form-item>

          <el-form-item label="佣金到账模板">
            <el-input
              v-model="smsConfig.sms_template_commission"
              placeholder="SMS_xxxxx"
              maxlength="50"
            />
            <div class="form-tip">模板变量：amount-佣金金额</div>
          </el-form-item>

          <el-form-item label="提现结果模板">
            <el-input
              v-model="smsConfig.sms_template_withdraw"
              placeholder="SMS_xxxxx"
              maxlength="50"
            />
            <div class="form-tip">模板变量：result-审核结果, amount-金额</div>
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- 微信配置 -->
      <el-tab-pane label="微信模板消息" name="wechat">
        <el-form
          ref="wechatFormRef"
          :model="wechatConfig"
          label-width="140px"
          class="config-form"
        >
          <el-form-item label="启用微信推送">
            <el-switch
              v-model="wechatConfig.wechat_enabled"
              active-value="1"
              inactive-value="0"
            />
          </el-form-item>

          <el-form-item label="AppID">
            <el-input
              v-model="wechatConfig.wechat_app_id"
              placeholder="请输入微信小程序AppID"
              maxlength="100"
            />
          </el-form-item>

          <el-form-item label="AppSecret">
            <el-input
              v-model="wechatConfig.wechat_app_secret"
              type="password"
              placeholder="请输入AppSecret（已存在时无需重复填写）"
              maxlength="200"
              show-password
            />
          </el-form-item>

          <el-divider content-position="left">微信模板配置</el-divider>

          <el-form-item label="支付成功模板">
            <el-input
              v-model="wechatConfig.wechat_template_payment"
              placeholder="模板ID"
              maxlength="50"
            />
          </el-form-item>

          <el-form-item label="佣金到账模板">
            <el-input
              v-model="wechatConfig.wechat_template_commission"
              placeholder="模板ID"
              maxlength="50"
            />
          </el-form-item>

          <el-form-item label="提现结果模板">
            <el-input
              v-model="wechatConfig.wechat_template_withdraw"
              placeholder="模板ID"
              maxlength="50"
            />
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- 测试发送 -->
      <el-tab-pane label="测试发送" name="test">
        <el-row :gutter="20">
          <!-- 短信测试 -->
          <el-col :span="12">
            <el-card>
              <template #header>
                <span>短信测试</span>
              </template>
              <el-form :model="smsTest" label-width="100px">
                <el-form-item label="手机号码">
                  <el-input v-model="smsTest.phone" placeholder="请输入手机号" />
                </el-form-item>
                <el-form-item label="模板CODE">
                  <el-input v-model="smsTest.template_code" placeholder="SMS_xxxxx" />
                </el-form-item>
                <el-form-item label="模板参数">
                  <el-input
                    v-model="smsTest.paramsJson"
                    type="textarea"
                    :rows="3"
                    placeholder='{"package":"次数包","amount":"99.00"}'
                  />
                </el-form-item>
                <el-form-item>
                  <el-button
                    type="primary"
                    :loading="smsTesting"
                    @click="handleTestSms"
                  >
                    发送测试短信
                  </el-button>
                </el-form-item>
              </el-form>
            </el-card>
          </el-col>

          <!-- 微信测试 -->
          <el-col :span="12">
            <el-card>
              <template #header>
                <span>微信模板消息测试</span>
              </template>
              <el-form :model="wechatTest" label-width="100px">
                <el-form-item label="OpenID">
                  <el-input v-model="wechatTest.openid" placeholder="用户OpenID" />
                </el-form-item>
                <el-form-item label="模板ID">
                  <el-input v-model="wechatTest.template_id" placeholder="模板ID" />
                </el-form-item>
                <el-form-item label="模板数据">
                  <el-input
                    v-model="wechatTest.dataJson"
                    type="textarea"
                    :rows="3"
                    placeholder='{"thing1":{"value":"次数包"}}'
                  />
                </el-form-item>
                <el-form-item label="跳转链接">
                  <el-input v-model="wechatTest.url" placeholder="可选" />
                </el-form-item>
                <el-form-item>
                  <el-button
                    type="primary"
                    :loading="wechatTesting"
                    @click="handleTestWechat"
                  >
                    发送测试消息
                  </el-button>
                </el-form-item>
              </el-form>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>
    </el-tabs>

    <!-- 保存按钮 -->
    <div class="action-bar">
      <el-button type="primary" :loading="saving" @click="handleSave">
        <el-icon><Check /></el-icon>保存配置
      </el-button>
      <el-button @click="handleReset">重置</el-button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Check } from '@element-plus/icons-vue'
import axios from 'axios'

const activeTab = ref('sms')
const saving = ref(false)
const smsTesting = ref(false)
const wechatTesting = ref(false)

// 短信配置
const smsConfig = reactive({
  sms_enabled: '0',
  sms_access_key_id: '',
  sms_access_key_secret: '',
  sms_sign_name: '',
  sms_template_payment: '',
  sms_template_commission: '',
  sms_template_withdraw: '',
})

// 微信配置
const wechatConfig = reactive({
  wechat_enabled: '0',
  wechat_app_id: '',
  wechat_app_secret: '',
  wechat_template_payment: '',
  wechat_template_commission: '',
  wechat_template_withdraw: '',
})

// 短信测试
const smsTest = reactive({
  phone: '',
  template_code: '',
  paramsJson: '',
})

// 微信测试
const wechatTest = reactive({
  openid: '',
  template_id: '',
  dataJson: '',
  url: '',
})

// 获取配置
const fetchConfig = async () => {
  try {
    const { data } = await axios.get('/api/v1/admin/notification-config')
    if (data.code === 0) {
      const config = data.data

      // 短信配置
      smsConfig.sms_enabled = config.sms_enabled || '0'
      smsConfig.sms_access_key_id = config.sms_access_key_id || ''
      smsConfig.sms_access_key_secret = config.sms_access_key_secret || ''
      smsConfig.sms_sign_name = config.sms_sign_name || ''
      smsConfig.sms_template_payment = config.sms_template_payment || ''
      smsConfig.sms_template_commission = config.sms_template_commission || ''
      smsConfig.sms_template_withdraw = config.sms_template_withdraw || ''

      // 微信配置
      wechatConfig.wechat_enabled = config.wechat_enabled || '0'
      wechatConfig.wechat_app_id = config.wechat_app_id || ''
      wechatConfig.wechat_app_secret = config.wechat_app_secret || ''
      wechatConfig.wechat_template_payment = config.wechat_template_payment || ''
      wechatConfig.wechat_template_commission = config.wechat_template_commission || ''
      wechatConfig.wechat_template_withdraw = config.wechat_template_withdraw || ''
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '获取配置失败')
  }
}

// 保存配置
const handleSave = async () => {
  saving.value = true
  try {
    const config = {
      // 短信
      sms_enabled: smsConfig.sms_enabled,
      sms_access_key_id: smsConfig.sms_access_key_id,
      sms_access_key_secret: smsConfig.sms_access_key_secret,
      sms_sign_name: smsConfig.sms_sign_name,
      sms_template_payment: smsConfig.sms_template_payment,
      sms_template_commission: smsConfig.sms_template_commission,
      sms_template_withdraw: smsConfig.sms_template_withdraw,

      // 微信
      wechat_enabled: wechatConfig.wechat_enabled,
      wechat_app_id: wechatConfig.wechat_app_id,
      wechat_app_secret: wechatConfig.wechat_app_secret,
      wechat_template_payment: wechatConfig.wechat_template_payment,
      wechat_template_commission: wechatConfig.wechat_template_commission,
      wechat_template_withdraw: wechatConfig.wechat_template_withdraw,
    }

    const { data } = await axios.post('/api/v1/admin/notification-config', config)
    if (data.code === 0) {
      ElMessage.success('保存成功')
      fetchConfig()
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '保存失败')
  } finally {
    saving.value = false
  }
}

// 重置
const handleReset = () => {
  fetchConfig()
}

// 测试短信
const handleTestSms = async () => {
  if (!smsTest.phone || !smsTest.template_code) {
    ElMessage.warning('请填写手机号和模板CODE')
    return
  }

  smsTesting.value = true
  try {
    let params = {}
    if (smsTest.paramsJson) {
      try {
        params = JSON.parse(smsTest.paramsJson)
      } catch {
        ElMessage.error('模板参数JSON格式错误')
        return
      }
    }

    const { data } = await axios.post('/api/v1/admin/notification-config/test-sms', {
      phone: smsTest.phone,
      template_code: smsTest.template_code,
      params,
    })

    if (data.code === 0) {
      ElMessage.success('短信发送成功')
    } else {
      ElMessage.error(data.message || '发送失败')
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '发送失败')
  } finally {
    smsTesting.value = false
  }
}

// 测试微信
const handleTestWechat = async () => {
  if (!wechatTest.openid || !wechatTest.template_id || !wechatTest.dataJson) {
    ElMessage.warning('请填写OpenID、模板ID和模板数据')
    return
  }

  wechatTesting.value = true
  try {
    let dataObj = {}
    try {
      dataObj = JSON.parse(wechatTest.dataJson)
    } catch {
      ElMessage.error('模板数据JSON格式错误')
      return
    }

    const { data } = await axios.post('/api/v1/admin/notification-config/test-wechat', {
      openid: wechatTest.openid,
      template_id: wechatTest.template_id,
      data: dataObj,
      url: wechatTest.url || undefined,
    })

    if (data.code === 0) {
      ElMessage.success('微信消息发送成功')
    } else {
      ElMessage.error(data.message || '发送失败')
    }
  } catch (error: any) {
    ElMessage.error(error.response?.data?.message || '发送失败')
  } finally {
    wechatTesting.value = false
  }
}

onMounted(() => {
  fetchConfig()
})
</script>

<style scoped>
.notification-config-container {
  padding: 20px;
}

.config-form {
  max-width: 600px;
  padding: 20px 0;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}

.action-bar {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
}
</style>
