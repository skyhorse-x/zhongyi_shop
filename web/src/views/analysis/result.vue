<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import html2canvas from 'html2canvas'
import { safeFetch } from '@/utils/fetch'
import {
  Loading, Refresh, Check, Calendar, Document, Histogram,
  Promotion, Sunny, ChatLineRound, Star, Trophy, Share, Download, Picture, InfoFilled
} from '@element-plus/icons-vue'

const route = useRoute()
const router = useRouter()

const taskNo = ref('')
const loading = ref(true)
const taskStatus = ref(0)
const pollTimer = ref<ReturnType<typeof setInterval> | null>(null)
const pollCount = ref(0)
const analysisMode = ref<'image' | 'text'>('image')

interface SectionItem {
  text: string
  isBold?: boolean
}

interface ReportSection {
  title: string
  icon: any
  level: number
  items: SectionItem[]
}

interface AnalysisResult {
  title: string
  type: 'tongue' | 'face'
  summary: string
  content: string
  healthScore: number
  mode: string
  details: { label: string; value: string; icon: any }[]
  suggestions: string[]
  createdAt: string
}

const result = ref<AnalysisResult | null>(null)

// 图片导出相关
const exportRef = ref<HTMLElement | null>(null)
const downloading = ref(false)

import { getToken } from '@/utils/auth'

