<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CameraFilled, ChatLineRound, Delete, User, ArrowDown, ArrowUp } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'

const router = useRouter()

interface ImageItem {
  url: string
  file: File
  name: string
}

const imageList = ref<ImageItem[]>([])
const loading = ref(false)
const aiText = ref('')
const analysisTimes = ref(0)
const gender = ref<number | null>(null)
const age = ref<number | null>(null)
const textExpanded = ref(false) // 症状描述是否展开

// 年龄选项 1-100岁
const ageOptions = Array.from({ length: 100 }, (_, i) => i + 1)

import { getToken } from '@/utils/auth'

// 获取用户剩余分析次数
const fetchAnalysisTimes = async () => {
  try {
    const res = await safeFetch('/api/v1/user/info', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      analysisTimes.value = data.data?.analysis_times ?? 0
    }
  } catch (e) {
    console.error('获取分析次数失败:', e)
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
const submitAnalysis = async (imageUrls: string[], type: 'tongue' | 'face', text: string, gender: number, age: number) => {
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
    return data.data.task_no
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
    // 1. 上传所有图片（如果有）
    let uploadedUrls: string[] = []
    if (imageList.value.length > 0) {
      const uploadPromises = imageList.value.map(img => uploadSingleImage(img.file))
      uploadedUrls = await Promise.all(uploadPromises)
    }

    // 2. 提交分析任务
    const taskNo = await submitAnalysis(uploadedUrls, 'tongue', aiText.value, gender.value, age.value)

    // 直接跳转到分析结果页面
    router.push(`/analysis/result/${taskNo}`)
  } catch (e: any) {
    const msg = e.message || '提交失败'
    if (msg.includes('次数不足') || msg.includes('先购买')) {
      ElMessageBox.confirm(msg, '提示', {
        confirmButtonText: '去购买',
        cancelButtonText: '取消',
        type: 'warning',
      }).then(() => {
        router.push('/packages')
      }).catch(() => {})
    } else {
      ElMessage.error(msg)
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchAnalysisTimes()
})
</script>

<template>
  <div class="tongue-page" v-loading="loading">
    <!-- 图片上传区域（必填） -->
    <div class="upload-section">
      <div class="upload-tip">
        <span class="required-tag">必填</span> 拍摄清晰的舌头照片，至少上传一张
      </div>
      
      <!-- 多张图片预览（展示在上传按钮前面） -->
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
      </div>

      <!-- 上传按钮 -->
      <el-upload
        ref="uploadRef"
        action="#"
        :auto-upload="false"
        :on-change="handleFileChange"
        :on-remove="handleRemove"
        :show-file-list="false"
        accept="image/*"
        multiple
      >
        <div class="upload-trigger">
          <el-icon class="upload-icon"><CameraFilled /></el-icon>
          <span class="upload-text">{{ imageList.length > 0 ? '继续添加照片' : '点击上传舌头照片' }}</span>
          <span class="upload-hint">支持多张上传，建议拍摄舌面、舌侧</span>
        </div>
      </el-upload>
    </div>

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

    <!-- AI 文本输入框（可折叠） -->
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
          placeholder="请详细描述您的症状（如：最近睡眠不好、口干、舌苔发白等），AI将根据您的描述进行分析..."
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
        :disabled="analysisTimes <= 0"
        @click="handleSubmit"
        style="width: 100%"
      >
        开始分析
      </el-button>
      <div v-if="analysisTimes > 0" class="free-tip">
        剩余 {{ analysisTimes }} 次分析次数
      </div>
      <div v-else class="free-tip">
        分析次数不足，请先购买套餐
      </div>
    </div>
  </div>
</template>

<style scoped>
.tongue-page {
  padding: 16px;
}

.ai-text-section {
  margin-top: 16px;
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

.upload-section {
  margin-bottom: 24px;
}

.upload-tip {
  font-size: 14px;
  color: #969799;
  margin-bottom: 16px;
  text-align: center;
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

.required-tip {
  color: #f56c6c;
  font-weight: 600;
  margin-left: 2px;
}

.upload-area {
  width: 100%;
  height: 120px;
  border: 2px dashed #dcdee0;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #969799;
  cursor: pointer;
  transition: border-color 0.3s;
}

.upload-area:hover {
  border-color: #60a5fa;
}

.upload-text {
  margin-top: 8px;
  font-size: 14px;
}

.upload-hint {
  margin-top: 4px;
  font-size: 12px;
  color: #c0c4cc;
}

.image-preview-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}

.image-preview-item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
}

.image-remove {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 24px;
  height: 24px;
  background: rgba(0, 0, 0, 0.5);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #fff;
  font-size: 12px;
  transition: background 0.3s;
}

.image-remove:hover {
  background: rgba(245, 108, 108, 0.8);
}

.actions {
  margin-top: 32px;
}

.free-tip {
  margin-top: 8px;
  font-size: 12px;
  color: #67c23a;
  text-align: center;
}

/* 免责声明 */
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
