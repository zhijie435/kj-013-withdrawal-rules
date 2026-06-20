import axios from 'axios'
import { MessageBox, Message } from 'element-ui'
import store from '@/store'
import router from '@/router'

const service = axios.create({
  baseURL: import.meta.env.VITE_APP_API_BASE_URL || '/api/v1',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json'
  }
})

service.interceptors.request.use(
  config => {
    const token = store.state.token
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  error => {
    console.error('Request error:', error)
    return Promise.reject(error)
  }
)

service.interceptors.response.use(
  response => {
    const res = response.data

    if (res.code !== 0 && res.code !== 200) {
      Message({
        message: res.message || '请求失败',
        type: 'error',
        duration: 3000
      })

      if (res.code === 401 || res.code === 40101 || res.code === 40102) {
        MessageBox.confirm('登录状态已过期，请重新登录', '提示', {
          confirmButtonText: '重新登录',
          cancelButtonText: '取消',
          type: 'warning'
        }).then(() => {
          store.dispatch('logout')
          router.push('/login')
        })
      }

      return Promise.reject(new Error(res.message || '请求失败'))
    }

    return res
  },
  error => {
    console.error('Response error:', error)

    if (error.response?.status === 401) {
      store.dispatch('logout')
      router.push('/login')
    }

    const message = error.response?.data?.message || error.message || '网络错误'
    Message({
      message: message,
      type: 'error',
      duration: 3000
    })

    return Promise.reject(error)
  }
)

export default service