const fetchStatus = async () => {
  try {
    pollCount.value++
    const res = await safeFetch(`/api/v1/analysis/status/${taskNo.value}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      taskStatus.value = data.data.status
      if (taskStatus.value === 2) {
        clearInterval(pollTimer.value!)
        await fetchReport()
      } else if (taskStatus.value === 3) {
        clearInterval(pollTimer.value!)
        loading.value = false
        ElMessage.error('分析失败，请重试')
      } else if (pollCount.value > 40) {
        // 超过 2 分钟（40次 * 3秒）仍在处理，提示用户稍后查看
        clearInterval(pollTimer.value!)
        loading.value = false
        ElMessage.warning('分析时间较长，请稍后在"我的历史"中查看结果')
      }
    }
  } catch (e: any) {
    clearInterval(pollTimer.value!)
    ElMessage.error(e.message || '查询状态失败')
    loading.value = false
  }
}

const fetchReport = async () => {
  try {
    const res = await safeFetch(`/api/v1/analysis/report/${taskNo.value}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const task = data.data
      const isTongue = task.type === 'tongue'
      analysisMode.value = task.result?.mode || 'image'
      result.value = {
        title: isTongue ? '舌诊分析报告' : '面诊分析报告',
        type: task.type,
        summary: task.result?.summary || '分析完成',
        content: task.result?.content || '',
        healthScore: task.health_score || 85,
        mode: task.result?.mode || 'image',
        createdAt: task.created_at || '-',
        details: [
          { label: '分析类型', value: isTongue ? '舌诊分析' : '面诊分析', icon: Histogram },
          { label: '分析方式', value: analysisMode.value === 'text' ? '症状描述' : '图像分析', icon: Document },
          { label: '分析编号', value: task.task_no, icon: Promotion },
          { label: '完成时间', value: formatDateTime(task.created_at), icon: Calendar },
        ],
        suggestions: parseSuggestions(task.result?.content || ''),
      }
    } else if (data.code === 402) {
      ElMessage.warning(data.message || '请先支付查看报告')
      const isTongue = data.data?.type === 'tongue'
      result.value = {
        title: isTongue ? '舌诊分析报告' : '面诊分析报告',
        type: isTongue ? 'tongue' : 'face',
        summary: data.data?.summary || '分析完成',
        content: '',
        healthScore: 0,
        mode: 'image',
        createdAt: data.data?.created_at || '-',
        details: [
          { label: '分析类型', value: isTongue ? '舌诊分析' : '面诊分析', icon: Histogram },
          { label: '分析编号', value: data.data?.task_no || taskNo.value, icon: Promotion },
        ],
        suggestions: ['完整报告需要支付后查看'],
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

// 新的报告解析逻辑 - 适配优化后的报告格式
const parsedReport = computed(() => {
  if (!result.value?.content) return null

  const content = result.value.content
  const lines = content.split('\n')
  
  // 解析健康评分
  const scoreMatch = content.match(/健康评分[：:]\s*(\d+)\s*\/\s*100/)
  const healthScore = scoreMatch ? parseInt(scoreMatch[1]) : result.value.healthScore
  
  // 解析评分依据
  const scoreItems: { text: string; isPositive: boolean }[] = []
  let inScoreSection = false
  for (const line of lines) {
    const trimmed = line.trim()
    if (trimmed.includes('评分依据') || trimmed.includes('评分说明')) {
      inScoreSection = true
      continue
    }
    if (inScoreSection) {
      if (trimmed.startsWith('+')) {
        scoreItems.push({ text: trimmed.replace(/^\+\s*/, '').trim(), isPositive: true })
      } else if (trimmed.startsWith('-') && !trimmed.startsWith('---')) {
        scoreItems.push({ text: trimmed.replace(/^-\s*/, '').trim(), isPositive: false })
      } else if (trimmed.startsWith('##') || trimmed === '---') {
        break
      }
    }
  }
  
  // 解析一句话总结
  let summary = result.value.summary
  const summaryMatch = content.match(/一句话总结[：:]?\s*\n([^\n]+)/)
  if (summaryMatch) {
    summary = summaryMatch[1].trim()
  }
  
  // 解析AI置信度
  const confidence: Record<string, string> = {}
  let inConfidenceSection = false
  for (const line of lines) {
    const trimmed = line.trim()
    if (trimmed.includes('置信度') || trimmed.includes('可信度')) {
      inConfidenceSection = true
      continue
    }
    if (inConfidenceSection) {
      const confMatch = trimmed.match(/[：:]\s*(\d+)%/)
      if (confMatch) {
        const label = trimmed.split(/[：:]/)[0].replace(/^[-*]\s*/, '').trim()
        const value = confMatch[1]
        confidence[label] = value + '%'
      }
      if (trimmed.startsWith('##') || trimmed === '---') {
        break
      }
    }
  }
  
  return {
    healthScore,
    scoreItems,
    summary,
    confidence,
    rawContent: content
  }
})

// 解析 Markdown 内容为章节（适配新格式）
const parsedSections = computed<ReportSection[]>(() => {
  if (!result.value?.content) return []

  const sections: ReportSection[] = []
  const lines = result.value.content.split('\n')
  let currentSection: ReportSection | null = null

  // 新的图标映射
  const iconMap: Record<string, any> = {
    '观察': Sunny,
    '舌象': Sunny,
    '推断': Sunny,
    '辨证': Trophy,
    '体质倾向': Trophy,
    '建议': Star,
    '调养': Star,
    '注意': ChatLineRound,
    '提示': ChatLineRound,
    '温馨': ChatLineRound,
    '风险': ChatLineRound,
    '了解': Promotion,
    '进一步': Promotion,
    '分析说明': Document,
    '评分': Histogram,
    '总结': Check,
  }

  for (const line of lines) {
    const trimmed = line.trim()
    if (!trimmed) continue

    // 跳过评分依据部分（已在评分区域展示）
    if (trimmed.includes('评分依据') || trimmed.includes('评分说明')) {
      // 跳过到下一个 ## 标题
      if (currentSection) {
        currentSection = null
      }
      continue
    }

    // 匹配 ## 标题
    const h2Match = trimmed.match(/^##\s+(.+)$/)
    if (h2Match) {
      const title = h2Match[1].trim()
      // 跳过评分相关标题
      if (title.includes('健康评分') || title.includes('评分')) {
        currentSection = null
        continue
      }
      const matchedKey = Object.keys(iconMap).find(k => title.includes(k))
      currentSection = {
        title,
        icon: iconMap[matchedKey || ''] || Document,
        level: 2,
        items: [],
      }
      sections.push(currentSection)
      continue
    }

    // 匹配 ### 标题
    const h3Match = trimmed.match(/^###\s+(.+)$/)
    if (h3Match) {
      const title = h3Match[1].trim()
      const matchedKey = Object.keys(iconMap).find(k => title.includes(k))
      currentSection = {
        title,
        icon: iconMap[matchedKey || ''] || Document,
        level: 3,
        items: [],
      }
      sections.push(currentSection)
      continue
    }

    // 匹配 - 列表项（包括 ○ 和 □）
    const listMatch = trimmed.match(/^[-*○□]\s+(.+)$/)
    if (listMatch) {
      if (!currentSection) {
        currentSection = { title: '分析内容', icon: Document, level: 2, items: [] }
        sections.push(currentSection)
      }
      const text = listMatch[1].trim()
      // 解析 **加粗** 标记
      const segments = text.split(/(\*\*[^*]+\*\*)/g).filter(Boolean)
      const items: SectionItem[] = segments.map(seg => ({
        text: seg.replace(/\*\*/g, ''),
        isBold: seg.startsWith('**') && seg.endsWith('**'),
      }))
      currentSection.items.push(...items.length > 1 ? items : [{ text }])
      continue
    }

    // 匹配数字列表
    const numMatch = trimmed.match(/^\d+\.\s+(.+)$/)
    if (numMatch) {
      if (!currentSection) {
        currentSection = { title: '分析内容', icon: Document, level: 2, items: [] }
        sections.push(currentSection)
      }
      currentSection.items.push({ text: numMatch[1].trim() })
      continue
    }

    // 普通段落（跳过空行和分隔线）
    if (currentSection && !trimmed.startsWith('#') && trimmed !== '---') {
      currentSection.items.push({ text: trimmed })
    }
  }

  return sections
})

// 解析建议列表
const parseSuggestions = (content: string): string[] => {
  const suggestions: string[] = []
  const lines = content.split('\n')
  let inSuggestionSection = false

  for (const line of lines) {
    const trimmed = line.trim()
    if (trimmed.includes('健康建议') || trimmed.includes('调理建议') || trimmed.includes('健康调理')) {
      inSuggestionSection = true
      continue
    }
    if (inSuggestionSection && trimmed.startsWith('-')) {
      const text = trimmed.replace(/^[-*]\s*/, '')
      // 提取 - 后面的实际内容（去掉 **标签**：）
      const cleanText = text.replace(/^\*\*[^*]+\*\*[：:]\s*/, '')
      if (cleanText) suggestions.push(cleanText)
    } else if (inSuggestionSection && trimmed.match(/^\d+\./)) {
      const text = trimmed.replace(/^\d+\.\s*/, '')
      const cleanText = text.replace(/^\*\*[^*]+\*\*[：:]\s*/, '')
      if (cleanText) suggestions.push(cleanText)
    }
  }

  return suggestions.length > 0 ? suggestions.slice(0, 6) : ['请咨询专业中医师获取详细建议']
}

const formatDateTime = (dateStr: string) => {
  if (!dateStr || dateStr === '-') return '-'
  try {
    const date = new Date(dateStr)
    if (isNaN(date.getTime())) return dateStr
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    const h = String(date.getHours()).padStart(2, '0')
    const min = String(date.getMinutes()).padStart(2, '0')
    return `${y}-${m}-${d} ${h}:${min}`
  } catch {
    return dateStr
  }
}

// 评分等级
const scoreLevel = computed(() => {
  const score = result.value?.healthScore || 0
  if (score >= 80) return { label: '良好', color: '#07c160', desc: '整体状态良好，请继续保持健康的生活方式' }
  if (score >= 60) return { label: '一般', color: '#e6a23c', desc: '存在一些亚健康状态，建议注意调理' }
  return { label: '需关注', color: '#f56c6c', desc: '存在明显健康问题，建议及时就医咨询' }
})

// 健康评分角度（用于环形进度）- 使用解析后的评分
const scoreDashArray = computed(() => {
  const score = parsedReport.value?.healthScore || result.value?.healthScore || 0
  const circumference = 2 * Math.PI * 54
  const offset = circumference - (score / 100) * circumference
  return { circumference, offset }
})

const startPolling = () => {
  pollTimer.value = setInterval(() => {
    fetchStatus()
  }, 3000)
}

const handleRetry = () => {
  loading.value = true
  taskStatus.value = 0
  result.value = null
  startPolling()
}

// 分享到社交平台
const handleShare = async () => {
  downloading.value = true
  try {
    if (!exportRef.value || !result.value) {
      ElMessage.error('报告未加载完成')
      return
    }

    // 等待 DOM 渲染完成
    await nextTick()
    await new Promise(resolve => setTimeout(resolve, 300))

    const canvas = await html2canvas(exportRef.value, {
      backgroundColor: '#f0f9f4',
      scale: 2,
      useCORS: true,
      allowTaint: true,
      logging: false,
      windowWidth: exportRef.value.scrollWidth,
      windowHeight: exportRef.value.scrollHeight,
    })

    const dataUrl = canvas.toDataURL('image/png', 1.0)

    // 检查是否支持 Web Share API
    if (navigator.share && navigator.canShare) {
      const response = await fetch(dataUrl)
      const blob = await response.blob()
      const file = new File([blob], `${result.value.title}-${taskNo.value}.png`, { type: 'image/png' })

      if (navigator.canShare({ files: [file] })) {
        await navigator.share({
          title: result.value.title,
          text: `${result.value.title} - 健康评分：${result.value.healthScore}分`,
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
      ElMessage.error('分享失败')
    }
  } finally {
    downloading.value = false
  }
}

// 显示分享选项
const showShareOptions = (dataUrl: string) => {
  const shareText = `${result.value?.title} - 健康评分：${result.value?.healthScore}分`
  const shareUrl = window.location.href

  const weiboUrl = `https://service.weibo.com/share/share.php?url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(shareText)}`
  const qqUrl = `https://connect.qq.com/widget/shareqq/index.html?url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(shareText)}`

  const shareOptions = [
    { name: '新浪微博', url: weiboUrl, icon: '🔗' },
    { name: 'QQ', url: qqUrl, icon: '💬' },
    { name: '下载图片', url: '', icon: '📷', action: 'download' },
  ]

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

  modal.querySelectorAll('.share-option').forEach(opt => {
    opt.addEventListener('click', () => {
      const url = (opt as HTMLElement).dataset.url
      const action = (opt as HTMLElement).dataset.action
      if (action === 'download') {
        const link = document.createElement('a')
        link.download = `${result.value?.title}-${taskNo.value}.png`
        link.href = dataUrl
        link.click()
        ElMessage.success('图片已下载')
      } else if (url) {
        window.open(url, '_blank', 'width=600,height=500')
      }
      modal.remove()
    })
  })

  modal.querySelector('.share-modal-overlay')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) modal.remove()
  })
}

// 下载文本报告
const handleDownload = () => {
  if (!result.value) return
  const content = `${result.value.title}\n\n健康评分：${result.value.healthScore}\n\n${result.value.content}`
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${result.value.title}-${result.value.details[2]?.value || taskNo.value}.txt`
  link.click()
  URL.revokeObjectURL(url)
  ElMessage.success('报告已下载')
}

// 下载图片报告
const handleDownloadImage = async () => {
  if (!exportRef.value || !result.value) {
    ElMessage.error('报告未加载完成')
    return
  }

  downloading.value = true
  ElMessage.info('正在生成图片，请稍候...')

  try {
    // 等待 DOM 渲染完成
    await nextTick()
    // 等待图片字体加载完成
    await new Promise(resolve => setTimeout(resolve, 300))

    const canvas = await html2canvas(exportRef.value, {
      backgroundColor: '#f0f9f4',
      scale: 2, // 2倍清晰度
      useCORS: true,
      allowTaint: true,
      logging: false,
      windowWidth: exportRef.value.scrollWidth,
      windowHeight: exportRef.value.scrollHeight,
    })

    // 转换为图片并下载
    const imageUrl = canvas.toDataURL('image/png', 1.0)
    const link = document.createElement('a')
    const fileName = result.value.details[2]?.value || taskNo.value
    link.href = imageUrl
    link.download = `${result.value.title}-${fileName}.png`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)

    ElMessage.success('图片已下载，可分享到朋友圈')
  } catch (e: any) {
    console.error('生成图片失败:', e)
    ElMessage.error(e.message || '生成图片失败，请重试')
  } finally {
    downloading.value = false
  }
}

onMounted(() => {
  taskNo.value = route.params.taskNo as string
  if (!taskNo.value) {
    ElMessage.error('任务编号无效')
    router.replace('/')
    return
  }
  startPolling()
  fetchStatus()
})

onUnmounted(() => {
  if (pollTimer.value) {
    clearInterval(pollTimer.value)
  }
})
</script>

<template>
  <div class="result-page">
    <!-- 加载状态 -->
    <div v-if="loading" class="loading-container">
      <div class="loading-animation">
        <div class="pulse-circle"></div>
        <div class="pulse-circle pulse-delay-1"></div>
        <div class="pulse-circle pulse-delay-2"></div>
        <el-icon class="loading-icon" :size="40"><Loading /></el-icon>
      </div>
      <div class="loading-text">
        <template v-if="taskStatus === 0">AI 分析中，请稍候...</template>
        <template v-else-if="taskStatus === 1">AI 分析中，请稍候...</template>
        <template v-else>加载中...</template>
      </div>
      <div class="loading-tip">正在为您生成分析报告</div>
    </div>

    <!-- 结果内容 -->
    <div v-else-if="result" class="result-content">
      <!-- 顶部免责声明 -->
      <div class="disclaimer-top">
        <el-icon class="disclaimer-icon"><InfoFilled /></el-icon>
        <span class="disclaimer-text">本报告基于 AI 舌象分析，仅供参考，不能替代专业医生诊断。</span>
      </div>

      <!-- 顶部报告卡片 -->
      <div class="report-header">
        <div class="header-top">
          <div class="header-info">
            <div class="header-tag">
              <span class="tag-dot"></span>
              <span>{{ analysisMode === 'text' ? '症状分析' : '图像分析' }}</span>
            </div>
            <h1 class="header-title">{{ result.title }}</h1>
            <div class="header-time">
              <el-icon><Calendar /></el-icon>
              <span>{{ result.details[3]?.value || result.createdAt }}</span>
            </div>
          </div>
          <div class="header-actions">
            <el-button :icon="Share" circle @click="handleShare" />
            <el-dropdown trigger="click" @command="(cmd: string) => cmd === 'image' ? handleDownloadImage() : handleDownload()">
              <el-button :icon="Download" circle :loading="downloading" />
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="image" :icon="Picture">下载图片</el-dropdown-item>
                  <el-dropdown-item command="text" :icon="Document">下载文本</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
        </div>

        <!-- 健康评分（新版 - 包含评分依据） -->
        <div v-if="parsedReport && parsedReport.healthScore > 0" class="score-section">
          <div class="score-circle-wrap">
            <svg class="score-circle" width="140" height="140">
              <circle class="score-track" cx="70" cy="70" r="54" />
              <circle
                class="score-progress"
                cx="70"
                cy="70"
                r="54"
                :stroke="scoreLevel.color"
                :stroke-dasharray="scoreDashArray.circumference"
                :stroke-dashoffset="scoreDashArray.offset"
              />
            </svg>
            <div class="score-center">
              <div class="score-num" :style="{ color: scoreLevel.color }">{{ parsedReport.healthScore }}</div>
              <div class="score-unit">分</div>
            </div>
          </div>
          <div class="score-detail">
            <div class="score-level" :style="{ color: scoreLevel.color }">
              {{ scoreLevel.label }}
            </div>
            <div class="score-desc-text">{{ scoreLevel.desc }}</div>
            <div class="score-summary">{{ parsedReport.summary }}</div>
          </div>
        </div>

        <!-- 评分依据 -->
        <div v-if="parsedReport && parsedReport.scoreItems.length > 0" class="score-reason-section">
          <div class="score-reason-title">
            <el-icon><Histogram /></el-icon>
            <span>评分依据</span>
          </div>
          <div class="score-reason-list">
            <div
              v-for="(item, idx) in parsedReport.scoreItems"
              :key="idx"
              class="score-reason-item"
              :class="item.isPositive ? 'positive' : 'negative'"
            >
              <span class="score-reason-icon">{{ item.isPositive ? '+' : '-' }}</span>
              <span class="score-reason-text">{{ item.text }}</span>
            </div>
          </div>
        </div>

        <!-- AI置信度 -->
        <div v-if="parsedReport && Object.keys(parsedReport.confidence).length > 0" class="confidence-section">
          <div class="confidence-title">
            <el-icon><Check /></el-icon>
            <span>AI 识别置信度</span>
          </div>
          <div class="confidence-list">
            <div
              v-for="(value, label) in parsedReport.confidence"
              :key="label"
              class="confidence-item"
            >
              <span class="confidence-label">{{ label }}</span>
              <div class="confidence-bar-wrap">
                <div class="confidence-bar" :style="{ width: value }"></div>
              </div>
              <span class="confidence-value">{{ value }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 报告章节 -->
      <div v-if="parsedSections.length > 0" class="report-sections">
        <div
          v-for="(section, idx) in parsedSections"
          :key="idx"
          class="report-section"
        >
          <div class="section-header">
            <div class="section-icon-wrap" :class="`section-icon-wrap--${idx % 4}`">
              <el-icon :size="18"><component :is="section.icon" /></el-icon>
            </div>
            <h2 class="section-title-text">{{ section.title }}</h2>
          </div>
          <div class="section-content">
            <template v-for="(item, i) in section.items" :key="i">
              <div v-if="item.isBold" class="content-bold">{{ item.text }}</div>
              <div v-else class="content-line">{{ item.text }}</div>
            </template>
          </div>
        </div>
      </div>

      <!-- 调理建议 -->
      <div v-if="result.suggestions.length > 0" class="suggestions-section">
        <div class="section-header">
          <div class="section-icon-wrap section-icon-wrap--3">
            <el-icon :size="18"><Star /></el-icon>
          </div>
          <h2 class="section-title-text">调理建议</h2>
        </div>
        <div class="suggestions-grid">
          <div
            v-for="(item, index) in result.suggestions"
            :key="index"
            class="suggestion-card"
          >
            <div class="suggestion-index">{{ index + 1 }}</div>
            <div class="suggestion-text">{{ item }}</div>
          </div>
        </div>
      </div>

      <!-- 报告信息 -->
      <div class="report-meta">
        <div class="meta-title">报告信息</div>
        <div class="meta-list">
          <div
            v-for="item in result.details"
            :key="item.label"
            class="meta-item"
          >
            <div class="meta-icon">
              <el-icon :size="14"><component :is="item.icon" /></el-icon>
            </div>
            <div class="meta-content">
              <div class="meta-label">{{ item.label }}</div>
              <div class="meta-value">{{ item.value }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 温馨提示（醒目提示） -->
      <div class="warm-tip warm-tip--warning">
        <el-icon class="tip-icon"><InfoFilled /></el-icon>
        <div class="tip-content">
          <div class="tip-title">温馨提示</div>
          <div class="tip-text">本报告基于舌象图片 AI 分析，仅用于传统健康管理参考，不能替代医生诊断。如有持续不适，请咨询专业医生。</div>
        </div>
      </div>

      <!-- 操作按钮 -->
      <div class="actions">
        <el-button round type="primary" size="large" @click="router.push('/')" class="action-btn">
          返回首页
        </el-button>
        <el-button round size="large" @click="router.push('/analysis/tongue')" class="action-btn">
          再次分析
        </el-button>
      </div>
    </div>

    <!-- 失败状态 -->
    <div v-else class="error-container">
      <el-icon :size="56" color="#f56c6c"><Refresh /></el-icon>
      <div class="error-text">分析失败</div>
      <el-button type="primary" round @click="handleRetry" style="margin-top: 16px">
        重新分析
      </el-button>
    </div>

    <!-- 图片导出专用视图（隐藏） -->
    <div v-if="result" class="export-view" ref="exportRef" aria-hidden="true">
      <div class="export-container">
        <!-- 顶部品牌区 -->
        <div class="export-brand">
          <div class="export-logo">
            <span class="export-logo-text">中医智诊</span>
          </div>
          <div class="export-title-area">
            <h1 class="export-title">{{ result.title }}</h1>
            <div class="export-subtitle">
              <span class="export-mode-tag">
                <span class="export-mode-dot"></span>
                {{ analysisMode === 'text' ? '症状分析' : '图像分析' }}
              </span>
              <span class="export-time">{{ result.details[3]?.value || result.createdAt }}</span>
            </div>
          </div>
        </div>

        <!-- 健康评分卡片 -->
        <div v-if="result.healthScore > 0" class="export-score-card">
          <div class="export-score-circle-wrap">
            <svg class="export-score-circle" width="120" height="120">
              <circle class="export-score-track" cx="60" cy="60" r="48" />
              <circle
                class="export-score-progress"
                cx="60"
                cy="60"
                r="48"
                :stroke="scoreLevel.color"
                :stroke-dasharray="2 * Math.PI * 48"
                :stroke-dashoffset="2 * Math.PI * 48 - (result.healthScore / 100) * 2 * Math.PI * 48"
              />
            </svg>
            <div class="export-score-center">
              <div class="export-score-num" :style="{ color: scoreLevel.color }">{{ result.healthScore }}</div>
              <div class="export-score-unit">分</div>
            </div>
          </div>
          <div class="export-score-info">
            <div class="export-score-level" :style="{ color: scoreLevel.color }">
              {{ scoreLevel.label }}
            </div>
            <div class="export-score-desc">{{ scoreLevel.desc }}</div>
            <div class="export-score-summary">{{ result.summary }}</div>
          </div>
        </div>

        <!-- 报告内容 -->
        <div v-if="parsedSections.length > 0" class="export-sections">
          <div
            v-for="(section, idx) in parsedSections"
            :key="idx"
            class="export-section"
          >
            <div class="export-section-header">
              <div class="export-section-icon" :class="`export-section-icon--${idx % 4}`">
                <el-icon :size="16"><component :is="section.icon" /></el-icon>
              </div>
              <h2 class="export-section-title">{{ section.title }}</h2>
            </div>
            <div class="export-section-body">
              <template v-for="(item, i) in section.items" :key="i">
                <div v-if="item.isBold" class="export-content-bold">{{ item.text }}</div>
                <div v-else class="export-content-line">{{ item.text }}</div>
              </template>
            </div>
          </div>
        </div>

        <!-- 调理建议 -->
        <div v-if="result.suggestions.length > 0" class="export-suggestions">
          <div class="export-section-header">
            <div class="export-section-icon export-section-icon--3">
              <el-icon :size="16"><Star /></el-icon>
            </div>
            <h2 class="export-section-title">调理建议</h2>
          </div>
          <div class="export-suggestions-list">
            <div
              v-for="(item, index) in result.suggestions"
              :key="index"
              class="export-suggestion"
            >
              <span class="export-suggestion-num">{{ index + 1 }}</span>
              <span class="export-suggestion-text">{{ item }}</span>
            </div>
          </div>
        </div>

        <!-- 底部信息 -->
        <div class="export-footer">
          <div class="export-divider"></div>
          <div class="export-footer-row">
            <span>任务编号：{{ result.details[2]?.value || taskNo }}</span>
            <span>分析类型：{{ result.details[0]?.value }}</span>
          </div>
          <div class="export-footer-tip">
            <span>本报告由 AI 智能分析生成，仅供参考，不能替代专业医生的诊断和治疗</span>
          </div>
          <div class="export-brand-footer">
            <span class="export-brand-name">中医智诊 · AI 健康管理</span>
            <span class="export-brand-date">{{ new Date().toLocaleDateString('zh-CN') }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.result-page {
  padding: 16px;
  padding-bottom: 32px;
  min-height: 100vh;
  background: linear-gradient(180deg, #f0f9f4 0%, #f7f8fa 30%);
}

/* 加载状态 */
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 60vh;
}

.loading-animation {
  position: relative;
  width: 120px;
  height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pulse-circle {
  position: absolute;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: rgba(7, 193, 96, 0.15);
  animation: pulse 2s ease-out infinite;
}

.pulse-delay-1 { animation-delay: 0.5s; }
.pulse-delay-2 { animation-delay: 1s; }

@keyframes pulse {
  0% { transform: scale(0.5); opacity: 1; }
  100% { transform: scale(1.2); opacity: 0; }
}

.loading-icon {
  color: #07c160;
  animation: rotating 2s linear infinite;
  z-index: 1;
}

@keyframes rotating {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.loading-text {
  margin-top: 24px;
  font-size: 16px;
  color: #323233;
  font-weight: 500;
}

.loading-tip {
  margin-top: 8px;
  font-size: 13px;
  color: #969799;
}

/* 顶部免责声明 */
.disclaimer-top {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: linear-gradient(135deg, #fff7e6 0%, #fff2d9 100%);
  border: 1px solid #ffe4a3;
  border-radius: 12px;
  margin-bottom: 16px;
}

.disclaimer-icon {
  color: #e6a23c;
  font-size: 18px;
  flex-shrink: 0;
}

.disclaimer-text {
  font-size: 13px;
  color: #8a6d3b;
  line-height: 1.5;
}

/* 顶部报告卡片 */
.report-header {
  background: #fff;
  border-radius: 20px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 4px 20px rgba(7, 193, 96, 0.08);
  position: relative;
  overflow: hidden;
}

.report-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 100px;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  opacity: 0.06;
  pointer-events: none;
}

.header-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  position: relative;
}

.header-info {
  flex: 1;
}

.header-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #e8f7ef;
  color: #07c160;
  font-size: 12px;
  padding: 3px 10px;
  border-radius: 12px;
  margin-bottom: 10px;
  font-weight: 500;
}

.tag-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #07c160;
  animation: blink 1.5s ease-in-out infinite;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}

.header-title {
  font-size: 22px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0 0 8px;
  letter-spacing: 0.5px;
}

.header-time {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #969799;
  margin-top: 8px;
}

.header-actions {
  display: flex;
  gap: 8px;
}

.header-actions :deep(.el-button) {
  width: 36px;
  height: 36px;
  padding: 0;
  background: #f5f5f5;
  border: none;
  color: #646566;
}

.header-actions :deep(.el-button:hover) {
  background: #07c160;
  color: #fff;
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

/* 评分区域 */
.score-section {
  display: flex;
  align-items: center;
  gap: 20px;
  padding-top: 16px;
  border-top: 1px dashed #ebedf0;
}

.score-circle-wrap {
  position: relative;
  width: 140px;
  height: 140px;
  flex-shrink: 0;
}

.score-circle {
  transform: rotate(-90deg);
}

.score-track {
  fill: none;
  stroke: #f0f0f0;
  stroke-width: 8;
}

.score-progress {
  fill: none;
  stroke-width: 8;
  stroke-linecap: round;
  transition: stroke-dashoffset 1s ease-out;
}

.score-center {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 2px;
}

.score-num {
  font-size: 42px;
  font-weight: 700;
  line-height: 1;
}

.score-unit {
  font-size: 14px;
  color: #646566;
  line-height: 1;
}

.score-detail {
  flex: 1;
  min-width: 0;
}

.score-level {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 6px;
}

.score-desc-text {
  font-size: 13px;
  color: #646566;
  line-height: 1.5;
  margin-bottom: 8px;
}

.score-summary {
  font-size: 12px;
  color: #969799;
  background: #f7f8fa;
  padding: 8px 12px;
  border-radius: 8px;
  border-left: 3px solid #07c160;
}

/* 报告章节 */
.report-sections {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.report-section {
  padding: 16px 0;
  border-bottom: 1px solid #f0f0f0;
}

.report-section:first-child {
  padding-top: 4px;
}

.report-section:last-child {
  border-bottom: none;
  padding-bottom: 4px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}

.section-icon-wrap {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.section-icon-wrap--0 { background: linear-gradient(135deg, #409eff, #2b7fd9); }
.section-icon-wrap--1 { background: linear-gradient(135deg, #67c23a, #4f9b2e); }
.section-icon-wrap--2 { background: linear-gradient(135deg, #e6a23c, #c8821f); }
.section-icon-wrap--3 { background: linear-gradient(135deg, #f56c6c, #e64242); }

.section-title-text {
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  margin: 0;
}

.section-content {
  padding-left: 42px;
}

.content-line {
  font-size: 14px;
  color: #323233;
  line-height: 1.8;
  padding: 4px 0;
}

.content-bold {
  font-size: 14px;
  color: #1a1a1a;
  font-weight: 600;
  padding: 6px 0 2px;
  margin-top: 6px;
  border-left: 3px solid #07c160;
  padding-left: 8px;
}

.content-bold:first-child {
  margin-top: 0;
}

/* 调理建议 */
.suggestions-section {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.suggestions-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  padding-left: 42px;
}

.suggestion-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  background: linear-gradient(135deg, #f0f9f4 0%, #e8f7ef 100%);
  border-radius: 10px;
  border-left: 3px solid #07c160;
}

.suggestion-index {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #07c160;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
  margin-top: 1px;
}

.suggestion-text {
  font-size: 14px;
  color: #323233;
  line-height: 1.6;
  flex: 1;
}

/* 报告信息 */
.report-meta {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.meta-title {
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 14px;
}

.meta-list {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: #fafafa;
  border-radius: 10px;
}

.meta-icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: #e8f7ef;
  color: #07c160;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.meta-content {
  flex: 1;
  min-width: 0;
}

.meta-label {
  font-size: 11px;
  color: #969799;
  margin-bottom: 2px;
}

.meta-value {
  font-size: 13px;
  color: #323233;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* 温馨提示 */
.warm-tip {
  display: flex;
  gap: 12px;
  padding: 14px 16px;
  background: linear-gradient(135deg, #fff7e6 0%, #fff2d9 100%);
  border-radius: 12px;
  margin-bottom: 16px;
  border: 1px solid #ffe4a3;
}

.warm-tip--warning {
  background: linear-gradient(135deg, #fef0f0 0%, #fde2e2 100%);
  border: 1px solid #f5c6c6;
  box-shadow: 0 2px 12px rgba(245, 108, 108, 0.1);
}

.warm-tip--warning .tip-icon {
  color: #f56c6c;
  font-size: 22px;
}

.warm-tip--warning .tip-title {
  color: #d32f2f;
  font-weight: 600;
  margin-bottom: 4px;
}

.tip-icon {
  font-size: 20px;
  flex-shrink: 0;
}

.tip-content {
  flex: 1;
}

.tip-title {
  font-size: 13px;
  font-weight: 600;
  color: #c8821f;
  margin-bottom: 4px;
}

.tip-text {
  font-size: 12px;
  color: #8b6914;
  line-height: 1.6;
}

/* 操作按钮 */
.actions {
  display: flex;
  gap: 12px;
  margin-top: 8px;
}

.action-btn {
  flex: 1;
  height: 44px;
  font-weight: 500;
}

.action-btn.el-button--primary {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border: none;
}

.action-btn.el-button--primary:hover {
  box-shadow: 0 4px 12px rgba(7, 193, 96, 0.3);
}

/* 错误状态 */
.error-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 60vh;
}

.error-text {
  margin-top: 16px;
  font-size: 16px;
  color: #323233;
}

/* 评分依据 */
.score-reason-section {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  border: 1px solid #f0f0f0;
}

.score-reason-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 14px;
}

.score-reason-title .el-icon {
  color: #07c160;
  font-size: 18px;
}

.score-reason-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.score-reason-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s;
}

.score-reason-item.positive {
  background: #e8f7ef;
  color: #07c160;
  border: 1px solid #b3e4c8;
}

.score-reason-item.negative {
  background: #fef0f0;
  color: #f56c6c;
  border: 1px solid #f5c6c6;
}

.score-reason-icon {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  flex-shrink: 0;
}

.score-reason-item.positive .score-reason-icon {
  background: #07c160;
  color: #fff;
}

.score-reason-item.negative .score-reason-icon {
  background: #f56c6c;
  color: #fff;
}

.score-reason-text {
  line-height: 1;
}

/* AI置信度 */
.confidence-section {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

.confidence-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 600;
  color: #1a1a1a;
  margin-bottom: 14px;
}

.confidence-title .el-icon {
  color: #07c160;
  font-size: 18px;
}

.confidence-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.confidence-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.confidence-label {
  width: 100px;
  font-size: 13px;
  color: #646566;
  flex-shrink: 0;
  text-align: right;
}

.confidence-bar-wrap {
  flex: 1;
  height: 8px;
  background: #f0f0f0;
  border-radius: 4px;
  overflow: hidden;
}

.confidence-bar {
  height: 100%;
  background: linear-gradient(90deg, #07c160 0%, #4fd180 100%);
  border-radius: 4px;
  transition: width 0.5s ease-out;
}

.confidence-value {
  width: 40px;
  font-size: 13px;
  color: #07c160;
  font-weight: 600;
  text-align: right;
}

/* 响应式 */
@media (max-width: 480px) {
  .score-section {
    flex-direction: column;
    text-align: center;
  }
  .score-circle-wrap {
    width: 120px;
    height: 120px;
  }
  .score-num {
    font-size: 36px;
  }
  .meta-list {
    grid-template-columns: 1fr;
  }
  .confidence-label {
    width: 70px;
    font-size: 12px;
  }
}

/* ============== 图片导出视图（隐藏） ============== */
.export-view {
  position: fixed;
  top: -10000px;
  left: -10000px;
  width: 750px;
  z-index: -1;
  pointer-events: none;
  background: #f0f9f4;
}

.export-container {
  width: 750px;
  padding: 40px 32px;
  background: linear-gradient(180deg, #f0f9f4 0%, #ffffff 50%);
  font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif;
}

/* 品牌区 */
.export-brand {
  display: flex;
  align-items: center;
  gap: 18px;
  padding-bottom: 24px;
  border-bottom: 2px solid #07c160;
  margin-bottom: 28px;
}

.export-logo {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(7, 193, 96, 0.3);
}

.export-logo-text {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: 1px;
}

.export-title-area {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.export-title {
  font-size: 28px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0 0 8px;
  letter-spacing: 1px;
}

.export-subtitle {
  display: flex;
  align-items: center;
  gap: 12px;
}

.export-mode-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #e8f7ef;
  color: #07c160;
  font-size: 13px;
  padding: 4px 10px;
  border-radius: 12px;
  font-weight: 500;
}

.export-mode-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #07c160;
}

.export-time {
  font-size: 13px;
  color: #646566;
}

/* 评分卡 */
.export-score-card {
  background: #fff;
  border-radius: 20px;
  padding: 28px;
  display: flex;
  align-items: center;
  gap: 28px;
  margin-bottom: 24px;
  box-shadow: 0 4px 20px rgba(7, 193, 96, 0.08);
}

.export-score-circle-wrap {
  position: relative;
  width: 120px;
  height: 120px;
  flex-shrink: 0;
}

.export-score-circle {
  transform: rotate(-90deg);
}

.export-score-track {
  fill: none;
  stroke: #f0f0f0;
  stroke-width: 8;
}

.export-score-progress {
  fill: none;
  stroke-width: 8;
  stroke-linecap: round;
}

.export-score-center {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 2px;
}

.export-score-num {
  font-size: 38px;
  font-weight: 700;
  line-height: 1;
}

.export-score-unit {
  font-size: 14px;
  color: #646566;
  line-height: 1;
}

.export-score-info {
  flex: 1;
  min-width: 0;
}

.export-score-level {
  font-size: 22px;
  font-weight: 700;
  margin-bottom: 8px;
}

.export-score-desc {
  font-size: 14px;
  color: #646566;
  line-height: 1.6;
  margin-bottom: 10px;
}

.export-score-summary {
  font-size: 13px;
  color: #969799;
  background: #f7f8fa;
  padding: 8px 12px;
  border-radius: 8px;
  border-left: 3px solid #07c160;
}

/* 报告章节 */
.export-sections {
  background: #fff;
  border-radius: 20px;
  padding: 28px;
  margin-bottom: 24px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.export-section {
  padding: 16px 0;
  border-bottom: 1px dashed #ebedf0;
}

.export-section:first-child {
  padding-top: 4px;
}

.export-section:last-child {
  border-bottom: none;
  padding-bottom: 4px;
}

.export-section-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.export-section-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.export-section-icon--0 { background: linear-gradient(135deg, #409eff, #2b7fd9); }
.export-section-icon--1 { background: linear-gradient(135deg, #67c23a, #4f9b2e); }
.export-section-icon--2 { background: linear-gradient(135deg, #e6a23c, #c8821f); }
.export-section-icon--3 { background: linear-gradient(135deg, #f56c6c, #e64242); }

.export-section-title {
  font-size: 17px;
  font-weight: 600;
  color: #1a1a1a;
  margin: 0;
}

.export-section-body {
  padding-left: 38px;
}

.export-content-line {
  font-size: 14px;
  color: #323233;
  line-height: 1.9;
  padding: 3px 0;
}

.export-content-bold {
  font-size: 14px;
  color: #1a1a1a;
  font-weight: 600;
  padding: 5px 0 2px;
  margin-top: 5px;
  border-left: 3px solid #07c160;
  padding-left: 8px;
}

.export-content-bold:first-child {
  margin-top: 0;
}

/* 调理建议 */
.export-suggestions {
  background: #fff;
  border-radius: 20px;
  padding: 28px;
  margin-bottom: 24px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.export-suggestions-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-left: 38px;
}

.export-suggestion {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 14px;
  background: linear-gradient(135deg, #f0f9f4 0%, #e8f7ef 100%);
  border-radius: 10px;
  border-left: 3px solid #07c160;
  font-size: 14px;
  color: #323233;
  line-height: 1.6;
}

.export-suggestion-num {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #07c160;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
  margin-top: 1px;
}

.export-suggestion-text {
  flex: 1;
}

/* 底部 */
.export-footer {
  margin-top: 24px;
}

.export-divider {
  height: 1px;
  background: linear-gradient(90deg, transparent 0%, #07c160 50%, transparent 100%);
  margin-bottom: 16px;
}

.export-footer-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #646566;
  margin-bottom: 8px;
}

.export-footer-tip {
  font-size: 11px;
  color: #969799;
  text-align: center;
  margin-bottom: 14px;
  line-height: 1.5;
}

.export-brand-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 12px;
  border-top: 1px solid #ebedf0;
}

.export-brand-name {
  font-size: 13px;
  color: #07c160;
  font-weight: 600;
}

.export-brand-date {
  font-size: 12px;
  color: #969799;
}
</style>
