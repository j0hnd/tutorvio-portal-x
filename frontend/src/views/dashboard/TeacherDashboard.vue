<template>
  <div class="td-page">

    <!-- Welcome -->
    <section class="td-welcome">
      <div>
        <h1 class="td-welcome__title">Good day, {{ firstName }}!</h1>
        <p class="td-welcome__sub">
          <template v-if="todayClasses.length">
            You have <strong>{{ todayClasses.length }} classes</strong> scheduled today.
            <template v-if="liveClass"> &mdash; <strong>{{ liveClass.subject }}</strong> is live now.</template>
          </template>
          <template v-else>No classes scheduled for today. Enjoy your day off!</template>
        </p>
      </div>
    </section>

    <!-- Stats -->
    <StatsGrid :stats="stats" />

    <!-- Content -->
    <div class="td-content">

      <!-- Main (cols 1–3) -->
      <div class="td-main">

        <!-- Today's Schedule -->
        <section class="td-panel">
          <div class="td-panel__header">
            <h2 class="td-panel__title">Today's Schedule</h2>
            <a href="/schedule" class="td-panel__link">View full schedule</a>
          </div>
          <ul class="td-schedule-list">
            <li
              v-for="cls in todayClasses"
              :key="cls.id"
              :class="['td-schedule-item', { 'td-schedule-item--live': cls.live, 'td-schedule-item--soon': cls.soon }]"
            >
              <div class="td-schedule-item__time">
                <span class="td-schedule-item__hour">{{ cls.time }}</span>
                <span class="td-schedule-item__dur">{{ cls.duration }}</span>
              </div>
              <div class="td-schedule-item__body">
                <p class="td-schedule-item__subject">{{ cls.subject }}</p>
                <p class="td-schedule-item__student">Student: {{ cls.student }}</p>
              </div>
              <div class="td-schedule-item__actions">
                <span v-if="cls.live" class="td-live-badge">LIVE</span>
                <button v-if="cls.live || cls.soon" class="td-join-btn" type="button">
                  <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                    <rect x="1" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/>
                    <path d="M9 5.5l4-2v7l-4-2V5.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
                  </svg>
                  Join
                </button>
                <button class="td-more-btn" type="button" aria-label="View lesson" @click="router.push({ name: 'LessonDetail', params: { id: cls.id } })">
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

        <!-- Upcoming Classes -->
        <section class="td-panel">
          <div class="td-panel__header">
            <h2 class="td-panel__title">Upcoming Classes</h2>
            <a href="/schedule" class="td-panel__link">Full schedule</a>
          </div>
          <ul class="td-list">
            <li v-for="cls in upcomingClasses" :key="cls.id" class="td-list-item">
              <div class="td-list-item__icon td-list-item__icon--cal">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <rect x="1" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                  <path d="M4 1v2M10 1v2M1 5.5h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </div>
              <div class="td-list-item__body">
                <p class="td-list-item__title">{{ cls.subject }}</p>
                <p class="td-list-item__meta">{{ cls.student }} · {{ cls.day }}, {{ cls.time }}</p>
              </div>
              <button class="td-profile-link" type="button" @click="router.push({ name: 'LessonDetail', params: { id: cls.id } })">View</button>
            </li>
            <li v-if="!upcomingClasses.length" class="td-list-item">
              <p class="td-list-item__meta" style="padding: 4px 0;">No upcoming classes in the next 7 days.</p>
            </li>
          </ul>
        </section>

        <!-- Students Needing Notes or Follow-up -->
        <section class="td-panel">
          <div class="td-panel__header">
            <h2 class="td-panel__title">Students Needing Notes / Follow-up</h2>
            <span class="td-panel__badge">{{ followupStudents.length }} pending</span>
          </div>
          <ul class="td-list">
            <li v-for="s in followupStudents" :key="s.id" class="td-list-item">
              <div class="td-list-item__avatar">{{ s.initials }}</div>
              <div class="td-list-item__body">
                <p class="td-list-item__title">{{ s.name }}</p>
                <p class="td-list-item__meta">Last lesson: {{ s.lastLesson }} · {{ s.reason }}</p>
              </div>
              <span class="td-chip" :class="`td-chip--${s.urgency}`">{{ s.urgencyLabel }}</span>
            </li>
            <li v-if="!followupStudents.length" class="td-list-item">
              <p class="td-list-item__meta" style="padding: 4px 0;">All notes are up to date!</p>
            </li>
          </ul>
        </section>

        <!-- Recent Lesson Submissions -->
        <section class="td-panel">
          <div class="td-panel__header">
            <h2 class="td-panel__title">Recent Lesson Submissions</h2>
          </div>
          <ul class="td-list">
            <li v-for="sub in recentSubmissions" :key="sub.id" class="td-list-item" style="cursor:pointer" @click="router.push({ name: 'LessonDetail', params: { id: sub.id } })">
              <div class="td-list-item__icon td-list-item__icon--doc">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M3 1h6l3 3v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                  <path d="M8 1v3h3M4 7h6M4 9.5h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </div>
              <div class="td-list-item__body">
                <p class="td-list-item__title">{{ sub.lesson }}</p>
                <p class="td-list-item__meta">{{ sub.student }} · {{ sub.date }}</p>
              </div>
              <span class="td-chip" :class="`td-chip--${sub.status}`">{{ sub.statusLabel }}</span>
            </li>
            <li v-if="!recentSubmissions.length" class="td-list-item">
              <p class="td-list-item__meta" style="padding: 4px 0;">No completed lessons yet.</p>
            </li>
          </ul>
        </section>

      </div>

      <!-- Sidebar -->
      <div class="td-sidebar">

        <!-- Pending Documentation -->
        <div class="td-card td-card--alert">
          <h3 class="td-card__title">Pending Documentation</h3>
          <p class="td-card__alert-msg">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M7 1.5l5.5 10H1.5L7 1.5z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
              <path d="M7 6v2.5M7 10.5v.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
            </svg>
            <strong>{{ pendingNoteLessons.length }} lessons</strong> need notes
          </p>
          <div class="td-doc-list">
            <div v-for="lesson in pendingNoteLessons.slice(0, 5)" :key="lesson.id" class="td-doc-item">
              <span class="td-doc-dot" />
              <div>
                <p class="td-doc-student">{{ lesson.studentName }}</p>
                <p class="td-doc-meta">{{ lesson.subject }} · {{ new Date(lesson.startTime).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</p>
              </div>
            </div>
            <p v-if="!pendingNoteLessons.length" class="td-doc-meta" style="padding: 4px 0;">No pending notes — all caught up!</p>
          </div>
          <button class="td-primary-btn" type="button" @click="router.push({ name: 'Lessons' })">View Lessons</button>
        </div>

        <!-- Admin Announcements -->
        <div class="td-card">
          <h3 class="td-card__title">Admin Announcements</h3>
          <ul class="td-remind-list">
            <li v-for="a in MOCK_ANNOUNCEMENTS" :key="a.id" class="td-remind-item">
              <span class="td-remind-dot" :class="`td-remind-dot--${a.type}`" />
              <div>
                <p class="td-remind-text">{{ a.text }}</p>
                <p class="td-remind-time">{{ a.date }}</p>
              </div>
            </li>
          </ul>
        </div>

        <!-- Quick Links -->
        <div class="td-card">
          <h3 class="td-card__title">Quick Links</h3>
          <div class="td-quick-links">
            <a href="/students" class="td-quick-link">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <circle cx="6" cy="4.5" r="2.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M1 13c0-2.761 2.239-5 5-5s5 2.239 5 5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                <path d="M11.5 6.5c1.105 0 2 .895 2 2v1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                <circle cx="11.5" cy="5" r="1.5" stroke="currentColor" stroke-width="1.2"/>
              </svg>
              My Students
            </a>
            <a href="/schedule" class="td-quick-link">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <rect x="1" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M4 1v2M10 1v2M1 5.5h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
              Full Schedule
            </a>
            <a href="/availability" class="td-quick-link">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.2"/>
                <path d="M7 3.5V7l2 2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
              My Availability
            </a>
            <a href="/payroll" class="td-quick-link">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <rect x="1" y="3" width="12" height="9" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M1 6.5h12" stroke="currentColor" stroke-width="1.2"/>
                <circle cx="4" cy="10" r="1" fill="currentColor"/>
              </svg>
              Payroll
            </a>
            <a href="/materials" class="td-quick-link">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <path d="M3 1h5l4 4v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                <path d="M8 1v4h4M4 7h6M4 9.5h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
              Materials
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

const icons = {
  users:    `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="7" cy="6" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M1 16c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="14" cy="6" r="2" stroke="currentColor" stroke-width="1.4"/><path d="M14 12c1.657 0 3 1.343 3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  calendar: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="3" width="16" height="13" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 1.5v3M13 1.5v3M1 7.5h16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  doc:      `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 2h7l4 4v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M11 2v4h4M6 9h6M6 12h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  alert:    `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2l7.5 13H1.5L9 2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M9 8v3M9 13.5v.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
}

const todayStr = computed(() => new Date().toLocaleDateString('sv-SE'))

const todayClasses = computed(() => {
  return schedule.myLessons
    .filter(l => {
      const dateStr = new Date(l.startTime).toLocaleDateString('sv-SE')
      return dateStr === todayStr.value && ['SCHEDULED', 'IN_PROGRESS', 'TRIAL'].includes(l.status)
    })
    .sort((a, b) => new Date(a.startTime).getTime() - new Date(b.startTime).getTime())
    .map(l => {
      const start = new Date(l.startTime)
      const end   = new Date(l.endTime)
      const now2  = new Date()
      const live  = l.status === 'IN_PROGRESS' || (now2 >= start && now2 <= end)
      const soon  = !live && (start.getTime() - now2.getTime()) <= 30 * 60_000 && start > now2
      const time  = start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
      const duration = `${Math.round((end.getTime() - start.getTime()) / 60000)} min`
      return { id: l.id, subject: l.subject, student: l.studentName, time, duration, live, soon, meetingUrl: l.meetingUrl }
    })
})

const liveClass = computed(() => todayClasses.value.find(c => c.live))

// ── Upcoming classes (next 7 days, excluding today, not yet started) ──
const upcomingClasses = computed(() => {
  const now = new Date()
  const cutoff = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000)
  return schedule.myLessons
    .filter(l => {
      const start = new Date(l.startTime)
      return start > now && start <= cutoff && ['SCHEDULED', 'TRIAL'].includes(l.status)
    })
    .sort((a, b) => new Date(a.startTime).getTime() - new Date(b.startTime).getTime())
    .slice(0, 5)
    .map(l => {
      const start = new Date(l.startTime)
      const isToday  = start.toLocaleDateString('sv-SE') === todayStr.value
      const isTomorrow = (() => {
        const tom = new Date(); tom.setDate(tom.getDate() + 1)
        return start.toLocaleDateString('sv-SE') === tom.toLocaleDateString('sv-SE')
      })()
      const dayLabel = isToday ? 'Today' : isTomorrow ? 'Tomorrow'
        : start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
      const timeLabel = start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
      return { id: l.id, subject: l.subject, student: l.studentName, studentId: l.studentId, day: dayLabel, time: timeLabel }
    })
})

// ── Students needing follow-up (pending notes, past completed lessons) ──
const followupStudents = computed(() => {
  const uid = auth.user?.id
  if (!uid) return []
  const seen = new Map<string, { id: string; name: string; initials: string; lastLesson: string; reason: string; urgency: string; urgencyLabel: string; daysAgo: number }>()
  for (const lesson of pendingNoteLessons.value) {
    if (seen.has(lesson.studentId)) continue
    const start = new Date(lesson.startTime)
    const daysAgo = Math.floor((Date.now() - start.getTime()) / 86_400_000)
    const urgency = daysAgo >= 7 ? 'high' : daysAgo >= 3 ? 'medium' : 'low'
    const urgencyLabel = daysAgo >= 7 ? 'Overdue' : daysAgo >= 3 ? 'Due Soon' : 'Pending'
    const nameParts = lesson.studentName.split(' ')
    const initials = nameParts.map((p: string) => p[0]).join('').toUpperCase().slice(0, 2)
    seen.set(lesson.studentId, {
      id: lesson.studentId,
      name: lesson.studentName,
      initials,
      lastLesson: start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
      reason: 'Note not filed',
      urgency,
      urgencyLabel,
      daysAgo,
    })
  }
  return [...seen.values()].sort((a, b) => b.daysAgo - a.daysAgo).slice(0, 5)
})

// ── Recent lesson submissions (completed lessons, most recent first) ──
const recentSubmissions = computed(() => {
  const uid = auth.user?.id
  if (!uid) return []
  return schedule.myLessons
    .filter(l => l.status === 'COMPLETED')
    .sort((a, b) => new Date(b.startTime).getTime() - new Date(a.startTime).getTime())
    .slice(0, 5)
    .map(l => {
      const note = schedule.getLessonNote(l.id)
      const hasNote = !!note
      const submitted = note?.status === 'submitted'
      return {
        id: l.id,
        lesson: l.subject,
        student: l.studentName,
        date: new Date(l.startTime).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
        status: submitted ? 'done' : hasNote ? 'pending' : 'missing',
        statusLabel: submitted ? 'Reviewed' : hasNote ? 'Draft' : 'No Note',
      }
    })
})

const MOCK_ANNOUNCEMENTS = [
  { id: 'a1', text: 'Monthly teacher meeting — May 28 at 3:00 PM',  date: 'May 20', type: 'info'    },
  { id: 'a2', text: 'Platform update scheduled May 25, 2–4 AM',     date: 'May 19', type: 'warning' },
  { id: 'a3', text: 'New lesson documentation format is now live',   date: 'May 18', type: 'success' },
]

const pendingNoteLessons = computed(() => {
  const uid = auth.user?.id
  if (!uid) return []
  return schedule.getPendingNoteLessons(uid)
})

const thisMonthLessons = computed(() => {
  const now = new Date()
  return schedule.myLessons.filter(l => {
    const d = new Date(l.startTime)
    return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth()
      && l.status === 'COMPLETED'
  }).length
})

const uniqueStudents = computed(() => new Set(schedule.myLessons.map(l => l.studentId)).size)

const stats = computed((): StatItem[] => [
  { label: 'Classes Today',       value: String(todayClasses.value.length), sub: liveClass.value ? '1 in progress' : 'Scheduled', trendUp: false, icon: icons.calendar, iconClass: 'icon-badge--teal'    },
  { label: 'Students Assigned',   value: String(uniqueStudents.value),      sub: 'Unique students',                               trendUp: true,  icon: icons.users,    iconClass: 'icon-badge--primary'  },
  { label: 'Lessons This Month',  value: String(thisMonthLessons.value),    sub: 'Completed',                                     trendUp: true,  icon: icons.doc,      iconClass: 'icon-badge--success'  },
  { label: 'Pending Notes',       value: String(pendingNoteLessons.value.length), sub: 'Needs documentation',                      trendUp: false, icon: icons.alert,    iconClass: 'icon-badge--warning'  },
])
</script>

<style scoped>
.td-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

/* Welcome */
.td-welcome__title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
}
.td-welcome__sub {
  margin-top: var(--tv-space-1);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-secondary);
}

/* Content grid */
.td-content {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--tv-space-4);
  align-items: start;
}
.td-main    { grid-column: 1 / 4; display: flex; flex-direction: column; gap: var(--tv-space-4); }
.td-sidebar { grid-column: 4;     display: flex; flex-direction: column; gap: var(--tv-space-4); }

/* Panel */
.td-panel {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
  overflow: hidden;
}
.td-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--tv-space-4) var(--tv-space-5);
}
.td-panel__title { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.td-panel__link  { font-size: var(--tv-text-sm); color: var(--tv-primary); font-weight: var(--tv-font-medium); text-decoration: none; }
.td-panel__link:hover { text-decoration: underline; }
.td-panel__badge {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}

/* Schedule list */
.td-schedule-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  padding: var(--tv-space-2) var(--tv-space-4) var(--tv-space-4);
  gap: var(--tv-space-2);
}
.td-schedule-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-4);
  padding: var(--tv-space-3) var(--tv-space-3);
  border-radius: var(--tv-radius-md);
  border: 1px solid transparent;
  transition: background-color var(--tv-transition-fast), border-color var(--tv-transition-fast);
}
.td-schedule-item:hover { background: var(--tv-bg-soft); border-color: var(--tv-border); }
.td-schedule-item--live { background: var(--tv-success-soft); border-color: var(--tv-success-fg); }
.td-schedule-item--live:hover { background: var(--tv-success-soft); }
.td-schedule-item--soon { background: var(--tv-primary-soft); border-color: var(--tv-primary-muted); }

.td-schedule-item__time {
  width: 68px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.td-schedule-item__hour { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }
.td-schedule-item__dur  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }

.td-schedule-item__body { flex: 1; min-width: 0; }
.td-schedule-item__subject { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.td-schedule-item__student { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.td-schedule-item__actions {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  flex-shrink: 0;
}

.td-live-badge {
  font-size: 10px;
  font-weight: var(--tv-font-bold);
  padding: 2px 6px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-success-soft);
  color: var(--tv-success-fg);
  border: 1px solid var(--tv-success-fg);
  letter-spacing: 0.04em;
}

.td-join-btn {
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
  transition: background-color var(--tv-transition-fast), transform var(--tv-transition-fast);
}
.td-join-btn:hover { background: var(--tv-primary-hover); }
.td-join-btn:active { transform: translateY(1px); }

.td-more-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: var(--tv-radius-sm);
  color: var(--tv-text-muted);
  cursor: pointer;
  border: none;
  background: transparent;
  transition: background-color var(--tv-transition-fast);
}
.td-more-btn:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

/* Generic list */
.td-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  padding: var(--tv-space-2) var(--tv-space-4) var(--tv-space-4);
  gap: 0;
}
.td-list-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-3) 0;
  border-bottom: 1px solid var(--tv-border);
}
.td-list-item:last-child { border-bottom: none; }

.td-list-item__avatar {
  width: 32px;
  height: 32px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.td-list-item__icon {
  width: 30px;
  height: 30px;
  border-radius: var(--tv-radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.td-list-item__icon--doc { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.td-list-item__icon--cal { background: var(--tv-primary-soft); color: var(--tv-primary); }

.td-list-item__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.td-list-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; }
.td-list-item__meta  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

/* Profile link */
.td-profile-link {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  color: var(--tv-primary);
  text-decoration: none;
  flex-shrink: 0;
}
.td-profile-link:hover { text-decoration: underline; }

/* Status chips */
.td-chip {
  flex-shrink: 0;
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}
.td-chip--high    { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.td-chip--medium  { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.td-chip--low     { background: var(--tv-bg-soft);      color: var(--tv-text-muted); }
.td-chip--done    { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.td-chip--pending { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.td-chip--missing { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }

/* Sidebar card */
.td-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
  box-shadow: var(--tv-shadow-sm);
}
.td-card--alert { border-color: var(--tv-warning-fg); }

.td-card__title {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0;
}

.td-card__alert-msg {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  font-size: var(--tv-text-sm);
  color: var(--tv-warning-fg);
  margin: 0;
}

/* Pending docs */
.td-doc-list { display: flex; flex-direction: column; gap: var(--tv-space-2); }
.td-doc-item { display: flex; align-items: flex-start; gap: var(--tv-space-2); }
.td-doc-dot  { width: 6px; height: 6px; border-radius: 50%; background: var(--tv-warning); flex-shrink: 0; margin-top: 5px; }
.td-doc-student { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); margin: 0; }
.td-doc-meta    { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.td-primary-btn {
  padding: var(--tv-space-2) var(--tv-space-3);
  background: var(--tv-primary);
  color: white;
  border: none;
  border-radius: var(--tv-radius);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  cursor: pointer;
  transition: background-color var(--tv-transition-fast);
}
.td-primary-btn:hover { background: var(--tv-primary-hover); }

/* Reminders */
.td-remind-list { list-style: none; display: flex; flex-direction: column; gap: 0; }
.td-remind-item { display: flex; align-items: flex-start; gap: var(--tv-space-2); padding: var(--tv-space-2) 0; border-bottom: 1px solid var(--tv-bg-soft); }
.td-remind-item:last-child { border-bottom: none; }
.td-remind-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.td-remind-dot--info    { background: var(--tv-info); }
.td-remind-dot--warning { background: var(--tv-warning); }
.td-remind-dot--success { background: var(--tv-success); }
.td-remind-text { font-size: var(--tv-text-sm); color: var(--tv-text); margin: 0; line-height: 1.4; }
.td-remind-time { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

/* Quick links */
.td-quick-links { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.td-quick-link {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  padding: var(--tv-space-2) var(--tv-space-2);
  border-radius: var(--tv-radius-sm);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-secondary);
  text-decoration: none;
  transition: background-color var(--tv-transition-fast), color var(--tv-transition-fast);
}
.td-quick-link:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

/* Responsive */
@media (max-width: 1280px) {
  .td-content { grid-template-columns: 3fr 1fr; }
  .td-main    { grid-column: 1; }
  .td-sidebar { grid-column: 2; }
}
@media (max-width: 1100px) {
  .td-content { grid-template-columns: 1fr; }
  .td-main    { grid-column: 1; }
  .td-sidebar { grid-column: 1; display: grid; grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 767px) {
  .td-page { padding: var(--tv-space-4); }
  .td-sidebar { grid-template-columns: 1fr; }
  .td-welcome__title { font-size: var(--tv-text-xl); }
}
</style>
