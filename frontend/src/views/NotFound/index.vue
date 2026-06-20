<template>
  <div class="not-found-page">
    <div class="not-found-container">
      <div class="error-code">404</div>
      <div class="error-message">页面不存在</div>
      <div class="error-desc">抱歉，您访问的页面不存在或已被删除</div>
      <el-button type="primary" size="large" @click="goHome">
        <i class="el-icon-home"></i> 返回首页
      </el-button>
    </div>
  </div>
</template>

<script>
export default {
  name: 'NotFound',
  methods: {
    goHome() {
      this.$router.push('/dashboard')
    }
  }
}
</script>

<style lang="scss" scoped>
.not-found-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0f2f5;
}

.not-found-container {
  text-align: center;

  .error-code {
    font-size: 120px;
    font-weight: 700;
    color: #409eff;
    line-height: 1;
    margin-bottom: 20px;
  }

  .error-message {
    font-size: 24px;
    color: #303133;
    margin-bottom: 12px;
  }

  .error-desc {
    font-size: 14px;
    color: #909399;
    margin-bottom: 30px;
  }
}
</style>
