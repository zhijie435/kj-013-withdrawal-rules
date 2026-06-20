<template>
  <div class="bank-cards-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">银行卡管理</h2>
        <p class="page-desc">管理用户的收款账户信息</p>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="handleAdd">
          <i class="el-icon-plus"></i> 添加银行卡
        </el-button>
      </div>
    </div>

    <el-card class="filter-card">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="持卡人/卡号/银行"
            clearable
            style="width: 200px"
            @keyup.enter.native="handleSearch"
          />
        </el-form-item>
        <el-form-item label="账户类型">
          <el-select v-model="queryParams.card_type" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in typeOptions"
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
        <el-table-column label="用户" v-if="hasPermission('view-users')" min-width="140">
          <template slot-scope="scope">
            <div>{{ scope.row.user?.name || '-' }}</div>
            <div style="font-size: 12px; color: #909399">{{ scope.row.user?.phone || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="账户类型" width="100" align="center">
          <template slot-scope="scope">
            <el-tag size="mini">{{ getTypeLabel(scope.row.card_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="bank_name" label="开户银行" min-width="140" />
        <el-table-column prop="branch_name" label="开户支行" min-width="140" />
        <el-table-column label="卡号" min-width="180">
          <template slot-scope="scope">{{ scope.row.masked_card_number || scope.row.card_number }}</template>
        </el-table-column>
        <el-table-column prop="card_holder_name" label="开户人" width="120" />
        <el-table-column prop="currency" label="币种" width="80" align="center" />
        <el-table-column label="默认" width="80" align="center">
          <template slot-scope="scope">
            <el-tag v-if="scope.row.is_default" type="success" size="mini">是</el-tag>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="已验证" width="80" align="center">
          <template slot-scope="scope">
            <el-tag v-if="scope.row.is_verified" type="success" size="mini">是</el-tag>
            <el-tag v-else type="warning" size="mini">否</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template slot-scope="scope">
            <el-switch
              v-model="scope.row.is_active"
              @change="handleToggleActive(scope.row)"
              active-text="启"
              inactive-text="禁"
            />
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160" />
        <el-table-column label="操作" width="220" align="center" fixed="right">
          <template slot-scope="scope">
            <el-button
              v-if="!scope.row.is_default"
              type="primary"
              link
              size="mini"
              @click="handleSetDefault(scope.row)"
            >设为默认</el-button>
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
      :title="isEdit ? '编辑银行卡' : '添加银行卡'"
      width="640px"
      append-to-body
      :close-on-click-modal="false"
    >
      <el-form :model="formData" :rules="formRules" ref="formRef" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="账户类型" prop="card_type">
              <el-select v-model="formData.card_type" style="width: 100%">
                <el-option
                  v-for="item in typeOptions"
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
                <el-option label="人民币(CNY)" value="CNY" />
                <el-option label="美元(USD)" value="USD" />
                <el-option label="港币(HKD)" value="HKD" />
                <el-option label="欧元(EUR)" value="EUR" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="开户银行" prop="bank_name">
              <el-select v-model="formData.bank_name" filterable style="width: 100%">
                <el-option
                  v-for="item in bankOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.label"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开户支行" prop="branch_name">
              <el-input v-model="formData.branch_name" placeholder="请输入开户支行" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="卡号" prop="card_number">
              <el-input v-model="formData.card_number" placeholder="请输入卡号" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="开户人" prop="card_holder_name">
              <el-input v-model="formData.card_holder_name" placeholder="请输入开户人姓名" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="SWIFT码" prop="swift_code">
              <el-input v-model="formData.swift_code" placeholder="境外汇款必填" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="IBAN" prop="iban">
              <el-input v-model="formData.iban" placeholder="境外汇款必填" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="省份" prop="province">
              <el-input v-model="formData.province" placeholder="请输入省份" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="城市" prop="city">
              <el-input v-model="formData.city" placeholder="请输入城市" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="设为默认" prop="is_default">
              <el-switch v-model="formData.is_default" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="是否启用" prop="is_active">
              <el-switch v-model="formData.is_active" />
            </el-form-item>
          </el-col>
        </el-row>
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
      </el-form>
      <template slot="footer">
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import {
  getBankCards,
  createBankCard,
  updateBankCard,
  deleteBankCard,
  setDefaultBankCard,
  getBankCardTypeOptions,
  getBankOptions
} from '@/api/bankCard'
import { mapGetters } from 'vuex'

const typeLabels = {
  debit: '借记卡',
  credit: '信用卡',
  alipay: '支付宝',
  wechat: '微信支付',
  usdt: 'USDT钱包'
}

export default {
  name: 'BankCards',
  data() {
    return {
      loading: false,
      submitLoading: false,
      tableData: [],
      total: 0,
      dialogVisible: false,
      isEdit: false,
      typeOptions: [],
      bankOptions: [],
      formRef: null,
      queryParams: {
        page: 1,
        per_page: 15,
        keyword: '',
        card_type: '',
        is_active: ''
      },
      formData: {
        id: null,
        card_type: 'debit',
        bank_name: '',
        bank_code: '',
        branch_name: '',
        card_number: '',
        card_holder_name: '',
        currency: 'CNY',
        province: '',
        city: '',
        swift_code: '',
        iban: '',
        is_default: false,
        is_active: true,
        remark: ''
      },
      formRules: {
        card_type: [
          { required: true, message: '请选择账户类型', trigger: 'change' }
        ],
        bank_name: [
          { required: true, message: '请选择开户银行', trigger: 'change' }
        ],
        card_number: [
          { required: true, message: '请输入卡号', trigger: 'blur' }
        ],
        card_holder_name: [
          { required: true, message: '请输入开户人姓名', trigger: 'blur' }
        ],
        currency: [
          { required: true, message: '请选择币种', trigger: 'change' }
        ]
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
    this.fetchOptions()
    this.fetchData()
  },
  methods: {
    getTypeLabel(type) {
      return typeLabels[type] || type
    },
    async fetchOptions() {
      try {
        const [typeRes, bankRes] = await Promise.all([
          getBankCardTypeOptions(),
          getBankOptions()
        ])
        this.typeOptions = typeRes.data || []
        this.bankOptions = bankRes.data || []
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
        const res = await getBankCards(params)
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
        card_type: '',
        is_active: ''
      }
      this.fetchData()
    },
    resetForm() {
      this.formData = {
        id: null,
        card_type: 'debit',
        bank_name: '',
        bank_code: '',
        branch_name: '',
        card_number: '',
        card_holder_name: '',
        currency: 'CNY',
        province: '',
        city: '',
        swift_code: '',
        iban: '',
        is_default: false,
        is_active: true,
        remark: ''
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
    async handleSetDefault(row) {
      try {
        await setDefaultBankCard(row.id)
        this.$message.success('已设为默认')
        this.fetchData()
      } catch (e) {
        console.error(e)
      }
    },
    async handleToggleActive(row) {
      try {
        await updateBankCard(row.id, { is_active: row.is_active })
        this.$message.success(row.is_active ? '已启用' : '已禁用')
      } catch (e) {
        row.is_active = !row.is_active
        console.error(e)
      }
    },
    async handleDelete(row) {
      this.$confirm(`确定要删除该银行卡吗？`, '删除确认', {
        confirmButtonText: '删除',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        try {
          await deleteBankCard(row.id)
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
            await updateBankCard(this.formData.id, this.formData)
            this.$message.success('更新成功')
          } else {
            await createBankCard(this.formData)
            this.$message.success('添加成功')
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
.bank-cards-page {
}
</style>
