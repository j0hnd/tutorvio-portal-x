<template>
  <div class="sd-page">

    <!-- Welcome -->
    <section class="sd-welcome">
      <div>
        <h1 class="sd-welcome__title">Welcome back, {{ firstName }}! 👋</h1>
        <p class="sd-welcome__sub">
          <template v-if="nextLesson">
            Your next lesson is <strong>{{ nextLesson.subject }}</strong> at {{ nextLesson.time }} today.
          </template>
          <template v-else>Here's your learning summary for today.</template>
        </p>
      </div>
    </section>

    <!-- Stats -->
    <StatsGrid :stats="stats" />

    <!-- Content -->
    <div class="sd-content">

      <!-- Main (cols 1–3) -->
      <div class="sd-main">

        <!-- Upcoming Lessons -->
        <UpcomingLessons :lessons="upcomingLessons" @join="() => {}" @more="() => {}" />

        <!-- Recent Homework -->
        <section class="sd-panel">
          <div class="sd-panel__header">
            <h2 class="sd-panel__title">Recent Homework</h2>
            <span class="sd-panel__badge">{{ pendingCount }} pending</span>
          </div>
          <ul class="sd-list">
            <li v-for="hw in MOCK_HOMEWORK" :key="hw.id" class="sd-list-item">
              <div class="sd-list-item__icon sd-list-item__icon--doc">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M3 1h6l3 3v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                  <path d="M8 1v3h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div class="sd-list-item__body">
                <p class="sd-list-item__title">{{ hw.title }}</p>
                <p class="sd-list-item__meta">{{ hw.lesson }} · Due {{ hw.due }}</p>
              </div>
              <span class="sd-status-chip" :class="`sd-status-chip--${hw.status}`">{{ hw.statusLabel }}</span>
            </li>
          </ul>
        </section>

        <!-- Recent Materials -->
        <section class="sd-panel">
          <div class="sd-panel__header">
            <h2 class="sd-panel__title">Recent Materials</h2>
          </div>
          <ul class="sd-list">
            <li v-for="mat in MOCK_MATERIALS" :key="mat.id" class="sd-list-item">
              <div class="sd-list-item__icon sd-list-item__icon--file">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M3 1h6l3 3v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                  <path d="M8 1v3h3M4 7h6M4 9.5h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div class="sd-list-item__body">
                <p class="sd-list-item__title">{{ mat.title }}</p>
                <p class="sd-list-item__meta">{{ mat.lesson }} · Accessed {{ mat.accessed }}</p>
              </div>
              <span class="sd-type-tag">{{ mat.type }}</span>
            </li>
          </ul>
        </section>

      </div>

      <!-- Sidebar (col 4) -->
      <div class="sd-sidebar">

        <!-- Active Plan -->
        <div class="sd-card">
          <h3 class="sd-card__title">Active Plan</h3>
          <p class="sd-card__plan">Standard Plan — 8 Lessons/Month</p>
          <div class="sd-prog-bar">
            <div class="sd-prog-bar__fill" style="width: 62.5%" />
          </div>
          <div class="sd-prog-meta">
            <span>5 used</span>
            <span>3 remaining</span>
          </div>
          <div class="sd-stat-row">
            <span class="sd-stat-label">Program</span>
            <span class="sd-stat-value">{{ studentProfile?.program || '—' }}</span>
          </div>
          <div class="sd-stat-row">
            <span class="sd-stat-label">Expires</span>
            <span class="sd-stat-value">Jun 1, 2026</span>
          </div>
        </div>

        <!-- Latest Teacher Note -->
        <div class="sd-card">
          <h3 class="sd-card__title">Latest Teacher Note</h3>
          <div v-if="assignedTeacher" class="sd-teacher-row">
            <div class="sd-teacher-row__avatar">{{ teacherInitials }}</div>
            <div>
              <p class="sd-teacher-row__name">{{ teacherName }}</p>
              <p class="sd-teacher-row__spec">{{ assignedTeacher.teacherProfile?.specialization }}</p>
            </div>
          </div>
          <blockquote class="sd-note">
            "Great progress on presentation skills. Focus on transition phrases and active listening next session."
          </blockquote>
          <p class="sd-note-date">After lesson on May 15, 2026</p>
        </div>

        <!-- Reminders -->
        <div class="sd-card">
          <h3 class="sd-card__title">Reminders</h3>
          <ul class="sd-remind-list">
            <li v-for="r in MOCK_REMINDERS" :key="r.id" class="sd-remind-item">
              <span class="sd-remind-dot" :class="`sd-remind-dot--${r.type}`" />
              <div>
                <p class="sd-remind-text">{{ r.text }}</p>
                <p class="sd-remind-time">{{ r.time }}</p>
              </div>
            </li>
          </ul>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useUsersStore } from '@/stores/users'
