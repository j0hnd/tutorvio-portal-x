<template>
  <div v-if="student" class="sd-page">

    <!-- Back -->
    <button class="sd-back" type="button" @click="router.push('/students')">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
        <path d="M9 2L4 7l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Students
    </button>

    <!-- Header card -->
    <div class="sd-header-card">
      <div class="sd-header-card__avatar-wrap">
        <div class="sd-avatar">
          <img v-if="student.avatarUrl" :src="student.avatarUrl" :alt="fullName" />
          <span v-else>{{ initials }}</span>
        </div>
      </div>
      <div class="sd-header-card__info">
        <div class="sd-header-card__title-row">
          <h1 class="sd-name">{{ fullName }}</h1>
          <TVBadge :label="student.isActive ? 'Active' : 'Inactive'" :variant="student.isActive ? 'active' : 'neutral'" dot />
          <span v-if="student.studentProfile?.englishLevel" :class="['sd-level', `sd-level--${levelClass(student.studentProfile.englishLevel)}`]">
            {{ levelLabel(student.studentProfile.englishLevel) }}
          </span>
        </div>
        <p class="sd-email">{{ student.email }}</p>
        <div class="sd-header-card__meta-row">
          <span v-if="student.studentProfile?.program" class="sd-meta-chip">{{ student.studentProfile.program }}</span>
          <span v-if="assignedTeacher" class="sd-meta-chip sd-meta-chip--teacher">
            <svg width="11" height="11" viewBox="0 0 11 11" fill="none" aria-hidden="true">
              <circle cx="5.5" cy="4" r="2.5" stroke="currentColor" stroke-width="1.1"/>
              <path d="M1 10c0-2.485 2.015-4 4.5-4s4.5 1.515 4.5 4" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/>
            </svg>
            {{ assignedTeacher }}
          </span>
          <span v-if="student.studentProfile?.startDate" class="sd-meta-chip">
            Started {{ formatDate(student.studentProfile.startDate) }}
          </span>
        </div>
      </div>
      <div class="sd-header-card__actions">
        <TVButton variant="primary" size="sm" @click="router.push(`/admin/users/${student.id}/edit`)">
          Edit Profile
        </TVButton>
        <TVButton variant="ghost" size="sm" @click="router.push({ name: 'Lessons', query: { student: student.id } })">
          View Lessons
        </TVButton>
        <TVButton variant="ghost" size="sm" @click="router.push({ name: 'Progress', query: { student: student.id } })">
          View Progress
        </TVButton>
      </div>
    </div>

    <!-- Stats row -->
    <div class="sd-stats">
      <div class="sd-stat-card">
        <span class="sd-stat-card__value">{{ totalLessons }}</span>
        <span class="sd-stat-card__label">Total Lessons</span>
      </div>
      <div class="sd-stat-card">
        <span class="sd-stat-card__value">{{ completedLessons }}</span>
        <span class="sd-stat-card__label">Completed</span>
      </div>
      <div class="sd-stat-card">
        <span class="sd-stat-card__value">{{ upcomingLessons }}</span>
        <span class="sd-stat-card__label">Upcoming</span>
      </div>
      <div class="sd-stat-card sd-stat-card--warn">
        <span class="sd-stat-card__value">{{ missedLessons }}</span>
        <span class="sd-stat-card__label">Missed</span>
      </div>
    </div>

    <!-- Three-card row -->
    <div class="sd-cards3">

      <!-- Card 1: Profile -->
      <section class="sd-panel">
        <h2 class="sd-panel__title">Profile</h2>
        <div class="sd-info-list">
          <div class="sd-info-row">
            <span class="sd-info-label">English Level</span>
            <span class="sd-info-value">{{ levelLabel(student.studentProfile?.englishLevel ?? '') || '—' }}</span>
          </div>
          <div class="sd-info-row">
            <span class="sd-info-label">Class Type</span>
            <span class="sd-info-value">{{ classTypeLabel(student.studentProfile?.classType ?? '') || '—' }}</span>
          </div>
          <div class="sd-info-row">
            <span class="sd-info-label">Program</span>
            <span class="sd-info-value">{{ student.studentProfile?.program || '—' }}</span>
          </div>
          <div class="sd-info-row">
            <span class="sd-info-label">Start Date</span>
            <span class="sd-info-value">{{ student.studentProfile?.startDate ? formatDate(student.studentProfile.startDate) : '—' }}</span>
          </div>
          <div class="sd-info-row">
            <span class="sd-info-label">Assigned Teacher</span>
            <span class="sd-info-value">{{ assignedTeacher || 'Unassigned' }}</span>
          </div>
          <div class="sd-info-row">
            <span class="sd-info-label">Timezone</span>
            <span class="sd-info-value">{{ student.timezone || '—' }}</span>
          </div>
        </div>
      </section>

      <!-- Card 2: Goals, Learning Concerns & Notes -->
      <section class="sd-panel">
        <h2 class="sd-panel__title">Goals & Notes</h2>
        <div class="sd-info-list">
          <div class="sd-info-row sd-info-row--block">
            <span class="sd-info-label">Goals</span>
            <p class="sd-info-text">{{ student.studentProfile?.goals || '—' }}</p>
          </div>
          <div class="sd-info-row sd-info-row--block">
            <span class="sd-info-label">Learning Concerns</span>
            <p class="sd-info-text">{{ student.studentProfile?.learningConcerns || '—' }}</p>
          </div>
          <div class="sd-info-row sd-info-row--block">
            <span class="sd-info-label">Notes</span>
            <p class="sd-info-text">{{ student.studentProfile?.notes || '—' }}</p>
          </div>
        </div>
      </section>

      <!-- Card 3: Account -->
      <section class="sd-panel">
        <h2 class="sd-panel__title">Account</h2>
        <div class="sd-info-list">
          <div class="sd-info-row">
            <span class="sd-info-label">Email</span>
            <span class="sd-info-value">{{ student.email }}</span>
          </div>
          <div class="sd-info-row">
            <span class="sd-info-label">Status</span>
            <TVBadge :label="student.isActive ? 'Active' : 'Inactive'" :variant="student.isActive ? 'active' : 'neutral'" dot />
          </div>
          <div class="sd-info-row">
            <span class="sd-info-label">Joined</span>
            <span class="sd-info-value">{{ formatDate(student.createdAt) }}</span>
          </div>
          <div v-if="student.lastLoginAt" class="sd-info-row">
            <span class="sd-info-label">Last Login</span>
            <span class="sd-info-value">{{ formatDate(student.lastLoginAt) }}</span>
          </div>
        </div>
        <div v-if="isAdmin" class="sd-account-actions">
          <button
            class="sd-action-btn"
            :class="student.isActive ? 'sd-action-btn--danger' : 'sd-action-btn--success'"
            type="button"
            @click="toggleActive"
          >
            {{ student.isActive ? 'Deactivate account' : 'Activate account' }}
          </button>
        </div>
      </section>
    </div>

    <!-- Lessons -->
    <section class="sd-panel sd-panel--full">
      <div class="sd-panel__header">
        <h2 class="sd-panel__title">Lessons</h2>
        <div class="sd-panel__search">
          <TVInput v-model="lessonSearch" placeholder="Search lessons…" aria-label="Search lessons">
            <template #icon-start>
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                <circle cx="5.5" cy="5.5" r="3.5" stroke="currentColor" stroke-width="1.3"/>
                <path d="M9.5 9.5l2 2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
              </svg>
            </template>
          </TVInput>
        </div>
      </div>

      <TVDataTable
        :columns="lessonColumns"
        :rows="lessonRows"
        row-key="id"
        :page-size="10"
        :page-size-options="[10, 25, 50]"
        clickable
        aria-label="Student lessons"
        empty-title="No lessons found"
        empty-subtitle="Try adjusting your search"
        @row-click="(row) => router.push({ name: 'LessonDetail', params: { id: row.id } })"
      >
        <template #cell-date="{ row }">
          <span class="sd-td--muted">{{ row._date }}</span>
        </template>

        <template #cell-status="{ row }">
          <span :class="['sd-status', `sd-status--${row._statusClass}`]">{{ row._statusLabel }}</span>
        </template>

        <template #cell-actions="{ row }">
          <div @click.stop>
            <button
              class="sd-view-btn"
              type="button"
              @click="router.push({ name: 'LessonDetail', params: { id: row.id } })"
            >
              View →
            </button>
          </div>
        </template>
      </TVDataTable>
    </section>

  </div>

  <!-- Not found -->
  <div v-else class="sd-not-found">
    <p>Student not found.</p>
    <button class="sd-back" type="button" @click="router.push('/students')">← Back to Students</button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUsersStore } from '@/stores/users'
