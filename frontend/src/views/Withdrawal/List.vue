<template>
  <div class="withdrawal-list-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">提现记录</h2>
        <p class="page-desc">查看和管理提现申请记录</p>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="$router.push('/withdrawal/apply')">
          <i class="el-icon-plus"></i> 申请提现
        </el-button>
      </div>
    </div>

    <el-card class="filter-card">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="单号/用户名/手机/邮箱"
            clearable
            style="width: 220px"
            @keyup.enter.native="handleSearch"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in statusOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="币种">
          <el-select v-model="queryParams.currency" placeholder="全部" clearable style="width: 120px">
            <el-option label="人民币" value="CNY" />
            <el-option label="美元" value="USD" />
            <el-option label="港币" value="HKD" />
            <el-option label="欧元" value="EUR" />
          </el-select>
        </el-form-item>
        <el-form-item label="提现方式">
          <el-select v-model="queryParams.withdrawal_method" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in methodOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="日期">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="yyyy-MM-dd"
            size="small"
            style="width: 240px"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" size="small" @click="handleSearch">
            <i class="el-icon-search"></i> 搜索
          </el-button>
          <el-button size="small" @click="handleReset">
            <i class="el-icon-refresh"></i> 重置
          </el-button>
          <el-button
            v-if="hasPermission('approve-withdrawal')"
            type="success"
            size="small"
            :disabled="selectedIds.length === 0"
            @click="handleBatchApprove"
          >
            <i class="el-icon-check"></i> 批量审核
          </el-button>
          <el-button
            v-if="hasPermission('process-withdrawal')"
            type="warning"
            size="small"
            :disabled="selectedIds.length === 0"
            @click="handleBatchProcess"
          >
            <i class="el-icon-s-promotion"></i> 批量打款
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <el-table
        :data="tableData"
        v-loading="loading"
        border
        stripe
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" align="center" />
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="withdrawal_no" label="提现单号" width="180" />
        <el-table-column label="用户" v-if="hasPermission('view-users')" min-width="140">
          <template slot-scope="scope">
            <div>{{ scope.row.user?.name || '-' }}</div>
            <div style="font-size: 12px; color: #909399">{{ scope.row.user?.phone || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="收款账户" min-width="180">
          <template slot-scope="scope">
            <div>{{ scope.row.bank_card?.bank_name || '-' }}</div>
            <div style="font-size: 12px; color: #909399">
              {{ scope.row.bank_card?.masked_card_number || scope.row.bank_card?.card_number || '-' }}
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="request_amount" label="申请金额" width="120" align="right">
          <template slot-scope="scope">¥{{ Number(scope.row.request_amount).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column prop="fee_amount" label="手续费" width="100" align="right">
          <template slot-scope="scope">¥{{ Number(scope.row.fee_amount).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column prop="actual_amount" label="实付金额" width="120" align="right">
          <template slot-scope="scope">¥{{ Number(scope.row.actual_amount).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column label="币种" width="80" align="center" prop="currency" />
        <el-table-column label="状态" width="100" align="center">
          <template slot-scope="scope">
            <el-tag :class="['status-tag', scope.row.status]">
              {{ scope.row.status_label || getStatusLabel(scope.row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="申请时间" width="160" />
        <el-table-column label="操作" width="280" align="center" fixed="right">
          <template slot-scope="scope">
            <el-button type="primary" link size="mini" @click="handleView(scope.row)">查看</el-button>
            <el-button
              v-if="scope.row.status === 'pending' && hasPermission('approve-withdrawal')"
              type="success"
              link
              size="mini"
              @click="handleApprove(scope.row)"
            >通过</el-button>
            <el-button
              v-if="scope.row.status === 'pending' && hasPermission('approve-withdrawal')"
              type="danger"
              link
              size="mini"
              @click="handleReject(scope.row)"
            >拒绝</el-button>
            <el-button
              v-if="scope.row.status === 'approved' && hasPermission('process-withdrawal')"
              type="warning"
              link
              size="mini"
              @click="handleProcess(scope.row)"
            >打款</el-button>
            <el-button
              v-if="scope.row.status === 'processing' && hasPermission('process-withdrawal')"
              type="success"
              link
              size="mini"
              @click="handleComplete(scope.row)"
            >完成</el-button>
            <el-button
              v-if="scope.row.status === 'pending' && (scope.row.user_id === userId || hasPermission('manage-withdrawals'))"
              type="info"
              link
              size="mini"
              @click="handleCancel(scope.row)"
            >取消</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="queryParams.page"
          v-model:page-size="queryParams.per_page"
          :page-sizes="[10, 15, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          background
          @size-change="fetchData"
          @current-change="fetchData"
        />
      </div>
    </el-card>

    <el-dialog
      :visible.sync="detailVisible"
      title="提现详情"
      width="720px"
      append-to-body
    >
      <el-descriptions :column="2" border v-if="currentWithdrawal">
        <el-descriptions-item label="提现单号">{{ currentWithdrawal.withdrawal_no }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :class="['status-tag', currentWithdrawal.status]">
            {{ getStatusLabel(currentWithdrawal.status) }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="用户" v-if="hasPermission('view-users')">
          {{ currentWithdrawal.user?.name || '-' }}
        </el-descriptions-item>
        <el-descriptions-item label="币种">{{ currentWithdrawal.currency }}</el-descriptions-item>
        <el-descriptions-item label="申请金额">¥{{ Number(currentWithdrawal.request_amount).toFixed(2) }}</el-descriptions-item>
        <el-descriptions-item label="手续费率">{{ (currentWithdrawal.fee_rate * 100).toFixed(2) }}%</el-descriptions-item>
        <el-descriptions-item label="手续费">¥{{ Number(currentWithdrawal.fee_amount).toFixed(2) }}</el-descriptions-item>
        <el-descriptions-item label="实付金额">¥{{ Number(currentWithdrawal.actual_amount).toFixed(2) }}</el-descriptions-item>
        <el-descriptions-item label="收款银行">{{ currentWithdrawal.bank_card?.bank_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="收款账号">{{ currentWithdrawal.bank_card?.masked_card_number || currentWithdrawal.bank_card?.card_number || '-' }}</el-descriptions-item>
        <el-descriptions-item label="开户人">{{ currentWithdrawal.bank_card?.card_holder_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="提现方式">{{ getMethodLabel(currentWithdrawal.withdrawal_method) }}</el-descriptions-item>
        <el-descriptions-item label="交易号">{{ currentWithdrawal.transaction_id || '-' }}</el-descriptions-item>
        <el-descriptions-item label="第三方单号">{{ currentWithdrawal.third_party_no || '-' }}</el-descriptions-item>
        <el-descriptions-item label="申请时间" :span="2">{{ currentWithdrawal.created_at }}</el-descriptions-item>
        <el-descriptions-item label="审核时间">{{ currentWithdrawal.approved_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="审核人">{{ currentWithdrawal.approver?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="打款时间">{{ currentWithdrawal.processed_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="打款人">{{ currentWithdrawal.processor?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="完成时间">{{ currentWithdrawal.completed_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="结算时间">{{ currentWithdrawal.settled_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="拒绝原因" v-if="currentWithdrawal.reject_reason" :span="2">
          {{ currentWithdrawal.reject_reason }}
        </el-descriptions-item>
        <el-descriptions-item label="失败原因" v-if="currentWithdrawal.fail_reason" :span="2">
          {{ currentWithdrawal.fail_reason }}
        </el-descriptions-item>
        <el-descriptions-item label="取消原因" v-if="currentWithdrawal.cancel_reason" :span="2">
          {{ currentWithdrawal.cancel_reason }}
        </el-descriptions-item>
        <el-descriptions-item label="备注" v-if="currentWithdrawal.remark" :span="2">
          {{ currentWithdrawal.remark }}
        </el-descriptions-item>
      </el-descriptions>

      <el-divider content-position="left" v-if="currentWithdrawal?.audit_log?.length">审核日志</el-divider>
      <el-timeline v-if="currentWithdrawal?.audit_log?.length">
        <el-timeline-item
          v-for="(log, index) in currentWithdrawal.audit_log"
          :key="index"
          :timestamp="log.time"
          placement="top"
        >
          <el-card shadow="never">
            <h4>{{ getActionLabel(log.action) }}</h4>
            <p>操作人: {{ log.user_name || '-' }}</p>
            <p v-if="log.remark">备注: {{ log.remark }}</p>
          </el-card>
        </el-timeline-item>
      </el-timeline>

      <template slot="footer">
        <el-button @click="detailVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <el-dialog
      :visible.sync="rejectVisible"
      title="拒绝提现"
      width="480px"
      append-to-body
    >
      <el-form :model="rejectForm" :rules="rejectRules" ref="rejectFormRef" label-width="80px">
        <el-form-item label="拒绝原因" prop="reject_reason">
          <el-input
            v-model="rejectForm.reject_reason"
            type="textarea"
            :rows="4"
            placeholder="请输入拒绝原因"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template slot="footer">
        <el-button @click="rejectVisible = false">取消</el-button>
        <el-button type="danger" :loading="actionLoading" @click="confirmReject">确认拒绝</el-button>
      </template>
    </el-dialog>

    <el-dialog
      :visible.sync="cancelVisible"
      title="取消提现"
      width="480px"
      append-to-body
    >
      <el-form :model="cancelForm" :rules="cancelRules" ref="cancelFormRef" label-width="80px">
        <el-form-item label="取消原因" prop="cancel_reason">
          <el-input
            v-model="cancelForm.cancel_reason"
            type="textarea"
            :rows="4"
            placeholder="请输入取消原因"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template slot="footer">
        <el-button @click="cancelVisible = false">取消</el-button>
        <el-button type="warning" :loading="actionLoading" @click="confirmCancel">确认取消</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import {
  getWithdrawals,
  getWithdrawalStatusOptions,
  approveWithdrawal,
  rejectWithdrawal,
  processWithdrawal,
  completeWithdrawal,
  cancelWithdrawal,
  batchApproveWithdrawals,
  batchProcessWithdrawals
} from '@/api/withdrawal'
import { getRuleMethodOptions } from '@/api/withdrawalRule'
import { mapGetters } from 'vuex'

const statusLabels = {
  pending: '待审核',
  approved: '已通过',
  rejected: '已拒绝',
  processing: '处理中',
  completed: '已完成',
  failed: '打款失败',
  cancelled: '已取消',
  settled: '已结算'
}

const methodLabels = {
  bank_transfer: '银行转账',
  alipay: '支付宝',
  wechat: '微信支付',
  usdt: 'USDT'
}

const actionLabels = {
  submit: '提交申请',
  auto_approve: '自动审核通过',
  approve: '审核通过',
  reject: '审核拒绝',
  process: '开始打款',
  complete: '打款完成',
  fail: '打款失败',
  cancel: '取消申请',
  settle: '系统结算'
}

export default {
  name: 'WithdrawalList',
  data() {
    return {
      loading: false,
      actionLoading: false,
      tableData: [],
      total: 0,
      selectedIds: [],
      dateRange: [],
      statusOptions: [],
      methodOptions: [],
      detailVisible: false,
      rejectVisible: false,
      cancelVisible: false,
      currentWithdrawal: null,
      currentId: null,
      rejectForm: {
        reject_reason: ''
      },
      rejectRules: {
        reject_reason: [
          { required: true, message: '请输入拒绝原因', trigger: 'blur' },
          { min: 2, message: '拒绝原因至少2个字符', trigger: 'blur' }
        ]
      },
      cancelForm: {
        cancel_reason: ''
      },
      cancelRules: {
        cancel_reason: [
          { required: true, message: '请输入取消原因', trigger: 'blur' },
          { min: 2, message: '取消原因至少2个字符', trigger: 'blur' }
        ]
      },
      queryParams: {
        page: 1,
        per_page: 15,
        keyword: '',
        status: '',
        currency: '',
        withdrawal_method: ''
      }
    }
  },
  computed: {
    ...mapGetters(['userInfo', 'permissions']),
    userId() {
      return this.userInfo?.id
    },
    hasPermission() {
      return (permission) => {
        return this.permissions.includes(permission) || this.permissions.includes('*')
      }
    }
  },
  created() {
    this.fetchOptions()
    this.fetchData()
  },
  methods: {
    getStatusLabel(status) {
      return statusLabels[status] || status
    },
    getMethodLabel(method) {
      return methodLabels[method] || method
    },
    getActionLabel(action) {
      return actionLabels[action] || action
    },
    async fetchOptions() {
      try {
        const [statusRes, methodRes] = await Promise.all([
          getWithdrawalStatusOptions(),
          getRuleMethodOptions()
        ])
        this.statusOptions = statusRes.data || []
        this.methodOptions = methodRes.data || []
      } catch (e) {
        console.error(e)
      }
    },
    async fetchData() {
      this.loading = true
      try {
        const params = { ...this.queryParams }
        if (this.dateRange?.length === 2) {
          params.start_date = this.dateRange[0]
          params.end_date = this.dateRange[1]
        }
        Object.keys(params).forEach(key => {
          if (params[key] === '' || params[key] === null || params[key] === undefined) {
            delete params[key]
          }
        })
        const res = await getWithdrawals(params)
        this.tableData = res.data.list || []
        this.total = res.data.total || 0
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
    handleSearch() {
      this.queryParams.page = 1
      this.fetchData()
    },
    handleReset() {
      this.queryParams = {
        page: 1,
        per_page: 15,
        keyword: '',
        status: '',
        currency: '',
        withdrawal_method: ''
      }
      this.dateRange = []
      this.fetchData()
    },
    handleSelectionChange(selection) {
      this.selectedIds = selection.map(item => item.id)
    },
    async handleView(row) {
      this.currentWithdrawal = row
      this.detailVisible = true
    },
    async handleApprove(row) {
      this.$confirm(`确认通过该笔 ¥${Number(row.request_amount).toFixed(2)} 的提现申请吗？`, '审核确认', {
        confirmButtonText: '确认通过',
        cancelButtonText: '取消',
        type: 'success'
      }).then(async () => {
        this.actionLoading = true
        try {
          await approveWithdrawal(row.id, { remark: '审核通过' })
          this.$message.success('审核通过')
          this.fetchData()
        } catch (e) {
          console.error(e)
        } finally {
          this.actionLoading = false
        }
      }).catch(() => {})
    },
    handleReject(row) {
      this.currentId = row.id
      this.rejectForm.reject_reason = ''
      this.rejectVisible = true
    },
    async confirmReject() {
      this.$refs.rejectFormRef.validate(async valid => {
        if (!valid) return

        this.actionLoading = true
        try {
          await rejectWithdrawal(this.currentId, this.rejectForm)
          this.$message.success('已拒绝')
          this.rejectVisible = false
          this.fetchData()
        } catch (e) {
          console.error(e)
        } finally {
          this.actionLoading = false
        }
      })
    },
    async handleProcess(row) {
      this.$confirm(`确认开始打款 ¥${Number(row.actual_amount).toFixed(2)} 吗？`, '打款确认', {
        confirmButtonText: '确认打款',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        this.actionLoading = true
        try {
          await processWithdrawal(row.id, { processing_note: '手动打款处理' })
          this.$message.success('已开始打款处理')
          this.fetchData()
        } catch (e) {
          console.error(e)
        } finally {
          this.actionLoading = false
        }
      }).catch(() => {})
    },
    async handleComplete(row) {
      this.$confirm(`确认该笔 ¥${Number(row.actual_amount).toFixed(2)} 的提现已成功到账吗？`, '完成确认', {
        confirmButtonText: '确认完成',
        cancelButtonText: '取消',
        type: 'success'
      }).then(async () => {
        this.actionLoading = true
        try {
          await completeWithdrawal(row.id, { remark: '打款完成' })
          this.$message.success('打款完成')
          this.fetchData()
        } catch (e) {
          console.error(e)
        } finally {
          this.actionLoading = false
        }
      }).catch(() => {})
    },
    handleCancel(row) {
      this.currentId = row.id
      this.cancelForm.cancel_reason = ''
      this.cancelVisible = true
    },
    async confirmCancel() {
      this.$refs.cancelFormRef.validate(async valid => {
        if (!valid) return

        this.actionLoading = true
        try {
          await cancelWithdrawal(this.currentId, this.cancelForm)
          this.$message.success('已取消')
          this.cancelVisible = false
          this.fetchData()
        } catch (e) {
          console.error(e)
        } finally {
          this.actionLoading = false
        }
      })
    },
    async handleBatchApprove() {
      this.$confirm(`确认审核通过选中的 ${this.selectedIds.length} 笔提现吗？`, '批量审核确认', {
        confirmButtonText: '确认审核',
        cancelButtonText: '取消',
        type: 'success'
      }).then(async () => {
        this.actionLoading = true
        try {
          const res = await batchApproveWithdrawals({
            ids: this.selectedIds,
            remark: '批量审核通过'
          })
          this.$message.success(res.message)
          this.fetchData()
        } catch (e) {
          console.error(e)
        } finally {
          this.actionLoading = false
        }
      }).catch(() => {})
    },
    async handleBatchProcess() {
      this.$confirm(`确认批量打款选中的 ${this.selectedIds.length} 笔提现吗？`, '批量打款确认', {
        confirmButtonText: '确认打款',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        this.actionLoading = true
        try {
          const res = await batchProcessWithdrawals({
            ids: this.selectedIds,
            processing_note: '批量打款处理'
          })
          this.$message.success(res.message)
          this.fetchData()
        } catch (e) {
          console.error(e)
        } finally {
          this.actionLoading = false
        }
      }).catch(() => {})
    }
  }
}
</script>

<style lang="scss" scoped>
.withdrawal-list-page {
}
</style>
