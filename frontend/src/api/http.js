import axios from 'axios'

/**
 * Shared axios instance for the Sanctum SPA.
 *
 * - baseURL is empty: requests are same-origin ("/api/…"), proxied to the
 *   backend by Vite in dev and by nginx in production.
 * - withCredentials + withXSRFToken make the browser carry the session cookie
 *   and echo the XSRF-TOKEN cookie back as the X-XSRF-TOKEN header.
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

/** Sanctum requires fetching the CSRF cookie once before the first mutation. */
export async function ensureCsrfCookie() {
  await http.get('/sanctum/csrf-cookie')
}

export default http
