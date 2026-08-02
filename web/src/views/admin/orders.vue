<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { safeFetch } from '@/utils/fetch'
import { Search, Refresh, View, Delete, Tickets, Money, Document, Check, Close } from '@element-plus/icons-vue'

const form = ref({
  orderNo: '',
  phone: '',
  payMethod: '',
  orderStatus: ''
})

const tableData = ref<any[]>([])
const pageSize = ref(10)
const total = ref(0)
const currentPage = ref(1)
const loading = ref(false)

const getToken = (): string => localStorage.getItem('admin_token') || ''

// 状态映射
const statusMap: Record<number, { type: string; label: string; color: string }> = {
  0: { type: 'warning', label: '待支付', color: '#e6a23c' },
  1: { type: 'success', label: '已支付', color: '#67c23a' },
  2: { type: 'info', label: '已取消', color: '#909399' },
  3: { type: 'danger', label: '已退款', color: '#f56c6c' }
}

// 统计
const stats = ref({
  total: 0,
  paid: 0,
  unpaid: 0,
  totalAmount: 0
})

// 加载订单列表
const loadOrders = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      limit: pageSize.value.toString(),
    })
    if (form.value.orderNo) params.append('order_no', form.value.orderNo)
    if (form.value.orderStatus) params.append('status', form.value.orderStatus)
    if (form.value.payMethod) params.append('pay_method', form.value.payMethod)

    const res = await safeFetch(`/api/v1/admin/orders?${params}`, {
      headers: {
        'Authorization': `Bearer ${getToken()}`,
        'Accept': 'application/json',
      },
    })
    const data = await res.json()
    if (data.code === 0) {
      const list = data.data.data || data.data
      tableData.value = list.map((order: any) => ({
        id: order.id,
        orderNo: order.order_no,
        phone: order.user?.mobile || order.user?.phone || '-',
        productName: order.product_name || order.package?.name || '-',
        amount: order.amount || 0,
        payMethod: order.pay_method === 'wechat' ? '微信' : order.pay_method === 'alipay' ? '支付宝' : (order.pay_method || '-'),
        status: order.status,
        payTime: order.paid_at || order.created_at || '-',
        createdAt: order.created_at,
      }))
      total.value = data.data.total || list.length

      // 计算统计
      stats.value.total = total.value
      stats.value.paid = tableData.value.filter((o: any) => o.status === 1).length
      stats.value.unpaid = tableData.value.filter((o: any) => o.status === 0).length
      stats.value.totalAmount = tableData.value
        .filter((o: any) => o.status === 1)
        .reduce((sum: number, o: any) => sum + (o.amount || 0), 0)
    } else {
      ElMessage.error(data.message || '加载订单列表失败')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '加载订单列表失败')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  currentPage.value = 1
  loadOrders()
}

const handleReset = () => {
  form.value = { orderNo: '', phone: '', payMethod: '', orderStatus: '' }
  currentPage.value = 1
  loadOrders()
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  loadOrders()
}

const handleSizeChange = (size: number) => {
  pageSize.value = size
  currentPage.value = 1
  loadOrders()
}

// 查看详情
const detailVisible = ref(false)
const detailData = ref<any>(null)

const viewDetail = (row: any) => {
  detailData.value = row
  detailVisible.value = true
}

// 删除订单
const deleteOrder = (row: any) => {
  ElMessageBox.confirm(`确定要删除订单 "${row.orderNo}" 吗？此操作不可恢复`, '确认删除', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(async () => {
    try {
      const res = await safeFetch(`/api/v1/admin/orders/${row.orderNo}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${getToken()}`,
          'Accept': 'application/json',
        },
      })
      const data = await res.json()
      if (data.code === 0) {
        ElMessage.success('删除成功')
        loadOrders()
      } else {
        ElMessage.error(data.message || '删除失败')
      }
    } catch (e: any) {
      ElMessage.error(e.message || '删除失败')
    }
  }).catch(() => {})
}

// 格式化时间
const formatTime = (time: string) => {
  if (!time || time === '-') return '-'
  return time
}

onMounted(() => {
  loadOrders()
})
</script>

