<template>
  <div class="lv-page">

    <!-- Header -->
    <div class="lv-page__header">
      <div>
        <h1 class="lv-page__title">{{ pageTitle }}</h1>
        <p class="lv-page__subtitle">{{ filteredLessons.length }} lesson{{ filteredLessons.length !== 1 ? 's' : '' }}</p>
      </div>
      <TVButton v-if="canCreate" variant="primary" @click="openCreateModal">
        <template #icon>
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </template>
        Add Lesson
      </TVButton>
    </div>

    <!-- Filters -->
    <div class="lv-page__filters">
      <div class="lv-page__search">
        <TVInput v-model="search" placeholder="Search subject, teacher, student…" aria-label="Search lessons">
          <template #icon-start>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <circle cx="6.5" cy="6.5" r="4" stroke="currentColor" stroke-width="1.4"/>
              <path d="M11 11l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
          </template>
        </TVInput>
      </div>
      <div class="lv-page__filter-selects">
        <TVSelect v-model="filterStatus" :options="statusOptions" placeholder="All Statuses" />
        <TVSelect v-if="showTeacherFilter" v-model="filterTeacher" :options="teacherOptions" placeholder="All Teachers" />
        <TVSelect v-if="showStudentFilter" v-model="filterStudent" :options="studentOptions" placeholder="All Students" />
      </div>
    </div>

    <!-- Table -->
    <TVDataTable
      :columns="columns"
      :rows="rows"
      row-key="id"
      :page-size="10"
      :page-size-options="[10, 20, 50]"
      clickable
      aria-label="Lessons list"
      empty-title="No lessons found"
      empty-subtitle="Try adjusting your search or filters"
      @row-click="(row) => goToLesson(String(row.id))"
    >
      <template #cell-subject="{ row }">
        <div class="lv-subject-cell">
          <span class="lv-subject-name">{{ row.title }}</span>
          <span v-if="row.isTrial" class="lv-badge lv-badge--trial">Trial</span>
          <span v-if="row.isRecurring && !row.isTrial" class="lv-badge lv-badge--recur">↻</span>
        </div>
      </template>

      <template #cell-dateTime="{ row }">
        <span class="lv-date-main">{{ formatDate(String(row.startTime)) }}</span>
        <span class="lv-date-sub">{{ formatTime(String(row.startTime)) }}</span>
      </template>

      <template #cell-duration="{ row }">
        <span class="lv-muted">{{ durationMin(String(row.startTime), String(row.endTime)) }} min</span>
      </template>

      <template #cell-status="{ row }">
        <span :class="['lv-status', `lv-status--${statusClass(String(row.status))}`]">
          {{ statusLabel(String(row.status)) }}
        </span>
      </template>

      <template #cell-actions="{ row }">
        <button class="lv-view-btn" type="button" @click.stop="goToLesson(String(row.id))">View →</button>
      </template>

      <template #empty>
        <div class="lv-empty-icon" aria-hidden="true">
          <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
            <rect x="6" y="4" width="24" height="28" rx="3" stroke="currentColor" stroke-width="1.8"/>
            <path d="M12 12h12M12 18h12M12 24h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <p class="lv-empty-title">No lessons found</p>
        <p class="lv-empty-sub">Try adjusting your search or filters</p>
      </template>
    </TVDataTable>

    <!-- Create Lesson Modal -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="lv-modal-overlay" @click.self="closeCreateModal">
        <div class="lv-modal" role="dialog" aria-modal="true" aria-labelledby="lv-modal-title">
          <div class="lv-modal__header">
            <h2 id="lv-modal-title" class="lv-modal__title">Add Lesson</h2>
            <button class="lv-modal__close" type="button" aria-label="Close" @click="closeCreateModal">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div class="lv-modal__body">
            <div class="lv-form-row">
              <TVSelect v-model="form.teacherId" :options="teacherFormOptions" label="Teacher" @update:modelValue="(v) => onTeacherChange(String(v))" />
              <TVSelect v-model="form.studentId" :options="studentFormOptions" label="Student" @update:modelValue="(v) => onStudentChange(String(v))" />
            </div>
            <TVInput v-model="form.subject" label="Subject / Title" placeholder="e.g. Business English" required />
            <div class="lv-form-row">
              <TVDatePicker v-model="form.date" label="Date" :min="today" />
              <TVTimePicker v-model="form.startTime" label="Start Time" />
            </div>
            <div class="lv-form-row">
              <TVSelect v-model="form.durationMin" :options="durationOptions" label="Duration" />
              <TVSelect v-model="form.lessonType" :options="lessonTypeOptions" label="Type" />
            </div>
          </div>
          <div class="lv-modal__footer">
            <button class="lv-btn lv-btn--ghost" type="button" @click="closeCreateModal">Cancel</button>
            <button
              class="lv-btn lv-btn--primary"
              type="button"
              :disabled="!form.teacherId || !form.studentId || !form.subject.trim() || !form.date || !form.startTime"
              @click="submitCreate"
            >
              Create Lesson
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useScheduleStore } from '@/stores/schedule'
import { useAuthStore } from '@/stores/auth'
import { useViewAs } from '@/composables/useViewAs'
import { useUsersStore } from '@/stores/users'
import TVButton from '@/components/ui/TVButton.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVDataTable from '@/components/ui/TVDataTable.vue'
import TVDatePicker from '@/components/ui/TVDatePicker.vue'
import TVTimePicker from '@/components/ui/TVTimePicker.vue'
import type { DataTableColumn } from '@/components/ui/TVDataTable.vue'
import type { LessonStatus } from '@/stores/schedule'

