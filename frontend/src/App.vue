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
        отзыв<span class="brand-o">о</span>рг
      </RouterLink>
      <div class="spacer" />
      <span class="muted small hide-sm">{{ auth.user?.email }}</span>
      <button class="btn btn-ghost btn-sm" @click="logout">Выйти</button>
    </div>
  </header>

  <main>
    <RouterView />
  </main>
</template>

<style scoped>
.topbar {
  background: #fff;
  border-bottom: 1px solid #f0f1f4;
  position: sticky;
  top: 0;
  z-index: 10;
}
.topbar-inner {
  max-width: 920px;
  margin: 0 auto;
  padding: 14px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
}
.brand {
  font-weight: 800;
  font-size: 20px;
  letter-spacing: -0.02em;
  color: #0a0a0a;
  text-transform: lowercase;
}
.brand:hover { color: #000; }
/* стилизованная «о» — отсылка к кольцевому логотипу prochitano */
.brand-o {
  display: inline-block;
  color: transparent;
  position: relative;
  width: 0.72em;
}
.brand-o::before {
  content: '';
  position: absolute;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  width: 0.6em; height: 0.6em;
  border: 3px solid var(--accent);
  border-radius: 50%;
}
.btn-sm { padding: 8px 14px; font-size: 13px; }
@media (max-width: 520px) { .hide-sm { display: none; } }
</style>
