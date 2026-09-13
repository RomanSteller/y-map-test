import http from './http'

/** Тонкая обёртка над эндпоинтами организаций и отзывов. */
export const organizationsApi = {
  list() {
    return http.get('/api/organizations').then((r) => r.data)
  },
  get(id) {
    return http.get(`/api/organizations/${id}`).then((r) => r.data)
  },
  // Сохранение сразу же синхронно парсит организацию и возвращает её с данными.
  save(url) {
    return http.post('/api/organizations', { url }).then((r) => r.data)
  },
  reparse(id) {
    return http.post(`/api/organizations/${id}/parse`).then((r) => r.data)
  },
  reviews(id, page = 1) {
    return http
      .get(`/api/organizations/${id}/reviews`, { params: { page } })
      .then((r) => r.data)
  },
}