const router   = useRouter()
const schedule = useScheduleStore()
const auth     = useAuthStore()
const users    = useUsersStore()
const { effectiveRole } = useViewAs()

const search        = ref('')
const filterStatus  = ref('')
const filterTeacher = ref('')
const filterStudent = ref('')

const role = computed(() => effectiveRole.value)

const showTeacherFilter = computed(() => ['ADMIN', 'STAFF'].includes(role.value))
const showStudentFilter = computed(() => ['ADMIN', 'STAFF'].includes(role.value))
const canCreate         = computed(() => ['ADMIN', 'STAFF'].includes(role.value))

const pageTitle = computed(() => {
  if (role.value === 'STUDENT') return 'My Lessons'
  if (role.value === 'TEACHER') return 'My Lessons'
  return 'Lessons'
})

// ── Source data (role-scoped) ──
const sourceLessons = computed(() => {
  const r = role.value
  const uid = auth.user?.id
  if (r === 'STUDENT') return schedule.lessons.filter(l => l.studentId === uid)
  if (r === 'TEACHER') return auth.user?.role === 'TEACHER'
    ? schedule.lessons.filter(l => l.teacherId === uid)
    : schedule.lessons
  return schedule.lessons
})

// ── Unique filter options derived from lessons ──
const teacherOptions = computed(() => {
  const seen = new Set<string>()
  const opts: { value: string; label: string }[] = []
  for (const l of schedule.lessons) {
    if (!seen.has(l.teacherId)) {
      seen.add(l.teacherId)
      opts.push({ value: l.teacherId, label: l.teacherName })
    }
  }
  return opts.sort((a, b) => a.label.localeCompare(b.label))
})

const studentOptions = computed(() => {
  const seen = new Set<string>()
  const opts: { value: string; label: string }[] = []
  for (const l of schedule.lessons) {
    if (!seen.has(l.studentId)) {
      seen.add(l.studentId)
      opts.push({ value: l.studentId, label: l.studentName })
    }
  }
  return opts.sort((a, b) => a.label.localeCompare(b.label))
})

const statusOptions = [
  { value: 'SCHEDULED',            label: 'Scheduled' },
  { value: 'IN_PROGRESS',          label: 'Live' },
  { value: 'COMPLETED',            label: 'Completed' },
  { value: 'CANCELLED',            label: 'Cancelled' },
  { value: 'MISSED_BY_STUDENT',    label: 'Missed (Student)' },
  { value: 'MISSED_BY_TEACHER',    label: 'Missed (Teacher)' },
  { value: 'TRIAL',                label: 'Trial' },
  { value: 'RESCHEDULED',          label: 'Rescheduled' },
  { value: 'PENDING_CONFIRMATION', label: 'Pending Confirmation' },
]

// ── Filtered + sorted lessons ──
const filteredLessons = computed(() => {
  const q = search.value.toLowerCase()
  return sourceLessons.value
    .filter(l => {
      if (filterStatus.value  && l.status    !== filterStatus.value)  return false
      if (filterTeacher.value && l.teacherId !== filterTeacher.value) return false
      if (filterStudent.value && l.studentId !== filterStudent.value) return false
      if (q && !`${l.title} ${l.teacherName} ${l.studentName} ${l.subject}`.toLowerCase().includes(q)) return false
      return true
    })
    .sort((a, b) => new Date(b.startTime).getTime() - new Date(a.startTime).getTime())
})

