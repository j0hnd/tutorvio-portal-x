<template>
  <span
    :class="['tv-badge', `tv-badge--${variant}`, { 'tv-badge--dot': dot }]"
    :aria-label="ariaLabel"
    v-bind="$attrs"
  >
    <span v-if="dot" class="tv-badge__dot" aria-hidden="true" />
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup lang="ts">
import type { BadgeVariant } from '@/types'

interface Props {
  variant?: BadgeVariant
  label?: string
  dot?: boolean
  ariaLabel?: string
}

withDefaults(defineProps<Props>(), {
  variant: 'neutral',
  dot: false,
})
</script>

<style scoped>
.tv-badge {
  display: inline-flex;
  align-items: center;
  gap: var(--tv-space-1);
  padding: 2px var(--tv-space-2);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  line-height: 1.5;
  border-radius: var(--tv-radius-full);
  white-space: nowrap;
  border: 1px solid transparent;
}

.tv-badge__dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}

/* --- Status Variants --- */
.tv-badge--scheduled {
  background: var(--tv-primary-soft);
  color: hsl(var(--tv-primary-h), var(--tv-primary-s), 42%);
  border-color: var(--tv-primary-muted);
}

.tv-badge--completed {
  background: var(--tv-success-soft);
  color: var(--tv-success-fg);
  border-color: var(--tv-success-border);
}

.tv-badge--cancelled {
  background: var(--tv-neutral-soft);
  color: var(--tv-neutral);
  border-color: var(--tv-neutral-border);
  text-decoration: line-through;
}

.tv-badge--missed {
  background: var(--tv-danger-soft);
  color: var(--tv-danger-fg);
  border-color: var(--tv-danger-border);
}

.tv-badge--trial {
  background: var(--tv-purple-soft);
  color: var(--tv-purple);
  border-color: var(--tv-purple-border);
}

.tv-badge--active {
  background: var(--tv-success-soft);
  color: var(--tv-success-fg);
  border-color: var(--tv-success-border);
}

.tv-badge--pending {
  background: var(--tv-warning-soft);
  color: var(--tv-warning-fg);
  border-color: var(--tv-warning-border);
}

.tv-badge--warning {
  background: var(--tv-warning-soft);
  color: var(--tv-warning-fg);
  border-color: var(--tv-warning-border);
}

.tv-badge--info {
  background: var(--tv-info-soft);
  color: var(--tv-info-fg);
  border-color: var(--tv-info-border);
}

.tv-badge--neutral {
  background: var(--tv-neutral-soft);
  color: var(--tv-neutral);
  border-color: var(--tv-neutral-border);
}
</style>
