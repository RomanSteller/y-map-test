<script setup>
const props = defineProps({
  value: { type: Number, default: 0 },
  size: { type: Number, default: 16 },
})

// Процент заливки для i-й звезды (1..5), с поддержкой половинок.
function fillFor(i) {
  const v = props.value ?? 0
  if (v >= i) return '100%'
  if (v > i - 1) return Math.round((v - (i - 1)) * 100) + '%'
  return '0%'
}
</script>

<template>
  <span class="stars" :style="{ fontSize: size + 'px' }" :title="value">
    <span v-for="i in 5" :key="i" class="star">
      <span class="star-bg">★</span>
      <span class="star-fill" :style="{ width: fillFor(i) }">★</span>
    </span>
  </span>
</template>

<style scoped>
.stars { display: inline-flex; gap: 2px; line-height: 1; }
.star { position: relative; display: inline-block; }
.star-bg { color: #dcdfe3; }
.star-fill {
  position: absolute;
  left: 0; top: 0;
  overflow: hidden;
  color: var(--star);
  white-space: nowrap;
}
</style>
