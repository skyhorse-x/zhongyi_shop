<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Connection, Setting, Money, Promotion, Cpu, ChatDotRound, Message, Wallet } from '@element-plus/icons-vue'

const form = ref({
  // 基本设置
  siteName: '',
  siteDescription: '',
  adminEmail: '',
  // 费用设置
  analysisMode: 'paid',
  analysisPrice: 9.99,
  aiCostPerTime: 0.05,
  // 推广返利设置
  commissionRate: 15,
  commissionMinAmount: 1,
  commissionSettleDays: 7,
  withdrawMinAmount: 10,
  // 大模型配置
  llmProvider: 'openai',
  llmApiUrl: 'https://api.openai.com/v1',
  llmApiKey: '',
  llmModel: 'gpt-4o-mini',
  llmTemperature: 0.7,
  llmMaxTokens: 2000,
  llmTimeout: 30,
  // 微信配置
  wechatAppid: '',
  wechatSecret: '',
  wechatMchId: '',
  wechatPayKey: '',
  // 支付宝配置
  alipayAppId: '',
  alipayPrivateKey: '',
  alipayPublicKey: '',
  // 短信宝配置
  smsProvider: 'smsbao',
  smsBaoUser: '',
  smsBaoPass: '',
})

const loading = ref(false)
const testingLlm = ref(false)
const activeTab = ref('basic')

// 测试大模型连接
const testLlmConnection = async () => {
  testingLlm.value = true
  try {
    const res = await fetch('/api/v1/admin/test-llm', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(`连接成功！回复: ${data.data.response}`)
    } else {
      ElMessage.error(data.message || '连接失败')
    }
  } catch (error) {
    ElMessage.error('连接失败，请检查网络')
  } finally {
    testingLlm.value = false
  }
}

// 加载配置
const loadConfigs = async () => {
  try {
    const res = await fetch('/api/v1/admin/configs', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const configs = data.data
      for (const group in configs) {
        for (const item of configs[group]) {
          const keyMap: Record<string, string> = {
            site_name: 'siteName',
            site_description: 'siteDescription',
            admin_email: 'adminEmail',
            analysis_mode: 'analysisMode',
            analysis_price: 'analysisPrice',
            ai_cost_per_time: 'aiCostPerTime',
            commission_rate: 'commissionRate',
            commission_min_amount: 'commissionMinAmount',
            commission_settle_days: 'commissionSettleDays',
            withdraw_min_amount: 'withdrawMinAmount',
            llm_provider: 'llmProvider',
            llm_api_url: 'llmApiUrl',
            llm_api_key: 'llmApiKey',
            llm_model: 'llmModel',
            llm_temperature: 'llmTemperature',
            llm_max_tokens: 'llmMaxTokens',
            llm_timeout: 'llmTimeout',
            wechat_appid: 'wechatAppid',
            wechat_secret: 'wechatSecret',
            wechat_mch_id: 'wechatMchId',
            wechat_pay_key: 'wechatPayKey',
            alipay_app_id: 'alipayAppId',
            alipay_private_key: 'alipayPrivateKey',
            alipay_public_key: 'alipayPublicKey',
            sms_provider: 'smsProvider',
            sms_bao_user: 'smsBaoUser',
            sms_bao_pass: 'smsBaoPass',
          }
          const formKey = keyMap[item.key] || item.key
          if (form.value.hasOwnProperty(formKey)) {
            (form.value as any)[formKey] = item.type === 'number' ? Number(item.value) : item.value
          }
        }
      }
    }
  } catch (error) {
    console.error('加载配置失败:', error)
  }
}