import { useScheduleStore } from '@/stores/schedule'
import { useViewAs } from '@/composables/useViewAs'
import { useToast } from '@/composables/useToast'
import TVButton from '@/components/ui/TVButton.vue'
import TVBadge from '@/components/ui/TVBadge.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVDataTable from '@/components/ui/TVDataTable.vue'
import type { DataTableColumn } from '@/components/ui/TVDataTable.vue'
import type { LessonStatus } from '@/stores/schedule'

const route    = useRoute()
const router   = useRouter()
const store    = useUsersStore()
const schedule = useScheduleStore()
const toast    = useToast()
const { effectiveRole } = useViewAs()

const isTeacher = computed(() => effectiveRole.value === 'TEACHER')
const isAdmin   = computed(() => effectiveRole.value === 'ADMIN' || effectiveRole.value === 'STAFF')

const studentId = route.params.id as string
const student   = computed(() => store.getUserById(studentId))

const fullName = computed(() => student.value ? `${student.value.firstName} ${student.value.lastName}` : '')
const initials = computed(() => student.value
  ? `${student.value.firstName[0]}${student.value.lastName[0]}`.toUpperCase() : '')

// Assigned teacher name
const assignedTeacher = computed(() => {
  const tid = student.value?.studentProfile?.assignedTeacherId
  if (!tid) return ''
  const t = store.getUserById(tid)
  return t ? `${t.firstName} ${t.lastName}` : ''
})

