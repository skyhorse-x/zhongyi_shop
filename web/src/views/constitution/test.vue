<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'

const router = useRouter()
const loading = ref(false)
const questions = ref<any[]>([])

// 答题结果
const answers = reactive<Record<number, string>>({})

// 获取题目列表
const fetchQuestions = async () => {
  try {
    const res = await safeFetch('/api/v1/constitution/questions', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      questions.value = data.data
    }
  } catch (error) {
    console.error('获取题目失败:', error)
    ElMessage.error('获取题目失败，请重试')
  }
}

// 选择答案
const selectAnswer = (questionId: number, value: string) => {
  answers[questionId] = value
}

// 检查是否全部作答
const isAllAnswered = computed(() => {
  return questions.value.length > 0 && questions.value.every((q) => answers[q.id])
})

// 进度百分比
const progressPercent = computed(() => {
  if (questions.value.length === 0) return 0
  const answered = Object.keys(answers).length
  return Math.round((answered / questions.value.length) * 100)
})

// 提交问卷
const handleSubmit = async () => {
  if (!isAllAnswered.value) {
    ElMessage.warning('请完成所有题目后再提交')
    return
  }

  loading.value = true
  try {
    const res = await safeFetch('/api/v1/constitution/submit', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify({ answers }),
    })
    const data = await res.json()

    if (data.code === 0) {
      ElMessage.success('提交成功')
      router.push(`/constitution/result/${data.data.task_no}`)
    } else {
      ElMessage.error(data.message || '提交失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '提交失败，请重试')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchQuestions()
})
</script>

<template>
  <div class="constitution-test-page">
    <div class="test-header">
      <div class="test-title">中医体质辨识问卷</div>
      <div class="test-desc">
        请根据您最近一段时间的真实情况作答，我们将为您分析体质类型
      </div>
      <div class="progress-bar" v-if="questions.length > 0">
        <div class="progress-fill" :style="{ width: progressPercent + '%' }"></div>
      </div>
      <div class="progress-text" v-if="questions.length > 0">
        已答 {{ Object.keys(answers).length }} / {{ questions.length }} 题
      </div>
    </div>

    <div class="question-list" v-loading="loading && questions.length === 0">
      <div v-if="questions.length === 0" class="empty-state">
        <el-empty description="暂无题目" />
      </div>
      <div
        v-for="(question, index) in questions"
        :key="question.id"
        class="question-card"
      >
        <div class="question-index">第 {{ index + 1 }} 题</div>
        <div class="question-topic">{{ question.question }}</div>
        <el-radio-group
          :model-value="answers[question.id]"
          @update:model-value="(val: any) => selectAnswer(question.id, val)"
        >
          <div class="radio-options">
            <el-radio
              v-for="option in question.options"
              :key="option.value"
              :value="option.value"
              class="radio-item"
            >
              {{ option.label }}
            </el-radio>
          </div>
        </el-radio-group>
      </div>
    </div>

    <div class="submit-section" v-if="questions.length > 0">
      <div class="submit-tip" v-if="!isAllAnswered">
        还未完成全部题目（{{ Object.keys(answers).length }} / {{ questions.length }}）
      </div>
      <el-button
        round
        size="large"
        :loading="loading"
        :disabled="!isAllAnswered"
        @click="handleSubmit"
        class="submit-btn"
        :class="{ 'is-disabled': !isAllAnswered }"
      >
        {{ isAllAnswered ? '提交测试' : '请完成全部题目' }}
      </el-button>
    </div>
  </div>
</template>

<style scoped>
.constitution-test-page {
  padding: 16px;
  padding-bottom: 140px;
  min-height: 100vh;
  background: #f7f8fa;
}

.test-header {
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border-radius: 12px;
  padding: 24px 20px;
  margin-bottom: 16px;
  color: #fff;
}

.test-title {
  font-size: 20px;
  font-weight: bold;
  margin-bottom: 8px;
}

.test-desc {
  font-size: 13px;
  opacity: 0.9;
  line-height: 1.6;
  margin-bottom: 16px;
}

.progress-bar {
  height: 6px;
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-fill {
  height: 100%;
  background: #fff;
  border-radius: 3px;
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 12px;
  opacity: 0.9;
  text-align: right;
}

.question-list {
  margin-bottom: 24px;
  min-height: 200px;
}

.empty-state {
  padding: 40px 0;
  text-align: center;
}

.question-card {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.question-index {
  font-size: 12px;
  color: #07c160;
  font-weight: bold;
  margin-bottom: 4px;
}

.question-topic {
  font-size: 15px;
  color: #323233;
  font-weight: 500;
  margin-bottom: 12px;
}

.radio-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.radio-item {
  display: flex;
  align-items: center;
  padding: 10px 12px;
  border: 1px solid #ebedf0;
  border-radius: 8px;
  width: 100%;
  margin-right: 0;
  transition: all 0.2s;
}

.radio-item:hover {
  border-color: #07c160;
  background: #f6fdf9;
}

.submit-section {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 12px 16px 16px;
  background: #fff;
  box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.08);
  z-index: 100;
}

.submit-tip {
  text-align: center;
  font-size: 12px;
  color: #ff976a;
  margin-bottom: 8px;
}

.submit-btn {
  width: 100%;
  height: 48px;
  font-size: 16px;
  font-weight: 600;
  background: linear-gradient(135deg, #07c160 0%, #04a152 100%);
  border: none;
  color: #fff;
  box-shadow: 0 4px 12px rgba(7, 193, 96, 0.25);
  transition: all 0.25s ease;
}

.submit-btn:hover:not(.is-disabled):not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(7, 193, 96, 0.35);
  opacity: 0.95;
}

.submit-btn:active:not(.is-disabled):not(:disabled) {
  transform: translateY(0);
}

.submit-btn.is-disabled,
.submit-btn:disabled {
  background: #e9ecef;
  color: #b5b5b5;
  box-shadow: none;
  cursor: not-allowed;
}
</style>
