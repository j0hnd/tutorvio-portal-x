<template>
  <div class="lv-page">

    <div class="lv-header">
      <div class="lv-header__left">
        <h1 class="lv-title">Lessons</h1>
        <p class="lv-subtitle">{{ filteredLessons.length }} lesson{{ filteredLessons.length !== 1 ? 's' : '' }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="lv-filters">
      <div class="lv-filter-search">
        <svg class="lv-filter-search__icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.2"/>
          <path d="M9.5 9.5l2.5 2.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
        </svg>
        <input v-model="search" class="lv-filter-search__input" type="search" placeholder="Search subject, teacher, student…" />
      </div>
      <TVSelect v-model="filterStatus" :options="statusOptions" placeholder="All Statuses" class="lv-filter-select" />
      <TVSelect v-if="showTeacherFilter" v-model="filterTeacher" :options="teacherOptions" placeholder="All Teachers" class="lv-filter-select" />
    </div>

    <!-- Table -->
    <div class="lv-table-wrap">
      <table class="lv-table" aria-label="Lessons">
        <thead class="lv-table__head">
          <tr>
            <th class="lv-th">Subject</th>
            <th class="lv-th">Teacher</th>
            <th class="lv-th lv-th--hide-sm">Student</th>
            <th class="lv-th">Date &amp; Time</th>
            <th class="lv-th lv-th--hide-sm">Duration</th>
            <th class="lv-th">Status</th>
            <th class="lv-th lv-th--right"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="lesson in paginatedLessons"
            :key="lesson.id"
            :class="['lv-tr', `lv-tr--${statusClass(lesson.status)}`]"
            @click="goToLesson(lesson.id)"
          >
            <td class="lv-td lv-td--subject">
              <span class="lv-subject">{{ lesson.title }}</span>
              <span v-if="lesson.isTrial" class="lv-badge lv-badge--trial">Trial</span>
              <span v-if="lesson.isRecurring" class="lv-badge lv-badge--recur">↻</span>
            </td>
            <td class="lv-td">{{ lesson.teacherName }}</td>
            <td class="lv-td lv-td--hide-sm">{{ lesson.studentName }}</td>
            <td class="lv-td">
              <span class="lv-date">{{ formatDate(lesson.startTime) }}</span>
              <span class="lv-time">{{ formatTime(lesson.startTime) }}</span>
            </td>
            <td class="lv-td lv-td--hide-sm lv-td--muted">{{ duration(lesson) }} min</td>
            <td class="lv-td">
              <span :class="['lv-status', `lv-status--${statusClass(lesson.status)}`]">{{ statusLabel(lesson.status) }}</span>
            </td>
            <td class="lv-td lv-td--right">
              <button class="lv-view-btn" type="button" @click.stop="goToLesson(lesson.id)">View →</button>
            </td>
          </tr>
          <tr v-if="filteredLessons.length === 0">
            <td colspan="7" class="lv-empty">No lessons found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="lv-pagination">
      <button class="lv-page-btn" :disabled="page === 1" @click="page--">‹ Prev</button>
      <span class="lv-page-info">Page {{ page }} of {{ totalPages }}</span>
      <button class="lv-page-btn" :disabled="page === totalPages" @click="page++">Next ›</button>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useScheduleStore } from '@/stores/schedule'
import { useAuthStore } from '@/stores/auth'
import { useViewAs } from '@/composables/useViewAs'
import TVSelect from '@/components/ui/TVSelect.vue'
import type { LessonStatus } from '@/stores/schedule'

const router   = useRouter()
const schedule = useScheduleStore()
const auth     = useAuthStore()
const { effectiveRole } = useViewAs()

const search       = ref('')
const filterStatus = ref('')
const filterTeacher = ref('')
const page         = ref(1)
const PAGE_SIZE    = 20

const showTeacherFilter = computed(() =>
  ['ADMIN', 'STAFF'].includes(effectiveRole.value) || auth.user?.role === 'ADMIN' || auth.user?.role === 'STAFF'
)

const teacherOptions = [
  { value: 'u2', label: 'James Reyes' },
  { value: 'u6', label: 'Sarah Lim' },
  { value: 'u7', label: 'Miguel Santos' },
]