import StatsGrid from '@/components/dashboard/StatsGrid.vue'
import UpcomingLessons from '@/components/dashboard/UpcomingLessons.vue'
import type { StatItem } from '@/components/dashboard/StatsGrid.vue'
import type { Lesson } from '@/components/dashboard/UpcomingLessons.vue'

const auth = useAuthStore()
const store = useUsersStore()

onMounted(async () => {
  if (!store.users.length) await store.fetchUsers()
})

const firstName = computed(() => auth.user?.firstName ?? 'there')
const studentRecord = computed(() => auth.user ? store.getUserByEmail(auth.user.email) : undefined)
const studentProfile = computed(() => studentRecord.value?.studentProfile)
const assignedTeacher = computed(() =>
  studentProfile.value?.assignedTeacherId
    ? store.getUserById(studentProfile.value.assignedTeacherId)
    : undefined,
)
const teacherName = computed(() =>
  assignedTeacher.value
    ? `${assignedTeacher.value.firstName} ${assignedTeacher.value.lastName}`
    : '',
)
const teacherInitials = computed(() =>
  assignedTeacher.value
    ? `${assignedTeacher.value.firstName[0]}${assignedTeacher.value.lastName[0]}`
    : '',
)

const LEVEL_SHORT: Record<string, string> = {
  BEGINNER: 'A1', ELEMENTARY: 'A2', INTERMEDIATE: 'B1',
  UPPER_INTERMEDIATE: 'B2', ADVANCED: 'C1', PROFICIENCY: 'C2',
}
const LEVEL_LONG: Record<string, string> = {
  BEGINNER: 'Beginner', ELEMENTARY: 'Elementary', INTERMEDIATE: 'Intermediate',
  UPPER_INTERMEDIATE: 'Upper Intermediate', ADVANCED: 'Advanced', PROFICIENCY: 'Proficiency',
}

const icons = {
  book:   `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 2h10a1 1 0 0 1 1 1v12l-5-2.5L5 15V3a1 1 0 0 1-1-1z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>`,
  clock:  `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7.5" stroke="currentColor" stroke-width="1.4"/><path d="M9 5.5V9l2.5 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  credit: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="4" width="16" height="11" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M1 7.5h16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="5" cy="11" r="1" fill="currentColor"/></svg>`,
  target: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7.5" stroke="currentColor" stroke-width="1.4"/><circle cx="9" cy="9" r="4" stroke="currentColor" stroke-width="1.4"/><circle cx="9" cy="9" r="1.5" fill="currentColor"/></svg>`,
}

const stats = computed((): StatItem[] => [
  { label: 'Completed Lessons', value: '24',  sub: '+12% this month',   trendUp: true,  icon: icons.book,   iconClass: 'icon-badge--teal' },
  { label: 'Lesson Balance',    value: '3',   sub: 'of 8 in plan',      trendUp: false, icon: icons.credit, iconClass: 'icon-badge--success' },
  {
    label: 'My Level',
    value: LEVEL_SHORT[studentProfile.value?.englishLevel ?? ''] ?? '—',
    sub:   `${LEVEL_LONG[studentProfile.value?.englishLevel ?? ''] ?? 'No level'} · ${studentProfile.value?.program ?? 'No program'}`,
    trendUp: false, icon: icons.target, iconClass: 'icon-badge--warning',
  },
  { label: 'Hours Learned', value: '36.5', sub: 'Total study time', trendUp: false, icon: icons.clock, iconClass: 'icon-badge--teal' },
])

