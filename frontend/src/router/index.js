import Vue from 'vue'
import VueRouter from 'vue-router'

Vue.use(VueRouter)

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login/index.vue'),
    meta: { title: '登录', requiresAuth: false }
  },
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard/index.vue'),
    meta: { title: '数据概览', requiresAuth: true }
  },
  {
    path: '/wallet/balance',
    name: 'WalletBalance',
    component: () => import('@/views/Wallet/Balance.vue'),
    meta: { title: '余额查询', requiresAuth: true }
  },
  {
    path: '/wallet/transactions',
    name: 'WalletTransactions',
    component: () => import('@/views/Wallet/Transactions.vue'),
    meta: { title: '交易记录', requiresAuth: true }
  },
  {
    path: '/withdrawal/apply',
    name: 'WithdrawalApply',
    component: () => import('@/views/Withdrawal/Apply.vue'),
    meta: { title: '申请提现', requiresAuth: true }
  },
  {
    path: '/withdrawal/list',
    name: 'WithdrawalList',
    component: () => import('@/views/Withdrawal/List.vue'),
    meta: { title: '提现记录', requiresAuth: true }
  },
  {
    path: '/config/rules',
    name: 'WithdrawalRules',
    component: () => import('@/views/Config/WithdrawalRules.vue'),
    meta: { title: '提现规则', requiresAuth: true, permission: 'view-withdrawal-rules' }
  },
  {
    path: '/config/bank-cards',
    name: 'BankCards',
    component: () => import('@/views/Config/BankCards.vue'),
    meta: { title: '银行卡管理', requiresAuth: true, permission: 'view-bank-cards' }
  },
  {
    path: '/system/users',
    name: 'UserManagement',
    component: () => import('@/views/System/Users.vue'),
    meta: { title: '用户管理', requiresAuth: true, permission: 'view-users' }
  },
  {
    path: '*',
    name: 'NotFound',
    component: () => import('@/views/NotFound/index.vue'),
    meta: { title: '页面不存在' }
  }
]

const router = new VueRouter({
  mode: 'history',
  base: process.env.VITE_APP_BASE_URL || '/',
  routes
})

router.beforeEach((to, from, next) => {
  document.title = to.meta.title ? `${to.meta.title} - Shearerline提现系统` : 'Shearerline提现系统'

  const token = localStorage.getItem('token')

  if (to.meta.requiresAuth && !token) {
    next({ path: '/login', query: { redirect: to.fullPath } })
  } else {
    next()
  }
})

export default router
