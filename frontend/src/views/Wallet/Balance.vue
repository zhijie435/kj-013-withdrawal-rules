<template>
  <div class="wallet-balance-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">余额查询</h2>
        <p class="page-desc">查看钱包余额和资金明细</p>
      </div>
    </div>

    <el-row :gutter="20">
      <el-col :span="8">
        <el-card class="wallet-card" v-for="(balance, currency) in balances" :key="currency">
          <div class="wallet-header">
            <span class="currency">{{ currency }}</span>
            <el-tag :type="balance.is_active ? 'success' : 'info'" size="mini">
              {{ balance.is_active ? '正常' : '冻结' }}
            </el-tag>
          </div>
          <div class="wallet-balance">
            <span class="currency-symbol">¥</span>
            <span class="amount">{{ formatNumber(balance.balance) }}</span>
          </div>
          <div class="wallet-info">
            <div class="info-item">
              <span class="label">可用余额</span>
              <span class="value">¥{{ formatNumber(balance.available_balance) }}</span>
            </div>
            <div class="info-item">
              <span class="label">冻结金额</span>
              <span class="value" style="color: #e6a23c">¥{{ formatNumber(balance.frozen_amount) }}</span>
            </div>
            <div class="info-item">
              <span class="label">待结算</span>
              <span class="value" style="color: #909399">¥{{ formatNumber(balance.pending_settle_amount) }}</span>
            </div>
            <div class="info-item">
              <span class="label">累计提现</span>
              <span class="value">¥{{ formatNumber(balance.total_withdrawn) }}</span>
            </div>
            <div class="info-item">
              <span class="label">累计充值</span>
              <span class="value">¥{{ formatNumber(balance.total_recharge) }}</span>
            </div>
          </div>
          <div class="wallet-actions" v-if="currency === 'CNY'">
            <el-button type="primary" size="small" @click="$router.push('/withdrawal/apply')">
              <i class="el-icon-money"></i> 申请提现
            </el-button>
            <el-button size="small" @click="handleRefresh">
              <i class="el-icon-refresh"></i> 刷新
            </el-button>
          </div>
        </el-card>
      </el-col>
      <el-col :span="16">
        <el-card class="chart-card">
          <div slot="header" class="card-header">
            <span>本月统计</span>
          </div>
          <el-descriptions :column="3" border v-if="monthStats">
            <el-descriptions-item label="本月提现">¥{{ formatNumber(monthStats.month_withdrawn) }}</el-descriptions-item>
            <el-descriptions-item label="本月充值">¥{{ formatNumber(monthStats.month_recharge) }}</el-descriptions-item>
            <el-descriptions-item label="本月收入">¥{{ formatNumber(monthStats.month_income) }}</el-descriptions-item>
            <el-descriptions-item label="当前余额">¥{{ formatNumber(monthStats.balance) }}</el-descriptions-item>
            <el-descriptions-item label="可用余额">¥{{ formatNumber(monthStats.available_balance) }}</el-descriptions-item>
            <el-descriptions-item label="冻结金额">¥{{ formatNumber(monthStats.frozen_amount) }}</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import { getBalance, getTransactions } from '@/api/wallet'
import { mapGetters } from 'vuex'

export default {
  name: 'WalletBalance',
  data() {
    return {
      loading: false,
      balances: {},
      monthStats: null
    }
  },
  computed: {
    ...mapGetters(['userInfo'])
  },
  created() {
    this.fetchBalance()
    this.fetchMonthStats()
  },
  methods: {
    formatNumber(num) {
      return Number(num || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    async fetchBalance() {
      this.loading = true
      try {
        const currencies = ['CNY', 'USD', 'HKD', 'EUR']
        const result = {}

        for (const currency of currencies) {
          try {
            const res = await getBalance(currency)
            result[currency] = res.data
          } catch (e) {
            console.error(`Fetch ${currency} balance error:`, e)
          }
        }

        this.balances = result
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
    async fetchMonthStats() {
      try {
        const res = await getTransactions({
          type: '',
          page: 1,
          per_page: 1
        })
        if (res.data?.list?.length > 0) {
          const firstTx = res.data.list[0]
          const currency = firstTx.currency || 'CNY'
          const balanceRes = await getBalance(currency)
          this.monthStats = balanceRes.data
        }
      } catch (e) {
        console.error(e)
      }
    },
    handleRefresh() {
      this.fetchBalance()
      this.fetchMonthStats()
      this.$message.success('刷新成功')
    }
  }
}
</script>

<style lang="scss" scoped>
.wallet-balance-page {
  .wallet-card {
    margin-bottom: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border: none;

    ::v-deep .el-card__body {
      padding: 24px;
    }

    .wallet-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;

      .currency {
        font-size: 16px;
        font-weight: 600;
      }
    }

    .wallet-balance {
      margin-bottom: 20px;

      .currency-symbol {
        font-size: 24px;
        margin-right: 4px;
      }

      .amount {
        font-size: 36px;
        font-weight: 700;
      }
    }

    .wallet-info {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 16px;

      .info-item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 13px;

        .label {
          color: rgba(255, 255, 255, 0.8);
        }

        .value {
          font-weight: 600;
        }
      }
    }

    .wallet-actions {
      display: flex;
      gap: 10px;
    }
  }

  .card-header {
    font-weight: 600;
  }
}
</style>
