<template>
  <div class="dashboard-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">数据概览</h2>
        <p class="page-desc">提现业务数据统计与分析</p>
      </div>
      <div class="header-right">
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="yyyy-MM-dd"
          size="small"
          @change="fetchStatistics"
        />
      </div>
    </div>

    <el-row :gutter="20" class="stat-row">
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-label">申请笔数</div>
          <div class="stat-number">{{ statistics.total?.count || 0 }}</div>
          <div class="stat-trend up">
            <i class="el-icon-top"></i> {{ statistics.success_rate || 0 }}% 成功率
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-label">申请金额</div>
          <div class="stat-number">¥{{ formatNumber(statistics.total?.request_amount || 0) }}</div>
          <div class="stat-trend">
            手续费收入: ¥{{ formatNumber(statistics.total?.fee_amount || 0) }}
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-label">实际打款</div>
          <div class="stat-number">¥{{ formatNumber(statistics.total?.actual_amount || 0) }}</div>
          <div class="stat-trend">
            已完成: {{ statistics.status?.completed?.count || 0 }} 笔
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card class="stat-card">
          <div class="stat-label">待处理</div>
          <div class="stat-number" style="color: #e6a23c">{{ statistics.status?.pending?.count || 0 }}</div>
          <div class="stat-trend">
            待处理金额: ¥{{ formatNumber(statistics.status?.pending?.amount || 0) }}
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="chart-row">
      <el-col :span="12">
        <el-card class="chart-card">
          <div slot="header" class="card-header">
            <span>按状态统计</span>
          </div>
          <el-table :data="statusData" border style="width: 100%">
            <el-table-column prop="label" label="状态" />
            <el-table-column prop="count" label="笔数" align="center" />
            <el-table-column prop="amount" label="金额(¥)" align="right">
              <template slot-scope="scope">{{ formatNumber(scope.row.amount) }}</template>
            </el-table-column>
            <el-table-column prop="percentage" label="占比" align="center">
              <template slot-scope="scope">
                <el-progress :percentage="scope.row.percentage" :show-text="true" :stroke-width="10" />
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card class="chart-card">
          <div slot="header" class="card-header">
            <span>按提现方式统计</span>
          </div>
          <el-table :data="methodData" border style="width: 100%">
            <el-table-column prop="label" label="提现方式" />
            <el-table-column prop="count" label="笔数" align="center" />
            <el-table-column prop="amount" label="金额(¥)" align="right">
              <template slot-scope="scope">{{ formatNumber(scope.row.amount) }}</template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="24">
        <el-card class="table-card">
          <div slot="header" class="card-header">
            <span>最近提现记录</span>
            <el-button type="primary" size="mini" @click="$router.push('/withdrawal/list')">查看全部</el-button>
          </div>
          <el-table :data="recentWithdrawals" v-loading="loading" border stripe>
            <el-table-column prop="withdrawal_no" label="提现单号" width="180" />
            <el-table-column label="用户">
              <template slot-scope="scope">{{ scope.row.user?.name || '-' }}</template>
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
            <el-table-column label="状态" width="100" align="center">
              <template slot-scope="scope">
                <el-tag :class="['status-tag', scope.row.status]">{{ scope.row.status_label || scope.row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="申请时间" width="160" />
          </el-table>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import { getWithdrawalStatistics, getWithdrawals } from '@/api/withdrawal'

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

export default {
  name: 'Dashboard',
  data() {
    return {
      loading: false,
      statistics: {},
      recentWithdrawals: [],
      dateRange: []
    }
  },
  computed: {
    statusData() {
      const statuses = this.statistics.status || {}
      const total = this.statistics.total?.count || 0
      const result = []

      Object.keys(statusLabels).forEach(key => {
        if (statuses[key]) {
          result.push({
            label: statusLabels[key],
            count: statuses[key].count || 0,
            amount: statuses[key].amount || 0,
            percentage: total > 0 ? Math.round((statuses[key].count / total) * 100) : 0
          })
        }
      })

      return result
    },
    methodData() {
      const methods = this.statistics.methods || {}
      const result = []

      Object.keys(methodLabels).forEach(key => {
        if (methods[key]) {
          result.push({
            label: methodLabels[key],
            count: methods[key].count || 0,
            amount: methods[key].amount || 0
          })
        }
      })

      return result
    }
  },
  created() {
    this.fetchStatistics()
    this.fetchRecentWithdrawals()
  },
  methods: {
    formatNumber(num) {
      return Number(num || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    async fetchStatistics() {
      this.loading = true
      try {
        const params = {}
        if (this.dateRange?.length === 2) {
          params.date_start = this.dateRange[0]
          params.date_end = this.dateRange[1]
        }
        const res = await getWithdrawalStatistics(params)
        this.statistics = res.data
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
    async fetchRecentWithdrawals() {
      try {
        const res = await getWithdrawals({ page: 1, per_page: 10 })
        this.recentWithdrawals = res.data.list || []
      } catch (e) {
        console.error(e)
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.dashboard-page {
  .stat-row {
    margin-bottom: 20px;
  }

  .chart-row {
    margin-bottom: 20px;
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
  }
}
</style>
