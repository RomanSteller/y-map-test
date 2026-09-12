import axios from 'axios'

/**
 * Общий экземпляр axios для Sanctum SPA.
 *
 * - baseURL пустой: запросы идут на тот же origin ("/api/…"), а до бэка их
 *   проксирует Vite в разработке и nginx на проде.
 * - withCredentials + withXSRFToken заставляют браузер таскать сессионную куку
 *   и возвращать куку XSRF-TOKEN обратно в заголовке X-XSRF-TOKEN.
 */
const http = axios.create({
  baseURL: '',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/** Sanctum требует один раз получить CSRF-куку перед первым «пишущим» запросом. */
export async function ensureCsrfCookie() {
  await http.get('/sanctum/csrf-cookie')
}

export default http
