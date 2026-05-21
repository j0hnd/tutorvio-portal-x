<template>
  <component
    :is="tag"
    :class="['tv-card', `tv-card--${variant}`, { 'tv-card--hoverable': hoverable, 'tv-card--padded': padded }]"
    v-bind="$attrs"
  >
    <div v-if="$slots.header" class="tv-card__header">
      <slot name="header" />
    </div>

    <div class="tv-card__body">
      <slot />
    </div>

    <div v-if="$slots.footer" class="tv-card__footer">
      <slot name="footer" />
    </div>
  </component>
</template>

<script setup lang="ts">
import type { CardVariant } from '@/types'

interface Props {
  variant?: CardVariant
  hoverable?: boolean
  padded?: boolean
  tag?: string
}

withDefaults(defineProps<Props>(), {
  variant: 'default',
  hoverable: false,
  padded: true,
  tag: 'div',
})
</script>

<style scoped>
.tv-card {
  background: var(--tv-bg-card);
  border-radius: var(--tv-radius-md);
  overflow: hidden;
  border: 1px solid var(--tv-border);
  transition: box-shadow var(--tv-transition), transform var(--tv-transition), border-color var(--tv-transition);
}

/* --- Variants --- */
.tv-card--default {
  box-shadow: var(--tv-shadow-sm);
}

.tv-card--elevated {
  box-shadow: var(--tv-shadow-md);
  border-color: transparent;
}

.tv-card--glass {
  /* True glassmorphism — requires a colorful/image background behind the element */
  background: hsla(0, 0%, 100%, 0.45);
  backdrop-filter: blur(20px) saturate(2) brightness(1.05);
  -webkit-backdrop-filter: blur(20px) saturate(2) brightness(1.05);
  border: 1px solid hsla(0, 0%, 100%, 0.65);
  box-shadow:
    0 8px 32px hsla(215, 25%, 18%, 0.12),
    inset 0 1px 0 hsla(0, 0%, 100%, 0.8),
    inset 0 -1px 0 hsla(0, 0%, 100%, 0.1);
}

.tv-card--outlined {
  background: transparent;
  box-shadow: none;
  border-color: var(--tv-border);
}

/* Hoverable */
.tv-card--hoverable {
  cursor: pointer;
}
.tv-card--hoverable:hover {
  box-shadow: var(--tv-shadow-md);
  border-color: var(--tv-primary-muted);
  transform: translateY(-2px);
}
.tv-card--hoverable:active {
  transform: translateY(0);
}

/* --- Slots --- */
.tv-card__header {
  padding: var(--tv-space-4) var(--tv-space-6);
  border-bottom: 1px solid var(--tv-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--tv-space-4);
}

.tv-card__body { flex: 1; }

.tv-card--padded .tv-card__body {
  padding: var(--tv-space-6);
}

.tv-card__footer {
  padding: var(--tv-space-4) var(--tv-space-6);
  border-top: 1px solid var(--tv-border);
  background: var(--tv-bg-soft);
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
}
</style>
