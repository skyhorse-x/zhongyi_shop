<script setup lang="ts">
/**
 * 风险监控面板
 * 显示当日事件、黑名单、规则状态
 */
import { ref, onMounted } from 'vue'
import request from '@/api/request'
import { ElMessage, ElMessageBox } from 'element-plus'

const loading = ref(false)
const stats = ref<any>({})
const events = ref<any[]>([])
const blacklists = ref<any[]>([])
const rules = ref<any[]>([])
const activeTab = ref('events')

const load = async () => {
  loading.value = true
  try {
    const [s, e, b, r] = await Promise.all([
      request.get('/admin/risk/statistics'),
      request.get('/admin/risk/events', { params: { per_page: 20 } }),
      request.get('/admin/risk/blacklists', { params: { per_page: 50 } }),
      request.get('/admin/risk/rules', { params: { per_page: 50 } }),
    ])
    stats.value = s
    events.value = (e as any).data || e || []
    blacklists.value = (b as any).data || b || []
    rules.value = (r as any).data || r || []
  } finally {
    loading.value = false
  }
}

const addBlacklist = async () => {
  try {
    const { value } = await ElMessageBox.prompt('输入要封禁的 IP / 手机号 / 设备 ID', '加入黑名单', {
      confirmButtonText: '加入',
      cancelButtonText: '取消',
    })
    const type = value.includes('@') ? 'mobile' : /^\d+$/.test(value) ? 'mobile' : value.includes('.') ? 'ip' : 'device'
    await request.post('/admin/risk/blacklists', { type, value, reason: '管理员手动封禁' })
    ElMessage.success('已加入黑名单')
    load()
  } catch (e) {
    // 用户取消
  }
}

const removeBlacklist = async (type: string, value: string) => {
  try {
    await ElMessageBox.confirm(`确认解禁 ${type}: ${value}？`, '提示', { type: 'warning' })
    await request.delete(`/admin/risk/blacklists/${type}/${value}`)
    ElMessage.success('已解禁')
    load()
  } catch (e) {}
}

const toggleRule = async (rule: any) => {
  await request.put(`/admin/risk/rules/${rule.id}`, { enabled: !rule.enabled })
  ElMessage.success(rule.enabled ? '已禁用' : '已启用')
  load()
}

const levelColor = (level: string) => ({
  low: 'info', medium: 'warning', high: 'danger', critical: 'danger',
}[level] || 'info')

onMounted(load)
</script>

<template>
  <div class="risk-page" v-loading="loading">
    <!-- 顶部统计卡 -->
    <el-row :gutter="16" class="stats-row">
      <el-col :span="4">
        <el-card><div class="stat-label">今日事件</div><div class="stat-value">{{ stats.today_events || 0 }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card><div class="stat-label">今日拒绝</div><div class="stat-value text-danger">{{ stats.today_denied || 0 }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card><div class="stat-label">今日审核中</div><div class="stat-value text-warning">{{ stats.today_review || 0 }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card><div class="stat-label">严重事件</div><div class="stat-value text-danger">{{ stats.critical_events || 0 }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card><div class="stat-label">启用规则</div><div class="stat-value">{{ stats.active_rules || 0 }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card><div class="stat-label">黑名单数</div><div class="stat-value">{{ stats.blacklist_total || 0 }}</div></el-card>
      </el-col>
    </el-row>

    <el-card class="mt-4">
      <template #header>
        <div class="card-header">
          <span>风控管理</span>
          <el-button type="primary" @click="addBlacklist">加入黑名单</el-button>
        </div>
      </template>

      <el-tabs v-model="activeTab">
        <!-- 事件日志 -->
        <el-tab-pane label="事件日志" name="events">
          <el-table :data="events" stripe>
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column label="时间" width="170">
              <template #default="{ row }">{{ new Date(row.created_at).toLocaleString() }}</template>
            </el-table-column>
            <el-table-column prop="type" label="类型" width="100" />
            <el-table-column label="用户" width="140">
              <template #default="{ row }">{{ row.user?.nickname || '匿名' }}</template>
            </el-table-column>
            <el-table-column prop="ip" label="IP" width="130" />
            <el-table-column prop="rule_code" label="规则" />
            <el-table-column label="风险等级" width="100">
              <template #default="{ row }">
                <el-tag :type="levelColor(row.risk_level)">{{ row.risk_level }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="动作" width="100">
              <template #default="{ row }">
                <el-tag :type="row.action === 'deny' ? 'danger' : row.action === 'review' ? 'warning' : 'success'">
                  {{ ({ deny: '拒绝', review: '审核', allow: '放行' } as Record<string, string>)[row.action] }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 黑名单 -->
        <el-tab-pane label="黑名单" name="blacklists">
          <el-table :data="blacklists" stripe>
            <el-table-column prop="type" label="类型" width="100" />
            <el-table-column prop="value" label="值" />
            <el-table-column prop="reason" label="原因" />
            <el-table-column label="到期时间" width="180">
              <template #default="{ row }">
                {{ row.expires_at ? new Date(row.expires_at).toLocaleString() : '永久' }}
              </template>
            </el-table-column>
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <el-button type="danger" link @click="removeBlacklist(row.type, row.value)">解禁</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 规则 -->
        <el-tab-pane label="规则列表" name="rules">
          <el-table :data="rules" stripe>
            <el-table-column prop="code" label="编码" width="180" />
            <el-table-column prop="name" label="名称" />
            <el-table-column prop="type" label="类型" width="120" />
            <el-table-column label="条件" width="280">
              <template #default="{ row }">
                <code>{{ JSON.stringify(row.conditions) }}</code>
              </template>
            </el-table-column>
            <el-table-column prop="action" label="动作" width="100" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-switch :model-value="row.enabled" @change="toggleRule(row)" />
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<style scoped>
.risk-page { padding: 16px; }
.stats-row { margin-bottom: 16px; }
.stat-label { color: #909399; font-size: 13px; }
.stat-value { font-size: 24px; font-weight: 500; margin-top: 6px; }
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.mt-4 { margin-top: 16px; }
</style>