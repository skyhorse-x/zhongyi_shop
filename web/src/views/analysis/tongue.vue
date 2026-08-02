<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CameraFilled, ChatLineRound, Delete } from '@element-plus/icons-vue'
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
const analysisMode = ref('paid')
const analysisPrice = ref(9.99)

const getToken = (): string => localStorage.getItem('token') || ''

// 获取分析模式配置
const fetchAnalysisMode = async () => {
  try {
    const res = await safeFetch('/api/v1/analysis/config', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      analysisMode.value = data.data.analysis_mode || 'paid'
      analysisPrice.value = data.data.analysis_price || 9.99
    }
  } catch (e) {
    console.error('获取分析配置失败:', e)
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
const submitAnalysis = async (imageUrls: string[], type: 'tongue' | 'face', text: string) => {
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
    }),
  })
  const data = await res.json()
  if (data.code === 0) {
    return data.data.task_no
  }
  throw new Error(data.message || '提交失败')
}

const handleSubmit = async () => {
  if (imageList.value.length === 0 && !aiText.value.trim()) {
    ElMessage.warning('请至少上传一张舌头照片或输入症状描述')
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
    const taskNo = await submitAnalysis(uploadedUrls, 'tongue', aiText.value)

    ElMessage.success('分析已提交')
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
  fetchAnalysisMode()
})
</script>

<template>
  <div class="tongue-page" v-loading="loading">
    <!-- AI 文本输入框 -->
    <div class="ai-text-section">
      <div class="ai-text-header">
        <el-icon><ChatLineRound /></el-icon>
        <span>症状描述 <span class="required-tip">*</span></span>
      </div>
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

    <!-- 图片上传区域（可选） -->
    <div class="upload-section">
      <div class="upload-tip">
        <span class="optional-tag">可选</span> 拍摄清晰的舌头照片可获得更精准的分析结果
      </div>
      
      <el-upload
        :auto-upload="false"
        accept="image/*"
        capture="environment"
        :show-file-list="false"
        @change="handleFileChange"
        multiple
      >
        <template #trigger>
          <div class="upload-area">
            <el-icon :size="48"><CameraFilled /></el-icon>
            <div class="upload-text">点击拍摄或上传</div>
            <div class="upload-hint">已上传 {{ imageList.length }} 张</div>
          </div>
        </template>
      </el-upload>

      <!-- 多张图片预览 -->
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
    </div>

    <div class="actions">
      <el-button
        round
        type="primary"
        :loading="loading"
        @click="handleSubmit"
        style="width: 100%"
      >
        {{ analysisMode === 'free' ? '开始分析' : `开始分析 ¥${analysisPrice}` }}
      </el-button>
      <div v-if="analysisMode === 'free'" class="free-tip">
        当前为免费分析模式
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
  margin-top: 16px;
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
</style>