const upcomingLessons: Lesson[] = [
  { id: 1, subject: 'Business English',       teacher: 'James Reyes', avatar: '', day: 'Today',    time: '2:00 PM',  duration: '60 min', soon: true  },
  { id: 2, subject: 'Presentation Skills',    teacher: 'James Reyes', avatar: '', day: 'Tomorrow', time: '10:00 AM', duration: '60 min', soon: false },
  { id: 3, subject: 'Email Writing Workshop', teacher: 'James Reyes', avatar: '', day: 'May 24',   time: '2:00 PM',  duration: '60 min', soon: false },
]

const nextLesson = computed(() => upcomingLessons.find(l => l.soon))

const MOCK_HOMEWORK = [
  { id: 'h1', title: 'Write a Formal Business Email',  lesson: 'Email Writing',       due: 'Today',  status: 'pending',  statusLabel: 'Pending' },
  { id: 'h2', title: 'Prepare Presentation Draft',     lesson: 'Presentation Skills', due: 'May 23', status: 'pending',  statusLabel: 'Pending' },
  { id: 'h3', title: 'Negotiation Vocabulary Quiz',    lesson: 'Negotiation Language', due: 'May 20', status: 'done',    statusLabel: 'Done' },
  { id: 'h4', title: 'Business Report — Chapter 2',    lesson: 'Business Report',     due: 'May 18', status: 'overdue', statusLabel: 'Overdue' },
]

const MOCK_MATERIALS = [
  { id: 'm1', title: 'Business English Workbook Ch. 6', type: 'PDF',  lesson: 'Business English',    accessed: 'May 15' },
  { id: 'm2', title: 'Presentation Skills Slides',      type: 'PPTX', lesson: 'Presentation Skills', accessed: 'May 15' },
  { id: 'm3', title: 'Negotiation Phrases Reference',   type: 'PDF',  lesson: 'Negotiation Language', accessed: 'May 8' },
  { id: 'm4', title: 'Professional Email Templates',    type: 'DOCX', lesson: 'Email Writing',        accessed: 'May 6' },
]

const MOCK_REMINDERS = [
  { id: 'r1', text: 'Business English at 2:00 PM today',        time: 'In 3 hours', type: 'lesson'  },
  { id: 'r2', text: 'Homework due — submit before next class',  time: 'Due today',  type: 'warning' },
  { id: 'r3', text: 'Platform maintenance on May 25, 2–4 AM',  time: 'May 25',     type: 'info'    },
]

const pendingCount = computed(() => MOCK_HOMEWORK.filter(h => h.status === 'pending').length)
</script>

<style scoped>
.sd-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

/* Welcome */
.sd-welcome__title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
}
.sd-welcome__sub {
  margin-top: var(--tv-space-1);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-secondary);
}

/* Content grid — matches StatsGrid (4 equal cols) */
.sd-content {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--tv-space-4);
  align-items: start;
}
.sd-main    { grid-column: 1 / 4; display: flex; flex-direction: column; gap: var(--tv-space-4); }
.sd-sidebar { grid-column: 4;     display: flex; flex-direction: column; gap: var(--tv-space-4); }

/* Panel */
.sd-panel {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
  overflow: hidden;
}
.sd-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--tv-space-4) var(--tv-space-5);
  border-bottom: 1px solid var(--tv-border);
}
.sd-panel__title { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.sd-panel__badge {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}

/* Generic list */
.sd-list { list-style: none; display: flex; flex-direction: column; padding: var(--tv-space-2) var(--tv-space-4) var(--tv-space-4); gap: 0; }

.sd-list-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-3) 0;
  border-bottom: 1px solid var(--tv-border);
}
.sd-list-item:last-child { border-bottom: none; }

