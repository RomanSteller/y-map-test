<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { organizationsApi } from '../api/organizations'
import StarRating from '../components/StarRating.vue'
import ReviewCard from '../components/ReviewCard.vue'
import Pagination from '../components/Pagination.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const org = ref(null)
const loading = ref(true)
const loadError = ref('')

const reviews = ref([])
const reviewsLoading = ref(false)
const page = ref(1)
const lastPage = ref(1)

let pollTimer = null

const errorMessages = {
  markup_changed: 'Похоже, Яндекс изменил разметку страницы — парсер нужно обновить.',
  blocked: 'Яндекс временно заблокировал запросы (антибот). Попробуйте позже.',
  source_unavailable: 'Источник недоступен. Проверьте ссылку или повторите попытку.',
  empty_result: 'Не удалось получить отзывы, хотя они должны быть.',
  invalid_url: 'Ссылка на организацию некорректна.',
}

const isBusy = computed(() => org.value?.parse?.is_busy)
const isFailed = computed(() => org.value?.parse?.status === 'failed')
const failureText = computed(() => {
  const r = org.value?.parse?.error_reason
  return errorMessages[r] || org.value?.parse?.error || 'Неизвестная ошибка.'
})

async function loadOrg() {
  try {
    org.value = await organizationsApi.get(props.id)
    if (isBusy.value) {
      startPolling()
    } else if (org.value.parse.status === 'completed') {
      await loadReviews(1)
    }
  } catch {
    loadError.value = 'Организация не найдена.'
  } finally {
    loading.value = false
  }
}

async function loadReviews(p) {
  reviewsLoading.value = true
  try {
    const res = await organizationsApi.reviews(props.id, p)
    reviews.value = res.data
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } finally {
    reviewsLoading.value = false
  }
}

function startPolling() {
  stopPolling()
  pollTimer = setInterval(async () => {
    try {
      const s = await organizationsApi.status(props.id)
      org.value.parse.status = s.status
      org.value.parse.progress = s.progress
      org.value.parse.is_busy = s.is_busy
      org.value.parse.error_reason = s.error_reason
      org.value.rating = s.rating
      org.value.ratings_count = s.ratings_count
      org.value.reviews_count = s.reviews_count

      if (!s.is_busy) {
        stopPolling()
        // Refresh full record + first page of reviews.
        org.value = await organizationsApi.get(props.id)
        if (s.status === 'completed') await loadReviews(1)
      }
    } catch {
      /* keep polling; transient */
    }
  }, 1500)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

async function reparse() {
  try {
    org.value = await organizationsApi.reparse(props.id)
    startPolling()
  } catch {
    /* ignore; a 409 just means it's already running */
    startPolling()
  }
}

onMounted(loadOrg)
onUnmounted(stopPolling)
</script>

<template>
  <div class="container stack">
    <div v-if="loading" class="row muted">
      <span class="spinner spinner-dark" /> Загрузка…
    </div>

    <div v-else-if="loadError" class="alert alert-error">{{ loadError }}</div>

    <template v-else>
      <RouterLink :to="{ name: 'settings' }" class="small">← К настройкам</RouterLink>

      <!-- Header / summary -->
      <section class="card summary">
        <div class="summary-head">
          <div>
            <h1>{{ org.title || 'Организация' }}</h1>
            <p class="muted small">{{ org.address }}</p>
            <p class="small">
              <a :href="org.url" target="_blank" rel="noopener">Открыть на Яндекс.Картах ↗</a>
            </p>
          </div>
          <button
            class="btn btn-secondary"
            :disabled="isBusy"
            title="Собрать данные заново"
            @click="reparse"
          >
            {{ isBusy ? 'Идёт сбор…' : 'Обновить' }}
          </button>
        </div>

        <!-- Stats: rating + two DISTINCT counters -->
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

      <!-- Live parsing progress -->
      <section v-if="isBusy" class="card stack">
        <div class="row">
          <span class="spinner spinner-dark" />
          <strong>Собираем отзывы…</strong>
          <span class="spacer" />
          <span class="muted">{{ org.parse.progress }}%</span>
        </div>
        <div class="progress"><div :style="{ width: org.parse.progress + '%' }" /></div>
        <p class="muted small">
          Данные тянутся в фоне через очередь — можно не ждать на этой странице.
        </p>
      </section>

      <!-- Failure -->
      <section v-else-if="isFailed" class="alert alert-error">
        <div>
          <strong>Не удалось собрать отзывы.</strong>
          <div class="small">{{ failureText }}</div>
        </div>
      </section>

      <!-- Reviews -->
      <section v-else class="stack">
        <div class="row">
          <h2 style="margin:0">Отзывы</h2>
          <span class="muted small">{{ reviews.length ? `страница ${page} из ${lastPage}` : '' }}</span>
        </div>

        <div v-if="reviewsLoading" class="row muted">
          <span class="spinner spinner-dark" /> Загрузка отзывов…
        </div>

        <template v-else>
          <p v-if="!reviews.length" class="muted">Отзывов пока нет.</p>

          <div v-else class="stack">
            <ReviewCard v-for="r in reviews" :key="r.id" :review="r" />
          </div>

          <Pagination
            :current-page="page"
            :last-page="lastPage"
            @change="loadReviews"
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