// Lessons for this student
const studentLessons = computed(() =>
  schedule.lessons.filter(l => l.studentId === studentId)
    .sort((a, b) => new Date(b.startTime).getTime() - new Date(a.startTime).getTime())
)

const lessonSearch = ref('')

const lessonColumns: DataTableColumn[] = [
  { key: 'title',       label: 'Subject',  sortable: true,  width: '30%' },
  { key: 'teacherName', label: 'Teacher',  sortable: true,  width: '20%', hide: 'sm' },
  { key: 'date',        label: 'Date',     sortable: true,  sortKey: '_startRaw', width: '22%' },
  { key: 'status',      label: 'Status',   sortable: true,  sortKey: 'status', width: '18%' },
  { key: 'actions',     label: '',         stopClick: true, width: '10%' },
]

const lessonRows = computed(() => {
  const q = lessonSearch.value.toLowerCase()
  return studentLessons.value
    .filter(l => !q || l.title.toLowerCase().includes(q) || l.teacherName.toLowerCase().includes(q))
    .map(l => ({
      ...l,
      _startRaw: l.startTime,
      _date: formatDate(l.startTime),
      _statusClass: statusClass(l.status),
      _statusLabel: statusLabel(l.status),
    }))
})

const totalLessons     = computed(() => studentLessons.value.length)
const completedLessons = computed(() => studentLessons.value.filter(l => l.status === 'COMPLETED').length)
const upcomingLessons  = computed(() => studentLessons.value.filter(l => ['SCHEDULED', 'TRIAL', 'RESCHEDULED', 'IN_PROGRESS'].includes(l.status)).length)
const missedLessons    = computed(() => studentLessons.value.filter(l => ['MISSED_BY_STUDENT', 'MISSED_BY_TEACHER'].includes(l.status)).length)

function levelLabel(level: string): string {
  const map: Record<string, string> = {
    BEGINNER: 'Beginner', ELEMENTARY: 'Elementary', INTERMEDIATE: 'Intermediate',
    UPPER_INTERMEDIATE: 'Upper Intermediate', ADVANCED: 'Advanced',
  }
  return map[level] ?? level
}

function levelClass(level: string): string {
  return level.toLowerCase().replace(/_/g, '-')
}