const statusOptions = [
  { value: 'SCHEDULED',            label: 'Scheduled' },
  { value: 'IN_PROGRESS',          label: 'Live' },
  { value: 'COMPLETED',            label: 'Completed' },
  { value: 'CANCELLED',            label: 'Cancelled' },
  { value: 'MISSED_BY_STUDENT',    label: 'Missed by Student' },
  { value: 'MISSED_BY_TEACHER',    label: 'Missed by Teacher' },
  { value: 'TRIAL',                label: 'Trial' },
  { value: 'RESCHEDULED',          label: 'Rescheduled' },
  { value: 'PENDING_CONFIRMATION', label: 'Pending Confirmation' },
]

const filteredLessons = computed(() => {
  const q = search.value.toLowerCase()
  return schedule.myLessons
    .filter(l => {
      if (filterStatus.value && l.status !== filterStatus.value) return false
      if (filterTeacher.value && l.teacherId !== filterTeacher.value) return false
      if (q && !`${l.title} ${l.teacherName} ${l.studentName} ${l.subject}`.toLowerCase().includes(q)) return false
      return true
    })
    .sort((a, b) => new Date(b.startTime).getTime() - new Date(a.startTime).getTime())
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredLessons.value.length / PAGE_SIZE)))
const paginatedLessons = computed(() => {
  const start = (page.value - 1) * PAGE_SIZE
  return filteredLessons.value.slice(start, start + PAGE_SIZE)
})

function goToLesson(id: string): void {
  router.push({ name: 'LessonDetail', params: { id } })
}

function statusClass(status: LessonStatus): string {
  switch (status) {
    case 'SCHEDULED':            return 'scheduled'
    case 'IN_PROGRESS':          return 'live'
    case 'COMPLETED':            return 'completed'
    case 'CANCELLED':            return 'cancelled'
    case 'MISSED_BY_STUDENT':
    case 'MISSED_BY_TEACHER':    return 'missed'
    case 'TRIAL':                return 'trial'
    case 'RESCHEDULED':          return 'rescheduled'
    case 'PENDING_CONFIRMATION': return 'pending'
    default:                     return 'scheduled'
  }
}

