<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { safeFetch } from '@/utils/fetch'

interface WithdrawRecord {
  id: string
  withdraw_no: string
  promoter: string
  amount: number
  pay_type: string
  pay_account: string
  applyTime: string
  status: number
}

const searchOrderNo = ref('')
const searchPromoter = ref('')
const searchStatus = ref('')
const currentPage = ref(1)
const pageSize = ref(10)
const total = ref(0)
const loading = ref(false)

const detailVisible = ref(false)
const detailData = ref<WithdrawRecord | null>(null)

const statusOptions = [
  { value: '', label: '全部' },
  { value: '0', label: '待审核' },
  { value: '1', label: '已通过' },
  { value: '2', label: '已拒绝' },
]

const statusMap: Record<number, { type: 'warning' | 'success' | 'danger'; label: string }> = {
  0: { type: 'warning', label: '待审核' },
  1: { type: 'success', label: '已通过' },
  2: { type: 'danger', label: '已拒绝' },
}

const tableData = ref<WithdrawRecord[]>([])

const getToken = (): string => localStorage.getItem('admin_token') || ''

// 加载提现列表
const loadWithdraws = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      limit: pageSize.value.toString(),
    })
    if (searchOrderNo.value) params.append('withdraw_no', searchOrderNo.value)
    if (searchPromoter.value) params.append('promoter', searchPromoter.value)
    if (searchStatus.value) params.append('status', searchStatus.value)

    const res = await safeFetch(`/api/v1/admin/withdraws?${params}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data.data || data.data
      tableData.value = list.map((item: any) => ({
        id: item.id,
        withdraw_no: item.withdraw_no,
        promoter: item.user?.name || item.user?.nickname || '-',
        amount: item.amount,
        pay_type: item.pay_type === 'wechat' ? '微信' : '支付宝',
        pay_account: item.pay_account || '-',
        applyTime: item.created_at || '-',
        status: item.status,
      }))
      total.value = data.data.total || list.length
    } else {
      ElMessage.error(data.message || '加载提现列表失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载提现列表失败')
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  currentPage.value = 1
  loadWithdraws()
}

function handleReset() {
  searchOrderNo.value = ''
  searchPromoter.value = ''
  searchStatus.value = ''
  currentPage.value = 1
  loadWithdraws()
}

function handlePageChange(page: number) {
  currentPage.value = page
  loadWithdraws()
}

function handleSizeChange(size: number) {
  pageSize.value = size
  currentPage.value = 1
  loadWithdraws()
}

function viewDetail(row: WithdrawRecord) {
  detailData.value = row
  detailVisible.value = true
}

async function approve(row: WithdrawRecord) {
  try {
    await ElMessageBox.confirm('确定要通过该提现申请吗？', '确认', {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消',
    })
    const res = await safeFetch(`/api/v1/admin/withdraws/${row.id}/audit`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ status: 1 }),
    })
    const data = await res.json()
    if (data.code === 0) {
      row.status = 1
      ElMessage.success('已通过')
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch {
    // canceled
  }
}

async function reject(row: WithdrawRecord) {
  try {
    await ElMessageBox.confirm('确定要拒绝该提现申请吗？', '确认', {
      type: 'warning',
      confirmButtonText: '确定',
      cancelButtonText: '取消',
    })
    const res = await safeFetch(`/api/v1/admin/withdraws/${row.id}/audit`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ status: 2 }),
    })
    const data = await res.json()
    if (data.code === 0) {
      row.status = 2
      ElMessage.success('已拒绝')
    } else {
      ElMessage.error(data.message || '操作失败')
    }
  } catch {
    // canceled
  }
}

onMounted(() => {
  loadWithdraws()
})
</script>

<template>
  <div class="admin-page-wrapper">
    <div class="page-header">
      <h2 class="page-title">提现审核</h2>
      <p class="page-desc">推广员提现申请审核</p>
    </div>

    <el-card>
      <template #header><span>提现申请列表</span></template>

      <el-form :model="{ orderNo: searchOrderNo, promoter: searchPromoter, status: searchStatus }" inline>
        <el-form-item label="提现单号">
          <el-input v-model="searchOrderNo" placeholder="请输入提现单号" clearable style="width: 180px" />
        </el-form-item>
        <el-form-item label="推广员">
          <el-input v-model="searchPromoter" placeholder="请输入推广员昵称" clearable style="width: 160px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchStatus" placeholder="全部" clearable style="width: 120px">
            <el-option
              v-for="opt in statusOptions"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table :data="tableData" border stripe style="width: 100%" v-loading="loading">
        <el-table-column prop="withdraw_no" label="提现单号" width="180" />
        <el-table-column prop="promoter" label="推广员" min-width="120" />
        <el-table-column prop="amount" label="提现金额" width="120" align="right">
          <template #default="{ row }">
            <span style="font-weight: 600; color: #f56c6c;">¥{{ row.amount.toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="pay_type" label="提现方式" width="100" align="center" />
        <el-table-column prop="pay_account" label="收款账号" width="180" />
        <el-table-column prop="applyTime" label="申请时间" width="170" />
        <el-table-column prop="status" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusMap[row.status]?.type || 'info'" size="small">
              {{ statusMap[row.status]?.label || '未知' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="viewDetail(row)">查看</el-button>
            <el-button
              size="small"
              type="primary"
              link
              :disabled="row.status !== 0"
              @click="approve(row)"
            >
              通过
            </el-button>
            <el-button
              size="small"
              type="danger"
              link
              :disabled="row.status !== 0"
              @click="reject(row)"
            >
              拒绝
            </el-button>
            <span v-if="row.status !== 0" style="color: #999; font-size: 12px">--</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          layout="total, prev, pager, next, jumper"
          background
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </el-card>

    <el-dialog v-model="detailVisible" title="提现详情" width="600px">
      <template v-if="detailData">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="提现单号" label-align="center">{{ detailData.withdraw_no }}</el-descriptions-item>
          <el-descriptions-item label="推广员" label-align="center">{{ detailData.promoter }}</el-descriptions-item>
          <el-descriptions-item label="提现金额" label-align="center">
            <span style="font-weight: 600; color: #f56c6c;">¥{{ detailData.amount.toFixed(2) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="提现方式" label-align="center">{{ detailData.pay_type }}</el-descriptions-item>
          <el-descriptions-item label="收款账号" label-align="center">{{ detailData.pay_account }}</el-descriptions-item>
          <el-descriptions-item label="申请时间" label-align="center">{{ detailData.applyTime }}</el-descriptions-item>
          <el-descriptions-item label="状态" label-align="center">
            <el-tag :type="statusMap[detailData.status]?.type || 'info'" size="small">
              {{ statusMap[detailData.status]?.label || '未知' }}
            </el-tag>
          </el-descriptions-item>
        </el-descriptions>
      </template>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.admin-page-wrapper { max-width: 100%; width: 100%; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 4px; }
.page-desc { font-size: 14px; color: #999; }

.pagination-wrapper { margin-top: 20px; display: flex; justify-content: flex-end; }

/* 手机端适配 */
@media (max-width: 768px) {
  .page-title { font-size: 18px; }

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

  .pagination-wrapper { justify-content: center; }
}
</style>
