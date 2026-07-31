import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api/axios'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(localStorage.getItem('token') || '')

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isSupervisor = computed(() => user.value?.role === 'supervisor')
  const isSeller = computed(() => user.value?.role === 'seller')

  async function login(email, password) {
    const { data } = await api.post('/auth/login', { email, password })
    user.value = data.user
    token.value = data.token
    localStorage.setItem('user', JSON.stringify(data.user))
    localStorage.setItem('token', data.token)
    return data
  }

  async function logout() {
    user.value = null
    token.value = ''
    localStorage.removeItem('user')
    localStorage.removeItem('token')
    api.post('/auth/logout').catch(() => {})
  }

  async function fetchUser() {
    const { data } = await api.get('/auth/user')
    user.value = data
    localStorage.setItem('user', JSON.stringify(data))
    return data
  }

  return { user, token, isAuthenticated, isAdmin, isSupervisor, isSeller, login, logout, fetchUser }
})