.sd-list-item__icon {
  width: 30px;
  height: 30px;
  border-radius: var(--tv-radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.sd-list-item__icon--doc  { background: var(--tv-warning-soft);  color: var(--tv-warning-fg); }
.sd-list-item__icon--file { background: var(--tv-primary-soft);  color: var(--tv-primary); }

.sd-list-item__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.sd-list-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sd-list-item__meta  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }

/* Status chips */
.sd-status-chip {
  flex-shrink: 0;
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}
.sd-status-chip--pending { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.sd-status-chip--done    { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sd-status-chip--overdue { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }

/* Type tag */
.sd-type-tag {
  flex-shrink: 0;
  font-size: 10px;
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  padding: 2px var(--tv-space-1);
  border-radius: var(--tv-radius-sm);
}

/* Sidebar card */
.sd-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
  box-shadow: var(--tv-shadow-sm);
}
.sd-card__title {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0;
  padding-bottom: var(--tv-space-2);
  border-bottom: 1px solid var(--tv-border);
}
.sd-card__plan { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }

/* Plan progress */
.sd-prog-bar { height: 8px; background: var(--tv-bg-soft); border-radius: var(--tv-radius-full); overflow: hidden; }
.sd-prog-bar__fill { height: 100%; background: var(--tv-primary); border-radius: var(--tv-radius-full); transition: width 0.4s ease; }
.sd-prog-meta { display: flex; justify-content: space-between; font-size: var(--tv-text-xs); color: var(--tv-text-muted); }

/* Stat rows */
.sd-stat-row { display: flex; justify-content: space-between; align-items: center; font-size: var(--tv-text-sm); padding: var(--tv-space-1) 0; border-bottom: 1px solid var(--tv-bg-soft); }
.sd-stat-row:last-child { border-bottom: none; }
.sd-stat-label { color: var(--tv-text-muted); }
.sd-stat-value { font-weight: var(--tv-font-medium); color: var(--tv-text); }

/* Teacher row */
.sd-teacher-row { display: flex; align-items: center; gap: var(--tv-space-2); }
.sd-teacher-row__avatar {
  width: 32px; height: 32px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-teal-soft); color: var(--tv-teal-fg);
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-bold);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sd-teacher-row__name { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); margin: 0; }
.sd-teacher-row__spec { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

/* Teacher note */
.sd-note { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); font-style: italic; line-height: var(--tv-leading-relaxed); border-left: 3px solid var(--tv-primary-muted); padding-left: var(--tv-space-3); margin: 0; }
.sd-note-date { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }

/* Reminders */
.sd-remind-list { list-style: none; display: flex; flex-direction: column; gap: 0; }
.sd-remind-item { display: flex; align-items: flex-start; gap: var(--tv-space-2); padding: var(--tv-space-2) 0; border-bottom: 1px solid var(--tv-bg-soft); }
.sd-remind-item:last-child { border-bottom: none; }
.sd-remind-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.sd-remind-dot--lesson  { background: var(--tv-primary); }
.sd-remind-dot--warning { background: var(--tv-warning); }
.sd-remind-dot--info    { background: var(--tv-info); }
.sd-remind-text { font-size: var(--tv-text-sm); color: var(--tv-text); margin: 0; line-height: 1.4; }
.sd-remind-time { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

/* Responsive */
@media (max-width: 1280px) {
  .sd-content { grid-template-columns: 3fr 1fr; }
  .sd-main    { grid-column: 1; }
  .sd-sidebar { grid-column: 2; }
}
@media (max-width: 1100px) {
  .sd-content { grid-template-columns: 1fr; }
  .sd-main    { grid-column: 1; }
  .sd-sidebar { grid-column: 1; display: grid; grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 767px) {
  .sd-page { padding: var(--tv-space-4); }
  .sd-sidebar { grid-template-columns: 1fr; }
}
</style>
