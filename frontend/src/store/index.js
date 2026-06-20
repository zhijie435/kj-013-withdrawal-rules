import Vue from 'vue'
import Vuex from 'vuex'
import { login, logout, getUserInfo } from '@/api/auth'

Vue.use(Vuex)

export default new Vuex.Store({
  state: {
    token: localStorage.getItem('token') || '',
    userInfo: JSON.parse(localStorage.getItem('userInfo') || 'null'),
    roles: JSON.parse(localStorage.getItem('roles') || '[]'),
    permissions: JSON.parse(localStorage.getItem('permissions') || '[]')
  },
  getters: {
    isLogin: state => !!state.token,
    userInfo: state => state.userInfo,
    roles: state => state.roles,
    permissions: state => state.permissions.map(p => p.name || p)
  },
  mutations: {
    SET_TOKEN(state, token) {
      state.token = token
      localStorage.setItem('token', token)
    },
    SET_USER_INFO(state, userInfo) {
      state.userInfo = userInfo
      localStorage.setItem('userInfo', JSON.stringify(userInfo))
    },
    SET_ROLES(state, roles) {
      state.roles = roles
      localStorage.setItem('roles', JSON.stringify(roles))
    },
    SET_PERMISSIONS(state, permissions) {
      state.permissions = permissions
      localStorage.setItem('permissions', JSON.stringify(permissions))
    },
    CLEAR_AUTH(state) {
      state.token = ''
      state.userInfo = null
      state.roles = []
      state.permissions = []
      localStorage.removeItem('token')
      localStorage.removeItem('userInfo')
      localStorage.removeItem('roles')
      localStorage.removeItem('permissions')
    }
  },
  actions: {
    async login({ commit }, loginData) {
      const res = await login(loginData)
      commit('SET_TOKEN', res.data.token)
      commit('SET_USER_INFO', res.data.user)
      commit('SET_ROLES', res.data.user.roles || [])
      commit('SET_PERMISSIONS', res.data.user.permissions || [])
      return res.data
    },
    async logout({ commit }) {
      try {
        await logout()
      } catch (e) {
        console.error('Logout error:', e)
      }
      commit('CLEAR_AUTH')
    },
    async getUserInfo({ commit, state }) {
      if (!state.token) return null
      try {
        const res = await getUserInfo()
        commit('SET_USER_INFO', res.data)
        commit('SET_ROLES', res.data.roles || [])
        commit('SET_PERMISSIONS', res.data.permissions || [])
        return res.data
      } catch (e) {
        if (e.response?.status === 401) {
          commit('CLEAR_AUTH')
        }
        throw e
      }
    }
  }
})
