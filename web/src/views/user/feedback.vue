<script setup lang="ts">
/**
 * 用户端 - 反馈与申诉
 */
import { ref, onMounted } from 'vue'
import request from '@/api/request'
import { ElMessage } from 'element-plus'

const activeTab = ref('feedback')
const feedbacks = ref<any[]>([])
const appeals = ref<any[]>([])

// 反馈表单
const feedbackDialog = ref(false)
const feedbackForm = ref({
  type: 'suggestion',
  title: '',
  content: '',
  contact: '',
  images: [] as string[],
})

// 申诉表单
const appealDialog = ref(false)
const appealForm = ref({
  task_no: '',
  reason: '',
  description: '',
  attachments: [] as string[],
})

const loadFeedbacks = async () => {
  const data: any = await request.get('/feedback')
  feedbacks.value = (data.data || data).data || data || []
}
const loadAppeals = async () => {
  const data: any = await request.get('/appeals')
  appeals.value = (data.data || data).data || data || []
}

const submitFeedback = async () => {
  await request.post('/feedback', feedbackForm.value)
  ElMessage.success('反馈已提交')
  feedbackDialog.value = false
  feedbackForm.value = { type: 'suggestion', title: '', content: '', contact: '', images: [] }
  loadFeedbacks()
}

const submitAppeal = async () => {
  await request.post('/appeals', appealForm.value)
  ElMessage.success('申诉已提交')
  appealDialog.value = false
  appealForm.value = { task_no: '', reason: '', description: '', attachments: [] }
  loadAppeals()
}

onMounted(() => {
  loadFeedbacks()
  loadAppeals()
})
</script>

<template>
  <div class="feedback-page">
    <el-card>
      <template #header>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span>反馈与申诉</span>
          <div>
            <el-button v-if="activeTab === 'feedback'" type="primary" @click="feedbackDialog = true">提交反馈</el-button>
            <el-button v-else type="primary" @click="appealDialog = true">发起申诉</el-button>
          </div>
        </div>
      </template>

      <el-tabs v-model="activeTab" @tab-change="(name: string) => name === 'feedback' ? loadFeedbacks() : loadAppeals()">
        <el-tab-pane label="我的反馈" name="feedback">
          <el-table :data="feedbacks" stripe>
            <el-table-column prop="title" label="标题" />
            <el-table-column prop="type" label="类型" width="100">
              <template #default="{ row }">
                <el-tag>{{ ({ bug: 'Bug', suggestion: '建议', complaint: '投诉', other: '其他' } as Record<string, string>)[row.type] }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="status" label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="({ pending: 'warning', processing: 'info', replied: 'success', closed: 'info' } as Record<string, string>)[row.status]">
                  {{ ({ pending: '待处理', processing: '处理中', replied: '已回复', closed: '已关闭' } as Record<string, string>)[row.status] }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="回复" min-width="200">
              <template #default="{ row }">
                <span v-if="row.reply">{{ row.reply }}</span>
                <span v-else class="text-muted">-</span>
              </template>
            </el-table-column>
            <el-table-column label="时间" width="160">
              <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="AI 申诉" name="appeal">
          <el-table :data="appeals" stripe>
            <el-table-column prop="task_no" label="任务号" width="160" />
            <el-table-column prop="reason" label="原因" />
            <el-table-column prop="status" label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="({ pending: 'warning', approved: 'success', rejected: 'danger' } as Record<string, string>)[row.status]">
                  {{ ({ pending: '待审核', approved: '已采纳', rejected: '已拒绝' } as Record<string, string>)[row.status] }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="audit_note" label="审核意见" min-width="200" />
            <el-table-column label="时间" width="160">
              <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 反馈对话框 -->
    <el-dialog v-model="feedbackDialog" title="提交反馈" width="600px">
      <el-form :model="feedbackForm" label-width="80px">
        <el-form-item label="类型">
          <el-select v-model="feedbackForm.type">
            <el-option label="Bug 报告" value="bug" />
            <el-option label="功能建议" value="suggestion" />
            <el-option label="投诉" value="complaint" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="feedbackForm.title" maxlength="100" show-word-limit />
        </el-form-item>
        <el-form-item label="内容">
          <el-input v-model="feedbackForm.content" type="textarea" :rows="5" maxlength="2000" show-word-limit />
        </el-form-item>
        <el-form-item label="联系方式">
          <el-input v-model="feedbackForm.contact" placeholder="选填，方便我们回复您" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="feedbackDialog = false">取消</el-button>
        <el-button type="primary" @click="submitFeedback">提交</el-button>
      </template>
    </el-dialog>

    <!-- 申诉对话框 -->
    <el-dialog v-model="appealDialog" title="AI 诊断申诉" width="600px">
      <el-form :model="appealForm" label-width="80px">
        <el-form-item label="任务号">
          <el-input v-model="appealForm.task_no" placeholder="被申诉的分析任务号" />
        </el-form-item>
        <el-form-item label="原因">
          <el-select v-model="appealForm.reason">
            <el-option label="诊断结果不准确" value="不准确" />
            <el-option label="建议不合理" value="不合理" />
            <el-option label="系统异常" value="异常" />
          </el-select>
        </el-form-item>
        <el-form-item label="详细说明">
          <el-input v-model="appealForm.description" type="textarea" :rows="5" maxlength="2000" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="appealDialog = false">取消</el-button>
        <el-button type="primary" @click="submitAppeal">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.feedback-page { padding: 16px; }
.text-muted { color: #c0c4cc; }
</style>
