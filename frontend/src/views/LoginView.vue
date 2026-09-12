<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('demo@example.com')
const password = ref('password')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.push(route.query.redirect || { name: 'settings' })
  } catch (e) {
    error.value =
      e.response?.data?.errors?.email?.[0] ||
      e.response?.data?.message ||
      'Не удалось войти. Проверьте соединение с сервером.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-wrap">
    <form class="card login-card stack" @submit.prevent="submit">
      <div>
        <h1>Вход</h1>
        <p class="muted small">Сервис отзывов организаций на Яндекс.Картах</p>
      </div>

      <div v-if="error" class="alert alert-error">{{ error }}</div>

      <div>
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" autocomplete="username" required />
      </div>

      <div>
        <label for="password">Пароль</label>
        <input
          id="password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          required
        />
      </div>

      <button class="btn" type="submit" :disabled="loading">
        <span v-if="loading" class="spinner" />
        {{ loading ? 'Входим…' : 'Войти' }}
      </button>

      <p class="muted small">Демо-доступ подставлен в поля. Регистрация не требуется.</p>
    </form>
  </div>
</template>

<style scoped>
.login-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.login-card { width: 100%; max-width: 380px; }
</style>
