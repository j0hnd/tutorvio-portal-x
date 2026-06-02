<template>
  <div class="adm-page">

    <!-- Welcome -->
    <section class="adm-welcome">
      <div>
        <h1 class="adm-welcome__title">Welcome back, {{ firstName }}!</h1>
        <p class="adm-welcome__sub">Here's your operational overview for today.</p>
      </div>
    </section>

    <!-- Stats -->
    <StatsGrid :stats="stats" />

    <!-- Content -->
    <div class="adm-content">

      <!-- Main (cols 1–3) -->
      <div class="adm-main">

        <!-- Today's Classes -->
        <section class="adm-panel">
          <div class="adm-panel__header">
            <h2 class="adm-panel__title">Today's Classes</h2>
            <a href="/schedule" class="adm-panel__link">View schedule</a>
          </div>
          <ul class="adm-schedule-list">
            <li
              v-for="cls in MOCK_TODAY_CLASSES"
              :key="cls.id"
              :class="['adm-schedule-item', { 'adm-schedule-item--live': cls.status === 'live', 'adm-schedule-item--missed': cls.status === 'missed' }]"
              style="cursor: pointer"
              @click="router.push({ name: 'LessonDetail', params: { id: cls.id } })"
            >
              <div class="adm-schedule-item__time">
                <span class="adm-schedule-item__hour">{{ cls.time }}</span>
                <span class="adm-schedule-item__dur">{{ cls.duration }}</span>
              </div>
              <div class="adm-schedule-item__body">
                <p class="adm-schedule-item__title">{{ cls.subject }}</p>
                <p class="adm-schedule-item__meta">{{ cls.teacher }} &rarr; {{ cls.student }}</p>
              </div>
              <span class="adm-chip" :class="`adm-chip--${cls.status}`">{{ cls.statusLabel }}</span>
            </li>
          </ul>
        </section>

        <!-- Pending Teacher Notes -->
        <section class="adm-panel">
          <div class="adm-panel__header">
            <h2 class="adm-panel__title">Pending Teacher Notes</h2>
            <span class="adm-panel__badge">{{ MOCK_PENDING_NOTES.length }} outstanding</span>
          </div>
          <ul class="adm-list">
            <li v-for="note in MOCK_PENDING_NOTES" :key="note.id" class="adm-list-item">
              <div class="adm-list-item__avatar">{{ note.teacherInitials }}</div>
              <div class="adm-list-item__body">
                <p class="adm-list-item__title">{{ note.teacher }}</p>
                <p class="adm-list-item__meta">{{ note.lessonCount }} lesson{{ note.lessonCount !== 1 ? 's' : '' }} without notes · Last: {{ note.lastLesson }}</p>
              </div>
              <span class="adm-chip" :class="`adm-chip--${note.urgency}`">{{ note.urgencyLabel }}</span>
            </li>
          </ul>
        </section>

        <!-- Enrollment Numbers -->
        <section class="adm-panel">
          <div class="adm-panel__header">
            <h2 class="adm-panel__title">Enrollment Overview</h2>
            <a href="/admin/users" class="adm-panel__link">Manage students</a>
          </div>
          <div class="adm-enrollment-grid">
            <div v-for="prog in enrollmentBreakdown" :key="prog.name" class="adm-enroll-row">
              <div class="adm-enroll-row__info">
                <span class="adm-enroll-row__name">{{ prog.name }}</span>
                <span class="adm-enroll-row__count">{{ prog.active }} active</span>
              </div>
              <div class="adm-prog-bar">
                <div class="adm-prog-bar__fill" :style="{ width: prog.pct + '%' }" />
              </div>
            </div>
          </div>
        </section>

        <!-- Missed Classes / No-shows -->
        <section class="adm-panel">
          <div class="adm-panel__header">
            <h2 class="adm-panel__title">Missed Classes / No-shows</h2>
            <span class="adm-panel__badge adm-panel__badge--danger">{{ MOCK_MISSED.length }} today</span>
          </div>
          <ul class="adm-list">
            <li v-for="m in MOCK_MISSED" :key="m.id" class="adm-list-item">
              <div class="adm-list-item__icon adm-list-item__icon--warn">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M7 1.5l5.5 10H1.5L7 1.5z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                  <path d="M7 6v2.5M7 10.5v.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </div>
              <div class="adm-list-item__body">
                <p class="adm-list-item__title">{{ m.subject }}</p>
                <p class="adm-list-item__meta">{{ m.teacher }} &rarr; {{ m.student }} · {{ m.time }}</p>
              </div>
              <span class="adm-chip adm-chip--missed">{{ m.missedBy }}</span>
            </li>
          </ul>
        </section>

      </div>

      <!-- Sidebar -->
      <div class="adm-sidebar">

        <!-- Payment / Package Alerts -->
        <div class="adm-card adm-card--alert">
          <h3 class="adm-card__title">Payment & Package Alerts</h3>
          <ul class="adm-alert-list">
            <li v-for="alert in MOCK_PAYMENT_ALERTS" :key="alert.id" class="adm-alert-item">
              <span class="adm-alert-dot" :class="`adm-alert-dot--${alert.type}`" />
              <div>
                <p class="adm-alert-text">{{ alert.text }}</p>
                <p class="adm-alert-meta">{{ alert.sub }}</p>
              </div>
            </li>
          </ul>
          <a href="/billing" class="adm-secondary-link">View all billing &rarr;</a>
        </div>

        <!-- Operational Announcements -->
        <div class="adm-card">
          <h3 class="adm-card__title">Operational Announcements</h3>
          <ul class="adm-remind-list">
            <li v-for="ann in MOCK_ANNOUNCEMENTS" :key="ann.id" class="adm-remind-item">
              <span class="adm-remind-dot" :class="`adm-remind-dot--${ann.type}`" />
              <div>
                <p class="adm-remind-text">{{ ann.text }}</p>
                <p class="adm-remind-time">{{ ann.date }}</p>
              </div>
            </li>
          </ul>
        </div>

        <!-- Quick Links to Management Modules -->
        <div class="adm-card">
          <h3 class="adm-card__title">Management Modules</h3>
          <div class="adm-quick-links">
            <a href="/admin/users" class="adm-quick-link">
              <span class="adm-quick-link__icon adm-quick-link__icon--teal">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <circle cx="6" cy="4.5" r="2.5" stroke="currentColor" stroke-width="1.2"/>
                  <path d="M1 13c0-2.761 2.239-5 5-5s5 2.239 5 5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                  <circle cx="11.5" cy="5.5" r="1.5" stroke="currentColor" stroke-width="1.2"/>
                  <path d="M11.5 7c1.105 0 2 .895 2 2v1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </span>
              <div>
                <p class="adm-quick-link__label">User Management</p>
                <p class="adm-quick-link__sub">{{ activeStudentCount }} students, {{ activeTeacherCount }} teachers</p>
              </div>
            </a>
            <a href="/billing" class="adm-quick-link">
              <span class="adm-quick-link__icon adm-quick-link__icon--success">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <rect x="1" y="3" width="12" height="9" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                  <path d="M1 6.5h12" stroke="currentColor" stroke-width="1.2"/>
                  <circle cx="4" cy="10" r="1" fill="currentColor"/>
                </svg>
              </span>
              <div>
                <p class="adm-quick-link__label">Billing & Packages</p>
                <p class="adm-quick-link__sub">{{ MOCK_PAYMENT_ALERTS.length }} alerts pending</p>
              </div>
            </a>
            <a href="/schedule" class="adm-quick-link">
              <span class="adm-quick-link__icon adm-quick-link__icon--primary">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <rect x="1" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                  <path d="M4 1v2M10 1v2M1 5.5h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </span>
              <div>
                <p class="adm-quick-link__label">Schedule</p>
                <p class="adm-quick-link__sub">{{ MOCK_TODAY_CLASSES.length }} classes today</p>
              </div>
            </a>
            <a href="/students" class="adm-quick-link">
              <span class="adm-quick-link__icon adm-quick-link__icon--warning">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M3 1h5l4 4v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                  <path d="M8 1v4h4M4 7h6M4 9.5h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </span>
              <div>
                <p class="adm-quick-link__label">Student Reports</p>
                <p class="adm-quick-link__sub">View all enrolled students</p>
              </div>
            </a>
            <a href="/payroll" class="adm-quick-link">
              <span class="adm-quick-link__icon adm-quick-link__icon--info">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.2"/>
                  <path d="M7 3v8M9 5H6a1 1 0 0 0 0 2h2a1 1 0 0 1 0 2H5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </span>
              <div>
                <p class="adm-quick-link__label">Payroll</p>
                <p class="adm-quick-link__sub">Teacher pay records</p>
              </div>
            </a>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore }     from '@/stores/auth'
