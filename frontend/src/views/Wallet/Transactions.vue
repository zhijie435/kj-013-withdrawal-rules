<template>
  <div class="wallet-transactions-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">交易记录</h2>
        <p class="page-desc">查看钱包交易流水明细</p>
      </div>
    </div>

    <el-card class="filter-card">
      <el-form :model="queryParams" inline>
        <el-form-item label="交易单号">
          <el-input
            v-model="queryParams.transaction_no"
            placeholder="请输入交易单号"
            clearable
            style="width: 200px"
            @keyup.enter.native="handleSearch"
          />
        </el-form-item>
        <el-form-item label="交易类型">
          <el-select v-model="queryParams.type" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in typeOptions"
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
        </el-form-item>
      </el-form>
    </el-card>

    <el-card class="table-card">
      <el-table :data="tableData" v-loading="loading" border stripe>
        <el-table-column type="index" label="序号" width="60" align="center" />
        <el-table-column prop="transaction_no" label="交易单号" width="180" />
        <el-table-column label="用户" v-if="hasPermission('view-users')">
          <template slot-scope="scope">{{ scope.row.user?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="类型" width="120">
          <template slot-scope="scope">
            <el-tag size="mini">{{ getTypeLabel(scope.row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="金额" width="120" align="right">
          <template slot-scope="scope">
            <span :style="{ color: scope.row.amount >= 0 ? '#67c23a' : '#f56c6c' }">
              {{ scope.row.amount >= 0 ? '+' : '' }}{{ Number(scope.row.amount).toFixed(2) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="变动前" width="120" align="right">
          <template slot-scope="scope">{{ Number(scope.row.balance_before).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column label="变动后" width="120" align="right">
          <template slot-scope="scope">{{ Number(scope.row.balance_after).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column prop="currency" label="币种" width="80" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template slot-scope="scope">
            <el-tag size="mini" :type="scope.row.status === 'completed' ? 'success' : 'warning'">
              {{ scope.row.status === 'completed' ? '成功' : scope.row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="说明" min-width="200" show-overflow-tooltip />
        <el-table-column prop="created_at" label="创建时间" width="160" />
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
  </div>
</template>

<script>
import { getTransactions } from '@/api/wallet'
import { mapGetters } from 'vuex'

const typeLabels = {
  recharge: '充值',
  withdrawal: '提现',
  withdrawal_fee: '提现手续费',
  withdrawal_refund: '提现退款',
  order_income: '订单收入',
  order_refund: '订单退款',
  transfer_in: '转入',
  transfer_out: '转出',
  adjust: '调账',
  settlement: '结算'
}

export default {
  name: 'WalletTransactions',
  data() {
    return {
      loading: false,
      tableData: [],
      total: 0,
      dateRange: [],
      typeOptions: Object.keys(typeLabels).map(key => ({
        value: key,
        label: typeLabels[key]
      })),
      queryParams: {
        page: 1,
        per_page: 15,
        transaction_no: '',
        type: '',
        currency: ''
      }
    }
  },
  computed: {
    ...mapGetters(['permissions']),
    hasPermission() {
      return (permission) => {
        return this.permissions.includes(permission) || this.permissions.includes('*')
      }
    }
  },
  created() {
    this.fetchData()
  },
  methods: {
    getTypeLabel(type) {
      return typeLabels[type] || type
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
        const res = await getTransactions(params)
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
        transaction_no: '',
        type: '',
        currency: ''
      }
      this.dateRange = []
      this.fetchData()
    }
  }
}
</script>

<style lang="scss" scoped>
.wallet-transactions-page {
}
</style>