<template>
  <div class="admin-page-wrapper">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>订单管理</h2>
      </div>
      <el-button :icon="Refresh" @click="loadOrders" :loading="loading">刷新</el-button>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon stat-icon--blue">
          <el-icon><Tickets /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-label">订单总数</div>
          <div class="stat-value">{{ stats.total }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon--green">
          <el-icon><Check /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-label">已支付</div>
          <div class="stat-value">{{ stats.paid }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon--orange">
          <el-icon><Close /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-label">待支付</div>
          <div class="stat-value">{{ stats.unpaid }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon--purple">
          <el-icon><Money /></el-icon>
        </div>
        <div class="stat-info">
          <div class="stat-label">已支付金额</div>
          <div class="stat-value">¥{{ stats.totalAmount.toFixed(2) }}</div>
        </div>
      </div>
    </div>

    <!-- 搜索区 -->
    <el-card class="search-card" shadow="never">
      <el-form :model="form" inline>
        <el-form-item label="订单号">
          <el-input
            v-model="form.orderNo"
            placeholder="请输入订单号"
            clearable
            style="width: 200px"
          />
        </el-form-item>
        <el-form-item label="支付方式">
          <el-select v-model="form.payMethod" placeholder="全部" clearable style="width: 150px">
            <el-option label="全部" value="" />
            <el-option label="微信" value="wechat" />
            <el-option label="支付宝" value="alipay" />
          </el-select>
        </el-form-item>
        <el-form-item label="订单状态">
          <el-select v-model="form.orderStatus" placeholder="全部" clearable style="width: 150px">
            <el-option label="全部" value="" />
            <el-option label="待支付" value="0" />
            <el-option label="已支付" value="1" />
            <el-option label="已取消" value="2" />
            <el-option label="已退款" value="3" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 表格 -->
    <el-card class="table-card" shadow="never">
      <el-table
        :data="tableData"
        stripe
        v-loading="loading"
        :header-cell-style="{ background: '#fafafa', color: '#606266' }"
      >
        <el-table-column prop="orderNo" label="订单号" min-width="180">
          <template #default="scope">
            <span class="order-no">{{ scope.row.orderNo }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="phone" label="用户手机号" min-width="130">
          <template #default="scope">
            <span class="phone-cell">{{ scope.row.phone }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="productName" label="商品名称" min-width="180" show-overflow-tooltip />
        <el-table-column label="金额" width="120" align="center">
          <template #default="scope">
            <span class="amount">¥{{ scope.row.amount.toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="payMethod" label="支付方式" width="110" align="center">
          <template #default="scope">
            <span v-if="scope.row.payMethod === '微信'" class="pay-tag pay-tag--wechat">
              <span class="pay-icon">💚</span>微信
            </span>
            <span v-else-if="scope.row.payMethod === '支付宝'" class="pay-tag pay-tag--alipay">
              <span class="pay-icon">💙</span>支付宝
            </span>
            <span v-else class="pay-tag pay-tag--default">-</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="110" align="center">
          <template #default="scope">
            <el-tag
              :type="statusMap[scope.row.status]?.type || 'info'"
              size="small"
              effect="light"
              round
            >
              <span class="status-dot" :style="{ background: statusMap[scope.row.status]?.color }"></span>
              {{ statusMap[scope.row.status]?.label || '未知' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="支付时间" width="180">
          <template #default="scope">
            <span class="time-cell">{{ formatTime(scope.row.payTime) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" align="center" fixed="right">
          <template #default="scope">
            <el-button type="primary" link size="small" :icon="View" @click="viewDetail(scope.row)">详情</el-button>
            <el-button type="danger" link size="small" :icon="Delete" @click="deleteOrder(scope.row)">删除</el-button>
          </template>
        </el-table-column>

        <template #empty>
          <div class="empty-state">
            <el-icon class="empty-icon"><Document /></el-icon>
            <p>暂无订单数据</p>
          </div>
        </template>
      </el-table>

      <el-pagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :page-sizes="[10, 20, 50, 100]"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        background
        @current-change="handlePageChange"
        @size-change="handleSizeChange"
        class="pagination"
      />
    </el-card>

    <!-- 订单详情弹窗 -->
    <el-dialog v-model="detailVisible" title="订单详情" width="640px" align-center>
      <template #header>
        <div class="dialog-header">
          <el-icon class="dialog-icon"><Tickets /></el-icon>
          <span>订单详情</span>
        </div>
      </template>
      <el-descriptions :column="2" border v-if="detailData" class="detail-descriptions">
        <el-descriptions-item label="订单号">
          <span class="order-no">{{ detailData.orderNo }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="用户手机号">{{ detailData.phone }}</el-descriptions-item>
        <el-descriptions-item label="商品名称" :span="2">{{ detailData.productName }}</el-descriptions-item>
        <el-descriptions-item label="金额">
          <span class="amount">¥{{ detailData.amount.toFixed(2) }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="支付方式">{{ detailData.payMethod }}</el-descriptions-item>
        <el-descriptions-item label="订单状态">
          <el-tag
            :type="statusMap[detailData.status]?.type || 'info'"
            size="small"
            effect="light"
            round
          >
            <span class="status-dot" :style="{ background: statusMap[detailData.status]?.color }"></span>
            {{ statusMap[detailData.status]?.label || '未知' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="支付时间">{{ detailData.payTime }}</el-descriptions-item>
      </el-descriptions>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.admin-page-wrapper {
  width: 100%;
}

/* 页面头部 */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #303133;
}

/* 统计卡片 */
.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.stat-card {
  background: #fff;
  border-radius: 8px;
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  transition: all 0.2s ease;
}

.stat-card:hover {
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-1px);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: #fff;
  flex-shrink: 0;
}

.stat-icon--blue {
  background: linear-gradient(135deg, #409eff, #2b7fd9);
}

.stat-icon--green {
  background: linear-gradient(135deg, #67c23a, #4f9b2e);
}

.stat-icon--orange {
  background: linear-gradient(135deg, #e6a23c, #c8821f);
}

.stat-icon--purple {
  background: linear-gradient(135deg, #9b59b6, #7d3c98);
}

.stat-info {
  flex: 1;
  min-width: 0;
}

.stat-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 22px;
  font-weight: 600;
  color: #303133;
  line-height: 1.2;
}

/* 卡片 */
:deep(.search-card),
:deep(.table-card) {
  border: none;
  border-radius: 8px;
  margin-bottom: 16px;
}

:deep(.search-card .el-card__body) {
  padding: 16px 20px;
}

:deep(.table-card .el-card__body) {
  padding: 16px 20px;
}

/* 表格 */
:deep(.el-table) {
  border-radius: 6px;
  overflow: hidden;
}

:deep(.el-table th.el-table__cell) {
  font-weight: 600;
  font-size: 13px;
}

:deep(.el-table td.el-table__cell) {
  padding: 12px 0;
}

/* 订单号 */
.order-no {
  font-family: 'Courier New', monospace;
  font-weight: 600;
  color: #303133;
  font-size: 13px;
}

/* 手机号 */
.phone-cell {
  font-family: 'Courier New', monospace;
  color: #606266;
}

/* 金额 */
.amount {
  font-weight: 600;
  color: #f56c6c;
  font-size: 14px;
}

/* 支付方式标签 */
.pay-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
}

.pay-icon {
  font-size: 12px;
}

.pay-tag--wechat {
  background: #e8f5e8;
  color: #07c160;
}

.pay-tag--alipay {
  background: #e8f1fc;
  color: #1677ff;
}

.pay-tag--default {
  color: #909399;
}

/* 状态点 */
:deep(.el-tag .status-dot) {
  display: inline-block;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  margin-right: 4px;
  vertical-align: middle;
}

/* 时间 */
.time-cell {
  font-size: 12px;
  color: #606266;
  font-family: 'Courier New', monospace;
}

/* 分页 */
.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

/* 空状态 */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 0;
  color: #c0c4cc;
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
}

.empty-state p {
  margin: 0;
  font-size: 14px;
}

/* 弹窗 */
.dialog-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 600;
  color: #303133;
}

.dialog-icon {
  color: #07c160;
  font-size: 18px;
}

:deep(.detail-descriptions) {
  margin-bottom: 16px;
}

/* 响应式 */
@media (max-width: 1200px) {
  .stats-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .stats-row {
    grid-template-columns: 1fr;
  }
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
}
</style>
