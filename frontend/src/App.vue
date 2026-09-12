<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header v-if="auth.isAuthenticated" class="topbar">
    <div class="topbar-inner">
      <RouterLink :to="{ name: 'settings' }" class="brand">
        <span class="brand-mark">Я</span> Отзывы&nbsp;организаций
      </RouterLink>
      <div class="spacer" />
      <span class="muted small">{{ auth.user?.email }}</span>
      <button class="btn btn-secondary" @click="logout">Выйти</button>
    </div>
  </header>

  <main>
    <RouterView />
  </main>
</template>

<style scoped>
.topbar {
  background: #fff;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 10;
}
.topbar-inner {
  max-width: 880px;
  margin: 0 auto;
  padding: 12px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
}
.brand { display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--text); }
.brand:hover { text-decoration: none; }
.brand-mark {
  width: 26px; height: 26px;
  background: var(--primary);
  color: #fff;
  border-radius: 7px;
  display: inline-flex; align-items: center; justify-content: center;
  font-weight: 800;
}
</style>