import { useUsersStore }    from '@/stores/users'
import { useScheduleStore } from '@/stores/schedule'

const router = useRouter()
import StatsGrid from '@/components/dashboard/StatsGrid.vue'
import type { StatItem } from '@/components/dashboard/StatsGrid.vue'

const auth     = useAuthStore()
const store    = useUsersStore()
const schedule = useScheduleStore()

onMounted(async () => {
  if (!store.users.length) await store.fetchUsers()
})

const firstName = computed(() => auth.user?.firstName ?? 'there')
const activeStudentCount = computed(() => store.users.filter(u => u.role === 'STUDENT' && u.isActive).length)
const activeTeacherCount = computed(() => store.users.filter(u => u.role === 'TEACHER' && u.isActive).length)

const icons = {
  // Students — group of people
  users:    `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="7" cy="6" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M1 16c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="14" cy="6" r="2" stroke="currentColor" stroke-width="1.4"/><path d="M14 12c1.657 0 3 1.343 3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  // Teachers — graduation cap
  teachers: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 3L1 7l8 4 8-4-8-4z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M5 9v4c0 1.657 1.79 3 4 3s4-1.343 4-3V9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M15 7v5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  // Today's classes — calendar with play dot
  calendar: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="3" width="16" height="13" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 1.5v3M13 1.5v3M1 7.5h16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="9" cy="12" r="2" stroke="currentColor" stroke-width="1.2"/></svg>`,
  // No-shows — person with X
  missed:   `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="7" cy="6" r="3" stroke="currentColor" stroke-width="1.4"/><path d="M1 16c0-3.314 2.686-5 6-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M12 11l4 4M16 11l-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
}

const todayStr = computed(() => new Date().toLocaleDateString('sv-SE'))

const todayAllLessons = computed(() =>
  schedule.lessons.filter(l => new Date(l.startTime).toLocaleDateString('sv-SE') === todayStr.value),
)

const MOCK_TODAY_CLASSES = computed(() =>
  todayAllLessons.value
    .filter(l => ['SCHEDULED', 'IN_PROGRESS', 'TRIAL', 'COMPLETED'].includes(l.status))
    .sort((a, b) => new Date(a.startTime).getTime() - new Date(b.startTime).getTime())
    .map(l => {
      const start = new Date(l.startTime)
      const end   = new Date(l.endTime)
      const now   = new Date()
      const isLive = l.status === 'IN_PROGRESS' || (now >= start && now <= end)
      const statusLabel = isLive ? 'Live' : l.status === 'COMPLETED' ? 'Completed' : 'Upcoming'
      const status      = isLive ? 'live'  : l.status === 'COMPLETED' ? 'completed' : 'scheduled'
      return {
        id: l.id, subject: l.subject, teacher: l.teacherName, student: l.studentName,
        time: start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
        duration: `${Math.round((end.getTime() - start.getTime()) / 60000)} min`,
        status, statusLabel,
      }
    }),
)

const MOCK_MISSED = computed(() =>
  todayAllLessons.value
    .filter(l => ['MISSED_BY_STUDENT', 'MISSED_BY_TEACHER'].includes(l.status))
    .map(l => ({
      id: l.id, subject: l.subject, teacher: l.teacherName, student: l.studentName,
      time: new Date(l.startTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
      missedBy: l.status === 'MISSED_BY_STUDENT' ? 'Student' : 'Teacher',
    })),
)

const MOCK_PENDING_NOTES = [
  { id: 'n1', teacher: 'James Reyes',  teacherInitials: 'JR', lessonCount: 3, lastLesson: 'May 15', urgency: 'high',   urgencyLabel: 'Overdue'  },
  { id: 'n2', teacher: 'Maria Garcia', teacherInitials: 'MG', lessonCount: 1, lastLesson: 'May 19', urgency: 'medium', urgencyLabel: 'Due Soon' },
  { id: 'n3', teacher: 'James Walker', teacherInitials: 'JW', lessonCount: 2, lastLesson: 'May 17', urgency: 'low',    urgencyLabel: 'Pending'  },
]

const MOCK_PAYMENT_ALERTS = [
  { id: 'p1', text: 'Emma Santos — Package expiring',   sub: 'Standard Plan expires Jun 1, 2026',  type: 'warning' },
  { id: 'p2', text: 'Marco Reyes — Low lesson balance', sub: '1 lesson remaining in current plan', type: 'danger'  },
  { id: 'p3', text: 'David Cruz — Renewal pending',     sub: 'Executive Plan due for renewal',     type: 'info'    },
]

const MOCK_ANNOUNCEMENTS = [
  { id: 'a1', text: 'Q2 enrollment review meeting — May 28', date: 'May 20', type: 'info'    },
  { id: 'a2', text: 'Platform maintenance May 25, 2–4 AM',   date: 'May 19', type: 'warning' },
  { id: 'a3', text: 'New teacher onboarding process updated', date: 'May 18', type: 'success' },
  { id: 'a4', text: 'End-of-month payroll cutoff on May 31', date: 'May 17', type: 'info'    },
]

const MOCK_ENROLLMENT_PROGRAMS = [
  { name: 'Business English',    active: 6, total: 8 },
  { name: 'General English',     active: 5, total: 7 },
  { name: 'IELTS Preparation',   active: 4, total: 6 },
  { name: 'Executive English',   active: 2, total: 3 },
  { name: 'Conversational Eng.', active: 1, total: 2 },
]

const enrollmentBreakdown = computed(() => {
  const maxActive = Math.max(...MOCK_ENROLLMENT_PROGRAMS.map(p => p.active), 1)
  return MOCK_ENROLLMENT_PROGRAMS.map(p => ({
    ...p,
    pct: Math.round((p.active / maxActive) * 100),
  }))
})

const liveCount = computed(() => MOCK_TODAY_CLASSES.value.filter(c => c.status === 'live').length)

const stats = computed((): StatItem[] => [
  { label: 'Active Students', value: String(activeStudentCount.value),          sub: 'Currently enrolled',                          trendUp: true,  icon: icons.users,    iconClass: 'icon-badge--teal'    },
  { label: 'Active Teachers', value: String(activeTeacherCount.value),          sub: 'Available this week',                         trendUp: false, icon: icons.teachers, iconClass: 'icon-badge--indigo'  },
  { label: "Today's Classes", value: String(MOCK_TODAY_CLASSES.value.length),   sub: liveCount.value ? `${liveCount.value} live now` : 'Scheduled', trendUp: false, icon: icons.calendar, iconClass: 'icon-badge--success' },
  { label: 'No-shows Today',  value: String(MOCK_MISSED.value.length),          sub: 'Requires follow-up',                          trendUp: false, icon: icons.missed,   iconClass: 'icon-badge--danger'  },
])
</script>

<style scoped>
.adm-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

.adm-welcome__title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
}
.adm-welcome__sub {
  margin-top: var(--tv-space-1);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-secondary);
}

.adm-content {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--tv-space-4);
  align-items: start;
}
.adm-main    { grid-column: 1 / 4; display: flex; flex-direction: column; gap: var(--tv-space-4); }
.adm-sidebar { grid-column: 4;     display: flex; flex-direction: column; gap: var(--tv-space-4); }

.adm-panel {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
  overflow: hidden;
}
.adm-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--tv-space-4) var(--tv-space-5);
}
.adm-panel__title { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.adm-panel__link  { font-size: var(--tv-text-sm); color: var(--tv-primary); font-weight: var(--tv-font-medium); text-decoration: none; }
.adm-panel__link:hover { text-decoration: underline; }
.adm-panel__badge {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}
.adm-panel__badge--danger { background: var(--tv-danger-soft); color: var(--tv-danger-fg); }

.adm-schedule-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  padding: var(--tv-space-2) var(--tv-space-4) var(--tv-space-4);
  gap: var(--tv-space-2);
}
.adm-schedule-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-4);
  padding: var(--tv-space-3);
  border-radius: var(--tv-radius-md);
  border: 1px solid transparent;
  transition: background-color var(--tv-transition-fast);
}
.adm-schedule-item:hover          { background: var(--tv-bg-soft); border-color: var(--tv-border); }
.adm-schedule-item--live          { background: var(--tv-success-soft); border-color: var(--tv-success-fg); }
.adm-schedule-item--live:hover    { background: var(--tv-success-soft); }
.adm-schedule-item--missed        { background: var(--tv-danger-soft); border-color: var(--tv-danger-fg); }
.adm-schedule-item--missed:hover  { background: var(--tv-danger-soft); }

.adm-schedule-item__time { width: 68px; flex-shrink: 0; display: flex; flex-direction: column; gap: 2px; }
.adm-schedule-item__hour { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }
.adm-schedule-item__dur  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.adm-schedule-item__body { flex: 1; min-width: 0; }
.adm-schedule-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.adm-schedule-item__meta  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.adm-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  padding: var(--tv-space-2) var(--tv-space-4) var(--tv-space-4);
  gap: 0;
}
.adm-list-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-3) 0;
  border-bottom: 1px solid var(--tv-border);
}
.adm-list-item:last-child { border-bottom: none; }

.adm-list-item__avatar {
  width: 32px; height: 32px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.adm-list-item__icon {
  width: 30px; height: 30px;
  border-radius: var(--tv-radius-sm);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.adm-list-item__icon--warn { background: var(--tv-danger-soft); color: var(--tv-danger-fg); }

.adm-list-item__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.adm-list-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; }
.adm-list-item__meta  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.adm-chip {
  flex-shrink: 0;
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}
.adm-chip--live      { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.adm-chip--completed { background: var(--tv-bg-soft);      color: var(--tv-text-muted); }
.adm-chip--scheduled { background: var(--tv-primary-soft); color: var(--tv-primary); }
.adm-chip--missed    { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.adm-chip--high      { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.adm-chip--medium    { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.adm-chip--low       { background: var(--tv-bg-soft);      color: var(--tv-text-muted); }

.adm-enrollment-grid {
  padding: var(--tv-space-4) var(--tv-space-5);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
}
.adm-enroll-row { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.adm-enroll-row__info  { display: flex; justify-content: space-between; align-items: center; }
.adm-enroll-row__name  { font-size: var(--tv-text-sm); color: var(--tv-text); font-weight: var(--tv-font-medium); }
.adm-enroll-row__count { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.adm-prog-bar { height: 6px; background: var(--tv-bg-soft); border-radius: var(--tv-radius-full); overflow: hidden; }
.adm-prog-bar__fill { height: 100%; background: var(--tv-primary); border-radius: var(--tv-radius-full); transition: width 0.4s ease; }

.adm-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
  box-shadow: var(--tv-shadow-sm);
}
.adm-card--alert { border-color: var(--tv-warning-fg); }

.adm-card__title {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0;
}

.adm-alert-list { display: flex; flex-direction: column; gap: var(--tv-space-2); }
.adm-alert-item { display: flex; align-items: flex-start; gap: var(--tv-space-2); }
.adm-alert-dot  { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.adm-alert-dot--warning { background: var(--tv-warning); }
.adm-alert-dot--danger  { background: var(--tv-danger); }
.adm-alert-dot--info    { background: var(--tv-info); }
.adm-alert-text { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); margin: 0; }
.adm-alert-meta { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.adm-secondary-link { font-size: var(--tv-text-xs); color: var(--tv-primary); text-decoration: none; font-weight: var(--tv-font-medium); }
.adm-secondary-link:hover { text-decoration: underline; }

.adm-remind-list { list-style: none; display: flex; flex-direction: column; gap: 0; }
.adm-remind-item { display: flex; align-items: flex-start; gap: var(--tv-space-2); padding: var(--tv-space-2) 0; border-bottom: 1px solid var(--tv-bg-soft); }
.adm-remind-item:last-child { border-bottom: none; }
.adm-remind-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.adm-remind-dot--info    { background: var(--tv-info); }
.adm-remind-dot--warning { background: var(--tv-warning); }
.adm-remind-dot--success { background: var(--tv-success); }
.adm-remind-text { font-size: var(--tv-text-sm); color: var(--tv-text); margin: 0; line-height: 1.4; }
.adm-remind-time { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.adm-quick-links { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.adm-quick-link {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-2);
  border-radius: var(--tv-radius-sm);
  text-decoration: none;
  transition: background-color var(--tv-transition-fast);
}
.adm-quick-link:hover { background: var(--tv-bg-soft); }

.adm-quick-link__icon {
  width: 28px; height: 28px;
  border-radius: var(--tv-radius-sm);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.adm-quick-link__icon--teal    { background: var(--tv-teal-soft);    color: var(--tv-teal-fg); }
.adm-quick-link__icon--success { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.adm-quick-link__icon--primary { background: var(--tv-primary-soft); color: var(--tv-primary); }
.adm-quick-link__icon--warning { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.adm-quick-link__icon--info    { background: var(--tv-info-soft);    color: var(--tv-info-fg); }

.adm-quick-link__label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); margin: 0; }
.adm-quick-link__sub   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

@media (max-width: 1280px) {
  .adm-content { grid-template-columns: 3fr 1fr; }
  .adm-main    { grid-column: 1; }
  .adm-sidebar { grid-column: 2; }
}
@media (max-width: 1100px) {
  .adm-content { grid-template-columns: 1fr; }
  .adm-main    { grid-column: 1; }
  .adm-sidebar { grid-column: 1; display: grid; grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 767px) {
  .adm-page { padding: var(--tv-space-4); }
  .adm-sidebar { grid-template-columns: 1fr; }
  .adm-welcome__title { font-size: var(--tv-text-xl); }
}
</style>
