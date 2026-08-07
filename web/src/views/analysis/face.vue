<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { CameraFilled, ChatLineRound, User, ArrowDown } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'
import InsufficientCredits from '@/components/analysis/InsufficientCredits.vue'

const router = useRouter()
const imageUrl = ref('')
const fileName = ref('')
const imageFile = ref<File | null>(null)
const loading = ref(false)
const aiText = ref('')
const analysisTimes = ref<number | null>(null)
const gender = ref<number | null>(null)
const age = ref<number>(18)
const textExpanded = ref(false)
const creditsLoaded = ref(false)
const showResult = ref(false)
const analysisResult = ref<any>(null)

// 返回表单
const backToForm = () => {
  showResult.value = false
  analysisResult.value = null
  imageUrl.value = ''
  imageFile.value = null
  aiText.value = ''
}

// 格式化分析内容
const formatContent = (content: string) => {
  if (!content) return ''
  return content
    .replace(/##\s*(.+)/g, '<h3>$1</h3>')
    .replace(/###\s*(.+)/g, '<h4>$1</h4>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\n/g, '<br>')
}

// 年龄选项 1-100岁
const ageOptions = Array.from({ length: 100 }, (_, i) => i + 1)

import { getToken } from '@/utils/auth'

// 积分消耗配置
const creditsPerAnalysis = 1

// 获取用户剩余分析积分
const fetchAnalysisTimes = async () => {
  const token = getToken()
  if (!token) {
    creditsLoaded.value = true
    return
  }

  try {
    const res = await safeFetch('/api/v1/user/info', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      analysisTimes.value = data.data?.analysis_times ?? 0
    }
  } catch (e) {
    console.error('获取分析积分失败:', e)
  } finally {
    creditsLoaded.value = true
  }
}

onMounted(() => {
  fetchAnalysisTimes()
})

const handleFileChange = (uploadFile: any) => {
  if (uploadFile.raw) {
    fileName.value = uploadFile.raw.name
    imageFile.value = uploadFile.raw
    imageUrl.value = URL.createObjectURL(uploadFile.raw)
  }
}

// 上传图片到服务器
const uploadImage = async (blob: Blob, name: string): Promise<string> => {
  const formData = new FormData()
  formData.append('image', blob, name)

  const res = await safeFetch('/api/v1/analysis/upload-image', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${getToken()}`,
    },
    body: formData,
  })
  const data = await res.json()
  if (data.code === 0) {
    return data.data.image_url
  }
  throw new Error(data.message || '图片上传失败')
}

// 提交分析任务
const submitAnalysisDirect = async (imageUrls: string[], type: 'tongue' | 'face' | 'palm', text: string, gender: number, age: number) => {
  const res = await safeFetch('/api/v1/analysis/submit', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${getToken()}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      type: type,
      image_urls: imageUrls,
      text: text,
      gender: gender,
      age: age,
    }),
  })
  const data = await res.json()
  if (data.code === 0) {
    return data.data
  }
  throw new Error(data.message || '提交失败')
}


const handleSubmit = async () => {
  if (!imageFile.value) {
    ElMessage.warning('请上传一张面部照片')
    return
  }
  if (!gender.value) {
    ElMessage.warning('请选择性别')
    return
  }
  if (!age.value || age.value <= 0) {
    ElMessage.warning('请选择年龄')
    return
  }
  loading.value = true
  try {
    let uploadedUrls: string[] = []
    if (imageFile.value) {
      const url = await uploadImage(imageFile.value, fileName.value || 'face.jpg')
      uploadedUrls = [url]
    }

    const result = await submitAnalysisDirect(uploadedUrls, 'face', aiText.value, gender.value, age.value)
    analysisResult.value = result
    showResult.value = true
    // 更新剩余积分
    if (analysisTimes.value !== null) {
      analysisTimes.value -= creditsPerAnalysis
    }
  } catch (e: any) {
    ElMessage.error(e.message || '提交失败')
  } finally {
    loading.value = false
  }
}

// 判断是否有足够积分
const hasEnoughCredits = () => {
  return analysisTimes.value !== null && analysisTimes.value >= creditsPerAnalysis
}
</script>

<template>
  <div class="face-page">
    <!-- 分析结果 -->
    <div v-if="showResult && analysisResult" class="result-section">
      <div class="result-header">
        <h2 class="result-title">面诊分析报告</h2>
        <el-button type="primary" @click="backToForm">再次分析</el-button>
      </div>
      <div class="result-score">
        <span class="score-label">健康评分</span>
        <span class="score-value">{{ analysisResult.health_score || 85 }}</span>
      </div>
      <div class="result-summary">
        <h3>分析摘要</h3>
        <p>{{ analysisResult.summary || '分析完成' }}</p>
      </div>
      <div class="result-content" v-if="analysisResult.result?.content">
        <h3>详细分析</h3>
        <div class="content-text" v-html="formatContent(analysisResult.result.content)"></div>
      </div>
    </div>

    <!-- 积分不足 - 只显示锁定提示 -->
    <div v-if="creditsLoaded && !hasEnoughCredits()" class="locked-state">
      <InsufficientCredits :credits="creditsPerAnalysis" :current-credits="analysisTimes ?? 0" />
    </div>

    <!-- 积分充足 - 只显示表单 -->
    <div v-else>
      <!-- 基本信息（必填） -->
      <div class="profile-section">
        <div class="ai-text-header">
          <el-icon><User /></el-icon>
          <span>基本信息 <span class="required-tip">*</span></span>
        </div>
        <div class="profile-row">
          <span class="profile-label">性别</span>
          <el-radio-group v-model="gender">
            <el-radio :value="1">男</el-radio>
            <el-radio :value="2">女</el-radio>
          </el-radio-group>
        </div>
        <div class="profile-row">
          <span class="profile-label">年龄</span>
          <el-select v-model="age" placeholder="请选择年龄" style="width: 120px;">
            <el-option
              v-for="ageVal in ageOptions"
              :key="ageVal"
              :label="ageVal + '岁'"
              :value="ageVal"
            />
          </el-select>
        </div>
      </div>

      <!-- 图片上传区域（必填） -->
      <div class="upload-section">
        <div class="upload-tip">
          <span class="required-tag">必填</span> 上传清晰的面部照片，至少一张
        </div>

        <div v-if="imageUrl" class="image-preview">
          <el-image :src="imageUrl" alt="面部照片" style="max-width: 100%; border-radius:12px;" />
        </div>

        <el-upload
          :auto-upload="false"
          accept="image/*"
          capture="user"
          :show-file-list="false"
          @change="handleFileChange"
        >
          <template #trigger>
            <div class="upload-area">
              <el-icon :size="48"><CameraFilled /></el-icon>
              <div class="upload-text">{{ imageUrl ? '重新上传' : '点击拍摄或上传' }}</div>
            </div>
          </template>
        </el-upload>
      </div>

      <!-- 症状描述（可折叠） -->
      <div class="ai-text-section">
        <div class="ai-text-header" @click="textExpanded = !textExpanded" style="cursor: pointer;">
          <el-icon><ChatLineRound /></el-icon>
          <span>症状描述</span>
          <span class="optional-tag">可选</span>
          <el-icon class="expand-icon" :class="{ expanded: textExpanded }">
            <ArrowDown />
          </el-icon>
        </div>
        <div v-show="textExpanded" class="ai-text-content">
          <el-input
            v-model="aiText"
            type="textarea"
            :rows="4"
            placeholder="请详细描述您想了解的面部问题（如：面色萎黄、皮肤暗沉、有黑眼圈等），AI将根据您的描述进行分析..."
            resize="none"
            maxlength="500"
            show-word-limit
          />
        </div>
        <div v-if="!textExpanded" class="ai-text-hint">
          点击展开输入症状描述（可选，已上传照片可不填）
        </div>
      </div>

      <!-- 免责声明 -->
      <div class="disclaimer-section">
        <el-alert
          title="免责声明"
          type="warning"
          :closable="false"
          show-icon
        >
          <template #default>
            <div class="disclaimer-content">
              <p>1. 本分析结果仅供参考，不能作为医疗诊断依据。</p>
              <p>2. 如有健康问题，请咨询专业医疗机构或医师。</p>
              <p>3. AI分析存在局限性，不能替代专业医疗建议。</p>
            </div>
          </template>
        </el-alert>
      </div>

      <div class="actions">
        <el-button
          round
          type="primary"
          :loading="loading"
          @click="handleSubmit"
          style="width: 100%"
        >
          开始分析
        </el-button>
        <div class="free-tip">
          剩余 {{ analysisTimes }} 积分
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.face-page {
  padding: 16px;
  background: #fff;
  min-height: 100vh;
}

.locked-state {
  min-height: 60vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ai-text-section {
  margin-bottom: 24px;
}

.profile-section {
  margin-bottom: 24px;
}

.profile-row {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 12px;
}

.profile-label {
  font-size: 14px;
  color: #323233;
  min-width: 48px;
}

.ai-text-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  font-size: 14px;
  font-weight: 500;
  color: #323233;
}

.ai-text-header .el-icon {
  font-size: 16px;
  color: #60a5fa;
}

.expand-icon {
  margin-left: auto;
  transition: transform 0.3s;
}

.expand-icon.expanded {
  transform: rotate(180deg);
}

.ai-text-hint {
  font-size: 13px;
  color: #969799;
  padding: 12px;
  background: #f7f8fa;
  border-radius: 8px;
  text-align: center;
}

.ai-text-content {
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    max-height: 0;
  }
  to {
    opacity: 1;
    max-height: 200px;
  }
}

.required-tip {
  color: #f56c6c;
  font-weight: 600;
  margin-left: 2px;
}

.upload-section {
  margin-bottom: 24px;
}

.upload-tip {
  font-size: 14px;
  color: #969799;
  margin-bottom: 16px;
  text-align: left;
}

.optional-tag {
  display: inline-block;
  background: #e8f7ef;
  color: #07c160;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 4px;
  margin-right: 6px;
  font-weight: 500;
}

.required-tag {
  display: inline-block;
  background: #fef0f0;
  color: #f56c6c;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 4px;
  margin-right: 6px;
  font-weight: 500;
}

.upload-area {
  width: 100%;
  min-height: 140px;
  border: 2px dashed #dcdee0;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #969799;
  cursor: pointer;
  transition: all 0.3s ease;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 24px;
}

.upload-area:hover {
  border-color: #60a5fa;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(96, 165, 250, 0.15);
}

.upload-area:active {
  transform: translateY(0);
  box-shadow: 0 2px 6px rgba(96, 165, 250, 0.1);
}

.upload-text {
  margin-top: 8px;
  font-size: 15px;
  font-weight: 500;
  color: #64748b;
}

.image-preview {
  margin-bottom: 16px;
  text-align: center;
}

.actions {
  margin-top: 32px;
}

.free-tip {
  text-align: center;
  font-size: 12px;
  color: #67c23a;
  margin-top: 8px;
}

.disclaimer-section {
  margin: 16px 0;
}

.disclaimer-content {
  font-size: 12px;
  line-height: 1.8;
  color: #666;
}

.disclaimer-content p {
  margin: 2px 0;
}
</style>
