<template>
  <div class="users-page">
    <div class="page-header">
      <div class="header-left">
        <h2 class="page-title">用户管理</h2>
        <p class="page-desc">管理系统用户和权限</p>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="handleAdd">
          <i class="el-icon-plus"></i> 新建用户
        </el-button>
      </div>
    </div>

    <el-card class="filter-card">
      <el-form :model="queryParams" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keyword"
            placeholder="姓名/邮箱/手机/公司"
            clearable
            style="width: 200px"
            @keyup.enter.native="handleSearch"
          />
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="queryParams.role" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in roleOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="等级">
          <el-select v-model="queryParams.level" placeholder="全部" clearable style="width: 140px">
            <el-option
              v-for="item in levelOptions"
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
        <el-table-column label="姓名" width="120">
          <template slot-scope="scope">
            <div class="user-info">
              <el-avatar :size="32" v-if="scope.row.avatar">{{ scope.row.name.charAt(0) }}</el-avatar>
              <el-avatar :size="32" v-else>{{ scope.row.name.charAt(0) }}</el-avatar>
              <span style="margin-left: 8px">{{ scope.row.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="email" label="邮箱" min-width="180" />
        <el-table-column prop="phone" label="手机" width="140" />
        <el-table-column label="角色" width="100" align="center">
          <template slot-scope="scope">
            <el-tag size="mini">{{ getRoleLabel(scope.row.role) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="等级" width="100" align="center">
          <template slot-scope="scope">
            <el-tag type="warning" size="mini">{{ getLevelLabel(scope.row.level) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="company_name" label="公司名称" min-width="160" show-overflow-tooltip />
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
        <el-table-column label="操作" width="180" align="center" fixed="right">
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
      :title="isEdit ? '编辑用户' : '新建用户'"
      width="640px"
      append-to-body
      :close-on-click-modal="false"
    >
      <el-form :model="formData" :rules="formRules" ref="formRef" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="姓名" prop="name">
              <el-input v-model="formData.name" placeholder="请输入姓名" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="邮箱" prop="email">
              <el-input v-model="formData.email" placeholder="请输入邮箱" :disabled="isEdit" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="手机" prop="phone">
              <el-input v-model="formData.phone" placeholder="请输入手机号码" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="密码" prop="password">
              <el-input
                v-model="formData.password"
                type="password"
                :placeholder="isEdit ? '不修改请留空' : '请输入密码'"
                show-password
              />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="角色" prop="role">
              <el-select v-model="formData.role" style="width: 100%">
                <el-option
                  v-for="item in roleOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="等级" prop="level">
              <el-select v-model="formData.level" style="width: 100%">
                <el-option
                  v-for="item in levelOptions"
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
            <el-form-item label="公司名称" prop="company_name">
              <el-input v-model="formData.company_name" placeholder="请输入公司名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="营业执照" prop="business_license">
              <el-input v-model="formData.business_license" placeholder="请输入营业执照号" />
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

    <el-drawer
      :visible.sync="detailVisible"
      title="用户详情"
      size="500px"
      append-to-body
    >
      <el-descriptions :column="1" border v-if="currentUser">
        <el-descriptions-item label="姓名">{{ currentUser.name }}</el-descriptions-item>
        <el-descriptions-item label="邮箱">{{ currentUser.email }}</el-descriptions-item>
        <el-descriptions-item label="手机">{{ currentUser.phone || '-' }}</el-descriptions-item>
        <el-descriptions-item label="角色">
          <el-tag>{{ getRoleLabel(currentUser.role) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="等级">
          <el-tag type="warning">{{ getLevelLabel(currentUser.level) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="公司名称">{{ currentUser.company_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="营业执照">{{ currentUser.business_license || '-' }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="currentUser.is_active ? 'success' : 'info'">
            {{ currentUser.is_active ? '启用' : '禁用' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="角色权限" v-if="currentUser.roles?.length">
          <el-tag v-for="role in currentUser.roles" :key="role.id" style="margin-right: 5px">
            {{ role.name }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="钱包余额" v-if="currentUser.wallets?.length">
          <div v-for="wallet in currentUser.wallets" :key="wallet.id" style="margin: 4px 0">
            {{ wallet.currency }}: ¥{{ Number(wallet.balance).toFixed(2) }}
            (可用: ¥{{ Number(wallet.available_balance).toFixed(2) }})
          </div>
        </el-descriptions-item>
        <el-descriptions-item label="默认银行卡" v-if="currentUser.default_bank_card">
          {{ currentUser.default_bank_card.bank_name }} -
          {{ currentUser.default_bank_card.masked_card_number || currentUser.default_bank_card.card_number }}
        </el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ currentUser.created_at }}</el-descriptions-item>
        <el-descriptions-item label="备注">{{ currentUser.remark || '-' }}</el-descriptions-item>
      </el-descriptions>
    </el-drawer>
  </div>
</template>

<script>
import {
  getUsers,
  createUser,
  updateUser,
  deleteUser,
  getUserRoleOptions,
  getUserLevelOptions
} from '@/api/user'

const roleLabels = {
  admin: '管理员',
  finance: '财务',
  operator: '运营',
  user: '普通用户'
}

const levelLabels = {
  super: '超级用户',
  vip: 'VIP用户',
  normal: '普通用户'
}

export default {
  name: 'Users',
  data() {
    return {
      loading: false,
      submitLoading: false,
      tableData: [],
      total: 0,
      dialogVisible: false,
      detailVisible: false,
      isEdit: false,
      currentUser: null,
      roleOptions: [],
      levelOptions: [],
      formRef: null,
      queryParams: {
        page: 1,
        per_page: 15,
        keyword: '',
        role: '',
        level: '',
        is_active: ''
      },
      formData: {
        id: null,
        name: '',
        email: '',
        phone: '',
        password: '',
        role: 'user',
        level: 'normal',
        company_name: '',
        business_license: '',
        is_active: true,
        remark: ''
      },
      formRules: {
        name: [
          { required: true, message: '请输入姓名', trigger: 'blur' }
        ],
        email: [
          { required: true, message: '请输入邮箱', trigger: 'blur' },
          { type: 'email', message: '请输入正确的邮箱地址', trigger: 'blur' }
        ],
        password: [
          { required: true, message: '请输入密码', trigger: 'blur', validator: (rule, value, callback) => {
            if (!this.isEdit && !value) {
              callback(new Error('请输入密码'))
            } else {
              callback()
            }
          }}
        ],
        role: [
          { required: true, message: '请选择角色', trigger: 'change' }
        ],
        level: [
          { required: true, message: '请选择等级', trigger: 'change' }
        ]
      }
    }
  },
  created() {
    this.fetchOptions()
    this.fetchData()
  },
  methods: {
    getRoleLabel(role) {
      return roleLabels[role] || role
    },
    getLevelLabel(level) {
      return levelLabels[level] || level
    },
    async fetchOptions() {
      try {
        const [roleRes, levelRes] = await Promise.all([
          getUserRoleOptions(),
          getUserLevelOptions()
        ])
        this.roleOptions = roleRes.data || []
        this.levelOptions = levelRes.data || []
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
        const res = await getUsers(params)
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
        role: '',
        level: '',
        is_active: ''
      }
      this.fetchData()
    },
    resetForm() {
      this.formData = {
        id: null,
        name: '',
        email: '',
        phone: '',
        password: '',
        role: 'user',
        level: 'normal',
        company_name: '',
        business_license: '',
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
      this.formData = { ...row, password: '' }
      this.dialogVisible = true
    },
    handleView(row) {
      this.currentUser = row
      this.detailVisible = true
    },
    async handleToggleActive(row) {
      try {
        await updateUser(row.id, { is_active: row.is_active })
        this.$message.success(row.is_active ? '已启用' : '已禁用')
      } catch (e) {
        row.is_active = !row.is_active
        console.error(e)
      }
    },
    async handleDelete(row) {
      this.$confirm(`确定要删除用户"${row.name}"吗？`, '删除确认', {
        confirmButtonText: '删除',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(async () => {
        try {
          await deleteUser(row.id)
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
          const data = { ...this.formData }
          if (this.isEdit && !data.password) {
            delete data.password
          }

          if (this.isEdit) {
            await updateUser(this.formData.id, data)
            this.$message.success('更新成功')
          } else {
            await createUser(data)
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
.users-page {
  .user-info {
    display: flex;
    align-items: center;
  }
}
</style>
