<template>
  <button
    :class="['tv-btn', `tv-btn--${variant}`, `tv-btn--${size}`, { 'tv-btn--loading': loading, 'tv-btn--icon-only': iconOnly }]"
    :disabled="disabled || loading"
    :aria-label="iconOnly ? ariaLabel : undefined"
    :aria-busy="loading"
    :type="type"
    v-bind="$attrs"
  >
    <span v-if="loading" class="tv-btn__spinner" aria-hidden="true" />
    <span v-if="$slots.icon && !iconOnly" class="tv-btn__icon tv-btn__icon--start" aria-hidden="true">
      <slot name="icon" />
    </span>
    <span v-if="iconOnly" class="tv-btn__icon" aria-hidden="true">
      <slot name="icon" />
    </span>
    <span v-if="!iconOnly" class="tv-btn__label">
      <slot />
    </span>
    <span v-if="$slots['icon-end'] && !iconOnly" class="tv-btn__icon tv-btn__icon--end" aria-hidden="true">
      <slot name="icon-end" />
    </span>
  </button>
</template>

<script setup lang="ts">
import type { ButtonVariant, ButtonSize } from '@/types'

interface Props {
  variant?: ButtonVariant
  size?: ButtonSize
  loading?: boolean
  disabled?: boolean
  iconOnly?: boolean
  ariaLabel?: string
  type?: 'button' | 'submit' | 'reset'
}

withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  loading: false,
  disabled: false,
  iconOnly: false,
  type: 'button',
})
</script>

<style scoped>
.tv-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--tv-space-2);
  font-family: var(--tv-font-sans);
  font-weight: var(--tv-font-medium);
  line-height: 1;
  white-space: nowrap;
  border: 1.5px solid transparent;
  border-radius: var(--tv-radius);
  cursor: pointer;
  transition:
    background-color var(--tv-transition-fast),
    border-color var(--tv-transition-fast),
    color var(--tv-transition-fast),
    box-shadow var(--tv-transition-fast),
    transform var(--tv-transition-fast),
    opacity var(--tv-transition-fast);
  outline: none;
  position: relative;
  user-select: none;
  -webkit-user-select: none;
}

/* Active press effect */
.tv-btn:active:not(:disabled) { transform: translateY(1px); }

/* Focus ring */
.tv-btn:focus-visible {
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.25);
}

/* Disabled state */
.tv-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

/* --- Sizes --- */
.tv-btn--sm {
  padding: var(--tv-space-2) var(--tv-space-3);
  font-size: var(--tv-text-xs);
  border-radius: var(--tv-radius-sm);
}

.tv-btn--md {
  padding: var(--tv-space-2) var(--tv-space-5);
  font-size: var(--tv-text-sm);
  min-height: 40px;
}

.tv-btn--lg {
  padding: var(--tv-space-3) var(--tv-space-8);
  font-size: var(--tv-text-base);
  min-height: 48px;
  border-radius: var(--tv-radius-md);
}

/* Icon-only size normalization */
.tv-btn--icon-only.tv-btn--sm { padding: var(--tv-space-2); width: 32px; height: 32px; }
.tv-btn--icon-only.tv-btn--md { padding: var(--tv-space-2); width: 40px; height: 40px; }
.tv-btn--icon-only.tv-btn--lg { padding: var(--tv-space-3); width: 48px; height: 48px; }

/* --- Variants --- */
.tv-btn--primary {
  background: var(--tv-primary);
  color: var(--tv-text-inverse);
  border-color: var(--tv-primary);
  box-shadow: var(--tv-shadow-primary);
}
.tv-btn--primary:hover:not(:disabled) {
  background: var(--tv-primary-hover);
  border-color: var(--tv-primary-hover);
  box-shadow: 0 6px 20px hsla(var(--tv-primary-h), var(--tv-primary-s), 50%, 0.38);
}

.tv-btn--secondary {
  background: var(--tv-bg-card);
  color: var(--tv-text);
  border-color: var(--tv-border);
  box-shadow: var(--tv-shadow-sm);
}
.tv-btn--secondary:hover:not(:disabled) {
  background: var(--tv-bg-soft);
  border-color: var(--tv-primary);
  color: var(--tv-primary);
}

.tv-btn--ghost {
  background: transparent;
  color: var(--tv-text-secondary);
  border-color: transparent;
}
.tv-btn--ghost:hover:not(:disabled) {
  background: var(--tv-bg-soft);
  color: var(--tv-text);
}

.tv-btn--danger {
  background: var(--tv-danger);
  color: var(--tv-text-inverse);
  border-color: var(--tv-danger);
}
.tv-btn--danger:hover:not(:disabled) {
  background: var(--tv-danger-hover);
  border-color: var(--tv-danger-hover);
}

.tv-btn--success {
  background: var(--tv-success);
  color: var(--tv-text-inverse);
  border-color: var(--tv-success);
}
.tv-btn--success:hover:not(:disabled) {
  background: var(--tv-success-hover);
  border-color: var(--tv-success-hover);
}

/* --- Loading state --- */
.tv-btn--loading { pointer-events: none; }

.tv-btn__spinner {
  width: 14px;
  height: 14px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: tv-spin 0.7s linear infinite;
  flex-shrink: 0;
}

@keyframes tv-spin {
  to { transform: rotate(360deg); }
}

/* --- Icon slots --- */
.tv-btn__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 16px;
  height: 16px;
}
.tv-btn__icon--start { margin-right: -2px; }
.tv-btn__icon--end  { margin-left: -2px; }
</style>
