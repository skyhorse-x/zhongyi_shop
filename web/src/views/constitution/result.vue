<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { safeFetch } from '@/utils/fetch'
import { ElMessage } from 'element-plus'
import { Check, Share, Download, Picture } from '@element-plus/icons-vue'
import html2canvas from 'html2canvas'

const route = useRoute()
const router = useRouter()

// 从路由参数获取任务编号
const taskNo = computed(() => route.params.taskNo as string)

// 加载中状态
const loading = ref(false)
const sharing = ref(false)

// 体质分析结果数据
const result = ref({
  constitutionType: '',
  score: 0,
  description: '',
  characteristics: [] as string[],
  suggestions: [] as { category: string; content: string }[],
  scores: [] as { name: string; score: number; isMain: boolean }[],
})

// 分享图片引用
const shareRef = ref<HTMLElement | null>(null)

import { getToken } from '@/utils/auth'

const getAuthToken = (): string => getToken() || ''

// 生成分享图片
const generateShareImage = async () => {
  if (!shareRef.value) return null
  try {
    // 等待 DOM 渲染完成
    await nextTick()
    await new Promise(resolve => setTimeout(resolve, 500))

    const canvas = await html2canvas(shareRef.value, {
      backgroundColor: '#f7f8fa',
      scale: 2,
      useCORS: true,
      logging: false,
      windowWidth: 375,
      width: shareRef.value.scrollWidth,
      height: shareRef.value.scrollHeight,
      scrollX: 0,
      scrollY: 0,
    })
    return canvas.toDataURL('image/png')
  } catch (e) {
    console.error('生成分享图片失败:', e)
    return null
  }
}

// 下载图片
const handleDownloadImage = async () => {
  sharing.value = true
  try {
    const dataUrl = await generateShareImage()
    if (dataUrl) {
      const link = document.createElement('a')
      link.download = `体质测试报告-${taskNo.value}.png`
      link.href = dataUrl
      link.click()
      ElMessage.success('图片已下载')
    } else {
      ElMessage.error('生成图片失败')
    }
  } finally {
    sharing.value = false
  }
}

// 分享到社交平台
const handleShare = async () => {
  sharing.value = true
  try {
    const dataUrl = await generateShareImage()
    if (!dataUrl) {
      ElMessage.error('生成分享图片失败')
      return
    }

    // 检查是否支持 Web Share API
    if (navigator.share && navigator.canShare) {
      // 将 dataURL 转换为 Blob
      const response = await fetch(dataUrl)
      const blob = await response.blob()
      const file = new File([blob], `体质测试报告-${taskNo.value}.png`, { type: 'image/png' })

      if (navigator.canShare({ files: [file] })) {
        await navigator.share({
          title: '中医体质测试报告',
          text: `我的体质类型：${result.value.constitutionType}，得分：${result.value.score}分`,
          files: [file],
        })
        ElMessage.success('分享成功')
        return
      }
    }

    // 不支持 Web Share API，显示分享选项
    showShareOptions(dataUrl)
  } catch (e: any) {
    if (e.name !== 'AbortError') {
      console.error('分享失败:', e)
    }
  } finally {
    sharing.value = false
  }
}

