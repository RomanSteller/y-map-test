<script setup>
import { ref, computed, onMounted } from 'vue'
import { Bootstrap5Pagination } from 'laravel-vue-pagination'
import { organizationsApi } from '../api/organizations'
import StarRating from '../components/StarRating.vue'
import ReviewCard from '../components/ReviewCard.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const org = ref(null)
const loading = ref(true)
const loadError = ref('')
const reparsing = ref(false)

// Весь ответ ленивого пагинатора Laravel (data + current_page/last_page/…) —
// его целиком принимает <Bootstrap5Pagination> из laravel-vue-pagination.
const reviews = ref({ data: [] })
const reviewsLoading = ref(false)

const hasError = computed(() => !!org.value?.parse_error)

async function loadOrg() {
  try {
    org.value = await organizationsApi.get(props.id)
    if (!hasError.value) await loadReviews()
  } catch {
    loadError.value = 'Организация не найдена.'
  } finally {
    loading.value = false
  }
}

async function loadReviews(page = 1) {
  reviewsLoading.value = true
  try {
    reviews.value = await organizationsApi.reviews(props.id, page)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    reviewsLoading.value = false
  }
}

async function reparse() {
  reparsing.value = true
  try {
    org.value = await organizationsApi.reparse(props.id)
    if (!hasError.value) await loadReviews()
  } finally {
    reparsing.value = false
  }
}

onMounted(loadOrg)
</script>

<template>
  <div class="container stack">
    <div v-if="loading" class="row muted">
      <span class="spinner spinner-dark" /> Загрузка…
    </div>

    <div v-else-if="loadError" class="alert alert-error">{{ loadError }}</div>

    <template v-else>
      <RouterLink :to="{ name: 'settings' }" class="small">← К настройкам</RouterLink>

      <section class="card summary">
        <div class="summary-head">
          <div>
            <h1>{{ org.title || 'Организация' }}</h1>
            <p class="muted small">{{ org.address }}</p>
            <p class="small">
              <a :href="org.url" target="_blank" rel="noopener">Открыть на Яндекс.Картах ↗</a>
            </p>
          </div>
          <button class="btn btn-secondary" :disabled="reparsing" @click="reparse">
            <span v-if="reparsing" class="spinner spinner-dark" />
            {{ reparsing ? 'Собираем…' : 'Обновить' }}
          </button>
        </div>

        <!-- Рейтинг + два РАЗНЫХ счётчика (оценки ≠ отзывы) -->
        <div v-if="org.rating || org.ratings_count" class="stats">
          <div class="stat">
            <div class="stat-rating">
              <span class="rating-value">{{ org.rating?.toFixed(1) ?? '—' }}</span>
              <StarRating :value="org.rating || 0" :size="18" />
            </div>
            <div class="muted small">средний рейтинг</div>
          </div>
          <div class="stat">
            <div class="stat-num">{{ org.ratings_count?.toLocaleString('ru-RU') }}</div>
            <div class="muted small">оценок</div>
          </div>
          <div class="stat">
            <div class="stat-num">{{ org.reviews_count?.toLocaleString('ru-RU') }}</div>
            <div class="muted small">отзывов</div>
          </div>
        </div>
      </section>

      <section v-if="hasError" class="alert alert-error">
        <div>
          <strong>Не удалось собрать отзывы.</strong>
          <div class="small">{{ org.parse_error }}</div>
        </div>
      </section>

      <section v-else class="stack">
        <div class="row">
          <h2 style="margin:0">Отзывы</h2>
          <span v-if="reviews.total" class="muted small">
            страница {{ reviews.current_page }} из {{ reviews.last_page }}
          </span>
        </div>

        <div v-if="reviewsLoading" class="row muted">
          <span class="spinner spinner-dark" /> Загрузка отзывов…
        </div>

        <template v-else>
          <p v-if="!reviews.data.length" class="muted">
            Отзывов пока нет. Тянем только первую страницу (~50) — полный сбор ещё
            не доделан, см. README.
          </p>

          <div v-else class="stack">
            <ReviewCard v-for="r in reviews.data" :key="r.id" :review="r" />
          </div>

          <!-- Пагинация Laravel-пагинатора одной строкой: отдаём ответ как есть -->
          <Bootstrap5Pagination
            :data="reviews"
            :limit="2"
            @pagination-change-page="loadReviews"
          />
        </template>
      </section>
    </template>
  </div>
</template>

<style scoped>
.summary-head { display: flex; align-items: flex-start; gap: 16px; }
.summary-head > div:first-child { flex: 1; }
.summary-head h1 { margin-bottom: 2px; }
.stats {
  display: flex;
  gap: 28px;
  margin-top: 18px;
  padding-top: 18px;
  border-top: 1px solid var(--border);
  flex-wrap: wrap;
}
.stat-rating { display: flex; align-items: center; gap: 8px; }
.rating-value { font-size: 28px; font-weight: 800; line-height: 1; }
.stat-num { font-size: 24px; font-weight: 800; line-height: 1.2; }
</style>