function statusLabel(status: LessonStatus): string {
  switch (status) {
    case 'SCHEDULED':            return 'Scheduled'
    case 'IN_PROGRESS':          return 'Live'
    case 'COMPLETED':            return 'Completed'
    case 'CANCELLED':            return 'Cancelled'
    case 'MISSED_BY_STUDENT':    return 'Missed (Student)'
    case 'MISSED_BY_TEACHER':    return 'Missed (Teacher)'
    case 'TRIAL':                return 'Trial'
    case 'RESCHEDULED':          return 'Rescheduled'
    case 'PENDING_CONFIRMATION': return 'Pending'
    default:                     return status
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatTime(iso: string): string {
  return new Date(iso).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
}

function duration(lesson: { startTime: string; endTime: string }): number {
  return Math.round((new Date(lesson.endTime).getTime() - new Date(lesson.startTime).getTime()) / 60_000)
}
</script>

<style scoped>
.lv-page { display: flex; flex-direction: column; gap: var(--tv-space-5); }

.lv-header { display: flex; align-items: flex-end; justify-content: space-between; }
.lv-title  { font-size: var(--tv-text-2xl); font-weight: var(--tv-font-bold); color: var(--tv-text); margin: 0; }
.lv-subtitle { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: 2px 0 0; }

/* Filters */
.lv-filters {
  display: flex;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
  align-items: flex-end;
}
.lv-filter-search {
  position: relative;
  flex: 1;
  min-width: 200px;
}
.lv-filter-search__icon {
  position: absolute;
  left: var(--tv-space-3);
  top: 50%;
  transform: translateY(-50%);
  color: var(--tv-text-muted);
  pointer-events: none;
}
.lv-filter-search__input {
  width: 100%;
  padding: var(--tv-space-2) var(--tv-space-3) var(--tv-space-2) var(--tv-space-8);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border);
  border-radius: var(--tv-radius);
  min-height: 42px;
  box-sizing: border-box;
  outline: none;
  transition: border-color 0.15s;
  font-family: inherit;
}
.lv-filter-search__input:focus { border-color: var(--tv-primary); }
.lv-filter-select { width: 180px; }

/* Table */
.lv-table-wrap {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  overflow: hidden;
}
.lv-table { width: 100%; border-collapse: collapse; }
.lv-table__head { background: var(--tv-bg-soft); border-bottom: 1px solid var(--tv-border); }
.lv-th {
  padding: var(--tv-space-3) var(--tv-space-4);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: .05em;
  text-align: left;
  white-space: nowrap;
}
.lv-th--right { text-align: right; }

.lv-tr {
  border-bottom: 1px solid var(--tv-border);
  cursor: pointer;
  transition: background 0.12s;
}
.lv-tr:last-child { border-bottom: none; }
.lv-tr:hover { background: var(--tv-bg-soft); }
.lv-tr--cancelled { opacity: 0.65; }
.lv-tr--live { background: hsl(var(--tv-success-h, 160), 55%, 96%); }

.lv-td {
  padding: var(--tv-space-3) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  vertical-align: middle;
}
.lv-td--muted { color: var(--tv-text-muted); }
.lv-td--right { text-align: right; }

.lv-td--subject { display: flex; align-items: center; gap: var(--tv-space-2); flex-wrap: wrap; }
.lv-subject { font-weight: var(--tv-font-medium); }

.lv-badge {
  font-size: 10px; font-weight: var(--tv-font-semibold);
  padding: 1px 6px; border-radius: var(--tv-radius-full);
  border: 1px solid; white-space: nowrap;
}
.lv-badge--trial  { background: var(--tv-purple-soft); color: var(--tv-purple); border-color: var(--tv-purple-border); }
.lv-badge--recur  { background: var(--tv-bg-soft); color: var(--tv-text-muted); border-color: var(--tv-border); }

.lv-date { display: block; font-weight: var(--tv-font-medium); }
.lv-time { display: block; font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin-top: 1px; }

.lv-status {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium);
  padding: 2px var(--tv-space-2); border-radius: var(--tv-radius-full);
  border: 1px solid transparent; white-space: nowrap;
}
.lv-status--scheduled   { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); border-color: var(--tv-primary-muted); }
.lv-status--completed   { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.lv-status--cancelled   { background: var(--tv-neutral-soft); color: var(--tv-neutral); border-color: var(--tv-neutral-border); }
.lv-status--missed      { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }
.lv-status--trial       { background: var(--tv-purple-soft); color: var(--tv-purple); border-color: var(--tv-purple-border); }
.lv-status--live        { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.lv-status--rescheduled { background: hsl(24,88%,93%); color: hsl(24,75%,35%); border-color: hsl(24,70%,70%); }
.lv-status--pending     { background: hsl(220,65%,93%); color: hsl(220,52%,38%); border-color: hsl(220,52%,65%); border-style: dashed; }

.lv-view-btn {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium);
  color: var(--tv-primary); background: transparent; border: none;
  cursor: pointer; padding: var(--tv-space-1) var(--tv-space-2);
  border-radius: var(--tv-radius-sm); transition: background 0.15s;
  white-space: nowrap;
}
.lv-view-btn:hover { background: var(--tv-primary-soft); }

.lv-empty { padding: var(--tv-space-8); text-align: center; color: var(--tv-text-muted); font-size: var(--tv-text-sm); }

/* Pagination */
.lv-pagination {
  display: flex; align-items: center; justify-content: center;
  gap: var(--tv-space-4);
}
.lv-page-btn {
  padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); color: var(--tv-text); cursor: pointer;
  transition: background 0.15s;
}
.lv-page-btn:hover:not(:disabled) { background: var(--tv-bg-soft); }
.lv-page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.lv-page-info { font-size: var(--tv-text-sm); color: var(--tv-text-muted); }

@media (max-width: 680px) {
  .lv-th--hide-sm, .lv-td--hide-sm { display: none; }
}
</style>
