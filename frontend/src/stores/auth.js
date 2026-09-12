import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import http, { ensureCsrfCookie } from '../api/http'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const ready = ref(false) // whether we've checked the session at least once

  const isAuthenticated = computed(() => user.value !== null)

  /** Restore the session on app boot (page reload keeps the cookie). */
  async function fetchUser() {
    try {
      const { data } = await http.get('/api/user')
      user.value = data.user
    } catch {
      user.value = null
    } finally {
      ready.value = true
    }
  }

  async function login(email, password) {
    await ensureCsrfCookie()
    const { data } = await http.post('/api/login', { email, password })
    user.value = data.user
  }

  async function logout() {
    try {
      await http.post('/api/logout')
    } finally {
      user.value = null
    }
  }

  return { user, ready, isAuthenticated, fetchUser, login, logout }
})
