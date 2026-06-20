<template>
  <div class="withdrawal-apply-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">申请提现</h2>
        <p class="page-desc">提交提现申请，审核通过后打款到您的银行卡</p>
      </div>
    </div>

    <el-row :gutter="20">
      <el-col :span="14">
        <el-card class="apply-card">
          <el-alert
            v-if="currentRule"
            :title="`当前适用规则: ${currentRule.name} - 费率 ${(currentRule.fee_rate * 100).toFixed(2)}%`"
            type="info"
            :closable="false"
            show-icon
            style="margin-bottom: 20px"
          >
            <template slot-scope="{ type }">
              <div>
                <p>最低提现: ¥{{ currentRule.min_amount }}，最高提现: ¥{{ currentRule.max_amount }}</p>
                <p>每日限额: ¥{{ currentRule.daily_limit }}，每日最多 {{ currentRule.daily_max_count }} 笔</p>
                <p>结算周期: T+{{ currentRule.settlement_days }} 个工作日</p>
                <p v-if="currentRule.require_approval">
                  <el-tag type="warning">需要人工审核</el-tag>
                </p>
              </div>
            </template>
          </el-alert>

          <el-form :model="formData" :rules="formRules" ref="formRef" label-width="120px">
            <el-form-item label="提现方式" prop="withdrawal_method">
              <el-select v-model="formData.withdrawal_method" style="width: 100%" @change="handleMethodChange">
                <el-option
                  v-for="item in methodOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>

            <el-form-item label="币种" prop="currency">
              <el-select v-model="formData.currency" style="width: 100%" @change="handleCurrencyChange">
                <el-option
                  v-for="item in currencyOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>

            <el-form-item label="收款账户" prop="bank_card_id" required>
              <el-select v-model="formData.bank_card_id" style="width: 100%" placeholder="请选择收款账户">
                <el-option
                  v-for="card in bankCards"
                  :key="card.id"
                  :label="`${card.bank_name} - ${card.masked_card_number || card.card_number} - ${card.card_holder_name}`"
                  :value="card.id"
                >
                  <span>{{ card.bank_name }}</span>
                  <span style="float: right; color: #8492a6; font-size: 13px">
                    {{ card.masked_card_number || card.card_number }}
                    <el-tag v-if="card.is_default" size="mini" type="success" style="margin-left: 8px">默认</el-tag>
                  </span>
                </el-option>
              </el-select>
              <el-button
                type="text"
                style="margin-top: 8px"
                @click="$router.push('/config/bank-cards')"
              >
                <i class="el-icon-plus"></i> 添加收款账户
              </el-button>
            </el-form-item>

            <el-form-item label="提现金额" prop="request_amount">
              <el-input-number
                v-model="formData.request_amount"
                :min="currentRule?.min_amount || 100"
                :max="currentRule?.max_amount || 50000"
                :step="100"
                :precision="2"
                style="width: 100%"
                size="large"
                @change="handleAmountChange"
              />
              <div class="amount-tips">
                <span>可用余额: <strong style="color: #67c23a">¥{{ formatNumber(balance?.available_balance || 0) }}</strong></span>
                <el-button type="text" @click="setMaxAmount">全部提现</el-button>
              </div>
            </el-form-item>

            <el-divider content-position="left">费用明细</el-divider>

            <el-table :data="feeDetails" border size="small" style="margin-bottom: 20px">
              <el-table-column prop="label" label="项目" />
              <el-table-column prop="value" label="金额(¥)" align="right">
                <template slot-scope="scope">
                  <span :class="{ 'text-danger': scope.row.highlight }">{{ scope.row.value }}</span>
                </template>
              </el-table-column>
            </el-table>

            <el-form-item label="备注" prop="remark">
              <el-input
                v-model="formData.remark"
                type="textarea"
                :rows="2"
                placeholder="选填，备注信息"
                maxlength="500"
                show-word-limit
              />
            </el-form-item>

            <el-form-item>
              <el-button
                type="primary"
                size="large"
                :loading="submitLoading"
                :disabled="!canSubmit"
                style="width: 100%"
                @click="handleSubmit"
              >
                <i class="el-icon-check"></i> 确认提现
              </el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-col>

      <el-col :span="10">
        <el-card class="info-card">
          <div slot="header" class="card-header">
            <span>提现须知</span>
          </div>
          <div class="tips-content">
            <h4><i class="el-icon-warning" style="color: #e6a23c"></i> 重要提示</h4>
            <ul>
              <li>提现将在审核通过后 1-3 个工作日内到账</li>
              <li>请确保收款账户信息准确无误，避免打款失败</li>
              <li>提现金额将冻结直至审核完成，审核不通过将自动解冻</li>
              <li>节假日提现可能会顺延至下一个工作日处理</li>
              <li>如有疑问，请联系客服：400-XXXX-XXXX</li>
            </ul>

            <h4 style="margin-top: 20px"><i class="el-icon-document" style="color: #409eff"></i> 最近提现</h4>
            <el-table :data="recentWithdrawals" v-loading="loading" size="small" border>
              <el-table-column prop="request_amount" label="金额" align="right">
                <template slot-scope="scope">¥{{ Number(scope.row.request_amount).toFixed(2) }}</template>
              </el-table-column>
              <el-table-column label="状态" align="center">
                <template slot-scope="scope">
                  <el-tag size="mini" :class="['status-tag', scope.row.status]">
                    {{ getStatusLabel(scope.row.status) }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="created_at" label="时间" width="140" />
            </el-table>
            <el-button
              type="text"
              style="width: 100%; margin-top: 10px"
              @click="$router.push('/withdrawal/list')"
            >
              查看全部提现记录 <i class="el-icon-arrow-right"></i>
            </el-button>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import { getBalance } from '@/api/wallet'
import { getCurrentRule, getRuleMethodOptions, getRuleCurrencyOptions } from '@/api/withdrawalRule'
import { calculateWithdrawalFee, applyWithdrawal, getWithdrawals } from '@/api/withdrawal'
import { getActiveBankCards } from '@/api/bankCard'
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

export default {
  name: 'WithdrawalApply',
  data() {
    return {
      loading: false,
      submitLoading: false,
      balance: null,
      currentRule: null,
      methodOptions: [],
      currencyOptions: [],
      bankCards: [],
      recentWithdrawals: [],
      feeDetails: [],
      formRef: null,
      formData: {
        request_amount: 1000,
        bank_card_id: null,
        currency: 'CNY',
        withdrawal_method: 'bank_transfer',
        remark: ''
      },
      formRules: {
        request_amount: [
          { required: true, message: '请输入提现金额', trigger: 'blur' },
          { type: 'number', min: 100, message: '提现金额不能低于最低限额', trigger: 'blur' }
        ],
        bank_card_id: [
          { required: true, message: '请选择收款账户', trigger: 'change' }
        ],
        withdrawal_method: [
          { required: true, message: '请选择提现方式', trigger: 'change' }
        ],
        currency: [
          { required: true, message: '请选择币种', trigger: 'change' }
        ]
      }
    }
  },
  computed: {
    ...mapGetters(['userInfo']),
    canSubmit() {
      return (
        this.currentRule &&
        this.balance &&
        this.formData.request_amount > 0 &&
        this.formData.bank_card_id &&
        this.formData.request_amount <= (this.balance.available_balance || 0)
      )
    }
  },
  created() {
    this.fetchInitialData()
    this.fetchRecentWithdrawals()
  },
  methods: {
    formatNumber(num) {
      return Number(num || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    getStatusLabel(status) {
      return statusLabels[status] || status
    },
    async fetchInitialData() {
      this.loading = true
      try {
        const [balanceRes, methodRes, currencyRes, cardsRes] = await Promise.all([
          getBalance(),
          getRuleMethodOptions(),
          getRuleCurrencyOptions(),
          getActiveBankCards()
        ])

        this.balance = balanceRes.data
        this.methodOptions = methodRes.data || []
        this.currencyOptions = currencyRes.data || []
        this.bankCards = cardsRes.data || []

        if (this.bankCards.length > 0) {
          const defaultCard = this.bankCards.find(c => c.is_default) || this.bankCards[0]
          this.formData.bank_card_id = defaultCard.id
        }

        await this.fetchCurrentRule()
        this.updateFeeDetails()
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
    async fetchCurrentRule() {
      try {
        const res = await getCurrentRule({
          user_level: this.userInfo?.level || 'normal',
          currency: this.formData.currency,
          withdrawal_method: this.formData.withdrawal_method
        })
        this.currentRule = res.data
        this.formRules.request_amount[1].min = this.currentRule.min_amount
      } catch (e) {
        console.error(e)
      }
    },
    async fetchRecentWithdrawals() {
      try {
        const res = await getWithdrawals({ page: 1, per_page: 5 })
        this.recentWithdrawals = res.data.list || []
      } catch (e) {
        console.error(e)
      }
    },
    async handleMethodChange() {
      await this.fetchCurrentRule()
      this.updateFeeDetails()
    },
    async handleCurrencyChange() {
      try {
        const res = await getBalance(this.formData.currency)
        this.balance = res.data
      } catch (e) {
        this.balance = { available_balance: 0, balance: 0 }
      }
      await this.fetchCurrentRule()
      this.updateFeeDetails()
    },
    async handleAmountChange() {
      this.updateFeeDetails()
    },
    setMaxAmount() {
      if (this.currentRule && this.balance) {
        const maxWithdrawable = Math.min(
          this.currentRule.max_amount,
          this.balance.available_balance
        )
        this.formData.request_amount = Math.floor(maxWithdrawable * 100) / 100
      }
    },
    async updateFeeDetails() {
      if (!this.currentRule || !this.formData.request_amount) {
        this.feeDetails = []
        return
      }

      try {
        const res = await calculateWithdrawalFee({
          request_amount: this.formData.request_amount,
          currency: this.formData.currency,
          withdrawal_method: this.formData.withdrawal_method,
          user_level: this.userInfo?.level || 'normal'
        })

        const fee = res.data
        this.feeDetails = [
          { label: '申请提现金额', value: `¥${Number(fee.request_amount).toFixed(2)}` },
          { label: `手续费 (${(fee.fee_rate * 100).toFixed(2)}%)`, value: `¥${Number(fee.fee_amount).toFixed(2)}`, highlight: true },
          { label: '预计到账金额', value: `¥${Number(fee.actual_amount).toFixed(2)}` }
        ]

        if (fee.require_approval) {
          this.feeDetails.push({
            label: '审核说明',
            value: '金额超过阈值，需要人工审核'
          })
        }
      } catch (e) {
        console.error(e)
      }
    },
    handleSubmit() {
      this.$refs.formRef.validate(async valid => {
        if (!valid) return

        this.$confirm(
          `确认提现 ¥${Number(this.formData.request_amount).toFixed(2)} 到选择的收款账户吗？`,
          '提现确认',
          {
            confirmButtonText: '确认提现',
            cancelButtonText: '取消',
            type: 'warning'
          }
        ).then(async () => {
          this.submitLoading = true
          try {
            await applyWithdrawal(this.formData)
            this.$message.success('提现申请提交成功')
            this.$router.push('/withdrawal/list')
          } catch (e) {
            console.error(e)
          } finally {
            this.submitLoading = false
          }
        }).catch(() => {})
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.withdrawal-apply-page {
  .apply-card {
    margin-bottom: 20px;
  }

  .info-card {
    .card-header {
      font-weight: 600;
    }

    .tips-content {
      h4 {
        font-size: 14px;
        margin: 0 0 12px 0;
        color: #303133;
      }

      ul {
        padding-left: 20px;
        margin: 0;

        li {
          font-size: 13px;
          color: #606266;
          line-height: 1.8;
        }
      }
    }
  }

  .amount-tips {
    margin-top: 8px;
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #909399;
  }

  .text-danger {
    color: #f56c6c;
    font-weight: 600;
  }
}
</style>