function classTypeLabel(type: string): string {
  const map: Record<string, string> = {
    ONE_ON_ONE: '1-on-1', GROUP: 'Group', SEMI_PRIVATE: 'Semi-private',
  }
  return map[type] ?? type
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function statusClass(status: LessonStatus): string {
  switch (status) {
    case 'SCHEDULED': return 'scheduled'
    case 'IN_PROGRESS': return 'live'
    case 'COMPLETED': return 'completed'
    case 'CANCELLED': return 'cancelled'
    case 'MISSED_BY_STUDENT':
    case 'MISSED_BY_TEACHER': return 'missed'
    case 'TRIAL': return 'trial'
    case 'RESCHEDULED': return 'rescheduled'
    case 'PENDING_CONFIRMATION': return 'pending'
    default: return 'scheduled'
  }
}

function statusLabel(status: LessonStatus): string {
  switch (status) {
    case 'SCHEDULED': return 'Scheduled'
    case 'IN_PROGRESS': return 'Live'
    case 'COMPLETED': return 'Completed'
    case 'CANCELLED': return 'Cancelled'
    case 'MISSED_BY_STUDENT': return 'Missed (Student)'
    case 'MISSED_BY_TEACHER': return 'Missed (Teacher)'
    case 'TRIAL': return 'Trial'
    case 'RESCHEDULED': return 'Rescheduled'
    case 'PENDING_CONFIRMATION': return 'Pending'
    default: return status
  }
}

function toggleActive(): void {
  if (!student.value) return
  const wasActive = student.value.isActive
  store.toggleActive(studentId)
  toast[wasActive ? 'warning' : 'success'](
    `${fullName.value} has been ${wasActive ? 'deactivated' : 'activated'}.`
  )
}
</script>

<style scoped>
.sd-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

/* Back */
.sd-back {
  display: inline-flex; align-items: center; gap: var(--tv-space-1);
  font-size: var(--tv-text-sm); color: var(--tv-text-secondary);
  background: none; border: none; cursor: pointer; padding: 0; width: fit-content;
  transition: color 0.15s;
}
.sd-back:hover { color: var(--tv-primary); }

/* Header card */
.sd-header-card {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); padding: var(--tv-space-5);
  display: flex; align-items: flex-start; gap: var(--tv-space-4); flex-wrap: wrap;
}

.sd-avatar {
  width: 72px; height: 72px; border-radius: var(--tv-radius-full);
  background: hsl(199,89%,92%); color: hsl(199,89%,28%);
  font-size: var(--tv-text-xl); font-weight: var(--tv-font-bold);
  display: flex; align-items: center; justify-content: center;
  overflow: hidden; flex-shrink: 0;
  border: 3px solid hsl(199,70%,78%);
}
.sd-avatar img { width: 100%; height: 100%; object-fit: cover; }

.sd-header-card__info { flex: 1; min-width: 200px; }

.sd-header-card__title-row {
  display: flex; align-items: center; gap: var(--tv-space-2); flex-wrap: wrap; margin-bottom: var(--tv-space-1);
}
.sd-name {
  font-size: var(--tv-text-xl); font-weight: var(--tv-font-bold); color: var(--tv-text); margin: 0;
}

.sd-level {
  font-size: 10px; font-weight: var(--tv-font-semibold);
  padding: 2px 8px; border-radius: var(--tv-radius-full); border: 1px solid;
}
.sd-level--beginner         { background: hsl(38,88%,93%); color: hsl(38,75%,30%); border-color: hsl(38,70%,72%); }
.sd-level--elementary       { background: hsl(48,88%,92%); color: hsl(42,72%,28%); border-color: hsl(42,68%,68%); }
.sd-level--intermediate     { background: hsl(199,89%,92%); color: hsl(199,89%,28%); border-color: hsl(199,70%,68%); }
.sd-level--upper-intermediate { background: hsl(262,70%,93%); color: hsl(262,65%,38%); border-color: hsl(262,60%,72%); }
.sd-level--advanced         { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }

.sd-email { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: 0 0 var(--tv-space-2); }

.sd-header-card__meta-row { display: flex; gap: var(--tv-space-2); flex-wrap: wrap; align-items: center; }

.sd-meta-chip {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: var(--tv-text-xs); color: var(--tv-text-secondary);
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-full); padding: 2px 8px;
}
.sd-meta-chip--teacher { background: var(--tv-primary-soft); border-color: var(--tv-primary-muted); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); }

.sd-header-card__actions { display: flex; flex-direction: column; gap: var(--tv-space-2); flex-shrink: 0; }

/* Stats */
.sd-stats {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--tv-space-3);
}

.sd-stat-card {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-1); align-items: center;
  text-align: center;
}
.sd-stat-card--warn .sd-stat-card__value { color: var(--tv-danger-fg); }
.sd-stat-card__value { font-size: var(--tv-text-2xl); font-weight: var(--tv-font-bold); color: var(--tv-text); }
.sd-stat-card__label { font-size: var(--tv-text-xs); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .04em; }

/* Two-col grid */
.sd-cards3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--tv-space-4); align-items: start; }

/* Panel */
.sd-panel {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); padding: var(--tv-space-5);
  display: flex; flex-direction: column; gap: var(--tv-space-4);
}
.sd-panel--full { grid-column: 1 / -1; }

.sd-panel__header { display: flex; align-items: center; justify-content: space-between; }
.sd-panel__title {
  font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0;
}
.sd-panel__link {
  font-size: var(--tv-text-sm); color: var(--tv-primary); background: none; border: none;
  cursor: pointer; padding: 0; font-weight: var(--tv-font-medium);
}
.sd-panel__link:hover { text-decoration: underline; }
.sd-panel__search { width: 220px; }

