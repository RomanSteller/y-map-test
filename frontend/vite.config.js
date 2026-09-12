import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// The API origin the dev server proxies to. In Docker this is the nginx
// container; locally it's `php artisan serve` on :8000.
const apiTarget = process.env.VITE_PROXY_TARGET || 'http://localhost:8000'

// Proxy the backend paths so the SPA and API share an origin in dev, exactly
// like nginx serves them in production. Same-origin means the Sanctum session
// cookie "just works" with no cross-site cookie headaches.
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