// 显示分享选项
const showShareOptions = (dataUrl: string) => {
  // 创建分享弹窗
  const shareText = `我的体质类型：${result.value.constitutionType}，得分：${result.value.score}分`
  const shareUrl = window.location.href

  // 生成各平台分享链接
  const weiboUrl = `https://service.weibo.com/share/share.php?url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(shareText)}`
  const qqUrl = `https://connect.qq.com/widget/shareqq/index.html?url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(shareText)}`

  // 显示分享选项
  const shareOptions = [
    { name: '新浪微博', url: weiboUrl, icon: '🔗' },
    { name: 'QQ', url: qqUrl, icon: '💬' },
    { name: '下载图片', url: '', icon: '📷', action: 'download' },
  ]

  // 创建临时弹窗
  const modal = document.createElement('div')
  modal.className = 'share-modal'
  modal.innerHTML = `
    <div class="share-modal-overlay">
      <div class="share-modal-content">
        <div class="share-modal-title">分享到</div>
        <div class="share-modal-options">
          ${shareOptions.map(opt => `
            <div class="share-option" data-url="${opt.url}" data-action="${opt.action || ''}">
              <span class="share-option-icon">${opt.icon}</span>
              <span class="share-option-name">${opt.name}</span>
            </div>
          `).join('')}
        </div>
        <div class="share-modal-cancel">取消</div>
      </div>
    </div>
  `
  document.body.appendChild(modal)

  // 点击选项
  modal.querySelectorAll('.share-option').forEach(opt => {
    opt.addEventListener('click', () => {
      const url = (opt as HTMLElement).dataset.url
      const action = (opt as HTMLElement).dataset.action
      if (action === 'download') {
        const link = document.createElement('a')
        link.download = `体质测试报告-${taskNo.value}.png`
        link.href = dataUrl
        link.click()
        ElMessage.success('图片已下载')
      } else if (url) {
        window.open(url, '_blank', 'width=600,height=500')
      }
      modal.remove()
    })
  })

  // 点击遮罩关闭
  modal.querySelector('.share-modal-overlay')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) modal.remove()
  })
}

// 体质类型对应颜色
const constitutionColor = computed(() => {
  const colorMap: Record<string, string> = {
    '平和质': '#52c41a',
    '气虚质': '#faad14',
    '阳虚质': '#1890ff',
    '阴虚质': '#722ed1',
    '痰湿质': '#fa8c16',
    '湿热质': '#f5222d',
    '血瘀质': '#eb2f96',
    '气郁质': '#13c2c2',
    '特禀质': '#8c8c8c',
  }
  return colorMap[result.value.constitutionType] || '#4f8cff'
})

