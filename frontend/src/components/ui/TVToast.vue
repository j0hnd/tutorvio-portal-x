<template>
  <Teleport to="body">
    <div class="toast-region" aria-live="polite" aria-atomic="false">
      <TransitionGroup name="toast" tag="div" class="toast-stack">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          :class="['toast', `toast--${toast.variant}`]"
          role="alert"
        >
          <span class="toast__icon" aria-hidden="true" v-html="iconSvg(toast.variant)" />
          <span class="toast__message">{{ toast.message }}</span>
          <button
            class="toast__close"
            aria-label="Dismiss notification"
            @click="removeToast(toast.id)"
          >
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M11 3L3 11M3 3l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useToast } from '@/composables/useToast'

const { toasts, removeToast } = useToast()

function iconSvg(variant: string): string {
  const icons: Record<string, string> = {
    success: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    error: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M8 5v3.5M8 11h.01" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
    warning: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 2L1.5 13.5h13L8 2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M8 7v2.5M8 11.5h.01" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
    info: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.4"/><path d="M8 7.5V11M8 5h.01" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
  }
  return icons[variant] ?? icons.info
}
</script>

<style scoped>
.toast-region {
  position: fixed;
  top: var(--tv-space-5);
  right: var(--tv-space-5);
  z-index: var(--tv-z-toast);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
  pointer-events: none;
  max-width: 380px;
  width: calc(100vw - 2.5rem);
}

.toast-stack {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.toast {
  display: flex;
  align-items: flex-start;
  gap: var(--tv-space-3);
  padding: var(--tv-space-3) var(--tv-space-4);
  border-radius: var(--tv-radius-md);
  background: var(--tv-bg-card);
  box-shadow: var(--tv-shadow-lg);
  border: 1px solid var(--tv-border);
  pointer-events: all;
  min-width: 280px;
  border-left: 4px solid;
}

.toast--success { border-left-color: var(--tv-success); }
.toast--error   { border-left-color: var(--tv-danger); }
.toast--warning { border-left-color: var(--tv-warning); }
.toast--info    { border-left-color: var(--tv-info); }

.toast__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 1px;
}

.toast--success .toast__icon { color: var(--tv-success-fg); }
.toast--error   .toast__icon { color: var(--tv-danger-fg); }
.toast--warning .toast__icon { color: var(--tv-warning-fg); }
.toast--info    .toast__icon { color: var(--tv-info-fg); }

.toast__message {
  flex: 1;
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  line-height: var(--tv-leading-snug);
}

.toast__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: var(--tv-radius-sm);
  color: var(--tv-text-muted);
  flex-shrink: 0;
  transition: background-color var(--tv-transition-fast), color var(--tv-transition-fast);
}

.toast__close:hover {
  background: var(--tv-bg-soft);
  color: var(--tv-text);
}

/* Transition */
.toast-enter-active {
  transition: opacity 250ms ease, transform 250ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toast-leave-active {
  transition: opacity 200ms ease, transform 200ms ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(24px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(24px);
}
.toast-move {
  transition: transform 250ms ease;
}

@media (prefers-reduced-motion: reduce) {
  .toast-enter-active,
  .toast-leave-active,
  .toast-move {
    transition: opacity 150ms ease;
  }
  .toast-enter-from,
  .toast-leave-to {
    transform: none;
  }
}
</style>