/* Info list */
.sd-info-list { display: flex; flex-direction: column; gap: 0; }

.sd-info-row {
  display: flex; align-items: baseline; gap: var(--tv-space-3);
  padding: var(--tv-space-2) 0; border-bottom: 1px solid var(--tv-border);
}
.sd-info-row:last-child { border-bottom: none; }
.sd-info-row--block { flex-direction: column; gap: var(--tv-space-1); align-items: flex-start; }

.sd-info-label {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted);
  text-transform: uppercase; letter-spacing: .05em; width: 148px; min-width: 148px; flex-shrink: 0;
}
.sd-info-value { font-size: var(--tv-text-sm); color: var(--tv-text); flex: 1; min-width: 0; }
.sd-info-text  { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.6; margin: 0; }

/* Account actions */
.sd-account-actions { margin-top: auto; padding-top: var(--tv-space-3); border-top: 1px solid var(--tv-border); }

.sd-action-btn {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
  padding: var(--tv-space-2) var(--tv-space-3);
  border-radius: var(--tv-radius-sm); border: 1px solid; cursor: pointer;
  background: transparent; transition: background 0.15s;
}
.sd-action-btn--danger { color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }
.sd-action-btn--danger:hover { background: var(--tv-danger-soft); }
.sd-action-btn--success { color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.sd-action-btn--success:hover { background: var(--tv-success-soft); }

/* Lessons table */
.sd-lessons-table-wrap {
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius); overflow: hidden;
}
.sd-lessons-table { width: 100%; border-collapse: collapse; }
.sd-th {
  padding: var(--tv-space-2) var(--tv-space-4); font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold); color: var(--tv-text-muted);
  text-transform: uppercase; letter-spacing: .05em; text-align: left;
  background: var(--tv-bg-soft); border-bottom: 1px solid var(--tv-border);
}
.sd-tr {
  border-bottom: 1px solid var(--tv-border); cursor: pointer; transition: background 0.12s;
}
.sd-tr:last-child { border-bottom: none; }
.sd-tr:hover { background: var(--tv-bg-soft); }

.sd-td {
  padding: var(--tv-space-3) var(--tv-space-4);
  font-size: var(--tv-text-sm); color: var(--tv-text); vertical-align: middle;
}
.sd-td--subject { font-weight: var(--tv-font-medium); }
.sd-td--muted   { color: var(--tv-text-muted); }
.sd-td--right   { text-align: right; }

.sd-status {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium);
  padding: 2px var(--tv-space-2); border-radius: var(--tv-radius-full);
  border: 1px solid transparent; white-space: nowrap; display: inline-block;
}
.sd-status--scheduled   { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); border-color: var(--tv-primary-muted); }
.sd-status--completed   { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.sd-status--cancelled   { background: var(--tv-neutral-soft); color: var(--tv-neutral); border-color: var(--tv-neutral-border); }
.sd-status--missed      { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }
.sd-status--trial       { background: var(--tv-purple-soft); color: var(--tv-purple); border-color: var(--tv-purple-border); }
.sd-status--live        { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.sd-status--rescheduled { background: hsl(24,88%,93%); color: hsl(24,75%,35%); border-color: hsl(24,70%,70%); }
.sd-status--pending     { background: hsl(220,65%,93%); color: hsl(220,52%,38%); border-color: hsl(220,52%,65%); }

.sd-view-btn {
  font-size: var(--tv-text-xs); color: var(--tv-primary); background: transparent;
  border: none; cursor: pointer; padding: var(--tv-space-1) var(--tv-space-2);
  border-radius: var(--tv-radius-sm); transition: background 0.15s;
}
.sd-view-btn:hover { background: var(--tv-primary-soft); }

.sd-empty-lessons { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: 0; }

/* Not found */
.sd-not-found {
  padding: var(--tv-space-6); display: flex; flex-direction: column;
  align-items: center; gap: var(--tv-space-4); color: var(--tv-text-muted);
}

@media (max-width: 1100px) { .sd-cards3 { grid-template-columns: 1fr 1fr; } }
@media (max-width: 900px) {
  .sd-cards3 { grid-template-columns: 1fr; }
  .sd-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .sd-page { padding: var(--tv-space-4); }
  .sd-header-card { flex-direction: column; }
  .sd-header-card__actions { flex-direction: row; }
}
</style>
