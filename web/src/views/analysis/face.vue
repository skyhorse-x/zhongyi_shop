<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { CameraFilled, ChatLineRound } from '@element-plus/icons-vue'
import { safeFetch } from '@/utils/fetch'

const router = useRouter()
const imageUrl = ref('')
const fileName = ref('')
const loading = ref(false)
const aiText = ref('')

const getToken = (): string => localStorage.getItem('token') || ''

const handleFileChange = (uploadFile: any) => {
  if (uploadFile.raw) {
    fileName.value = uploadFile.raw.name
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
const submitAnalysis = async (imageUrl: string, type: 'tongue' | 'face', text: string) => {
  const res = await safeFetch('/api/v1/analysis/submit', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${getToken()}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      type: type,
      image_url: imageUrl,
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
  if (!imageUrl.value && !aiText.value.trim()) {
    ElMessage.warning('请上传面部照片或输入症状描述')
    return
  }
  loading.value = true
  try {
    // 1. 上传图片（如果有）
    let uploadedUrl = ''
    if (imageUrl.value) {
      const response = await fetch(imageUrl.value)
      const blob = await response.blob()
      uploadedUrl = await uploadImage(blob, fileName.value || 'face.jpg')
    }

    // 2. 提交分析任务
    const taskNo = await submitAnalysis(uploadedUrl, 'face', aiText.value)

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
</script>

<template>
  <div class="face-page" v-loading="loading">
    <!-- 症状描述（必填） -->
    <div class="ai-text-section">
      <div class="ai-text-header">
        <el-icon><ChatLineRound /></el-icon>
        <span>症状描述 <span class="required-tip">*</span></span>
      </div>
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

    <div class="upload-section">
      <div class="upload-tip">
        <span class="optional-tag">可选</span> 上传清晰的面部照片可获得更精准的分析结果
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
            <div class="upload-text">点击拍摄或上传</div>
          </div>
        </template>
      </el-upload>

      <div v-if="imageUrl" class="image-preview">
        <el-image :src="imageUrl" alt="面部照片" style="max-width: 100%; border-radius: 12px;" />
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
        开始分析
      </el-button>
    </div>
  </div>
</template>

<style scoped>
.face-page {
  padding: 16px;
}

/* 文本输入区 */
.ai-text-section {
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

.upload-area {
  width: 100%;
  height: 200px;
  border: 2px dashed #dcdee0;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #969799;
  cursor: pointer;
}

.upload-text {
  margin-top: 8px;
  font-size: 14px;
}

.image-preview {
  margin-top: 16px;
  text-align: center;
}

.actions {
  margin-top: 32px;
}
</style>
