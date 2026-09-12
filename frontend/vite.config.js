import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// Куда dev-сервер проксирует API. В Docker это контейнер nginx, локально —
// `php artisan serve` на :8000.
const apiTarget = process.env.VITE_PROXY_TARGET || 'http://localhost:8000'

// Проксируем бэкендовые пути, чтобы в разработке SPA и API жили на одном origin —
// ровно так же их отдаёт nginx на проде. Один origin означает, что сессионная
// кука Sanctum «просто работает» без плясок с кросс-сайтовыми куками.
const proxy = Object.fromEntries(
  ['/api', '/sanctum', '/login', '/logout'].map((path) => [
    path,
    { target: apiTarget, changeOrigin: true },
  ])
)

export default defineConfig({
  plugins: [vue()],
  server: {
    host: true,
    port: 5173,
    proxy,
  },
})
