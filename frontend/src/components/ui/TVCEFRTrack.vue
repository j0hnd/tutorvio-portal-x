<template>
  <div class="cefr-track">
    <div
      v-for="lvl in LEVELS"
      :key="lvl.code"
      :class="[
        'cefr-card',
        lvl.order < currentOrder ? 'cefr-card--done' :
        lvl.code === currentLevel ? 'cefr-card--active' :
        'cefr-card--future'
      ]"
    >
      <!-- Status icon -->
      <div class="cefr-card__icon">
        <svg v-if="lvl.order < currentOrder" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <path d="M2.5 7l3 3 6-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <svg v-else-if="lvl.code === currentLevel" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <circle cx="7" cy="7" r="3" fill="currentColor"/>
        </svg>
        <svg v-else width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.3"/>
          <path d="M5.5 5.5c0-1 .8-1.5 1.5-1.5s1.5.5 1.5 1.5c0 .8-.7 1.2-1.5 1.5v.5M7 10v.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
        </svg>
      </div>

      <!-- Code + label -->
      <div class="cefr-card__body">
        <span class="cefr-card__code">{{ lvl.code }}</span>
        <span class="cefr-card__label">{{ lvl.label }}</span>
      </div>

      <!-- Badges -->
      <div class="cefr-card__badges">
        <span v-if="lvl.code === currentLevel" class="cefr-badge cefr-badge--current">Your Level</span>
        <span v-if="lvl.code === startLevel && lvl.code !== currentLevel" class="cefr-badge cefr-badge--start">Started</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const LEVELS = [
  { code: 'A1', label: 'Beginner',          order: 1 },
  { code: 'A2', label: 'Elementary',         order: 2 },
  { code: 'B1', label: 'Intermediate',       order: 3 },
  { code: 'B2', label: 'Upper-Intermediate', order: 4 },
  { code: 'C1', label: 'Advanced',           order: 5 },
  { code: 'C2', label: 'Proficiency',        order: 6 },
]

const props = defineProps<{
  currentLevel: string   // e.g. 'B1'
  startLevel?: string    // e.g. 'A2'
}>()

const currentOrder = computed(() =>
  LEVELS.find(l => l.code === props.currentLevel)?.order ?? 0
)
</script>

<style scoped>
.cefr-track {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--tv-space-2);
}

.cefr-card {
  display: flex;
  align-items: flex-start;
  gap: var(--tv-space-2);
  padding: var(--tv-space-3);
  border-radius: var(--tv-radius-md);
  border: 1.5px solid var(--tv-border);
  background: var(--tv-bg-card);
  transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
  min-height: 72px;
}

/* Done */
.cefr-card--done {
  background: var(--tv-success-soft);
  border-color: var(--tv-success-border);
}
.cefr-card--done .cefr-card__icon { color: var(--tv-success-fg); background: white; border-color: var(--tv-success-border); }
.cefr-card--done .cefr-card__code { color: var(--tv-success-fg); }
.cefr-card--done .cefr-card__label { color: var(--tv-success-fg); opacity: 0.85; }

/* Active / Current */
.cefr-card--active {
  background: var(--tv-primary-soft);
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), 50%, 0.12);
}
.cefr-card--active .cefr-card__icon { color: var(--tv-primary); background: white; border-color: var(--tv-primary-muted); }
.cefr-card--active .cefr-card__code { color: var(--tv-primary); }
.cefr-card--active .cefr-card__label { color: var(--tv-primary); opacity: 0.85; }

/* Future / locked */
.cefr-card--future { opacity: 0.45; }
.cefr-card--future .cefr-card__icon { color: var(--tv-text-muted); }
.cefr-card--future .cefr-card__code { color: var(--tv-text-muted); }
.cefr-card--future .cefr-card__label { color: var(--tv-text-muted); }

/* Icon circle */
.cefr-card__icon {
  width: 26px; height: 26px; flex-shrink: 0;
  border-radius: 50%;
  border: 1.5px solid var(--tv-border);
  background: var(--tv-bg-soft);
  display: flex; align-items: center; justify-content: center;
  color: var(--tv-text-muted);
}

/* Body */
.cefr-card__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.cefr-card__code  { font-size: var(--tv-text-sm); font-weight: var(--tv-font-bold); color: var(--tv-text); line-height: 1; }
.cefr-card__label { font-size: 10px; color: var(--tv-text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Badges */
.cefr-card__badges { display: flex; flex-direction: column; gap: 3px; flex-shrink: 0; }
.cefr-badge {
  font-size: 9px; font-weight: var(--tv-font-bold);
  padding: 2px 6px; border-radius: var(--tv-radius-full);
  white-space: nowrap; text-transform: uppercase; letter-spacing: .04em;
}
.cefr-badge--current { background: var(--tv-primary); color: var(--tv-text-inverse); }
.cefr-badge--start   { background: var(--tv-bg-soft); color: var(--tv-text-muted); border: 1px solid var(--tv-border); }

@media (max-width: 640px) {
  .cefr-track { grid-template-columns: repeat(2, 1fr); }
}
</style>