// 保存设置
const handleSave = async () => {
  loading.value = true
  try {
    const configData = {
      site_name: form.value.siteName,
      site_description: form.value.siteDescription,
      admin_email: form.value.adminEmail,
      analysis_mode: form.value.analysisMode,
      analysis_price: form.value.analysisPrice,
      ai_cost_per_time: form.value.aiCostPerTime,
      commission_rate: form.value.commissionRate,
      commission_min_amount: form.value.commissionMinAmount,
      commission_settle_days: form.value.commissionSettleDays,
      withdraw_min_amount: form.value.withdrawMinAmount,
      llm_provider: form.value.llmProvider,
      llm_api_url: form.value.llmApiUrl,
      llm_api_key: form.value.llmApiKey,
      llm_model: form.value.llmModel,
      llm_temperature: form.value.llmTemperature,
      llm_max_tokens: form.value.llmMaxTokens,
      llm_timeout: form.value.llmTimeout,
      wechat_appid: form.value.wechatAppid,
      wechat_secret: form.value.wechatSecret,
      wechat_mch_id: form.value.wechatMchId,
      wechat_pay_key: form.value.wechatPayKey,
      alipay_app_id: form.value.alipayAppId,
      alipay_private_key: form.value.alipayPrivateKey,
      alipay_public_key: form.value.alipayPublicKey,
      sms_provider: form.value.smsProvider,
      sms_bao_user: form.value.smsBaoUser,
      sms_bao_pass: form.value.smsBaoPass,
    }

    const res = await fetch('/api/v1/admin/configs', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
      },
      body: JSON.stringify(configData),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('保存成功')
    } else {
      ElMessage.error(data.message || '保存失败')
    }
  } catch (error) {
    ElMessage.error('保存失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadConfigs()
})
</script>

