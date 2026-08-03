<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { Plus, Edit, Delete, Search, Refresh, ArrowDown, ArrowUp } from '@element-plus/icons-vue'

interface QuestionOption {
  label: string  // 显示文本
  value: number  // 分值
}

interface ConstitutionQuestion {
  id: number
  category: string
  question: string
  type: 'single' | 'multi'
  options: QuestionOption[]
  sort_order: number
  is_enabled: boolean
  created_at: string
}

const loading = ref(false)
const allQuestions = ref<ConstitutionQuestion[]>([])
const activeCategory = ref('all')

// 弹窗
const dialogVisible = ref(false)
const dialogMode = ref<'create' | 'edit'>('create')
const submitting = ref(false)
const form = reactive({
  id: 0,
  category: '气虚质',
  question: '',
  type: 'single' as 'single' | 'multi',
  options: [
    { label: '没有', value: 1 },
    { label: '很少', value: 2 },
    { label: '有时', value: 3 },
    { label: '经常', value: 4 },
    { label: '总是', value: 5 },
  ] as QuestionOption[],
  sort_order: 0,
  is_enabled: true,
})

// 体质分类列表
const CATEGORIES = [
  '气虚质', '阳虚质', '阴虚质', '痰湿质', '湿热质',
  '血瘀质', '气郁质', '特禀质', '平和质',
]

import { getAdminToken } from '@/utils/auth'

const getAuthToken = (): string => getAdminToken() || ''