// ── TVDataTable columns ──
const columns = computed((): DataTableColumn[] => {
  const base: DataTableColumn[] = [
    { key: 'subject',  label: 'Subject',  sortable: true, sortKey: 'title', width: '28%' },
    { key: 'teacher',  label: 'Teacher',  sortable: true, sortKey: 'teacherName', hide: 'sm', width: '15%' },
  ]
  if (showStudentFilter.value) {
    base.push({ key: 'student', label: 'Student', sortable: true, sortKey: 'studentName', hide: 'md', width: '15%' })
  }
  base.push(
    { key: 'dateTime', label: 'Date & Time', sortable: true, sortKey: '_startMs', width: '16%' },
    { key: 'duration', label: 'Duration', sortable: false, hide: 'sm', width: '8%' },
    { key: 'status',   label: 'Status',   sortable: true, width: '12%' },
    { key: 'actions',  label: '',         stopClick: true, width: '6%' },
  )
  return base
})

const rows = computed(() =>
  filteredLessons.value.map(l => ({
    ...l,
    _startMs: new Date(l.startTime).getTime(),
    teacher: l.teacherName,
    student: l.studentName,
  }))
)

function goToLesson(id: string): void {
  router.push({ name: 'LessonDetail', params: { id } })
}

// ── Status helpers ──
function statusClass(status: string): string {
  switch (status as LessonStatus) {
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

function statusLabel(status: string): string {
  switch (status as LessonStatus) {
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

function durationMin(start: string, end: string): number {
  return Math.round((new Date(end).getTime() - new Date(start).getTime()) / 60_000)
}

// ── Create Lesson Modal ──
const showCreateModal = ref(false)
const today = new Date().toLocaleDateString('sv-SE')

const form = ref({
  teacherId:   '',
  teacherName: '',
  studentId:   '',
  studentName: '',
  subject:     '',
  date:        today,
  startTime:   '09:00',
  durationMin: '60',
  lessonType:  'one-time',
})

onMounted(() => {
  if (!users.users.length) users.fetchUsers()
})

const allTeachers = computed(() =>
  users.users
    .filter(u => u.role === 'TEACHER' && u.isActive)
    .map(u => ({ value: u.id, label: `${u.firstName} ${u.lastName}` }))
)

const allStudents = computed(() =>
  users.users
    .filter(u => u.role === 'STUDENT' && u.isActive)
    .map(u => ({ value: u.id, label: `${u.firstName} ${u.lastName}` }))
)

const teacherFormOptions = computed(() => allTeachers.value)
const studentFormOptions = computed(() => allStudents.value)

const durationOptions = [
  { value: '30',  label: '30 min' },
  { value: '45',  label: '45 min' },
  { value: '60',  label: '60 min' },
  { value: '90',  label: '90 min' },
  { value: '120', label: '2 hours' },
]

const lessonTypeOptions = [
  { value: 'one-time',  label: 'One-time' },
  { value: 'recurring', label: 'Recurring' },
  { value: 'trial',     label: 'Trial' },
]

function openCreateModal(): void {
  form.value = {
    teacherId: '', teacherName: '', studentId: '', studentName: '',
    subject: '', date: today, startTime: '09:00', durationMin: '60', lessonType: 'one-time',
  }
  showCreateModal.value = true
}

function closeCreateModal(): void {
  showCreateModal.value = false
}

function onTeacherChange(id: string): void {
  const t = users.getUserById(id)
  form.value.teacherName = t ? `${t.firstName} ${t.lastName}` : ''
}

function onStudentChange(id: string): void {
  const s = users.getUserById(id)
  form.value.studentName = s ? `${s.firstName} ${s.lastName}` : ''
}

function submitCreate(): void {
  const { teacherId, teacherName, studentId, studentName, subject, date, startTime, durationMin: dur, lessonType } = form.value
  if (!teacherId || !studentId || !subject.trim() || !date || !startTime) return
  const startISO = `${date}T${startTime}:00+08:00`
  schedule.addLesson(
    teacherId, teacherName,
    studentId, studentName,
    subject.trim(), startISO, Number(dur),
    lessonType === 'trial',
    lessonType === 'recurring',
  )
  closeCreateModal()
}
</script>

<style scoped>
.lv-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

.lv-page__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--tv-space-4);
  flex-wrap: wrap;
}

.lv-page__title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
  margin: 0 0 var(--tv-space-1);
}

.lv-page__subtitle {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: 0;
}

.lv-page__filters {
  display: flex;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
  align-items: flex-end;
}

.lv-page__search {
  flex: 1;
  min-width: 200px;
  max-width: 360px;
}

.lv-page__filter-selects {
  display: flex;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
}

.lv-page__filter-selects > * {
  width: 160px;
  flex-shrink: 0;
}

/* Subject cell */
.lv-subject-cell {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  flex-wrap: wrap;
}

.lv-subject-name {
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
}

.lv-badge {
  font-size: 10px; font-weight: var(--tv-font-semibold);
  padding: 1px 6px; border-radius: var(--tv-radius-full);
  border: 1px solid; white-space: nowrap;
}
.lv-badge--trial { background: var(--tv-purple-soft); color: var(--tv-purple); border-color: var(--tv-purple-border); }
.lv-badge--recur { background: var(--tv-bg-soft); color: var(--tv-text-muted); border-color: var(--tv-border); }

/* Date cell */
.lv-date-main { display: block; font-weight: var(--tv-font-medium); }
.lv-date-sub  { display: block; font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin-top: 1px; }
.lv-muted     { color: var(--tv-text-muted); }

/* Status badges */
.lv-status {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium);
  padding: 2px var(--tv-space-2); border-radius: var(--tv-radius-full);
  border: 1px solid transparent; white-space: nowrap; display: inline-block;
}
.lv-status--scheduled   { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); border-color: var(--tv-primary-muted); }
.lv-status--completed   { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.lv-status--cancelled   { background: var(--tv-neutral-soft); color: var(--tv-neutral); border-color: var(--tv-neutral-border); }
.lv-status--missed      { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }
.lv-status--trial       { background: var(--tv-purple-soft); color: var(--tv-purple); border-color: var(--tv-purple-border); }
.lv-status--live        { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.lv-status--rescheduled { background: hsl(24,88%,93%); color: hsl(24,75%,35%); border-color: hsl(24,70%,70%); }
.lv-status--pending     { background: hsl(220,65%,93%); color: hsl(220,52%,38%); border-color: hsl(220,52%,65%); border-style: dashed; }

/* View button */
.lv-view-btn {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium);
  color: var(--tv-primary); background: transparent; border: none;
  cursor: pointer; padding: var(--tv-space-1) var(--tv-space-2);
  border-radius: var(--tv-radius-sm); transition: background 0.15s;
  white-space: nowrap;
}
.lv-view-btn:hover { background: var(--tv-primary-soft); }

/* Empty state */
.lv-empty-icon {
  width: 72px; height: 72px; border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft); color: var(--tv-primary);
  display: flex; align-items: center; justify-content: center;
}
.lv-empty-title {
  font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0;
}
.lv-empty-sub {
  font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: 0;
}

