<template>
  <div class="login-container">
    <div class="login-box">
      <div class="login-header">
        <h2 class="login-title">Shearerline 提现管理系统</h2>
        <p class="login-subtitle">跨境S2B2B平台 · 提现规则模块</p>
      </div>
      <el-form :model="loginForm" :rules="loginRules" ref="loginForm" class="login-form">
        <el-form-item prop="email">
          <el-input
            v-model="loginForm.email"
            placeholder="请输入邮箱"
            prefix-icon="el-icon-user"
            size="large"
            @keyup.enter.native="handleLogin"
          />
        </el-form-item>
        <el-form-item prop="password">
          <el-input
            v-model="loginForm.password"
            type="password"
            placeholder="请输入密码"
            prefix-icon="el-icon-lock"
            size="large"
            show-password
            @keyup.enter.native="handleLogin"
          />
        </el-form-item>
        <el-form-item>
          <el-button
            type="primary"
            size="large"
            :loading="loading"
            class="login-btn"
            @click="handleLogin"
          >
            登 录
          </el-button>
        </el-form-item>
      </el-form>
      <div class="login-footer">
        <p>测试账号：admin@shearerline.com / Admin@123456</p>
        <p>测试账号：finance@shearerline.com / Finance@123456</p>
        <p>测试账号：user@shearerline.com / User@123456</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Login',
  data() {
    return {
      loading: false,
      loginForm: {
        email: 'admin@shearerline.com',
        password: 'Admin@123456'
      },
      loginRules: {
        email: [
          { required: true, message: '请输入邮箱', trigger: 'blur' },
          { type: 'email', message: '请输入正确的邮箱地址', trigger: 'blur' }
        ],
        password: [
          { required: true, message: '请输入密码', trigger: 'blur' },
          { min: 6, message: '密码长度不能小于6位', trigger: 'blur' }
        ]
      }
    }
  },
  methods: {
    handleLogin() {
      this.$refs.loginForm.validate(async valid => {
        if (!valid) return

        this.loading = true
        try {
          await this.$store.dispatch('login', this.loginForm)
          this.$message.success('登录成功')

          const redirect = this.$route.query.redirect || '/dashboard'
          this.$router.push(redirect)
        } catch (e) {
          console.error(e)
        } finally {
          this.loading = false
        }
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-box {
  width: 400px;
  padding: 40px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.login-header {
  text-align: center;
  margin-bottom: 30px;

  .login-title {
    font-size: 24px;
    color: #303133;
    margin: 0 0 8px 0;
  }

  .login-subtitle {
    font-size: 13px;
    color: #909399;
    margin: 0;
  }
}

.login-form {
  .login-btn {
    width: 100%;
  }
}

.login-footer {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
  text-align: center;

  p {
    font-size: 12px;
    color: #909399;
    margin: 4px 0;
  }
}
</style>
