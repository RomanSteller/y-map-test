<script setup>
import StarRating from './StarRating.vue'

defineProps({
  review: { type: Object, required: true },
})

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}
</script>

<template>
  <article class="review card">
    <header class="review-head">
      <div class="avatar">{{ (review.author || '?').charAt(0) }}</div>
      <div>
        <div class="author">{{ review.author || 'Аноним' }}</div>
        <div class="muted small">{{ formatDate(review.reviewed_at) }}</div>
      </div>
      <div class="spacer" />
      <StarRating v-if="review.rating" :value="review.rating" :size="15" />
    </header>
    <p class="review-text">{{ review.text }}</p>
  </article>
</template>

<style scoped>
.review { padding: 16px 18px; }
.review-head { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.avatar {
  width: 38px; height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, #ffd1d1, #ff9a9a);
  color: #7a1010;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; text-transform: uppercase;
  flex-shrink: 0;
}
.author { font-weight: 600; }
.review-text { margin: 0; white-space: pre-line; color: #2b3138; }
</style>
