<template>
  <section class="panel" aria-labelledby="activity-title">
    <div class="panel__header">
      <h2 id="activity-title" class="panel__title">Recent Activity</h2>
    </div>
    <ul class="timeline" role="list">
      <li v-for="(item, index) in items" :key="item.id" class="timeline-item">
        <div class="timeline-item__track">
          <div :class="['timeline-item__dot', 'icon-badge', item.iconClass]">
            <span class="timeline-item__icon" aria-hidden="true" v-html="item.icon" />
          </div>
          <div v-if="index < items.length - 1" class="timeline-item__line" />
        </div>
        <div class="timeline-item__body">
          <span class="timeline-item__title">{{ item.title }}</span>
          <span class="timeline-item__desc">{{ item.desc }}</span>
          <span class="timeline-item__time">{{ item.time }}</span>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
export interface ActivityItem {
  id: number
  title: string
  desc: string
  time: string
  icon: string
  iconClass: string
}

defineProps<{ items: ActivityItem[] }>()
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

.timeline {
  list-style: none;
  padding: var(--tv-space-3) var(--tv-space-5) var(--tv-space-4);
  display: flex;
  flex-direction: column;
}

.timeline-item {
  display: flex;
  gap: var(--tv-space-3);
}

.timeline-item__track {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.timeline-item__dot {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  flex-shrink: 0;
  z-index: 1;
}

.timeline-item__line {
  width: 2px;
  flex: 1;
  min-height: var(--tv-space-4);
  background: var(--tv-border);
  margin: 3px 0;
}

.timeline-item__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  padding-bottom: var(--tv-space-4);
}

.timeline-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }
.timeline-item__desc  { font-size: var(--tv-text-xs); color: var(--tv-text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.timeline-item__time  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin-top: 1px; }

.timeline-item__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
</style>
