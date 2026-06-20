<template>
  <div class="withdrawal-rules-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">提现规则</h2>
        <p class="page-desc">配置不同用户等级、币种和提现方式的费率和限额</p>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="handleAdd">
          <i class="el-icon-plus"></i> 新建规则
        </el-button>
      </div>
    </div>

    <el-card class="filter-card">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="规则名称/编码/描述"
            clearable
            style="width: 200px"
            @keyup.enter.native="handleSearch"
          />
        </el-form-item>
        <el-form-item label="用户等级">
          <el-select v-model="queryParams.user_level" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in levelOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="币种">
          <el-select v-model="queryParams.currency" placeholder="全部" clearable style="width: 120px">
            <el-option
              v-for="item in currencyOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
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
        <el-form-item label="状态">
          <el-select v-model="queryParams.is_active" placeholder="全部" clearable style="width: 120px">
            <el-option label="启用" :value="true" />
            <el-option label="禁用" :value="false" />
          </el-select>
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
        <el-table-column prop="code" label="规则编码" width="120" />
        <el-table-column prop="name" label="规则名称" min-width="160" />
        <el-table-column label="适用对象" width="100" align="center">
          <template slot-scope="scope">
            <el-tag size="mini">{{ getLevelLabel(scope.row.user_level) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="currency" label="币种" width="80" align="center" />
        <el-table-column label="提现方式" width="100" align="center">
          <template slot-scope="scope">{{ getMethodLabel(scope.row.withdrawal_method) }}</template>
        </el-table-column>
        <el-table-column label="金额范围" width="160" align="center">
          <template slot-scope="scope">
            ¥{{ Number(scope.row.min_amount).toFixed(2) }} ~ ¥{{ Number(scope.row.max_amount).toFixed(2) }}
          </template>
        </el-table-column>
        <el-table-column label="手续费率" width="100" align="center">
          <template slot-scope="scope">{{ (scope.row.fee_rate * 100).toFixed(2) }}%</template>
        </el-table-column>
        <el-table-column label="手续费上下限" width="160" align="center">
          <template slot-scope="scope">
            ¥{{ Number(scope.row.fee_min).toFixed(2) }} ~ ¥{{ Number(scope.row.fee_max).toFixed(2) }}
          </template>
        </el-table-column>
        <el-table-column label="每日限额" width="120" align="right">
          <template slot-scope="scope">¥{{ Number(scope.row.daily_limit).toFixed(0) }}</template>
        </el-table-column>
        <el-table-column label="结算周期" width="100" align="center">
          <template slot-scope="scope">T+{{ scope.row.settlement_days }}</template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template slot-scope="scope">
            <el-switch
              v-model="scope.row.is_active"
              @change="handleToggle(scope.row)"
              active-text="启"
              inactive-text="禁"
            />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template slot-scope="scope">
            <el-button type="primary" link size="mini" @click="handleView(scope.row)">查看</el-button>
            <el-button type="primary" link size="mini" @click="handleEdit(scope.row)">编辑</el-button>
            <el-button type="danger" link size="mini" @click="handleDelete(scope.row)">删除</el-button>
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
      :visible.sync="dialogVisible"
      :title="isEdit ? '编辑规则' : '新建规则'"
      width="800px"
      append-to-body
      :close-on-click-modal="false"
    >
      <el-form :model="formData" :rules="formRules" ref="formRef" label-width="140px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="规则名称" prop="name">
              <el-input v-model="formData.name" placeholder="请输入规则名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="规则编码" prop="code">
              <el-input v-model="formData.code" placeholder="请输入规则编码" :disabled="isEdit" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="适用等级" prop="user_level">
              <el-select v-model="formData.user_level" style="width: 100%">
                <el-option
                  v-for="item in levelOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="币种" prop="currency">
              <el-select v-model="formData.currency" style="width: 100%">
                <el-option
                  v-for="item in currencyOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="提现方式" prop="withdrawal_method">
              <el-select v-model="formData.withdrawal_method" style="width: 100%">
                <el-option
                  v-for="item in methodOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="排序" prop="sort_order">
              <el-input-number v-model="formData.sort_order" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-divider content-position="left">金额配置</el-divider>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="最低提现" prop="min_amount">
              <el-input-number v-model="formData.min_amount" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="最高提现" prop="max_amount">
              <el-input-number v-model="formData.max_amount" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="每日限额" prop="daily_limit">
              <el-input-number v-model="formData.daily_limit" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="每月限额" prop="monthly_limit">
              <el-input-number v-model="formData.monthly_limit" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="每日次数" prop="daily_max_count">
              <el-input-number v-model="formData.daily_max_count" :min="1" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="结算周期(天)" prop="settlement_days">
              <el-input-number v-model="formData.settlement_days" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-divider content-position="left">手续费配置</el-divider>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="手续费率(%)" prop="fee_rate">
              <el-input-number
                v-model="formData.fee_rate"
                :min="0"
                :max="1"
                :step="0.0001"
                :precision="4"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="最低手续费" prop="fee_min">
              <el-input-number v-model="formData.fee_min" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="最高手续费" prop="fee_max">
              <el-input-number v-model="formData.fee_max" :min="0" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-divider content-position="left">审核配置</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="需要审核" prop="require_approval">
              <el-switch v-model="formData.require_approval" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="审核阈值" prop="approval_threshold">
              <el-input-number
                v-model="formData.approval_threshold"
                :min="0"
                :precision="2"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-divider content-position="left">生效时间</el-divider>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="生效开始" prop="effective_from">
              <el-date-picker
                v-model="formData.effective_from"
                type="datetime"
                placeholder="选择生效时间"
                value-format="yyyy-MM-dd HH:mm:ss"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="生效结束" prop="effective_to">
              <el-date-picker
                v-model="formData.effective_to"
                type="datetime"
                placeholder="选择结束时间"
                value-format="yyyy-MM-dd HH:mm:ss"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="是否启用" prop="is_active">
              <el-switch v-model="formData.is_active" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="规则描述" prop="description">
          <el-input
            v-model="formData.description"
            type="textarea"
            :rows="3"
            placeholder="请输入规则描述"
          />
        </el-form-item>
      </el-form>
      <template slot="footer">
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>

    <el-drawer
      :visible.sync="detailVisible"
      title="规则详情"
      size="500px"
      append-to-body
    >
      <el-descriptions :column="1" border v-if="currentRule">
        <el-descriptions-item label="规则编码">{{ currentRule.code }}</el-descriptions-item>
        <el-descriptions-item label="规则名称">{{ currentRule.name }}</el-descriptions-item>
        <el-descriptions-item label="适用等级">{{ getLevelLabel(currentRule.user_level) }}</el-descriptions-item>
        <el-descriptions-item label="币种">{{ currentRule.currency }}</el-descriptions-item>
        <el-descriptions-item label="提现方式">{{ getMethodLabel(currentRule.withdrawal_method) }}</el-descriptions-item>
        <el-descriptions-item label="金额范围">
          ¥{{ Number(currentRule.min_amount).toFixed(2) }} ~ ¥{{ Number(currentRule.max_amount).toFixed(2) }}
        </el-descriptions-item>
        <el-descriptions-item label="每日限额">¥{{ Number(currentRule.daily_limit).toFixed(2) }}</el-descriptions-item>
        <el-descriptions-item label="每月限额">¥{{ Number(currentRule.monthly_limit).toFixed(2) }}</el-descriptions-item>
        <el-descriptions-item label="每日次数">{{ currentRule.daily_max_count }} 次</el-descriptions-item>
        <el-descriptions-item label="手续费率">{{ (currentRule.fee_rate * 100).toFixed(2) }}%</el-descriptions-item>
        <el-descriptions-item label="手续费范围">
          ¥{{ Number(currentRule.fee_min).toFixed(2) }} ~ ¥{{ Number(currentRule.fee_max).toFixed(2) }}
        </el-descriptions-item>
        <el-descriptions-item label="结算周期">T+{{ currentRule.settlement_days }} 天</el-descriptions-item>
        <el-descriptions-item label="需要审核">
          <el-tag :type="currentRule.require_approval ? 'warning' : 'success'">
            {{ currentRule.require_approval ? '是' : '否' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="审核阈值">
          超过 ¥{{ Number(currentRule.approval_threshold).toFixed(2) }} 需要审核
        </el-descriptions-item>
        <el-descriptions-item label="生效时间">
          {{ currentRule.effective_from || '-' }} ~ {{ currentRule.effective_to || '永久' }}
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="currentRule.is_active ? 'success' : 'info'">
            {{ currentRule.is_active ? '启用' : '禁用' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="引用次数">{{ currentRule.withdrawals_count || 0 }} 次</el-descriptions-item>
        <el-descriptions-item label="规则描述">{{ currentRule.description || '-' }}</el-descriptions-item>
        <el-descriptions-item label="创建人">{{ currentRule.creator?.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ currentRule.created_at }}</el-descriptions-item>
      </el-descriptions>
    </el-drawer>
  </div>
</template>

<script>
import {
  getWithdrawalRules,
  createWithdrawalRule,
  updateWithdrawalRule,
  deleteWithdrawalRule,
  toggleRuleActive,
  getRuleStatusOptions,
  getRuleLevelOptions,
  getRuleMethodOptions,
  getRuleCurrencyOptions
} from '@/api/withdrawalRule'

const levelLabels = {
  all: '所有等级',
  super: '超级用户',
  vip: 'VIP用户',
  normal: '普通用户'
}

const methodLabels = {
  bank_transfer: '银行转账',
  alipay: '支付宝',
  wechat: '微信支付',
  usdt: 'USDT'
}

export default {
  name: 'WithdrawalRules',
  data() {
    return {
      loading: false,
      submitLoading: false,
      tableData: [],
      total: 0,
      dialogVisible: false,
      detailVisible: false,
      isEdit: false,
      currentRule: null,
      statusOptions: [],
      levelOptions: [],
      methodOptions: [],
      currencyOptions: [],
      formRef: null,
      queryParams: {
        page: 1,
        per_page: 15,
        keyword: '',
        user_level: '',
        currency: '',
        withdrawal_method: '',
        is_active: ''
      },
      formData: {
        id: null,
        name: '',
        code: '',
        user_level: 'normal',
        currency: 'CNY',
        withdrawal_method: 'bank_transfer',
        min_amount: 100,
        max_amount: 50000,
        daily_limit: 200000,
        monthly_limit: 1000000,
        fee_rate: 0.005,
        fee_min: 1,
        fee_max: 500,
        settlement_days: 7,
        daily_max_count: 5,
        require_approval: true,
        approval_threshold: 10000,
        description: '',
        is_active: true,
        sort_order: 0,
        effective_from: '',
        effective_to: ''
      },
      formRules: {
        name: [
          { required: true, message: '请输入规则名称', trigger: 'blur' }
        ],
        code: [
          { required: true, message: '请输入规则编码', trigger: 'blur' }
        ],
        user_level: [
          { required: true, message: '请选择适用等级', trigger: 'change' }
        ],
        currency: [
          { required: true, message: '请选择币种', trigger: 'change' }
        ],
        withdrawal_method: [
          { required: true, message: '请选择提现方式', trigger: 'change' }
        ],
        min_amount: [
          { required: true, message: '请输入最低提现金额', trigger: 'blur' }
        ],
        max_amount: [
          { required: true, message: '请输入最高提现金额', trigger: 'blur' }
        ],
        fee_rate: [
          { required: true, message: '请输入手续费率', trigger: 'blur' }
        ],
        settlement_days: [
          { required: true, message: '请输入结算周期', trigger: 'blur' }
        ]
      }
    }
  },
  created() {
    this.fetchOptions()
    this.fetchData()
  },
  methods: {
    getLevelLabel(level) {
      return levelLabels[level] || level
    },
    getMethodLabel(method) {
      return methodLabels[method] || method
    },
    async fetchOptions() {
      try {
        const [statusRes, levelRes, methodRes, currencyRes] = await Promise.all([
          getRuleStatusOptions(),
          getRuleLevelOptions(),
          getRuleMethodOptions(),
          getRuleCurrencyOptions()
        ])
        this.statusOptions = statusRes.data || []
        this.levelOptions = levelRes.data || []
        this.methodOptions = methodRes.data || []
        this.currencyOptions = currencyRes.data || []
      } catch (e) {
        console.error(e)
      }
    },
    async fetchData() {
      this.loading = true
      try {
        const params = { ...this.queryParams }
        Object.keys(params).forEach(key => {
          if (params[key] === '' || params[key] === null || params[key] === undefined) {
            delete params[key]
          }
        })
        const res = await getWithdrawalRules(params)
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
        user_level: '',
        currency: '',
        withdrawal_method: '',
        is_active: ''
      }
      this.fetchData()
    },
    resetForm() {
      this.formData = {
        id: null,
        name: '',
        code: '',
        user_level: 'normal',
        currency: 'CNY',
        withdrawal_method: 'bank_transfer',
        min_amount: 100,
        max_amount: 50000,
        daily_limit: 200000,
        monthly_limit: 1000000,
        fee_rate: 0.005,
        fee_min: 1,
        fee_max: 500,
        settlement_days: 7,
        daily_max_count: 5,
        require_approval: true,
        approval_threshold: 10000,
        description: '',
        is_active: true,
        sort_order: 0,
        effective_from: '',
        effective_to: ''
      }
    },
    handleAdd() {
      this.isEdit = false
      this.resetForm()
      this.dialogVisible = true
    },
    handleEdit(row) {
      this.isEdit = true
      this.formData = { ...row }
      this.dialogVisible = true
    },
    handleView(row) {
      this.currentRule = row
      this.detailVisible = true
    },
    async handleToggle(row) {
      try {
        await toggleRuleActive(row.id)
        this.$message.success(row.is_active ? '已启用' : '已禁用')
      } catch (e) {
        row.is_active = !row.is_active
        console.error(e)
      }
    },
    async handleDelete(row) {
      this.$confirm(`确定要删除规则"${row.name}"吗？`, '删除确认', {
        confirmButtonText: '删除',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        try {
          await deleteWithdrawalRule(row.id)
          this.$message.success('删除成功')
          this.fetchData()
        } catch (e) {
          console.error(e)
        }
      }).catch(() => {})
    },
    async handleSubmit() {
      this.$refs.formRef.validate(async valid => {
        if (!valid) return

        this.submitLoading = true
        try {
          if (this.isEdit) {
            await updateWithdrawalRule(this.formData.id, this.formData)
            this.$message.success('更新成功')
          } else {
            await createWithdrawalRule(this.formData)
            this.$message.success('创建成功')
          }
          this.dialogVisible = false
          this.fetchData()
        } catch (e) {
          console.error(e)
        } finally {
          this.submitLoading = false
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.withdrawal-rules-page {
}
</style>
