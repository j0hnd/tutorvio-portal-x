<template>
  <div v-if="totalPages > 1" class="tvp-wrap" role="navigation" aria-label="Pagination">
    <button
      class="tvp-btn tvp-btn--prev"
      type="button"
      :disabled="modelValue === 1"
      aria-label="Previous page"
      @click="emit('update:modelValue', modelValue - 1)"
    >
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
        <path d="M9 2L4 7l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <template v-for="p in pages" :key="p">
      <span v-if="p === '...'" class="tvp-ellipsis">…</span>
      <button
        v-else
        :class="['tvp-btn', 'tvp-btn--page', { 'tvp-btn--active': p === modelValue }]"
        type="button"
        :aria-label="`Page ${p}`"
        :aria-current="p === modelValue ? 'page' : undefined"
        @click="emit('update:modelValue', p as number)"
      >{{ p }}</button>
    </template>

    <button
      class="tvp-btn tvp-btn--next"
      type="button"
      :disabled="modelValue === totalPages"
      aria-label="Next page"
      @click="emit('update:modelValue', modelValue + 1)"
    >
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
        <path d="M5 2l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <span class="tvp-meta">{{ from }}–{{ to }} of {{ total }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  total: number
  pageSize: number
  modelValue: number
}>()

const emit = defineEmits<{ (e: 'update:modelValue', page: number): void }>()

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.pageSize)))
const from = computed(() => Math.min((props.modelValue - 1) * props.pageSize + 1, props.total))
const to   = computed(() => Math.min(props.modelValue * props.pageSize, props.total))

const pages = computed<(number | '...')[]>(() => {
  const n = totalPages.value
  const c = props.modelValue
  if (n <= 7) return Array.from({ length: n }, (_, i) => i + 1)
  const result: (number | '...')[] = [1]
  if (c > 3) result.push('...')
  for (let i = Math.max(2, c - 1); i <= Math.min(n - 1, c + 1); i++) result.push(i)
  if (c < n - 2) result.push('...')
  result.push(n)
  return result
})
</script>

<style scoped>
.tvp-wrap {
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
  flex-wrap: wrap;
}

.tvp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 32px;
  padding: 0 var(--tv-space-2);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  font-family: inherit;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-card);
  color: var(--tv-text-secondary);
  cursor: pointer;
  transition: background 0.12s, border-color 0.12s, color 0.12s;
  line-height: 1;
}
.tvp-btn:hover:not(:disabled):not(.tvp-btn--active) {
  background: var(--tv-bg-soft);
  border-color: var(--tv-border-strong, var(--tv-border));
  color: var(--tv-text);
}
.tvp-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.tvp-btn--active {
  background: var(--tv-primary);
  border-color: var(--tv-primary);
  color: var(--tv-text-inverse);
  cursor: default;
}
.tvp-btn--prev,
.tvp-btn--next { padding: 0 var(--tv-space-2); }

.tvp-ellipsis {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  user-select: none;
}

.tvp-meta {
  margin-left: var(--tv-space-2);
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  white-space: nowrap;
}
</style>
