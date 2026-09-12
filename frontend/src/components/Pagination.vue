<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage: { type: Number, required: true },
  lastPage: { type: Number, required: true },
})
const emit = defineEmits(['change'])

// Compact page window with ellipses, e.g. 1 … 4 5 [6] 7 8 … 12
const pages = computed(() => {
  const last = props.lastPage
  const cur = props.currentPage
  const out = []
  const push = (p) => out.push(p)
  const around = 1
  const range = new Set([1, last, cur])
  for (let i = cur - around; i <= cur + around; i++) {
    if (i >= 1 && i <= last) range.add(i)
  }
  const sorted = [...range].sort((a, b) => a - b)
  let prev = 0
  for (const p of sorted) {
    if (p - prev > 1) push('…')
    push(p)
    prev = p
  }
  return out
})

function go(p) {
  if (p !== '…' && p !== props.currentPage) emit('change', p)
}
</script>

<template>
  <nav v-if="lastPage > 1" class="pagination">
    <button class="pg" :disabled="currentPage === 1" @click="go(currentPage - 1)">←</button>
    <button
      v-for="(p, i) in pages"
      :key="i"
      class="pg"
      :class="{ active: p === currentPage, dots: p === '…' }"
      :disabled="p === '…'"
      @click="go(p)"
    >
      {{ p }}
    </button>
    <button class="pg" :disabled="currentPage === lastPage" @click="go(currentPage + 1)">→</button>
  </nav>
</template>

<style scoped>
.pagination { display: flex; gap: 6px; flex-wrap: wrap; justify-content: center; }
.pg {
  min-width: 38px;
  height: 38px;
  padding: 0 10px;
  border: 1px solid var(--border);
  background: #fff;
  border-radius: 9px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
}
.pg:hover:not(:disabled):not(.active) { background: #f2f3f5; }
.pg.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.pg.dots { border: none; background: transparent; cursor: default; }
.pg:disabled:not(.active):not(.dots) { opacity: 0.4; cursor: not-allowed; }
</style>
