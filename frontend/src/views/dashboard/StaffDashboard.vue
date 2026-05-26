<template>
  <div class="sfd-page">

    <!-- Welcome -->
    <section class="sfd-welcome">
      <div>
        <h1 class="sfd-welcome__title">Welcome back, {{ firstName }}!</h1>
        <p class="sfd-welcome__sub">
          {{ staffProfile?.department ?? 'Staff' }} dashboard
          <template v-if="staffProfile?.accessLimitations"> &mdash; {{ staffProfile.accessLimitations }}</template>
        </p>
      </div>
      <div class="sfd-welcome__badge">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <rect x="1" y="4" width="12" height="9" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
          <path d="M5 4V3a2 2 0 0 1 4 0v1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
          <circle cx="7" cy="8.5" r="1" fill="currentColor"/>
        </svg>
        {{ staffProfile?.department ?? 'Staff' }}
      </div>
    </section>

    <!-- Permission-gated stats -->
    <StatsGrid :stats="stats" />

    <!-- Content -->
    <div class="sfd-content">

      <!-- Main -->
      <div class="sfd-main">

        <!-- Schedule (if MANAGE_SCHEDULE) -->
        <section v-if="can('MANAGE_SCHEDULE')" class="sfd-panel">
          <div class="sfd-panel__header">
            <h2 class="sfd-panel__title">Today's Schedule</h2>
            <a href="/schedule" class="sfd-panel__link">View full schedule</a>
          </div>
          <ul class="sfd-schedule-list">
            <li
              v-for="cls in MOCK_SCHEDULE"
              :key="cls.id"
              :class="['sfd-schedule-item', { 'sfd-schedule-item--live': cls.status === 'live' }]"
            >
              <div class="sfd-schedule-item__time">
                <span class="sfd-schedule-item__hour">{{ cls.time }}</span>
                <span class="sfd-schedule-item__dur">{{ cls.duration }}</span>
              </div>
              <div class="sfd-schedule-item__body">
                <p class="sfd-schedule-item__title">{{ cls.subject }}</p>
                <p class="sfd-schedule-item__meta">{{ cls.teacher }} &rarr; {{ cls.student }}</p>
              </div>
              <span class="sfd-chip" :class="`sfd-chip--${cls.status}`">{{ cls.statusLabel }}</span>
            </li>
          </ul>
        </section>

        <!-- Assigned Tasks -->
        <section class="sfd-panel">
          <div class="sfd-panel__header">
            <h2 class="sfd-panel__title">Assigned Tasks</h2>
            <span class="sfd-panel__badge">{{ pendingTasks }} pending</span>
          </div>
          <ul class="sfd-list">
            <li v-for="task in MOCK_TASKS" :key="task.id" class="sfd-list-item">
              <div class="sfd-list-item__icon" :class="`sfd-list-item__icon--${task.color}`">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M3 1h5l4 4v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
                  <path d="M8 1v4h4M4 7h6M4 9.5h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </div>
              <div class="sfd-list-item__body">
                <p class="sfd-list-item__title">{{ task.title }}</p>
                <p class="sfd-list-item__meta">{{ task.description }} · Due {{ task.due }}</p>
              </div>
              <span class="sfd-chip" :class="`sfd-chip--${task.status}`">{{ task.statusLabel }}</span>
            </li>
          </ul>
        </section>

        <!-- Students (if EDIT_STUDENTS) -->
        <section v-if="can('EDIT_STUDENTS')" class="sfd-panel">
          <div class="sfd-panel__header">
            <h2 class="sfd-panel__title">Students Needing Attention</h2>
            <a href="/students" class="sfd-panel__link">View all</a>
          </div>
          <ul class="sfd-list">
            <li v-for="s in MOCK_STUDENTS_ATTENTION" :key="s.id" class="sfd-list-item">
              <div class="sfd-list-item__avatar">{{ s.initials }}</div>
              <div class="sfd-list-item__body">
                <p class="sfd-list-item__title">{{ s.name }}</p>
                <p class="sfd-list-item__meta">{{ s.program }} · {{ s.reason }}</p>
              </div>
              <span class="sfd-chip" :class="`sfd-chip--${s.priority}`">{{ s.priorityLabel }}</span>
            </li>
          </ul>
        </section>

        <!-- Billing (if VIEW_BILLING) -->
        <section v-if="can('VIEW_BILLING')" class="sfd-panel">
          <div class="sfd-panel__header">
            <h2 class="sfd-panel__title">Billing Summary</h2>
            <a href="/billing" class="sfd-panel__link">View billing</a>
          </div>
          <ul class="sfd-list">
            <li v-for="b in MOCK_BILLING_ITEMS" :key="b.id" class="sfd-list-item">
              <div class="sfd-list-item__icon sfd-list-item__icon--success">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <rect x="1" y="3" width="12" height="9" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                  <path d="M1 6.5h12" stroke="currentColor" stroke-width="1.2"/>
                  <circle cx="4" cy="10" r="1" fill="currentColor"/>
                </svg>
              </div>
              <div class="sfd-list-item__body">
                <p class="sfd-list-item__title">{{ b.student }}</p>
                <p class="sfd-list-item__meta">{{ b.package }} · {{ b.status }}</p>
              </div>
              <span class="sfd-chip" :class="`sfd-chip--${b.chipType}`">{{ b.chipLabel }}</span>
            </li>
          </ul>
        </section>

      </div>

      <!-- Sidebar -->
      <div class="sfd-sidebar">

        <!-- Module Access -->
        <div class="sfd-card">
          <h3 class="sfd-card__title">Your Access</h3>
          <div class="sfd-access-list">
            <div
              v-for="mod in ALL_MODULES"
              :key="mod.key"
              :class="['sfd-access-item', { 'sfd-access-item--granted': can(mod.key), 'sfd-access-item--denied': !can(mod.key) }]"
            >
              <span class="sfd-access-item__icon" aria-hidden="true">
                <svg v-if="can(mod.key)" width="12" height="12" viewBox="0 0 12 12" fill="none">
                  <circle cx="6" cy="6" r="5.5" stroke="currentColor" stroke-width="1.1"/>
                  <path d="M3.5 6l2 2 3-3" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg v-else width="12" height="12" viewBox="0 0 12 12" fill="none">
                  <circle cx="6" cy="6" r="5.5" stroke="currentColor" stroke-width="1.1"/>
                  <path d="M4 4l4 4M8 4l-4 4" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/>
                </svg>
              </span>
              <span class="sfd-access-item__label">{{ mod.label }}</span>
            </div>
          </div>
        </div>

        <!-- Operational Notices -->
        <div class="sfd-card">
          <h3 class="sfd-card__title">Operational Notices</h3>
          <ul class="sfd-remind-list">
            <li v-for="n in MOCK_NOTICES" :key="n.id" class="sfd-remind-item">
              <span class="sfd-remind-dot" :class="`sfd-remind-dot--${n.type}`" />
              <div>
                <p class="sfd-remind-text">{{ n.text }}</p>
                <p class="sfd-remind-time">{{ n.date }}</p>
              </div>
            </li>
          </ul>
        </div>

        <!-- Quick Actions -->
        <div class="sfd-card">
          <h3 class="sfd-card__title">Quick Actions</h3>
          <div class="sfd-actions">
            <a v-if="can('EDIT_STUDENTS')"    href="/students"    class="sfd-action-btn">View Students</a>
            <a v-if="can('MANAGE_SCHEDULE')"  href="/schedule"    class="sfd-action-btn">View Schedule</a>
            <a v-if="can('VIEW_BILLING')"     href="/billing"     class="sfd-action-btn">Billing Overview</a>
            <a v-if="can('VIEW_PAYROLL')"     href="/payroll"     class="sfd-action-btn">Payroll Records</a>
            <a                                href="/profile"     class="sfd-action-btn">My Profile</a>
          </div>
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
import type { StatItem } from '@/components/dashboard/StatsGrid.vue'
import type { StaffPermission } from '@/types'

