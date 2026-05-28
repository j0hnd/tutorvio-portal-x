<template>
  <Teleport to="body">
    <Transition name="tv-modal">
      <div
        v-if="modelValue"
        class="tv-modal-overlay"
        @mousedown.self="$emit('update:modelValue', false)"
      >
        <div
          class="tv-modal"
          :style="maxWidth ? { maxWidth } : {}"
          role="dialog"
          :aria-modal="true"
          :aria-labelledby="titleId"
        >
          <div class="tv-modal__header">
            <h2 :id="titleId" class="tv-modal__title">{{ title }}</h2>
            <button class="tv-modal__close" type="button" aria-label="Close" @click="$emit('update:modelValue', false)">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
          <div class="tv-modal__body">
            <slot />
          </div>
          <div v-if="$slots.footer" class="tv-modal__footer">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'

const props = defineProps<{
  modelValue: boolean
  title: string
  maxWidth?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const titleId = computed(() => `tv-modal-title-${Math.random().toString(36).slice(2, 7)}`)

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.modelValue) emit('update:modelValue', false)
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<style scoped>
.tv-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: var(--tv-space-4);
}

.tv-modal {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-lg, 0 20px 60px rgba(0,0,0,0.18));
  width: 100%;
  max-width: 520px;
  max-height: 90dvh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.tv-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--tv-space-4) var(--tv-space-5);
  border-bottom: 1px solid var(--tv-border);
  flex-shrink: 0;
}

.tv-modal__title {
  font-size: var(--tv-text-base);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
}

.tv-modal__close {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  color: var(--tv-text-muted);
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  flex-shrink: 0;
}
.tv-modal__close:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

.tv-modal__body {
  padding: var(--tv-space-5);
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
}

.tv-modal__footer {
  padding: var(--tv-space-3) var(--tv-space-5);
  border-top: 1px solid var(--tv-border);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--tv-space-2);
  flex-shrink: 0;
  background: var(--tv-bg-soft);
}

/* Transition */
.tv-modal-enter-active { transition: opacity 150ms ease, transform 150ms ease; }
.tv-modal-leave-active { transition: opacity 120ms ease, transform 120ms ease; }
.tv-modal-enter-from   { opacity: 0; }
.tv-modal-leave-to     { opacity: 0; }
.tv-modal-enter-from .tv-modal { transform: scale(0.96) translateY(-8px); }
.tv-modal-leave-to .tv-modal   { transform: scale(0.96) translateY(-4px); }
</style>