<template>
  <div class="admin-page-wrapper">
    <div class="page-header">
      <h2 class="page-title">系统设置</h2>
      <p class="page-desc">基本参数、费用、推广返利及接口配置</p>
    </div>

    <el-tabs v-model="activeTab" class="settings-tabs">
      <!-- 基本设置 -->
      <el-tab-pane label="基本设置" name="basic">
        <template #label>
          <span class="tab-label">
            <el-icon><Setting /></el-icon>
            <span>基本设置</span>
          </span>
        </template>
        <el-form :model="form" label-width="140px" class="settings-form" v-loading="loading">
          <el-form-item label="站点名称">
            <el-input v-model="form.siteName" placeholder="请输入站点名称" style="max-width: 400px" />
          </el-form-item>
          <el-form-item label="站点描述">
            <el-input v-model="form.siteDescription" placeholder="请输入站点描述" type="textarea" :rows="3" style="max-width: 400px" />
          </el-form-item>
          <el-form-item label="管理员邮箱">
            <el-input v-model="form.adminEmail" placeholder="请输入管理员邮箱" style="max-width: 400px" />
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- 费用设置 -->
      <el-tab-pane label="费用设置" name="fee">
        <template #label>
          <span class="tab-label">
            <el-icon><Money /></el-icon>
            <span>费用设置</span>
          </span>
        </template>
        <el-form :model="form" label-width="140px" class="settings-form" v-loading="loading">
          <el-form-item label="分析模式">
            <el-radio-group v-model="form.analysisMode">
              <el-radio value="paid">付费分析</el-radio>
              <el-radio value="free">免费分析</el-radio>
            </el-radio-group>
            <div class="form-tip">付费分析需要用户支付费用，免费分析无需支付</div>
          </el-form-item>
          <el-form-item label="单次分析价格" v-if="form.analysisMode === 'paid'">
            <el-input-number v-model="form.analysisPrice" :min="0" :precision="2" style="width: 200px" />
            <span class="form-unit">元</span>
            <span class="form-tip">用户单次AI分析收费</span>
          </el-form-item>
          <el-form-item label="AI分析成本">
            <el-input-number v-model="form.aiCostPerTime" :min="0" :precision="2" style="width: 200px" />
            <span class="form-unit">元/次</span>
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- 推广返利设置 -->
      <el-tab-pane label="推广返利" name="promotion">
        <template #label>
          <span class="tab-label">
            <el-icon><Promotion /></el-icon>
            <span>推广返利</span>
          </span>
        </template>
        <el-form :model="form" label-width="140px" class="settings-form" v-loading="loading">
          <el-form-item label="推广佣金比例">
            <el-input-number v-model="form.commissionRate" :min="0" :max="100" :precision="1" style="width: 200px" />
            <span class="form-unit">%</span>
            <span class="form-tip">被邀请用户消费后，推广员可获得消费金额的百分比作为佣金</span>
          </el-form-item>
          <el-form-item label="最低佣金金额">
            <el-input-number v-model="form.commissionMinAmount" :min="0" :precision="2" style="width: 200px" />
            <span class="form-unit">元</span>
            <span class="form-tip">订单金额低于此值不产生佣金</span>
          </el-form-item>
          <el-form-item label="佣金结算天数">
            <el-input-number v-model="form.commissionSettleDays" :min="0" :max="30" style="width: 200px" />
            <span class="form-unit">天</span>
            <span class="form-tip">订单完成后几天结算佣金到推广员账户</span>
          </el-form-item>
          <el-form-item label="最低提现金额">
            <el-input-number v-model="form.withdrawMinAmount" :min="1" :precision="2" style="width: 200px" />
            <span class="form-unit">元</span>
            <span class="form-tip">推广员最低可提现金额</span>
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- 大模型接口配置 -->
      <el-tab-pane label="大模型配置" name="llm">
        <template #label>
          <span class="tab-label">
            <el-icon><Cpu /></el-icon>
            <span>大模型配置</span>
          </span>
        </template>
        <el-form :model="form" label-width="140px" class="settings-form" v-loading="loading">
          <el-form-item label="大模型服务商">
            <el-select v-model="form.llmProvider" placeholder="请选择服务商" style="width: 200px">
              <el-option label="OpenAI" value="openai" />
              <el-option label="Anthropic (Claude)" value="anthropic" />
              <el-option label="DeepSeek" value="deepseek" />
              <el-option label="通义千问" value="qwen" />
            </el-select>
          </el-form-item>
          <el-form-item label="API地址">
            <el-input v-model="form.llmApiUrl" placeholder="https://api.openai.com/v1" style="max-width: 400px" />
          </el-form-item>
          <el-form-item label="API密钥">
            <el-input v-model="form.llmApiKey" placeholder="请输入API密钥" type="password" show-password style="max-width: 400px" />
          </el-form-item>
          <el-form-item label="模型名称">
            <el-input v-model="form.llmModel" placeholder="gpt-4o-mini" style="max-width: 400px" />
          </el-form-item>
          <el-form-item label="温度参数">
            <el-input-number v-model="form.llmTemperature" :min="0" :max="2" :precision="1" style="width: 200px" />
            <span class="form-tip">生成文本的随机性（0-2，越高越随机）</span>
          </el-form-item>
          <el-form-item label="最大Token数">
            <el-input-number v-model="form.llmMaxTokens" :min="100" :max="10000" style="width: 200px" />
            <span class="form-unit">tokens</span>
          </el-form-item>
          <el-form-item label="超时时间">
            <el-input-number v-model="form.llmTimeout" :min="5" :max="120" style="width: 200px" />
            <span class="form-unit">秒</span>
          </el-form-item>
          <el-form-item label="测试连接">
            <el-button @click="testLlmConnection" :loading="testingLlm">
              <el-icon><Connection /></el-icon>
              测试API连接
            </el-button>
            <span class="form-tip">保存配置后点击测试</span>
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- 微信配置 -->
      <el-tab-pane label="微信配置" name="wechat">
        <template #label>
          <span class="tab-label">
            <el-icon><ChatDotRound /></el-icon>
            <span>微信配置</span>
          </span>
        </template>
        <el-form :model="form" label-width="140px" class="settings-form" v-loading="loading">
          <el-form-item label="小程序AppID">
            <el-input v-model="form.wechatAppid" placeholder="请输入小程序AppID" style="max-width: 400px" />
          </el-form-item>
          <el-form-item label="小程序Secret">
            <el-input v-model="form.wechatSecret" placeholder="请输入小程序Secret" type="password" show-password style="max-width: 400px" />
          </el-form-item>
          <el-form-item label="支付商户号">
            <el-input v-model="form.wechatMchId" placeholder="请输入微信支付商户号" style="max-width: 400px" />
          </el-form-item>
          <el-form-item label="支付API密钥">
            <el-input v-model="form.wechatPayKey" placeholder="请输入微信支付API密钥" type="password" show-password style="max-width: 400px" />
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- 支付宝配置 -->
      <el-tab-pane label="支付宝配置" name="alipay">
        <template #label>
          <span class="tab-label">
            <el-icon><Wallet /></el-icon>
            <span>支付宝配置</span>
          </span>
        </template>
        <el-form :model="form" label-width="140px" class="settings-form" v-loading="loading">
          <el-form-item label="AppID">
            <el-input v-model="form.alipayAppId" placeholder="请输入支付宝AppID" style="max-width: 400px" />
          </el-form-item>
          <el-form-item label="应用私钥">
            <el-input v-model="form.alipayPrivateKey" placeholder="请输入应用私钥" type="password" show-password style="max-width: 600px" />
            <div class="form-tip">RSA2 私钥，可从支付宝开放平台生成</div>
          </el-form-item>
          <el-form-item label="支付宝公钥">
            <el-input v-model="form.alipayPublicKey" placeholder="请输入支付宝公钥" type="textarea" :rows="4" style="max-width: 600px" />
            <div class="form-tip">注意：此为支付宝公钥，不是应用公钥</div>
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- 短信宝配置 -->
      <el-tab-pane label="短信配置" name="sms">
        <template #label>
          <span class="tab-label">
            <el-icon><Message /></el-icon>
            <span>短信配置</span>
          </span>
        </template>
        <el-form :model="form" label-width="140px" class="settings-form" v-loading="loading">
          <el-form-item label="短信服务商">
            <el-select v-model="form.smsProvider" style="width: 200px">
              <el-option label="短信宝" value="smsbao" />
            </el-select>
          </el-form-item>
          <el-form-item label="短信宝账号">
            <el-input v-model="form.smsBaoUser" placeholder="请输入短信宝账号" style="max-width: 400px" />
          </el-form-item>
          <el-form-item label="短信宝密码">
            <el-input v-model="form.smsBaoPass" placeholder="请输入短信宝密码" type="password" show-password style="max-width: 400px" />
            <div class="form-tip">系统使用MD5(密码) 调用短信宝API，密码本身不传输</div>
          </el-form-item>
        </el-form>
      </el-tab-pane>
    </el-tabs>

    <!-- 保存按钮 -->
    <div class="submit-area">
      <el-button type="primary" size="large" @click="handleSave" :loading="loading">保存设置</el-button>
    </div>
  </div>
</template>

<style scoped>
.admin-page-wrapper {
  max-width: 100%;
}

.page-header {
  margin-bottom: 24px;
}

.page-title {
  font-size: 20px;
  font-weight: 600;
  color: #333;
  margin-bottom: 4px;
}

.page-desc {
  font-size: 14px;
  color: #999;
}

.settings-tabs {
  margin-bottom: 20px;
}

.tab-label {
  display: flex;
  align-items: center;
  gap: 6px;
}

.tab-label .el-icon {
  font-size: 16px;
}

.settings-form {
  max-width: 700px;
  padding: 20px 0;
}

.form-unit {
  margin-left: 8px;
  color: #999;
  font-size: 14px;
}

.form-tip {
  margin-left: 12px;
  color: #999;
  font-size: 12px;
}

.submit-area {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
}
</style>
