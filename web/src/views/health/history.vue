<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { User, Sunny } from '@element-plus/icons-vue'

const router = useRouter()

interface AnalysisRecord {
  task_no: string
  type: 'face' | 'tongue'
  type_name: string
  created_at: string
  summary: string
  status: number
  is_paid: boolean
}

const records = ref<AnalysisRecord[]>([])
const loading = ref(false)

const getToken = (): string => localStorage.getItem('token') || ''

// 从后端加载历史记录（无任何硬编码）
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
        const result = r.result || {}
        const typeName = r.type === 'face' ? '面诊分析'
          : r.type === 'tongue' ? '舌诊分析'
          : r.type === 'constitution' ? '体质测试' : '分析'
        return {
          task_no: r.task_no,
          type: r.type,
          type_name: typeName,
          created_at: r.created_at,
          summary: result.summary || result.diagnosis || '分析完成',
          status: r.status,
          is_paid: r.is_paid,
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
  // status: 0=待处理 1=处理中 2=已完成
  if (record.status !== 2) {
    ElMessage.info('分析进行中，请稍后再查看')
    return
  }
  router.push(`/analysis/result/${record.task_no}`)
}

const getStatusTagType = (status: number) => {
  if (status === 2) return 'success' as const
  if (status === 1) return 'warning' as const
  return 'info' as const
}

const getStatusText = (status: number) => {
  if (status === 2) return '已完成'
  if (status === 1) return '分析中'
  return '待处理'
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
        :key="record.task_no"
        class="record-card"
        @click="viewDetail(record)"
      >
        <div class="record-header">
          <div class="record-type">
            <el-icon v-if="record.type === 'face'" color="#07c160"><User /></el-icon>
            <el-icon v-else color="#07c160"><Sunny /></el-icon>
            <span>{{ record.type_name }}</span>
          </div>
          <el-tag :type="getStatusTagType(record.status)" size="small">
            {{ getStatusText(record.status) }}
          </el-tag>
        </div>

        <div class="record-summary">{{ record.summary }}</div>

        <div class="record-footer">
          <span class="record-date">{{ record.created_at }}</span>
          <span class="record-task-no">{{ record.task_no }}</span>
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

.record-task-no {
  font-family: monospace;
}
</style>
