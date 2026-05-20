<template>
  <Transition name="tv-confirm">
    <div v-if="visible" class="tv-confirm" role="alert" aria-live="assertive">
      <p class="tv-confirm__message">{{ message }}</p>
      <div class="tv-confirm__actions">
        <TVButton
          :variant="confirmVariant"
          size="sm"
          @click="$emit('confirm')"
        >
          {{ confirmLabel }}
        </TVButton>
        <TVButton variant="ghost" size="sm" @click="$emit('cancel')">
          Cancel
        </TVButton>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import TVButton from '@/components/ui/TVButton.vue'
import type { ButtonVariant } from '@/types'

withDefaults(
  defineProps<{
    visible?: boolean
    message?: string
    confirmLabel?: string
    confirmVariant?: ButtonVariant
  }>(),
  {
    visible: false,
    message: 'Are you sure?',
    confirmLabel: 'Confirm',
    confirmVariant: 'danger',
  },
)

defineEmits<{
  confirm: []
  cancel: []
}>()
</script>

<style scoped>
.tv-confirm {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
  padding: var(--tv-space-3) var(--tv-space-4);
  background: hsl(0, 72%, 97%);
  border: 1px solid hsl(0, 72%, 88%);
  border-radius: var(--tv-radius);
}

.tv-confirm__message {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  margin: 0;
  flex: 1;
  min-width: 120px;
}

.tv-confirm__actions {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
}

/* Slide-in transition */
.tv-confirm-enter-active,
.tv-confirm-leave-active {
  transition: opacity var(--tv-transition-fast), transform var(--tv-transition-fast);
}

.tv-confirm-enter-from,
.tv-confirm-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