/* Modal */
.lv-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center;
  z-index: var(--tv-z-modal); padding: var(--tv-space-4);
}

.lv-modal {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-lg); width: 100%; max-width: 560px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.18);
  display: flex; flex-direction: column;
  max-height: 90vh;
}

.lv-modal__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: var(--tv-space-5) var(--tv-space-5) var(--tv-space-4);
  border-bottom: 1px solid var(--tv-border);
  flex-shrink: 0;
}
.lv-modal__title { font-size: var(--tv-text-lg); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.lv-modal__close {
  width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
  background: transparent; border: 1px solid transparent; border-radius: var(--tv-radius-sm);
  color: var(--tv-text-muted); cursor: pointer; transition: background 0.15s;
}
.lv-modal__close:hover { background: var(--tv-bg-soft); border-color: var(--tv-border); }

.lv-modal__body {
  padding: var(--tv-space-5);
  display: flex; flex-direction: column; gap: var(--tv-space-4);
  overflow: visible;
}

.lv-modal__footer {
  display: flex; gap: var(--tv-space-2); justify-content: flex-end;
  padding: var(--tv-space-4) var(--tv-space-5);
  border-top: 1px solid var(--tv-border);
  flex-shrink: 0;
}

.lv-form-row {
  display: grid; grid-template-columns: 1fr 1fr; gap: var(--tv-space-3);
}

.lv-btn {
  display: inline-flex; align-items: center; gap: var(--tv-space-1);
  padding: var(--tv-space-2) var(--tv-space-5); font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium); border-radius: var(--tv-radius-sm);
  border: 1px solid transparent; cursor: pointer; transition: background 0.15s;
  font-family: inherit;
}
.lv-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.lv-btn--primary { background: var(--tv-primary); color: var(--tv-text-inverse); }
.lv-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.lv-btn--ghost { background: transparent; border-color: var(--tv-border); color: var(--tv-text-secondary); }
.lv-btn--ghost:hover:not(:disabled) { background: var(--tv-bg-soft); }

@media (max-width: 767px) {
  .lv-page { padding: var(--tv-space-4); }
  .lv-page__search { max-width: 100%; width: 100%; }
  .lv-page__filter-selects { width: 100%; }
  .lv-page__filter-selects > * { width: auto; flex: 1; }
  .lv-form-row { grid-template-columns: 1fr; }
}
</style>
