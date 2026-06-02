<template>
  <div class="cefr-track">
    <!-- Progress line behind the dots -->
    <div class="cefr-track__line">
      <div class="cefr-track__line-fill" :style="{ width: fillWidth }" />
    </div>

    <div
      v-for="lvl in LEVELS"
      :key="lvl.code"
      :class="[
        'cefr-step',
        lvl.order < currentOrder ? 'cefr-step--done' :
        lvl.code === currentLevel ? 'cefr-step--active' :
        'cefr-step--future'
      ]"
    >
      <!-- Dot -->
      <div class="cefr-step__dot">
        <svg v-if="lvl.order < currentOrder" width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true">
          <path d="M2 5l2.5 2.5 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div v-else-if="lvl.code === currentLevel" class="cefr-step__dot-inner" />
      </div>

      <!-- Labels -->
      <span class="cefr-step__code">{{ lvl.code }}</span>
      <span class="cefr-step__label">{{ lvl.short }}</span>

      <!-- Badges -->
      <span v-if="lvl.code === currentLevel" class="cefr-badge cefr-badge--current">Your Level</span>
      <span v-else-if="lvl.code === startLevel" class="cefr-badge cefr-badge--start">Started</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const LEVELS = [
  { code: 'A1', short: 'Beginner',    order: 1 },
  { code: 'A2', short: 'Elementary',  order: 2 },
  { code: 'B1', short: 'Intermed.',   order: 3 },
  { code: 'B2', short: 'Upper-Int.',  order: 4 },
  { code: 'C1', short: 'Advanced',    order: 5 },
  { code: 'C2', short: 'Proficiency', order: 6 },
]

const props = defineProps<{
  currentLevel: string
  startLevel?: string
}>()

const currentOrder = computed(() =>
  LEVELS.find(l => l.code === props.currentLevel)?.order ?? 0
)

// Fill line from 0% to position of current level dot
const fillWidth = computed(() => {
  const idx = currentOrder.value - 1  // 0-based
  if (idx <= 0) return '0%'
  const total = LEVELS.length - 1     // 5 gaps
  return `${(idx / total) * 100}%`
})
</script>

<style scoped>
.cefr-track {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  position: relative;
  padding-top: 20px;   /* room for dot above labels */
  padding-bottom: 4px;
  gap: 0;
}

/* Background line sits behind dots, spans full width */
.cefr-track__line {
  position: absolute;
  top: 28px;   /* vertically centered on dot */
  left: calc(100% / 12);   /* start at center of first dot */
  right: calc(100% / 12);  /* end at center of last dot */
  height: 2px;
  background: var(--tv-border);
  border-radius: 9999px;
}
.cefr-track__line-fill {
  height: 100%;
  background: var(--tv-primary);
  border-radius: 9999px;
  transition: width 0.4s ease;
}

/* Each step */
.cefr-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  flex: 1;
  position: relative;
  z-index: 1;
  min-width: 0;
}

/* Dot */
.cefr-step__dot {
  width: 22px; height: 22px;
  border-radius: 50%;
  border: 2px solid var(--tv-border);
  background: var(--tv-bg-card);
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
}

.cefr-step--done .cefr-step__dot {
  background: var(--tv-success-fg);
  border-color: var(--tv-success-fg);
  color: white;
}

.cefr-step--active .cefr-step__dot {
  background: var(--tv-primary);
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 4px var(--tv-primary-soft);
}

.cefr-step--future .cefr-step__dot {
  opacity: 0.45;
}

.cefr-step__dot-inner {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: white;
}

/* Code + label */
.cefr-step__code {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  line-height: 1;
}
.cefr-step--done .cefr-step__code   { color: var(--tv-success-fg); }
.cefr-step--active .cefr-step__code { color: var(--tv-primary); }
.cefr-step--future .cefr-step__code { opacity: 0.5; }

.cefr-step__label {
  font-size: 9px;
  color: var(--tv-text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 60px;
  text-align: center;
}
.cefr-step--active .cefr-step__label { color: var(--tv-primary); font-weight: var(--tv-font-semibold); }

/* Badges */
.cefr-badge {
  font-size: 8px; font-weight: var(--tv-font-bold);
  padding: 1px 5px; border-radius: var(--tv-radius-full);
  white-space: nowrap; text-transform: uppercase; letter-spacing: .04em;
  max-width: 60px; overflow: hidden; text-overflow: ellipsis;
}
.cefr-badge--current { background: var(--tv-primary); color: var(--tv-text-inverse); }
.cefr-badge--start   { background: var(--tv-bg-soft); color: var(--tv-text-muted); border: 1px solid var(--tv-border); }
</style>
