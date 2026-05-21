<template>
  <section class="panel" aria-labelledby="qa-title">
    <div class="panel__header">
      <h2 id="qa-title" class="panel__title">Quick Actions</h2>
    </div>
    <div class="quick-actions">
      <button
        v-for="action in actions"
        :key="action.id"
        :class="['qa-btn', { 'qa-btn--primary': action.primary }]"
        type="button"
        @click="emit('action', action.id)"
      >
        <span class="qa-btn__icon" aria-hidden="true" v-html="action.icon" />
        <span class="qa-btn__text">
          <span class="qa-btn__label">{{ action.label }}</span>
          <span class="qa-btn__sub">{{ action.sub }}</span>
        </span>
      </button>
    </div>
  </section>
</template>

<script setup lang="ts">
export interface QuickAction {
  id: string
  label: string
  sub: string
  icon: string
  primary?: boolean
}

defineProps<{ actions: QuickAction[] }>()

const emit = defineEmits<{
  action: [id: string]
}>()
</script>

<style scoped>
.panel {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
  overflow: hidden;
}

.panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--tv-space-4) var(--tv-space-5);
}

.panel__title { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); }

.quick-actions {
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
  padding: var(--tv-space-4);
}

.qa-btn {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  width: 100%;
  padding: var(--tv-space-3) var(--tv-space-4);
  border-radius: var(--tv-radius-md);
  border: 1.5px solid var(--tv-border);
  background: var(--tv-bg-card);
  cursor: pointer;
  text-align: left;
  transition:
    background-color var(--tv-transition-fast),
    border-color var(--tv-transition-fast),
    box-shadow var(--tv-transition-fast),
    transform var(--tv-transition-fast);
}

.qa-btn:hover {
  border-color: var(--tv-primary-muted);
  box-shadow: var(--tv-shadow-sm);
  transform: translateY(-1px);
}

.qa-btn:active { transform: translateY(0); }

.qa-btn--primary {
  background: var(--tv-primary);
  border-color: var(--tv-primary);
  box-shadow: 0 4px 12px hsla(var(--tv-primary-h), var(--tv-primary-s), 40%, 0.30);
}

.qa-btn--primary:hover {
  background: var(--tv-primary-hover);
  border-color: var(--tv-primary-hover);
  box-shadow: 0 6px 18px hsla(var(--tv-primary-h), var(--tv-primary-s), 40%, 0.38);
}

.qa-btn--primary .qa-btn__icon  { background: hsla(0, 0%, 100%, 0.2); color: white; }
.qa-btn--primary .qa-btn__label { color: white; }
.qa-btn--primary .qa-btn__sub   { color: hsla(0, 0%, 100%, 0.75); }

.qa-btn__icon {
  width: 38px;
  height: 38px;
  border-radius: var(--tv-radius);
  background: var(--tv-bg-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: var(--tv-primary);
}

.qa-btn__text  { display: flex; flex-direction: column; gap: 2px; }
.qa-btn__label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }
.qa-btn__sub   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
</style>
