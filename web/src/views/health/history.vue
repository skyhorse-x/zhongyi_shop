<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { User, Sunny, Histogram } from '@element-plus/icons-vue'

const router = useRouter()

interface AnalysisRecord {
  id: number
  type: 'face' | 'tongue' | 'constitution' | 'palm' | 'eye'
  type_name: string
  created_at: string
  summary: string
  health_score: number
  content: any
}

const records = ref<AnalysisRecord[]>([])
const loading = ref(false)

import { getToken } from '@/utils/auth'

const getAuthToken = (): string => getToken() || ''

// 从后端加载健康档案记录
const fetchHistory = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/health/history?limit=20', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data?.data || data.data || []
      records.value = list.map((r: any) => {
        const content = r.content || {}
        const typeName = r.type === 'face' ? '面诊分析'
          : r.type === 'tongue' ? '舌诊分析'
          : r.type === 'palm' ? '手相分析'
          : r.type === 'eye' ? '眼部分析'
          : r.type === 'constitution' ? '体质测试' : '分析'
        return {
          id: r.id,
          type: r.type,
          type_name: typeName,
          created_at: r.created_at,
          summary: r.summary || content.summary || '分析完成',
          health_score: r.health_score || 85,
          content: content,
        }
      })
    } else {
      ElMessage.error(data.message || '获取记录失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误，请稍后重试')
  } finally {
    loading.value = false
  }
}

const viewDetail = (record: AnalysisRecord) => {
  // 跳转到分析详情页
  if (record.type === 'constitution') {
    router.push(`/constitution/result/${record.id}`)
  } else {
    router.push(`/analysis/detail/${record.id}`)
  }
}

onMounted(() => {
  fetchHistory()
})
</script>

<template>
  <div class="history-page">
    <div class="records-list" v-loading="loading">
      <el-empty v-if="!loading && records.length === 0" description="暂无分析记录" />

      <div
        v-for="record in records"
        :key="record.id"
        class="record-card"
        @click="viewDetail(record)"
      >
        <div class="record-header">
          <div class="record-type">
            <el-icon v-if="record.type === 'face'" color="#07c160"><User /></el-icon>
            <el-icon v-else-if="record.type === 'constitution'" color="#07c160"><Histogram /></el-icon>
            <el-icon v-else color="#07c160"><Sunny /></el-icon>
            <span>{{ record.type_name }}</span>
          </div>
          <span class="health-score">健康评分: {{ record.health_score }}</span>
        </div>

        <div class="record-summary">{{ record.summary }}</div>

        <div class="record-footer">
          <span class="record-date">{{ record.created_at }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.history-page {
  min-height: 100vh;
  background-color: #f7f8fa;
}

.records-list {
  padding: 12px 16px;
  min-height: 200px;
}

.record-card {
  background-color: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  cursor: pointer;
}

.record-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.record-type {
  display: flex;
  align-items: center;
  font-size: 16px;
  font-weight: bold;
  color: #323233;
  gap: 6px;
}

.health-score {
  font-size: 14px;
  color: #07c160;
  font-weight: 500;
}

.record-summary {
  font-size: 14px;
  color: #646566;
  line-height: 1.6;
  margin-bottom: 12px;
}

.record-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: #969799;
}

.record-date {
  display: flex;
  align-items: center;
}
</style>