const auth = useAuthStore()
const store = useUsersStore()

onMounted(async () => {
  if (!store.users.length) await store.fetchUsers()
})

const firstName = computed(() => auth.user?.firstName ?? 'there')

const staffRecord = computed(() =>
  auth.user ? store.getUserByEmail(auth.user.email) : undefined,
)
const staffProfile = computed(() => staffRecord.value?.adminStaffProfile)
const permissions = computed((): StaffPermission[] => staffProfile.value?.permissions ?? [])

function can(permission: StaffPermission): boolean {
  return permissions.value.includes(permission)
}

const ALL_MODULES: { key: StaffPermission; label: string }[] = [
  { key: 'EDIT_STUDENTS',       label: 'Edit Students'     },
  { key: 'MANAGE_SCHEDULE',     label: 'Manage Schedule'   },
  { key: 'SEND_COMMUNICATIONS', label: 'Send Communications' },
  { key: 'VIEW_BILLING',        label: 'View Billing'      },
  { key: 'VIEW_PAYROLL',        label: 'View Payroll'      },
]

const icons = {
  task:     `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 2h7l4 4v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M11 2v4h4M6 9h6M6 12h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  students: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="7" cy="6" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M1 16c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="14" cy="6" r="2" stroke="currentColor" stroke-width="1.4"/><path d="M14 12c1.657 0 3 1.343 3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  calendar: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="3" width="16" height="13" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 1.5v3M13 1.5v3M1 7.5h16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  lock:     `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="3" y="8" width="12" height="9" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M6 8V6a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="9" cy="13" r="1.5" fill="currentColor"/></svg>`,
}

const MOCK_TASKS = [
  { id: 't1', title: 'Update student enrollment records', description: 'Review and update Q2 enrollments', due: 'Today',  status: 'urgent',     statusLabel: 'Urgent',     color: 'warn' },
  { id: 't2', title: 'Send weekly schedule to teachers',  description: 'Distribute next week schedule',   due: 'May 22', status: 'pending',    statusLabel: 'Pending',    color: 'info' },
  { id: 't3', title: 'Coordinate trial class booking',    description: 'New student from inquiry form',   due: 'May 23', status: 'pending',    statusLabel: 'Pending',    color: 'info' },
  { id: 't4', title: 'Review attendance records',         description: 'May 1–15 attendance audit',       due: 'May 25', status: 'in-progress', statusLabel: 'In Progress', color: 'teal' },
  { id: 't5', title: 'Onboard new student — Sofia Chen',  description: 'Profile setup and first class',   due: 'May 28', status: 'pending',    statusLabel: 'Pending',    color: 'info' },
]

const MOCK_SCHEDULE = [
  { id: 1, subject: 'Business English — Meetings',  teacher: 'James Reyes',  student: 'Emma Santos',      time: '10:00 AM', duration: '60 min', status: 'live',      statusLabel: 'Live'     },
  { id: 2, subject: 'General English — Reading',    teacher: 'Maria Garcia', student: 'Marco Reyes',      time: '12:00 PM', duration: '45 min', status: 'scheduled', statusLabel: 'Upcoming' },
  { id: 3, subject: 'IELTS Preparation — Writing',  teacher: 'James Walker', student: 'Carlos Dela Cruz', time: '2:00 PM',  duration: '60 min', status: 'scheduled', statusLabel: 'Upcoming' },
]

const MOCK_STUDENTS_ATTENTION = [
  { id: 's1', name: 'Marco Reyes',    initials: 'MR', program: 'General English',   reason: 'Package expiring soon',    priority: 'medium', priorityLabel: 'Medium' },
  { id: 's2', name: 'Yuki Tanaka',    initials: 'YT', program: 'General English',   reason: 'No attendance in 2 weeks', priority: 'high',   priorityLabel: 'High'   },
  { id: 's3', name: 'Emma Santos',    initials: 'ES', program: 'Business English',  reason: 'Requested schedule change', priority: 'low',   priorityLabel: 'Low'    },
]

const MOCK_BILLING_ITEMS = [
  { id: 'b1', student: 'Emma Santos',    package: 'Standard Plan',  status: 'Expires Jun 1, 2026',     chipType: 'warning', chipLabel: 'Expiring' },
  { id: 'b2', student: 'Marco Reyes',    package: 'Basic Plan',     status: '1 lesson remaining',       chipType: 'danger',  chipLabel: 'Low'      },
  { id: 'b3', student: 'David Cruz',     package: 'Executive Plan', status: 'Active — renews Jul 2026', chipType: 'success', chipLabel: 'Active'   },
]

const MOCK_NOTICES = [
  { id: 'n1', text: 'Monthly staff check-in — May 28, 2:00 PM', date: 'May 20', type: 'info'    },
  { id: 'n2', text: 'Platform maintenance May 25, 2–4 AM',      date: 'May 19', type: 'warning' },
  { id: 'n3', text: 'New student communication templates added', date: 'May 18', type: 'success' },
]

const pendingTasks = computed(() => MOCK_TASKS.filter(t => t.status !== 'done').length)

const activeStudentCount = computed(() => store.users.filter(u => u.role === 'STUDENT' && u.isActive).length)

const stats = computed((): StatItem[] => {
  const items: StatItem[] = [
    { label: 'Assigned Tasks',    value: String(MOCK_TASKS.length),  sub: `${pendingTasks.value} pending`,  trendUp: false, icon: icons.task,     iconClass: 'icon-badge--teal'   },
  ]
  if (can('EDIT_STUDENTS') || can('MANAGE_SCHEDULE')) {
    items.push({ label: 'Active Students', value: String(activeStudentCount.value), sub: 'Currently enrolled', trendUp: true, icon: icons.students, iconClass: 'icon-badge--primary' })
  }
  if (can('MANAGE_SCHEDULE')) {
    items.push({ label: "Today's Classes", value: String(MOCK_SCHEDULE.length), sub: '1 live right now', trendUp: false, icon: icons.calendar, iconClass: 'icon-badge--success' })
  }
  items.push({ label: 'Access Level', value: String(permissions.value.length), sub: 'of 5 permissions', trendUp: false, icon: icons.lock, iconClass: 'icon-badge--warning' })
  return items.slice(0, 4)
})
</script>

<style scoped>
.sfd-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

.sfd-welcome {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--tv-space-4);
}
.sfd-welcome__title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
}
.sfd-welcome__sub {
  margin-top: var(--tv-space-1);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-secondary);
}
.sfd-welcome__badge {
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
  padding: var(--tv-space-1) var(--tv-space-3);
  background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-full);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-secondary);
  white-space: nowrap;
  flex-shrink: 0;
}

.sfd-content {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--tv-space-4);
  align-items: start;
}
.sfd-main    { grid-column: 1 / 4; display: flex; flex-direction: column; gap: var(--tv-space-4); }
.sfd-sidebar { grid-column: 4;     display: flex; flex-direction: column; gap: var(--tv-space-4); }

.sfd-panel {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
  overflow: hidden;
}
.sfd-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--tv-space-4) var(--tv-space-5);
}
.sfd-panel__title { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.sfd-panel__link  { font-size: var(--tv-text-sm); color: var(--tv-primary); font-weight: var(--tv-font-medium); text-decoration: none; }
.sfd-panel__link:hover { text-decoration: underline; }
.sfd-panel__badge {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}

.sfd-schedule-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  padding: var(--tv-space-2) var(--tv-space-4) var(--tv-space-4);
  gap: var(--tv-space-2);
}
.sfd-schedule-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-4);
  padding: var(--tv-space-3);
  border-radius: var(--tv-radius-md);
  border: 1px solid transparent;
  transition: background-color var(--tv-transition-fast);
}
.sfd-schedule-item:hover      { background: var(--tv-bg-soft); border-color: var(--tv-border); }
.sfd-schedule-item--live      { background: var(--tv-success-soft); border-color: var(--tv-success-fg); }
.sfd-schedule-item--live:hover { background: var(--tv-success-soft); }

.sfd-schedule-item__time { width: 68px; flex-shrink: 0; display: flex; flex-direction: column; gap: 2px; }
.sfd-schedule-item__hour { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }
.sfd-schedule-item__dur  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.sfd-schedule-item__body { flex: 1; min-width: 0; }
.sfd-schedule-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sfd-schedule-item__meta  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.sfd-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  padding: var(--tv-space-2) var(--tv-space-4) var(--tv-space-4);
  gap: 0;
}
.sfd-list-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-3) 0;
  border-bottom: 1px solid var(--tv-border);
}
.sfd-list-item:last-child { border-bottom: none; }

.sfd-list-item__avatar {
  width: 32px; height: 32px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.sfd-list-item__icon {
  width: 30px; height: 30px;
  border-radius: var(--tv-radius-sm);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.sfd-list-item__icon--warn    { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.sfd-list-item__icon--info    { background: var(--tv-primary-soft); color: var(--tv-primary); }
.sfd-list-item__icon--teal    { background: var(--tv-teal-soft);    color: var(--tv-teal-fg); }
.sfd-list-item__icon--success { background: var(--tv-success-soft); color: var(--tv-success-fg); }

.sfd-list-item__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.sfd-list-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; }
.sfd-list-item__meta  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.sfd-chip {
  flex-shrink: 0;
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}
.sfd-chip--urgent      { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.sfd-chip--in-progress { background: var(--tv-primary-soft); color: var(--tv-primary); }
.sfd-chip--pending     { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.sfd-chip--done        { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sfd-chip--high        { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.sfd-chip--medium      { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.sfd-chip--low         { background: var(--tv-bg-soft);      color: var(--tv-text-muted); }
.sfd-chip--warning     { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.sfd-chip--danger      { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.sfd-chip--success     { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sfd-chip--live        { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sfd-chip--scheduled   { background: var(--tv-primary-soft); color: var(--tv-primary); }

.sfd-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
  box-shadow: var(--tv-shadow-sm);
}
.sfd-card__title {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0;
}

.sfd-access-list { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.sfd-access-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  padding: var(--tv-space-1) 0;
}
.sfd-access-item__icon { display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sfd-access-item__label { font-size: var(--tv-text-sm); }
.sfd-access-item--granted .sfd-access-item__icon { color: var(--tv-success-fg); }
.sfd-access-item--granted .sfd-access-item__label { color: var(--tv-text); font-weight: var(--tv-font-medium); }
.sfd-access-item--denied  .sfd-access-item__icon { color: var(--tv-text-muted); }
.sfd-access-item--denied  .sfd-access-item__label { color: var(--tv-text-muted); text-decoration: line-through; }

.sfd-remind-list { list-style: none; display: flex; flex-direction: column; gap: 0; }
.sfd-remind-item { display: flex; align-items: flex-start; gap: var(--tv-space-2); padding: var(--tv-space-2) 0; border-bottom: 1px solid var(--tv-bg-soft); }
.sfd-remind-item:last-child { border-bottom: none; }
.sfd-remind-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }
.sfd-remind-dot--info    { background: var(--tv-info); }
.sfd-remind-dot--warning { background: var(--tv-warning); }
.sfd-remind-dot--success { background: var(--tv-success); }
.sfd-remind-text { font-size: var(--tv-text-sm); color: var(--tv-text); margin: 0; line-height: 1.4; }
.sfd-remind-time { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.sfd-actions { display: flex; flex-direction: column; gap: var(--tv-space-2); }
.sfd-action-btn {
  display: block;
  padding: var(--tv-space-2) var(--tv-space-3);
  background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-sm);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-secondary);
  text-decoration: none;
  transition: background-color var(--tv-transition-fast), color var(--tv-transition-fast);
  text-align: center;
}
.sfd-action-btn:hover { background: var(--tv-primary-soft); color: var(--tv-primary); border-color: var(--tv-primary-muted); }

@media (max-width: 1280px) {
  .sfd-content { grid-template-columns: 3fr 1fr; }
  .sfd-main    { grid-column: 1; }
  .sfd-sidebar { grid-column: 2; }
}
@media (max-width: 1100px) {
  .sfd-content { grid-template-columns: 1fr; }
  .sfd-main    { grid-column: 1; }
  .sfd-sidebar { grid-column: 1; display: grid; grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 767px) {
  .sfd-page { padding: var(--tv-space-4); }
  .sfd-welcome { flex-direction: column; align-items: flex-start; }
  .sfd-sidebar { grid-template-columns: 1fr; }
  .sfd-welcome__title { font-size: var(--tv-text-xl); }
}
</style>
