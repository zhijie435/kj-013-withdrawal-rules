<template>
  <el-container class="app-container">
    <el-header v-if="isLogin" class="app-header">
      <div class="header-left">
        <h1 class="logo">Shearerline 提现管理系统</h1>
      </div>
      <div class="header-right">
        <el-dropdown @command="handleCommand">
          <span class="user-info">
            <i class="el-icon-user"></i>
            {{ userInfo?.name || '用户' }}
            <i class="el-icon-arrow-down el-icon--right"></i>
          </span>
          <el-dropdown-menu slot="dropdown">
            <el-dropdown-item command="profile">个人中心</el-dropdown-item>
            <el-dropdown-item command="logout" divided>退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </el-dropdown>
      </div>
    </el-header>
    <el-container>
      <el-aside v-if="isLogin" width="220px" class="app-aside">
        <el-menu
          :default-active="$route.path"
          router
          background-color="#304156"
          text-color="#bfcbd9"
          active-text-color="#ffd04b"
        >
          <el-menu-item index="/dashboard">
            <i class="el-icon-s-home"></i>
            <span slot="title">数据概览</span>
          </el-menu-item>
          <el-submenu index="/wallet">
            <template slot="title">
              <i class="el-icon-wallet"></i>
              <span>钱包管理</span>
            </template>
            <el-menu-item index="/wallet/balance">余额查询</el-menu-item>
            <el-menu-item index="/wallet/transactions">交易记录</el-menu-item>
          </el-submenu>
          <el-submenu index="/withdrawal">
            <template slot="title">
              <i class="el-icon-money"></i>
              <span>提现管理</span>
            </template>
            <el-menu-item index="/withdrawal/apply">申请提现</el-menu-item>
            <el-menu-item index="/withdrawal/list">提现记录</el-menu-item>
          </el-submenu>
          <el-submenu index="/config" v-if="hasPermission('view-withdrawal-rules')">
            <template slot="title">
              <i class="el-icon-setting"></i>
              <span>规则配置</span>
            </template>
            <el-menu-item index="/config/rules">提现规则</el-menu-item>
            <el-menu-item index="/config/bank-cards">银行卡管理</el-menu-item>
          </el-submenu>
          <el-submenu index="/system" v-if="hasPermission('view-users')">
            <template slot="title">
              <i class="el-icon-s-custom"></i>
              <span>系统管理</span>
            </template>
            <el-menu-item index="/system/users">用户管理</el-menu-item>
          </el-submenu>
        </el-menu>
      </el-aside>
      <el-main class="app-main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<script>
import { mapGetters } from 'vuex'

export default {
  name: 'App',
  computed: {
    ...mapGetters(['isLogin', 'userInfo', 'permissions']),
    hasPermission() {
      return (permission) => {
        return this.permissions.includes(permission) || this.permissions.includes('*')
      }
    }
  },
  methods: {
    handleCommand(command) {
      if (command === 'logout') {
        this.$confirm('确定要退出登录吗？', '提示', {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        }).then(() => {
          this.$store.dispatch('logout')
          this.$router.push('/login')
          this.$message.success('已退出登录')
        }).catch(() => {})
      } else if (command === 'profile') {
        this.$message.info('个人中心功能开发中')
      }
    }
  }
}
</script>

<style lang="scss">
.app-container {
  height: 100vh;
}

.app-header {
  background: #fff;
  border-bottom: 1px solid #e6e6e6;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 24px;

  .header-left .logo {
    font-size: 18px;
    margin: 0;
    color: #303133;
  }

  .header-right .user-info {
    cursor: pointer;
    color: #606266;
  }
}

.app-aside {
  background: #304156;
  overflow-x: hidden;

  .el-menu {
    border-right: none;
  }
}

.app-main {
  background: #f0f2f5;
  padding: 20px;
  overflow-y: auto;
}
</style>
