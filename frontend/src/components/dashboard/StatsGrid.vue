<template>
  <section class="stats-grid" :style="{ '--sg-cols': cols }" aria-label="Overview statistics">
    <div v-for="stat in stats" :key="stat.label" class="stat-card">
      <div class="stat-card__content">
        <div class="stat-card__text">
          <span class="stat-card__label">{{ stat.label }}</span>
          <span class="stat-card__value">{{ stat.value }}</span>
          <span class="stat-card__sub" :class="{ 'stat-card__sub--up': stat.trendUp }">
            {{ stat.sub }}
          </span>
        </div>
        <div :class="['stat-card__icon-wrap', 'icon-badge', stat.iconClass]">
          <span class="stat-card__icon" aria-hidden="true" v-html="stat.icon" />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'

export interface StatItem {
  label: string
  value: string
  sub: string
  trendUp: boolean
  icon: string
  iconClass: string
}

const props = defineProps<{ stats: StatItem[]; columns?: number }>()
const cols = computed(() => props.columns ?? 4)
</script>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(var(--sg-cols, 4), 1fr);
  gap: var(--tv-space-4);
}

.stat-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
  transition: box-shadow var(--tv-transition), transform var(--tv-transition);
}

.stat-card:hover { box-shadow: var(--tv-shadow); transform: translateY(-1px); }

.stat-card__content {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: var(--tv-space-5);
  gap: var(--tv-space-4);
}

.stat-card__text { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.stat-card__label { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); font-weight: var(--tv-font-medium); }
.stat-card__value { font-size: var(--tv-text-3xl); font-weight: var(--tv-font-bold); color: var(--tv-text); line-height: 1.1; }
.stat-card__sub { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.stat-card__sub--up { color: var(--tv-success); }

.stat-card__icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: var(--tv-radius-md);
  flex-shrink: 0;
}

.stat-card__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

@media (max-width: 1280px) { .stats-grid { grid-template-columns: repeat(min(var(--sg-cols, 4), 3), 1fr); } }
@media (max-width: 900px)  { .stats-grid { grid-template-columns: repeat(2, 1fr); gap: var(--tv-space-3); } }
@media (max-width: 480px)  { .stats-grid { grid-template-columns: 1fr; } }
</style>
