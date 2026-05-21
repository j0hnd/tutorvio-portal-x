<template>
  <section class="panel" aria-labelledby="upcoming-title">
    <div class="panel__header">
      <h2 id="upcoming-title" class="panel__title">Upcoming Lessons</h2>
      <a href="#" class="panel__link">View all</a>
    </div>
    <ul class="lesson-list" role="list">
      <li
        v-for="lesson in lessons"
        :key="lesson.id"
        :class="['lesson-item', { 'lesson-item--soon': lesson.soon }]"
      >
        <div class="lesson-item__avatar">
          <img v-if="lesson.avatar" :src="lesson.avatar" :alt="lesson.teacher" />
          <span v-else class="lesson-item__initials">
            {{ lesson.teacher.split(' ').map((n: string) => n[0]).join('') }}
          </span>
        </div>
        <div class="lesson-item__body">
          <div class="lesson-item__top">
            <span class="lesson-item__subject">{{ lesson.subject }}</span>
            <span v-if="lesson.soon" class="lesson-item__soon-badge">SOON</span>
          </div>
          <span class="lesson-item__teacher">Teacher: {{ lesson.teacher }}</span>
          <div class="lesson-item__meta">
            <span class="lesson-item__meta-item">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none" aria-hidden="true">
                <rect x="1" y="2" width="11" height="10" rx="1.5" stroke="currentColor" stroke-width="1.1"/>
                <path d="M4 1v2M9 1v2M1 5.5h11" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/>
              </svg>
              {{ lesson.day }}
            </span>
            <span class="lesson-item__meta-item">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none" aria-hidden="true">
                <circle cx="6.5" cy="6.5" r="5.5" stroke="currentColor" stroke-width="1.1"/>
                <path d="M6.5 3.5v3l2 1.5" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/>
              </svg>
              {{ lesson.time }} ({{ lesson.duration }})
            </span>
          </div>
        </div>
        <div class="lesson-item__actions">
          <button v-if="lesson.soon" class="lesson-item__join-btn" type="button" @click="emit('join', lesson.id)">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <rect x="1" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/>
              <path d="M9 5.5l4-2v7l-4-2V5.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
            </svg>
            Join
          </button>
          <button class="lesson-item__more-btn" type="button" aria-label="More options" @click="emit('more', lesson.id)">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <circle cx="3" cy="8" r="1.2" fill="currentColor"/>
              <circle cx="8" cy="8" r="1.2" fill="currentColor"/>
              <circle cx="13" cy="8" r="1.2" fill="currentColor"/>
            </svg>
          </button>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
export interface Lesson {
  id: number
  subject: string
  teacher: string
  avatar?: string
  day: string
  time: string
  duration: string
  soon: boolean
}

defineProps<{ lessons: Lesson[] }>()

const emit = defineEmits<{
  join: [id: number]
  more: [id: number]
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
.panel__link  { font-size: var(--tv-text-sm); color: var(--tv-primary); font-weight: var(--tv-font-medium); text-decoration: none; }
.panel__link:hover { text-decoration: underline; }

.lesson-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
  padding: var(--tv-space-3) var(--tv-space-4) var(--tv-space-4);
}

.lesson-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-4);
  padding: var(--tv-space-4);
  border-radius: var(--tv-radius-md);
  border: 1px solid transparent;
  transition: background-color var(--tv-transition-fast), border-color var(--tv-transition-fast);
}

.lesson-item:hover {
  background: var(--tv-bg-soft);
  border-color: var(--tv-border);
}

.lesson-item--soon {
  background: var(--tv-primary-soft);
  border-color: var(--tv-primary-muted);
}

.lesson-item--soon:hover {
  background: hsl(var(--tv-primary-h), 70%, 91%);
  border-color: var(--tv-primary-muted);
}

.lesson-item__avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--tv-bg-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
  border: 2px solid var(--tv-primary-muted);
}

.lesson-item__avatar img { width: 100%; height: 100%; object-fit: cover; }

.lesson-item__initials {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-bold);
  color: var(--tv-primary);
}

.lesson-item__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.lesson-item__top  { display: flex; align-items: center; gap: var(--tv-space-2); }
.lesson-item__subject { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }

.lesson-item__soon-badge {
  font-size: 10px;
  font-weight: var(--tv-font-bold);
  padding: 2px 6px;
  border-radius: var(--tv-radius-full);
  background: hsl(var(--tv-primary-h), 70%, 88%);
  color: var(--tv-teal-fg);
  border: 1px solid var(--tv-primary-muted);
  letter-spacing: 0.04em;
}

.lesson-item__teacher { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }

.lesson-item__meta { display: flex; align-items: center; gap: var(--tv-space-4); flex-wrap: wrap; }

.lesson-item__meta-item {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: var(--tv-text-xs);
  color: var(--tv-text-secondary);
}

.lesson-item__actions {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  flex-shrink: 0;
}

.lesson-item__join-btn {
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
  padding: var(--tv-space-2) var(--tv-space-3);
  background: var(--tv-primary);
  color: white;
  border-radius: var(--tv-radius);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  border: none;
  cursor: pointer;
  white-space: nowrap;
  box-shadow: 0 2px 8px hsla(var(--tv-primary-h), var(--tv-primary-s), 40%, 0.28);
  transition:
    background-color var(--tv-transition-fast),
    box-shadow var(--tv-transition-fast),
    transform var(--tv-transition-fast);
}

.lesson-item__join-btn:hover {
  background: var(--tv-primary-hover);
  box-shadow: 0 4px 12px hsla(var(--tv-primary-h), var(--tv-primary-s), 40%, 0.36);
}

.lesson-item__join-btn:active { transform: translateY(1px); }

.lesson-item__more-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: var(--tv-radius-sm);
  color: var(--tv-text-muted);
  cursor: pointer;
  transition: background-color var(--tv-transition-fast), color var(--tv-transition-fast);
}

.lesson-item__more-btn:hover {
  background: hsla(var(--tv-primary-h), 40%, 85%, 0.5);
  color: var(--tv-text);
}
</style>