// 加载测试结果
const loadResult = async () => {
  if (!taskNo.value) {
    ElMessage.error('任务编号无效')
    router.back()
    return
  }
  loading.value = true
  try {
    const res = await safeFetch(`/api/v1/constitution/report/${taskNo.value}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()

    if (data.code === 0) {
      const report = data.data
      result.value = {
        constitutionType: report.constitution_type,
        score: Math.max(...(report.scores ? Object.values(report.scores) as number[] : [0])),
        description: report.features || '',
        characteristics: report.features ? [report.features] : [],
        suggestions: [
          { category: '饮食建议', content: report.diet_advice || '请咨询专业中医师获取饮食建议' },
          { category: '运动建议', content: report.exercise_advice || '请咨询专业中医师获取运动建议' },
          { category: '起居建议', content: report.life_advice || '请咨询专业中医师获取起居建议' },
          { category: '情志建议', content: report.emotion_advice || '请咨询专业中医师获取情志调节建议' },
        ],
        scores: report.scores
          ? Object.entries(report.scores).map(([name, score]) => ({
              name,
              score: score as number,
              isMain: name === report.constitution_type,
            }))
          : [],
      }
    } else {
      ElMessage.error(data.message || '获取报告失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '获取报告失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadResult()
})
</script>

<template>
  <div class="constitution-result-page" v-loading="loading" element-loading-text="正在加载报告...">
    <template v-if="!loading && result.constitutionType">
      <!-- 分享图片区域 -->
      <div class="share-content" ref="shareRef">
        <!-- 报告头部 -->
        <div class="result-header">
          <div class="header-top">
            <div class="task-no">报告编号：{{ taskNo }}</div>
            <div class="report-date">生成时间：{{ new Date().toLocaleDateString('zh-CN') }}</div>
          </div>
          <div class="constitution-badge">
            {{ result.constitutionType }}
          </div>
          <div class="score-section">
            <div class="score-circle">
              <div class="score-text">
                <div class="score-value">{{ result.score }}</div>
                <div class="score-label">分</div>
              </div>
            </div>
          </div>
        </div>

        <!-- 体质描述 -->
        <div class="result-section">
          <div class="section-title">体质概述</div>
          <div class="section-content">
            <p>{{ result.description }}</p>
          </div>
        </div>

        <!-- 主要特征 -->
        <div v-if="result.characteristics.length > 0" class="result-section">
          <div class="section-title">主要特征</div>
          <div class="section-content">
            <div class="characteristic-list">
              <div
                v-for="(item, index) in result.characteristics"
                :key="index"
                class="characteristic-item"
              >
                <span class="check-icon">✓</span>
                <span>{{ item }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 调理建议 -->
        <div class="result-section">
          <div class="section-title">调理建议</div>
          <div class="section-content">
            <div class="suggestion-list">
              <div
                v-for="(item, index) in result.suggestions"
                :key="index"
                class="suggestion-card"
              >
                <div class="suggestion-category">{{ item.category }}</div>
                <div class="suggestion-content">{{ item.content }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- 九种体质得分 -->
        <div v-if="result.scores.length > 0" class="result-section">
          <div class="section-title">九种体质得分</div>
          <div class="section-content">
            <div class="scores-list">
              <div
                v-for="(item, index) in result.scores"
                :key="index"
                class="score-bar-item"
              >
                <div class="score-bar-label">
                  <span :class="{ 'main-type': item.isMain }">{{ item.name }}</span>
                  <span v-if="item.isMain" class="main-tag">主导体质</span>
                </div>
                <div class="score-bar-track">
                  <div
                    class="score-bar-fill"
                    :style="{
                      width: item.score + '%',
                      background: item.isMain ? 'linear-gradient(90deg, #07c160 0%, #04a152 100%)' : '#dcdee0',
                    }"
                  ></div>
                </div>
                <div class="score-bar-value">{{ item.score }}分</div>
              </div>
            </div>
          </div>
        </div>

        <!-- 底部标识 -->
        <div class="share-footer">
          <div class="footer-logo">中医AI助手</div>
          <div class="footer-tip">本报告由AI智能分析生成，仅供参考</div>
        </div>
      </div>

      <!-- 操作按钮（不纳入分享图片） -->
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
              <p>1. 本报告基于问卷结果自动生成，仅供参考，不能作为医疗诊断依据。</p>
              <p>2. 如有健康问题，请咨询专业医疗机构或中医师。</p>
              <p>3. 调理建议在专业医师指导下进行，切勿自行用药。</p>
            </div>
          </template>
        </el-alert>
      </div>

      <!-- 底部操作 -->
      <div class="result-actions">
        <el-button
          round
          plain
          type="primary"
          :icon="Share"
          :loading="sharing"
          @click="handleShare"
        >
          分享
        </el-button>
        <el-button
          round
          plain
          type="primary"
          :icon="Download"
          :loading="sharing"
          @click="handleDownloadImage"
        >
          下载图片
        </el-button>
        <el-button
          round
          plain
          type="primary"
          @click="router.push('/constitution/test')"
        >
          重新测试
        </el-button>
        <el-button
          round
          type="primary"
          @click="router.push('/qa/chat')"
        >
          咨询调理方案
        </el-button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.constitution-result-page {
  padding: 16px;
  padding-bottom: 32px;
  min-height: 100vh;
  background: #f7f8fa;
  box-sizing: border-box;
}

/* 分享图片区域 */
.share-content {
  background: #f7f8fa;
  padding-bottom: 16px;
}

.result-header {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 16px;
  padding: 24px 20px;
  margin-bottom: 16px;
  box-shadow: 0 4px 16px rgba(7, 193, 96, 0.2);
}

.header-top {
  display: flex;
  justify-content: space-between;
  margin-bottom: 16px;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.85);
}

.task-no {
  font-family: monospace;
}

.constitution-badge {
  display: inline-block;
  padding: 8px 24px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.95) !important;
  color: #07c160 !important;
  font-size: 20px;
  font-weight: bold;
  margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.score-section {
  display: flex;
  justify-content: center;
}

.score-circle {
  position: relative;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  padding: 8px;
}

.score-text {
  text-align: center;
}

.score-value {
  font-size: 28px;
  font-weight: bold;
  color: #fff;
}

.score-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.8);
}

.result-section {
  background: #fff;
  border-radius: 16px;
  padding: 18px;
  margin-bottom: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  border: 1px solid #f0f0f0;
}

.section-title {
  font-size: 16px;
  font-weight: bold;
  color: #1a1a1a;
  margin-bottom: 14px;
  padding-left: 10px;
  border-left: 3px solid #07c160;
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-title::before {
  content: '';
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #07c160;
}

.section-content {
  font-size: 14px;
  color: #646566;
  line-height: 1.8;
}

.characteristic-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.characteristic-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #323233;
  padding: 8px 12px;
  background: #f6fdf9;
  border-radius: 8px;
}

.check-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #07c160;
  color: #fff;
  font-size: 12px;
  font-weight: bold;
  flex-shrink: 0;
}

.suggestion-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.suggestion-card {
  background: #f7f8fa;
  border-radius: 12px;
  padding: 14px;
  border-left: 3px solid #07c160;
}

.suggestion-category {
  font-size: 14px;
  font-weight: bold;
  color: #07c160;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.suggestion-category::before {
  content: '✓';
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #07c160;
  color: #fff;
  font-size: 12px;
}

.suggestion-content {
  font-size: 13px;
  color: #646566;
  line-height: 1.6;
}

.scores-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.score-bar-item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.score-bar-label {
  width: 90px;
  font-size: 13px;
  color: #323233;
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.score-bar-label .main-type {
  font-weight: bold;
  color: #07c160;
}

.main-tag {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  background: #07c160;
  color: #fff;
  line-height: 1;
}

.score-bar-track {
  flex: 1;
  height: 10px;
  background: #f5f5f5;
  border-radius: 5px;
  overflow: hidden;
}

.score-bar-fill {
  height: 100%;
  border-radius: 5px;
  transition: width 0.6s ease;
}

.score-bar-value {
  width: 45px;
  font-size: 12px;
  color: #969799;
  text-align: right;
  flex-shrink: 0;
}

.share-footer {
  text-align: center;
  padding: 24px 0 8px;
  border-top: 1px solid #f0f0f0;
  margin-top: 16px;
}

.footer-logo {
  font-size: 16px;
  font-weight: bold;
  color: #07c160;
  margin-bottom: 4px;
}

.footer-tip {
  font-size: 11px;
  color: #969799;
}

.result-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
  padding-bottom: 16px;
}

.result-actions .el-button {
  flex: 1;
}

.result-actions .el-button--primary {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border: none;
}

.result-actions .el-button--primary.is-plain {
  background: #fff;
  color: #07c160;
  border: 1px solid #07c160;
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

/* 分享弹窗 */
.share-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;
}

.share-modal-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.share-modal-content {
  background: #fff;
  border-radius: 16px 16px 0 0;
  width: 100%;
  max-width: 500px;
  padding: 20px 20px 32px;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    transform: translateY(100%);
  }
  to {
    transform: translateY(0);
  }
}

.share-modal-title {
  font-size: 16px;
  font-weight: bold;
  color: #1a1a1a;
  text-align: center;
  margin-bottom: 20px;
}

.share-modal-options {
  display: flex;
  justify-content: space-around;
  margin-bottom: 20px;
}

.share-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 12px 20px;
  border-radius: 12px;
  transition: background 0.2s;
}

.share-option:hover {
  background: #f5f5f5;
}

.share-option-icon {
  font-size: 32px;
}

.share-option-name {
  font-size: 13px;
  color: #646566;
}

.share-modal-cancel {
  text-align: center;
  font-size: 16px;
  color: #969799;
  padding: 12px;
  cursor: pointer;
  border-top: 1px solid #f0f0f0;
}

.share-modal-cancel:hover {
  color: #646566;
}
</style>