const fetchQuestions = async () => {
  loading.value = true
  try {
    const res = await safeFetch('/api/v1/admin/constitution/questions', {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      allQuestions.value = data.data || []
    } else {
      ElMessage.error(data.message || '加载题目失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误')
  } finally {
    loading.value = false
  }
}

// 按分类分组
const groupedQuestions = computed(() => {
  const map: Record<string, ConstitutionQuestion[]> = {}
  for (const cat of CATEGORIES) map[cat] = []
  for (const q of allQuestions.value) {
    if (map[q.category]) map[q.category].push(q)
  }
  return map
})

// 分类计数
const categoryCount = (cat: string) => groupedQuestions.value[cat]?.length || 0

// 当前展示列表
const currentList = computed(() => {
  if (activeCategory.value === 'all') return allQuestions.value
  return groupedQuestions.value[activeCategory.value] || []
})

const resetForm = () => {
  Object.assign(form, {
    id: 0,
    category: '气虚质',
    question: '',
    type: 'single',
    options: [
      { label: '没有', value: 1 },
      { label: '很少', value: 2 },
      { label: '有时', value: 3 },
      { label: '经常', value: 4 },
      { label: '总是', value: 5 },
    ],
    sort_order: 0,
    is_enabled: true,
  })
}

const openCreate = () => {
  resetForm()
  dialogMode.value = 'create'
  dialogVisible.value = true
}

const openEdit = (row: ConstitutionQuestion) => {
  Object.assign(form, JSON.parse(JSON.stringify(row)))
  dialogMode.value = 'edit'
  dialogVisible.value = true
}

const addOption = () => {
  form.options.push({ label: '', value: 1 })
}

const removeOption = (idx: number) => {
  if (form.options.length <= 2) {
    ElMessage.warning('至少保留 2 个选项')
    return
  }
  form.options.splice(idx, 1)
}

const moveOption = (idx: number, dir: -1 | 1) => {
  const newIdx = idx + dir
  if (newIdx < 0 || newIdx >= form.options.length) return
  const tmp = form.options[idx]
  form.options[idx] = form.options[newIdx]
  form.options[newIdx] = tmp
}

const handleSubmit = async () => {
  if (!form.question.trim()) {
    ElMessage.warning('请输入题目内容')
    return
  }
  if (form.options.some(o => !o.label.trim())) {
    ElMessage.warning('请填写所有选项内容')
    return
  }
  submitting.value = true
  try {
    const url = dialogMode.value === 'create'
      ? '/api/v1/admin/constitution/questions'
      : `/api/v1/admin/constitution/questions/${form.id}`
    const method = dialogMode.value === 'create' ? 'POST' : 'PUT'
    const res = await safeFetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify(form),
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success(dialogMode.value === 'create' ? '创建成功' : '更新成功')
      dialogVisible.value = false
      fetchQuestions()
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch (e: any) {
    ElMessage.error(e?.message || '网络错误')
  } finally {
    submitting.value = false
  }
}

const handleDelete = async (row: ConstitutionQuestion) => {
  try {
    await ElMessageBox.confirm(
      `确认删除题目「${row.question}」？`,
      '删除确认',
      { type: 'warning' }
    )
    const res = await safeFetch(`/api/v1/admin/constitution/questions/${row.id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      ElMessage.success('删除成功')
      fetchQuestions()
    } else {
      ElMessage.error(data.message || '删除失败')
    }
  } catch (e: any) {
    if (e !== 'cancel') ElMessage.error(e?.message || '操作失败')
  }
}

const handleToggle = async (row: ConstitutionQuestion) => {
  const res = await safeFetch(`/api/v1/admin/constitution/questions/${row.id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${getToken()}`,
      'Accept': 'application/json',
    },
    body: JSON.stringify({ is_enabled: !row.is_enabled }),
  })
  const data = await res.json()
  if (data.code === 0) {
    ElMessage.success(row.is_enabled ? '已禁用' : '已启用')
    fetchQuestions()
  } else {
    ElMessage.error(data.message || '操作失败')
  }
}

onMounted(() => {
  fetchQuestions()
})
</script>

<template>
  <div class="page-container">
    <el-card shadow="never" class="toolbar-card">
      <div class="toolbar">
        <div class="left-info">
          <el-icon :size="20" color="#07c160"><Search /></el-icon>
          <span>共 {{ allQuestions.length }} 道题目，覆盖 {{ CATEGORIES.length }} 种体质</span>
        </div>
        <div class="spacer" />
        <el-button @click="fetchQuestions">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
        <el-button type="primary" @click="openCreate">
          <el-icon><Plus /></el-icon>
          新增题目
        </el-button>
      </div>
    </el-card>

    <el-card shadow="never" style="margin-top: 16px">
      <el-tabs v-model="activeCategory" type="border-card">
        <el-tab-pane label="全部" name="all">
          <template #label>
            <span>全部 <el-tag size="small">{{ allQuestions.length }}</el-tag></span>
          </template>
        </el-tab-pane>
        <el-tab-pane
          v-for="cat in CATEGORIES"
          :key="cat"
          :name="cat"
        >
          <template #label>
            <span>{{ cat }} <el-tag size="small" type="info">{{ categoryCount(cat) }}</el-tag></span>
          </template>
        </el-tab-pane>
      </el-tabs>

      <div v-loading="loading">
        <el-empty v-if="!loading && currentList.length === 0" description="该分类暂无题目" />
        <div
          v-for="(q, idx) in currentList"
          :key="q.id"
          class="question-card"
        >
          <div class="q-header">
            <div class="q-info">
              <el-tag :type="q.category === '平和质' ? 'success' : 'warning'" size="small">
                {{ q.category }}
              </el-tag>
              <el-tag size="small" effect="plain">{{ q.type === 'single' ? '单选' : '多选' }}</el-tag>
              <span class="q-num">#{{ idx + 1 }}</span>
              <span class="q-text">{{ q.question }}</span>
            </div>
            <div class="q-actions">
              <el-switch
                :model-value="q.is_enabled"
                @change="handleToggle(q)"
                active-color="#07c160"
                size="small"
              />
              <el-button size="small" type="primary" link @click="openEdit(q)">
                <el-icon><Edit /></el-icon>编辑
              </el-button>
              <el-button size="small" type="danger" link @click="handleDelete(q)">
                <el-icon><Delete /></el-icon>删除
              </el-button>
            </div>
          </div>
          <div class="q-options">
            <div
              v-for="(opt, oIdx) in q.options"
              :key="oIdx"
              class="option-item"
            >
              <span class="opt-label">{{ String.fromCharCode(65 + oIdx) }}.</span>
              <span class="opt-text">{{ opt.label }}</span>
              <el-tag size="small" type="info">{{ opt.value }} 分</el-tag>
            </div>
          </div>
        </div>
      </div>
    </el-card>

    <!-- 新增/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogMode === 'create' ? '新增题目' : '编辑题目'"
      width="640px"
      :close-on-click-modal="false"
    >
      <el-form :model="form" label-width="100px">
        <el-form-item label="体质分类" required>
          <el-select v-model="form.category" style="width: 100%">
            <el-option v-for="c in CATEGORIES" :key="c" :value="c" :label="c" />
          </el-select>
        </el-form-item>
        <el-form-item label="题目类型">
          <el-radio-group v-model="form.type">
            <el-radio value="single">单选</el-radio>
            <el-radio value="multi">多选</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="题目内容" required>
          <el-input
            v-model="form.question"
            type="textarea"
            :rows="2"
            maxlength="200"
            show-word-limit
            placeholder="请输入题目内容"
          />
        </el-form-item>
        <el-form-item label="选项" required>
          <div class="options-editor">
            <div
              v-for="(opt, idx) in form.options"
              :key="idx"
              class="option-row"
            >
              <span class="opt-letter">{{ String.fromCharCode(65 + idx) }}.</span>
              <el-input v-model="opt.label" placeholder="选项内容" size="small" />
              <el-input-number
                v-model="opt.value"
                :min="0"
                :max="100"
                size="small"
                controls-position="right"
                style="width: 100px"
              />
              <el-button-group size="small">
                <el-button :disabled="idx === 0" @click="moveOption(idx, -1)">
                  <el-icon><ArrowUp /></el-icon>
                </el-button>
                <el-button :disabled="idx === form.options.length - 1" @click="moveOption(idx, 1)">
                  <el-icon><ArrowDown /></el-icon>
                </el-button>
                <el-button type="danger" @click="removeOption(idx)">
                  <el-icon><Delete /></el-icon>
                </el-button>
              </el-button-group>
            </div>
            <el-button type="primary" link @click="addOption">
              <el-icon><Plus /></el-icon>新增选项
            </el-button>
          </div>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" :max="9999" />
          <span class="form-tip">数字越小越靠前</span>
        </el-form-item>
        <el-form-item label="是否启用">
          <el-switch v-model="form.is_enabled" active-color="#07c160" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">
          {{ dialogMode === 'create' ? '创建' : '保存' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.page-container { padding: 0; }
.toolbar-card { border: none; }
.toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
}
.left-info {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #646566;
}
.spacer { flex: 1; }
.question-card {
  background: #fafbfc;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 12px;
  border: 1px solid #f0f0f0;
}
.q-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}
.q-info {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.q-num {
  color: #c8c9cc;
  font-size: 12px;
  font-family: monospace;
}
.q-text {
  font-size: 15px;
  font-weight: 500;
  color: #323233;
  flex: 1;
  min-width: 200px;
}
.q-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.q-options {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 8px;
}
.option-item {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #fff;
  padding: 6px 10px;
  border-radius: 4px;
  font-size: 13px;
}
.opt-label {
  color: #1989fa;
  font-weight: 600;
  font-family: monospace;
}
.opt-text {
  color: #323233;
  flex: 1;
}
.options-editor {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}
.option-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.opt-letter {
  color: #1989fa;
  font-weight: 600;
  font-family: monospace;
  width: 24px;
  text-align: center;
}
.form-tip {
  margin-left: 8px;
  color: #969799;
  font-size: 12px;
}

/* 手机端适配 */
@media (max-width: 768px) {
  .el-form--inline .el-form-item {
    margin-right: 0;
    margin-bottom: 8px;
    width: 100%;
  }

  .el-form--inline .el-form-item .el-input,
  .el-form--inline .el-form-item .el-select {
    width: 100% !important;
  }

  .el-table { font-size: 12px; }

  .el-pagination { flex-wrap: wrap; justify-content: center; }

  .el-dialog { width: 90% !important; max-width: 400px; }

  .el-row { flex-direction: column; }

  .el-row .el-col {
    width: 100% !important;
    max-width: 100% !important;
    flex: 0 0 100% !important;
  }
}
</style>
