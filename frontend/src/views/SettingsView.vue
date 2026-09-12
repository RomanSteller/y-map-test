<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { organizationsApi } from '../api/organizations'
import StarRating from '../components/StarRating.vue'

const router = useRouter()

const url = ref('')
const saving = ref(false)
const fieldError = ref('')
const generalError = ref('')

const organizations = ref([])
const loadingList = ref(true)

async function loadList() {
  loadingList.value = true
  try {
    organizations.value = await organizationsApi.list()
  } catch {
    generalError.value = 'Не удалось загрузить список организаций.'
  } finally {
    loadingList.value = false
  }
}

onMounted(loadList)

async function save() {
  saving.value = true
  fieldError.value = ''
  generalError.value = ''
  try {
    const org = await organizationsApi.save(url.value)
    // Go straight to the org page — it shows live parsing progress.
    router.push({ name: 'organization', params: { id: org.id } })
  } catch (e) {
    if (e.response?.status === 422) {
      fieldError.value =
        e.response.data?.errors?.url?.[0] ||
        e.response.data?.message ||
        'Некорректная ссылка.'
    } else {
      generalError.value = 'Ошибка сохранения. Попробуйте ещё раз.'
    }
  } finally {
    saving.value = false
  }
}

const statusLabels = {
  pending: 'Ожидает',
  queued: 'В очереди',
  parsing: 'Парсится',
  completed: 'Готово',
  failed: 'Ошибка',
}
</script>

<template>
  <div class="container stack">
    <section class="card stack">
      <div>
        <h1>Настройки</h1>
        <p class="muted small">
          Вставьте ссылку на карточку организации в Яндекс.Картах — мы соберём её
          отзывы, рейтинг и счётчики.
        </p>
      </div>

      <form class="stack" @submit.prevent="save">
        <div>
          <label for="url">Ссылка на организацию</label>
          <input
            id="url"
            v-model="url"
            type="text"
            placeholder="https://yandex.ru/maps/org/…/reviews/"
            :class="{ invalid: fieldError }"
          />
          <div v-if="fieldError" class="field-error">{{ fieldError }}</div>
        </div>

        <div class="row">
          <button class="btn" type="submit" :disabled="saving || !url">
            <span v-if="saving" class="spinner" />
            {{ saving ? 'Сохраняем…' : 'Сохранить и собрать отзывы' }}
          </button>
        </div>

        <div v-if="generalError" class="alert alert-error">{{ generalError }}</div>
      </form>
    </section>

    <section class="stack">
      <h2>Подключённые организации</h2>

      <div v-if="loadingList" class="row muted">
        <span class="spinner spinner-dark" /> Загрузка…
      </div>

      <p v-else-if="!organizations.length" class="muted">Пока ничего не добавлено.</p>

      <RouterLink
        v-for="org in organizations"
        :key="org.id"
        :to="{ name: 'organization', params: { id: org.id } }"
        class="org-row card"
      >
        <div class="org-main">
          <div class="org-title">{{ org.title || org.url }}</div>
          <div class="muted small">{{ org.address }}</div>
        </div>
        <div class="org-meta">
          <template v-if="org.rating">
            <StarRating :value="org.rating" :size="14" />
            <span class="rating-num">{{ org.rating?.toFixed(1) }}</span>
          </template>
          <span class="badge" :class="`badge-${org.parse.status}`">
            {{ statusLabels[org.parse.status] || org.parse.status }}
          </span>
        </div>
      </RouterLink>
    </section>
  </div>
</template>

<style scoped>
input.invalid { border-color: var(--danger); }
.org-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  color: var(--text);
}
.org-row:hover { text-decoration: none; border-color: #d4d7db; }
.org-main { flex: 1; min-width: 0; }
.org-title { font-weight: 600; }
.org-meta { display: flex; align-items: center; gap: 10px; }
.rating-num { font-weight: 700; }
.badge {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 4px 10px;
  border-radius: 0;
  background: #eceef0;
  color: var(--muted);
  white-space: nowrap;
}
.badge-completed { background: #e5f6ec; color: var(--success); }
.badge-failed { background: #fdecee; color: var(--danger); }
.badge-parsing, .badge-queued { background: #d8fbf8; color: #087b73; }
</style>
