<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { CameraFilled, ChatLineRound, Delete, ArrowDown, HomeFilled, ChatDotRound, Message, User, Document, DataAnalysis, MagicStick, Sunny, Aim, Warning, Plus } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'
import { getToken } from '@/utils/auth'
import InsufficientCredits from '@/components/analysis/InsufficientCredits.vue'

const router = useRouter()

interface ImageItem {
  url: string
  file: File
  name: string
}

// 年龄选项 1-100岁（定义在组件外，避免重复创建）
const ageOptions = Array.from({ length: 100 }, (_, i) => i + 1)

// 积分消耗配置
const creditsPerAnalysis = 1

const imageList = ref<ImageItem[]>([])
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
  imageList.value = []
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

const handleFileChange = (uploadFile: any) => {
  if (uploadFile.raw) {
    const newImage: ImageItem = {
      url: URL.createObjectURL(uploadFile.raw),
      file: uploadFile.raw,
      name: uploadFile.raw.name
    }
    imageList.value.push(newImage)
  }
}

const removeImage = (index: number) => {
  URL.revokeObjectURL(imageList.value[index].url)
  imageList.value.splice(index, 1)
}

// 上传单张图片到服务器
const uploadSingleImage = async (file: File): Promise<string> => {
  const formData = new FormData()
  formData.append('image', file)

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
const submitAnalysisAsync = async (imageUrls: string[], type: 'tongue' | 'face' | 'palm', text: string, gender: number, age: number) => {
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
  if (imageList.value.length === 0) {
    ElMessage.warning('请至少上传一张舌头照片')
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
    if (imageList.value.length > 0) {
      const uploadPromises = imageList.value.map(img => uploadSingleImage(img.file))
      uploadedUrls = await Promise.all(uploadPromises)
    }

    const result = await submitAnalysisAsync(uploadedUrls, 'tongue', aiText.value, gender.value, age.value)
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

// 底部导航跳转
const goToPage = (path: string) => {
  router.push(path)
}

onMounted(() => {
  fetchAnalysisTimes()
})
</script>

<template>
  <div class="tongue-page">
    <!-- 分析结果 -->
    <div v-if="showResult && analysisResult" class="result-section">
      <!-- 综合评分卡片 -->
      <div class="result-score-card">
        <div class="score-ring">
          <svg class="score-ring-bg" viewBox="0 0 120 120">
            <circle cx="60" cy="60" r="54" fill="none" stroke="#e8faf0" stroke-width="8"/>
            <circle cx="60" cy="60" r="54" fill="none" stroke="url(#gradient)" stroke-width="8" 
              stroke-linecap="round" :stroke-dasharray="`${(analysisResult.health_score || 85) * 3.39}, 339`"
              transform="rotate(-90 60 60)"/>
            <defs>
              <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#00C777"/>
                <stop offset="100%" stop-color="#00A86B"/>
              </linearGradient>
            </defs>
          </svg>
          <div class="score-ring-content">
            <span class="score-value">{{ analysisResult.health_score || 85 }}</span>
            <span class="score-status">状态良好</span>
          </div>
        </div>
        <p class="score-label">综合健康评分</p>
      </div>

      <!-- 分项评分 -->
      <div class="score-details">
        <div class="score-item">
          <div class="score-item-header">
            <span class="score-item-label">舌色</span>
            <span class="score-item-value">85%</span>
          </div>
          <div class="score-bar">
            <div class="score-bar-fill" style="width: 85%"></div>
          </div>
        </div>
        <div class="score-item">
          <div class="score-item-header">
            <span class="score-item-label">苔质</span>
            <span class="score-item-value">80%</span>
          </div>
          <div class="score-bar">
            <div class="score-bar-fill" style="width: 80%"></div>
          </div>
        </div>
        <div class="score-item">
          <div class="score-item-header">
            <span class="score-item-label">舌形</span>
            <span class="score-item-value">90%</span>
          </div>
          <div class="score-bar">
            <div class="score-bar-fill" style="width: 90%"></div>
          </div>
        </div>
      </div>

      <div class="result-summary">
        <div class="card-title">
          <div class="title-icon-box">
            <el-icon><Document /></el-icon>
          </div>
          <span>分析摘要</span>
        </div>
        <p>{{ analysisResult.summary || '分析完成' }}</p>
      </div>
      <div class="result-content" v-if="analysisResult.result?.content">
        <div class="card-title">
          <div class="title-icon-box">
            <el-icon><DataAnalysis /></el-icon>
          </div>
          <span>详细分析</span>
        </div>
        <div class="content-text" v-html="formatContent(analysisResult.result.content)"></div>
      </div>
    </div>

    <!-- 积分不足 - 只显示锁定提示 -->
    <div v-if="creditsLoaded && !hasEnoughCredits()" class="locked-state">
      <InsufficientCredits :credits="creditsPerAnalysis" :current-credits="analysisTimes ?? 0" />
    </div>

    <!-- 积分充足 - 只显示表单 -->
    <div v-else class="page-content">
      <!-- 基本信息卡片 -->
      <div class="card profile-card">
        <div class="card-title">
          <div class="title-icon-box">
            <el-icon><User /></el-icon>
          </div>
          <span>基本信息</span>
        </div>

        <!-- 性别选择 -->
        <div class="form-item">
          <label class="form-label">性别</label>
          <div class="gender-options">
            <button
              type="button"
              class="gender-btn"
              :class="{ active: gender === 1 }"
              @click="gender = 1"
            >
              <div class="gender-icon-wrap">
                <svg class="gender-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="8" r="4"/>
                  <path d="M12 14v7M9 18h6"/>
                </svg>
              </div>
              <span class="gender-text">男</span>
            </button>
            <button
              type="button"
              class="gender-btn"
              :class="{ active: gender === 2 }"
              @click="gender = 2"
            >
              <div class="gender-icon-wrap">
                <svg class="gender-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="9" r="4"/>
                  <path d="M12 14v3M10 17h4M12 21v-1"/>
                </svg>
              </div>
              <span class="gender-text">女</span>
            </button>
          </div>
        </div>

        <!-- 年龄选择 -->
        <div class="form-item">
          <label class="form-label">年龄</label>
          <el-select v-model="age" placeholder="请选择年龄" class="age-select">
            <el-option
              v-for="ageVal in ageOptions"
              :key="ageVal"
              :label="ageVal + '岁'"
              :value="ageVal"
            >
              <span style="font-size: 16px;">{{ ageVal }}岁</span>
            </el-option>
          </el-select>
        </div>
      </div>

      <!-- 舌头照片上传区域 - 核心区域 -->
      <div class="card upload-card">
        <div class="upload-card-header">
          <div class="card-title">
            <div class="title-icon-box">
              <el-icon><CameraFilled /></el-icon>
            </div>
            <span>上传舌头照片</span>
            <span class="required-tag">必填</span>
          </div>
          <p class="upload-desc">拍摄清晰的舌头照片，至少上传一张</p>
        </div>

        <!-- 已上传图片预览 + 上传按钮 -->
        <div class="upload-area">
          <!-- 图片预览网格 -->
          <div v-if="imageList.length > 0" class="image-preview-grid">
            <div
              v-for="(img, index) in imageList"
              :key="index"
              class="image-preview-item"
            >
              <el-image
                :src="img.url"
                :alt="img.name"
                style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;"
                fit="cover"
              />
              <div class="image-remove" @click="removeImage(index)">
                <el-icon><Delete /></el-icon>
              </div>
            </div>
            <!-- 内联添加按钮 -->
            <el-upload
              ref="uploadRef"
              action="#"
              :auto-upload="false"
              :on-change="handleFileChange"
              :show-file-list="false"
              accept="image/*"
              multiple
            >
              <div class="upload-add-btn">
                <el-icon class="add-icon"><Plus /></el-icon>
                <span class="add-text">添加</span>
              </div>
            </el-upload>
          </div>

          <!-- 初始上传触发区域 -->
          <el-upload
            v-if="imageList.length === 0"
            ref="uploadRef"
            action="#"
            :auto-upload="false"
            :on-change="handleFileChange"
            :show-file-list="false"
            accept="image/*"
            multiple
            class="full-width-upload"
          >
            <div class="upload-trigger">
              <p class="upload-value">30秒生成专属健康报告</p>
              <div class="upload-icon-wrapper">
                <el-icon class="upload-icon"><CameraFilled /></el-icon>
              </div>
              <span class="upload-text">点击上传舌头照片</span>
              <span class="upload-hint">AI智能分析您的舌象特征</span>
            </div>
          </el-upload>
        </div>

        <!-- 拍摄指导 -->
        <div class="photo-guide">
          <div class="guide-item">
            <div class="guide-icon-box">
              <el-icon><Sunny /></el-icon>
            </div>
            <div class="guide-text">
              <span class="guide-title">光线充足</span>
              <span class="guide-desc">自然光最佳</span>
            </div>
          </div>
          <div class="guide-item">
            <div class="guide-icon-box">
              <el-icon><Aim /></el-icon>
            </div>
            <div class="guide-text">
              <span class="guide-title">舌面平伸</span>
              <span class="guide-desc">伸出舌头放平</span>
            </div>
          </div>
          <div class="guide-item">
            <div class="guide-icon-box">
              <el-icon><Warning /></el-icon>
            </div>
            <div class="guide-text">
              <span class="guide-title">对焦清晰</span>
              <span class="guide-desc">确保图片清楚</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 症状描述区域 - 可折叠 -->
      <div class="card symptom-card">
        <div class="card-title collapsible" @click="textExpanded = !textExpanded">
          <div class="title-left">
            <div class="title-icon-box">
              <el-icon><ChatLineRound /></el-icon>
            </div>
            <span>症状描述</span>
            <span class="optional-tag">可选</span>
          </div>
          <el-icon class="expand-icon" :class="{ expanded: textExpanded }">
            <ArrowDown />
          </el-icon>
        </div>
        <div v-show="textExpanded" class="symptom-content">
          <el-input
            v-model="aiText"
            type="textarea"
            :rows="4"
            placeholder="请详细描述您的症状（如：最近睡眠不好、口干、舌苔发白等），AI将根据您的描述进行分析..."
            resize="none"
            maxlength="500"
            show-word-limit
          />
        </div>
        <div v-if="!textExpanded" class="symptom-hint">
          点击展开输入症状描述（可选，已上传照片可不填）
        </div>
      </div>

      <!-- 免责声明 -->
      <div class="disclaimer-card">
        <el-icon class="disclaimer-icon"><Warning /></el-icon>
        <div class="disclaimer-content">
          <h4 class="disclaimer-title">免责声明</h4>
          <ul class="disclaimer-list">
            <li>本分析结果仅供健康参考，不作为医疗诊断依据</li>
            <li>如有健康问题，请咨询专业医生</li>
            <li>AI分析存在局限性，不能替代专业医疗建议</li>
          </ul>
        </div>
      </div>

      <!-- 开始分析按钮 -->
      <div class="actions">
        <button
          type="button"
          class="submit-btn"
          :disabled="loading"
          @click="handleSubmit"
        >
          <el-icon class="submit-icon"><MagicStick /></el-icon>
          <span v-if="!loading">开始分析</span>
          <span v-else>分析中...</span>
        </button>
        <div class="credits-hint">
          本次消耗积分 <span class="credits-num">{{ creditsPerAnalysis }}</span>，
          剩余积分 <span class="credits-num">{{ analysisTimes }}</span>
        </div>
      </div>
    </div>

    <!-- 底部固定导航 -->
    <div class="bottom-nav">
      <div class="nav-item active" @click="goToPage('/')">
        <el-icon><HomeFilled /></el-icon>
        <span>首页</span>
      </div>
      <div class="nav-item" @click="goToPage('/promoter')">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>
        <span>邀请</span>
      </div>
      <div class="nav-item" @click="goToPage('/qa/chat')">
        <el-icon><ChatDotRound /></el-icon>
        <span>问答</span>
      </div>
      <div class="nav-item" @click="goToPage('/messages')">
        <el-icon><Message /></el-icon>
        <span>消息</span>
      </div>
      <div class="nav-item" @click="goToPage('/member')">
        <el-icon><User /></el-icon>
        <span>我的</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tongue-page {
  min-height: 100vh;
  background: #F8FAFC;
  padding-bottom: 80px;
  font-family: 'PingFang SC', 'Microsoft YaHei', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* 页面内容 */
.page-content {
  padding: 16px;
}

/* 卡片通用样式 */
.card {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  transition: box-shadow 0.3s;
}

.card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

/* 基本信息卡片 */
.profile-card {
  animation: fadeInUp 0.4s ease-out;
}

/* 表单项 */
.form-item {
  margin-bottom: 16px;
}

.form-item:last-child {
  margin-bottom: 0;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #334155;
  margin-bottom: 10px;
}

/* 性别选择 */
.gender-options {
  display: flex;
  gap: 12px;
}

.gender-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px 12px;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fafc;
  cursor: pointer;
  transition: all 0.3s ease;
}

.gender-btn:hover {
  border-color: #00C777;
  background: #ECFDF5;
}

.gender-btn.active {
  border-color: #00C777;
  background: linear-gradient(135deg, #00C777 0%, #00A86B 100%);
  box-shadow: 0 4px 16px rgba(0, 199, 119, 0.3);
}

.gender-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.gender-btn.active .gender-icon-wrap {
  background: rgba(255, 255, 255, 0.2);
}

.gender-icon {
  width: 24px;
  height: 24px;
  color: #64748b;
  transition: color 0.3s ease;
}

.gender-btn.active .gender-icon {
  color: #fff;
}

.gender-text {
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  transition: color 0.3s ease;
}

.gender-btn.active .gender-text {
  color: #fff;
}

/* 年龄选择 */
.age-select {
  width: 100%;
}

.age-select :deep(.el-input__wrapper) {
  height: 48px;
  border-radius: 12px;
  font-size: 16px;
  box-shadow: 0 0 0 1px #e2e8f0 inset;
  transition: all 0.3s;
}

.age-select :deep(.el-input__wrapper:hover) {
  box-shadow: 0 0 0 1px #00C777 inset;
}

.age-select :deep(.el-input__wrapper.is-focus) {
  box-shadow: 0 0 0 2px #00C777 inset;
}

.age-select :deep(.el-input__inner) {
  font-size: 16px;
  color: #1F2937;
}

.card-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 16px;
  font-weight: 600;
  color: #166534;
  margin-bottom: 16px;
}

.title-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 12px;
  background: #dcfce7;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.title-icon-box .el-icon {
  font-size: 18px;
  color: #166534;
}

/* 上传卡片 - 核心区域 */
.upload-card {
  animation: fadeInUp 0.5s ease-out;
  border: 2px solid rgba(0, 199, 119, 0.1);
  background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%);
  padding: 0;
  overflow: hidden;
  margin-left: -16px;
  margin-right: -16px;
  border-radius: 0;
  width: auto;
}

.upload-card-header {
  padding: 20px 32px 16px;
}

.upload-desc {
  font-size: 13px;
  color: #94a3b8;
  margin: 8px 0 0;
}

.required-tag {
  display: inline-block;
  background: #fef2f2;
  color: #ef4444;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 4px;
  margin-left: auto;
  font-weight: 500;
}

.optional-tag {
  display: inline-block;
  background: #ECFDF5;
  color: #00C777;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 4px;
  margin-left: 6px;
  font-weight: 500;
}

/* 覆盖 Element Plus 默认 inline-flex */
.el-upload {
  display: block;
}

.full-width-upload {
  display: block;
  width: 100%;
}

/* 上传触发区域 */
.upload-trigger {
  width: 100%;
  box-sizing: border-box;
  padding: 32px 20px;
  border: 2px dashed #00C777;
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #64748b;
  cursor: pointer;
  transition: all 0.3s ease;
  background: linear-gradient(135deg, #f0fdf4 0%, #ECFDF5 100%);
}

.upload-trigger:hover {
  background: linear-gradient(135deg, #ECFDF5 0%, #dcfce7 100%);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 199, 119, 0.15);
}

.upload-trigger:active {
  transform: translateY(0);
}

.upload-value {
  font-size: 18px;
  font-weight: 600;
  color: #166534;
  margin: 0 0 16px;
}

.upload-icon-wrapper {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, #00C777 0%, #00A86B 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
  box-shadow: 0 4px 16px rgba(0, 199, 119, 0.3);
}

.upload-icon {
  font-size: 28px;
  color: #fff;
}

.upload-text {
  font-size: 16px;
  font-weight: 600;
  color: #1F2937;
}

.upload-hint {
  margin-top: 6px;
  font-size: 12px;
  color: #94a3b8;
}

/* 内联添加按钮 */
.upload-add-btn {
  aspect-ratio: 1;
  width: 100%;
  height: 100%;
  min-height: 100px;
  border: 2px dashed #00C777;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #00C777;
  cursor: pointer;
  transition: all 0.3s ease;
  background: linear-gradient(135deg, #f0fdf4 0%, #ECFDF5 100%);
  box-sizing: border-box;
}

.upload-add-btn:hover {
  background: linear-gradient(135deg, #ECFDF5 0%, #dcfce7 100%);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 199, 119, 0.15);
}

.add-icon {
  font-size: 24px;
  color: #00C777;
}

.add-text {
  font-size: 12px;
  color: #00C777;
  margin-top: 4px;
}

/* 拍摄指导 */
.photo-guide {
  display: flex;
  justify-content: space-between;
  margin-top: 20px;
  padding: 16px 32px;
  border-top: 1px solid #f1f5f9;
  background: #f8fafc;
}

.guide-item {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}

.guide-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 12px;
  background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.guide-icon-box .el-icon {
  font-size: 16px;
  color: #166534;
}

.guide-text {
  display: flex;
  flex-direction: column;
}

.guide-title {
  font-size: 12px;
  font-weight: 600;
  color: #1F2937;
}

.guide-desc {
  font-size: 11px;
  color: #94a3b8;
}

/* 图片预览 */
.image-preview-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  padding: 0 32px 16px;
}

.image-preview-item {
  position: relative;
  border-radius: 12px;
  overflow: hidden;
  animation: fadeIn 0.3s ease-out;
  aspect-ratio: 1;
  padding: 4px;
  box-sizing: border-box;
}

.image-remove {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 28px;
  height: 28px;
  background: rgba(0, 0, 0, 0.5);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #fff;
  font-size: 14px;
  transition: background 0.3s;
}

.image-remove:hover {
  background: rgba(239, 68, 68, 0.9);
}

/* 症状描述卡片 */
.symptom-card {
  animation: fadeInUp 0.6s ease-out;
}

.card-title.collapsible {
  cursor: pointer;
  margin-bottom: 0;
  padding: 0;
  transition: color 0.2s;
}

.card-title.collapsible:hover {
  color: #00C777;
}

.title-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.expand-icon {
  margin-left: auto;
  color: #94a3b8;
  transition: transform 0.3s;
}

.expand-icon.expanded {
  transform: rotate(180deg);
}

.symptom-content {
  margin-top: 16px;
  animation: slideDown 0.3s ease-out;
}

.symptom-hint {
  font-size: 13px;
  color: #94a3b8;
  padding: 14px;
  background: #f8fafc;
  border-radius: 12px;
  text-align: center;
  margin-top: 8px;
}

/* 免责声明 */
.disclaimer-card {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #FFF7ED;
  border-radius: 16px;
  margin-bottom: 16px;
  animation: fadeInUp 0.7s ease-out;
}

.disclaimer-icon {
  font-size: 24px;
  color: #f59e0b;
  flex-shrink: 0;
}

.disclaimer-content {
  flex: 1;
}

.disclaimer-title {
  font-size: 14px;
  font-weight: 600;
  color: #c2410c;
  margin: 0 0 8px;
}

.disclaimer-list {
  margin: 0;
  padding-left: 18px;
}

.disclaimer-list li {
  font-size: 13px;
  color: #9a3412;
  line-height: 1.8;
}

/* 提交按钮 - 增强 */
.actions {
  margin-top: 24px;
  animation: fadeInUp 0.8s ease-out;
}

.submit-btn {
  width: 100%;
  height: 52px;
  border-radius: 26px;
  border: none;
  background: linear-gradient(135deg, #00C777 0%, #00A86B 100%);
  color: #fff;
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 6px 20px rgba(0, 199, 119, 0.4);
  position: relative;
  overflow: hidden;
}

.submit-icon {
  font-size: 18px;
  margin-right: 6px;
}

/* AI 光效动画 */
.submit-btn::before {
  content: '';
  position: absolute;
  width: 100px;
  height: 100px;
  background: white;
  opacity: 0.2;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% {
    transform: scale(0);
    opacity: 0.3;
  }
  100% {
    transform: scale(4);
    opacity: 0;
  }
}

.submit-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(0, 199, 119, 0.5);
}

.submit-btn:active:not(:disabled) {
  transform: translateY(0);
  box-shadow: 0 4px 12px rgba(0, 199, 119, 0.3);
}

.submit-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.credits-hint {
  margin-top: 12px;
  font-size: 13px;
  color: #64748b;
  text-align: center;
}

.credits-num {
  color: #00C777;
  font-weight: 600;
}

/* 锁定状态 */
.locked-state {
  min-height: 60vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* 结果区域 */
.result-section {
  padding: 16px;
}

/* 综合评分卡片 */
.result-score-card {
  background: linear-gradient(135deg, #00C777 0%, #00A86B 100%);
  border-radius: 20px;
  padding: 32px 20px;
  text-align: center;
  margin-bottom: 20px;
  color: #fff;
  position: relative;
  overflow: hidden;
}

.result-score-card::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 100%;
  height: 100%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 60%);
  pointer-events: none;
}

.score-ring {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 0 auto 16px;
}

.score-ring-bg {
  width: 100%;
  height: 100%;
}

.score-ring-content {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
}

.score-value {
  display: block;
  font-size: 36px;
  font-weight: 700;
  line-height: 1;
}

.score-status {
  font-size: 12px;
  opacity: 0.9;
}

.score-label {
  font-size: 14px;
  opacity: 0.9;
  margin: 0;
}

/* 分项评分 */
.score-details {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 16px;
}

.score-item {
  margin-bottom: 16px;
}

.score-item:last-child {
  margin-bottom: 0;
}

.score-item-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.score-item-label {
  font-size: 14px;
  color: #64748b;
}

.score-item-value {
  font-size: 14px;
  font-weight: 600;
  color: #166534;
}

.score-bar {
  height: 8px;
  background: #f1f5f9;
  border-radius: 4px;
  overflow: hidden;
}

.score-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #00C777 0%, #00A86B 100%);
  border-radius: 4px;
  transition: width 1s ease;
}

.result-summary,
.result-content {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 16px;
}

.result-summary p {
  font-size: 14px;
  color: #64748b;
  line-height: 1.6;
  margin: 0;
}

.content-text {
  font-size: 14px;
  color: #475569;
  line-height: 1.8;
}

.content-text :deep(h3) {
  font-size: 16px;
  color: #166534;
  margin: 16px 0 8px;
}

.content-text :deep(h4) {
  font-size: 14px;
  color: #334155;
  margin: 12px 0 6px;
}

.content-text :deep(strong) {
  color: #00C777;
}

/* 底部固定导航 */
.bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 64px;
  background: #fff;
  box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.05);
  display: flex;
  justify-content: space-around;
  align-items: center;
  z-index: 100;
  max-width: 100%;
  padding: 0 16px;
  margin: 0 auto;
  box-sizing: border-box;
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  padding: 8px 16px;
  border-radius: 8px;
  transition: all 0.2s;
}

.nav-item .el-icon,
.nav-icon {
  font-size: 22px;
  color: #94a3b8;
  transition: color 0.2s;
}

.nav-item span {
  font-size: 11px;
  color: #94a3b8;
  transition: color 0.2s;
}

.nav-item.active .el-icon,
.nav-item.active .nav-icon,
.nav-item.active span {
  color: #00C777;
}

.nav-item:hover .el-icon,
.nav-item:hover .nav-icon,
.nav-item:hover span {
  color: #00C777;
}

.nav-icon {
  width: 22px;
  height: 22px;
}

/* 动画 */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
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
</style>
