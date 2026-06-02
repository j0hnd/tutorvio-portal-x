<template>
  <div class="att-page">

    <!-- Header -->
    <div class="att-page__header">
      <div>
        <h1 class="att-page__title">Attendance</h1>
        <p class="att-page__subtitle">
          <template v-if="role === 'STUDENT'">Your attendance history across all lessons</template>
          <template v-else-if="role === 'TEACHER'">Attendance records for your classes</template>
          <template v-else>All attendance records across teachers and students</template>
        </p>
      </div>
    </div>

    <!-- Stats -->
    <div class="att-stats">
      <div v-for="stat in statCards" :key="stat.label" class="att-stat-card">
        <div class="att-stat-card__content">
          <div class="att-stat-card__text">
            <span class="att-stat-card__label">{{ stat.label }}</span>
            <span class="att-stat-card__value">{{ stat.value }}</span>
            <span class="att-stat-card__sub" :class="{ 'att-stat-card__sub--up': stat.trendUp }">{{ stat.sub }}</span>
          </div>
          <div :class="['att-stat-card__icon-wrap', 'icon-badge', stat.iconClass]">
            <span class="att-stat-card__icon" aria-hidden="true" v-html="stat.icon" />
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="att-page__filters">
      <TVDateRangePicker
        v-model="filterDateRange"
        placeholder="All dates"
        aria-label="Filter by date range"
      />
      <div class="att-page__filter-selects">
        <TVSelect
          v-if="canFilterByTeacher"
          v-model="filterTeacherId"
          :options="teacherSelectOptions"
          placeholder="All Teachers"
          aria-label="Filter by teacher"
        />
        <TVSelect
          v-if="canFilterByStudent"
          v-model="filterStudentId"
          :options="studentSelectOptions"
          placeholder="All Students"
          aria-label="Filter by student"
        />
        <TVSelect
          v-model="filterStatus"
          :options="statusSelectOptions"
          placeholder="All Statuses"
          aria-label="Filter by status"
        />
        <TVSelect
          v-model="filterSubject"
          :options="subjectSelectOptions"
          placeholder="All Subjects"
          aria-label="Filter by subject"
        />
        <button v-if="hasAttFilters" class="att-clear-btn" type="button" @click="clearAttFilters">Clear</button>
      </div>
    </div>

    <!-- Data table -->
    <TVDataTable
      :columns="columns"
      :rows="tableRows"
      row-key="lessonId"
      :page-size="10"
      :page-size-options="[10, 25, 50]"
      clickable
      aria-label="Attendance records"
      empty-title="No attendance records found"
      empty-subtitle="Try adjusting your date range or filters"
      @row-click="(row) => router.push(`/lessons/${row.lessonId}`)"
    >
      <template #cell-lesson="{ row }">
        <div class="att-lesson-cell">
          <span class="att-lesson-cell__subject">{{ row._subject }}</span>
          <span class="att-lesson-cell__date">{{ row._date }}</span>
        </div>
      </template>

      <template #cell-student="{ row }">
        <span class="att-name">{{ row._studentName }}</span>
      </template>

      <template #cell-teacher="{ row }">
        <span class="att-name">{{ row._teacherName }}</span>
      </template>

      <template #cell-studentStatus="{ row }">
        <TVBadge
          :label="attStatusLabel(String(row.studentStatus) as AttendanceStatus)"
          :variant="attBadgeVariant(String(row.studentStatus) as AttendanceStatus)"
        />
      </template>

      <template #cell-teacherStatus="{ row }">
        <TVBadge
          :label="attStatusLabel(String(row.teacherStatus) as AttendanceStatus)"
          :variant="attBadgeVariant(String(row.teacherStatus) as AttendanceStatus)"
        />
      </template>

      <template #cell-comments="{ row }">
        <div class="att-comments-cell">
          <span v-if="row.absenceReason" class="att-comments-cell__reason">{{ row.absenceReason }}</span>
          <span v-if="row.comments" class="att-comments-cell__note">{{ row.comments }}</span>
          <span v-if="row.isOverridden" class="att-override-pill">overridden</span>
          <span v-if="!row.absenceReason && !row.comments && !row.isOverridden" class="tv-dt-muted">—</span>
        </div>
      </template>

      <template #cell-actions="{ row }">
        <div v-if="canOverride" class="att-actions" @click.stop>
          <button
            class="icon-btn icon-btn--edit"
            aria-label="Override attendance"
            title="Override attendance"
            @click="openOverride(row as unknown as AttendanceRecord)"
          >
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
              <path d="M10.5 2.5l2 2L5 12H3v-2l7.5-7.5z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
      </template>

      <template #empty>
        <div class="att-empty-icon" aria-hidden="true">
          <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
            <rect x="4" y="6" width="28" height="26" rx="3" stroke="currentColor" stroke-width="1.8"/>
            <path d="M11 4v4M25 4v4M4 14h28" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M12 22l4 4 8-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <p class="att-empty-title">No attendance records found</p>
        <p class="att-empty-sub">Try adjusting your date range or filters</p>
      </template>
    </TVDataTable>

    <!-- Override modal (admin/staff) -->
    <Teleport to="body">
      <div v-if="overrideModal.open" class="att-modal-backdrop" @click.self="closeOverride">
        <div class="att-modal" role="dialog" aria-modal="true" aria-labelledby="att-modal-title">
          <div class="att-modal__header">
            <h2 id="att-modal-title" class="att-modal__title">Override Attendance</h2>
            <button class="att-modal__close" type="button" aria-label="Close" @click="closeOverride">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 4l10 10M14 4L4 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            </button>
          </div>
          <div v-if="overrideModal.record" class="att-modal__body">
            <p class="att-modal__desc">
              <strong>{{ overrideModal.record.lesson.subject }}</strong> &mdash;
              {{ overrideModal.record.lesson.studentName }} with {{ overrideModal.record.lesson.teacherName }}<br />
              <span class="att-modal__date">{{ formatDate(overrideModal.record.lesson.startTime) }}</span>
            </p>

            <div class="att-form-row">
              <TVSelect v-model="overrideForm.studentStatus" :options="statusSelectOptions" label="Student Status" />
              <TVSelect v-model="overrideForm.teacherStatus" :options="statusSelectOptions" label="Teacher Status" />
            </div>
            <div class="att-field">
              <label class="att-label">Absence Reason</label>
              <input v-model="overrideForm.absenceReason" type="text" class="att-input" placeholder="Reason for absence (if applicable)" />
            </div>
            <div class="att-field">
              <label class="att-label">Admin Comments</label>
              <textarea v-model="overrideForm.comments" class="att-textarea" rows="3" placeholder="Notes visible to teachers and admins…" />
            </div>
          </div>
          <div class="att-modal__footer">
            <TVButton variant="ghost" @click="closeOverride">Cancel</TVButton>
            <TVButton variant="primary" @click="submitOverride">Save Override</TVButton>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted, type WritableComputedRef } from 'vue'
import { useRouter } from 'vue-router'
import { useScheduleStore } from '@/stores/schedule'
import { useAuthStore } from '@/stores/auth'
import { useUsersStore } from '@/stores/users'
import { useViewAs } from '@/composables/useViewAs'
import TVDataTable from '@/components/ui/TVDataTable.vue'
import type { DataTableColumn } from '@/components/ui/TVDataTable.vue'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVDateRangePicker from '@/components/ui/TVDateRangePicker.vue'
import type { DateRange } from '@/components/ui/TVDateRangePicker.vue'
import TVBadge from '@/components/ui/TVBadge.vue'
import TVButton from '@/components/ui/TVButton.vue'
import type { AttendanceStatus, LessonAttendance, ScheduleLesson } from '@/stores/schedule'
import type { SelectOption, BadgeVariant } from '@/types'

const router = useRouter()
const schedule = useScheduleStore()
const auth = useAuthStore()
const usersStore = useUsersStore()
const { effectiveRole } = useViewAs()

const role = computed(() => effectiveRole.value)
const canFilterByTeacher = computed(() => role.value === 'ADMIN' || role.value === 'STAFF')
const canFilterByStudent = computed(() => role.value === 'ADMIN' || role.value === 'STAFF' || role.value === 'TEACHER')
const canOverride = computed(() => role.value === 'ADMIN' || role.value === 'STAFF')

// ── Filters ──
const filterDateFrom = ref('')
const filterDateTo   = ref('')

const hasAttFilters = computed(() => !!filterDateFrom.value || !!filterDateTo.value || !!filterTeacherId.value || !!filterStudentId.value || !!filterStatus.value || !!filterSubject.value)
function clearAttFilters() { filterDateFrom.value = ''; filterDateTo.value = ''; filterTeacherId.value = ''; filterStudentId.value = ''; filterStatus.value = ''; filterSubject.value = '' }
const filterDateRange = computed({
  get: (): DateRange => ({ start: filterDateFrom.value, end: filterDateTo.value }),
  set: (v: DateRange) => { filterDateFrom.value = v.start; filterDateTo.value = v.end },
})
const filterTeacherId = ref('')
const filterStudentId = ref('')
const filterStatus   = ref('')
const filterSubject  = ref('')

// ── Status options ──
const STATUS_OPTIONS: { value: AttendanceStatus; label: string }[] = [
  { value: 'PRESENT',               label: 'Present' },
  { value: 'LATE',                  label: 'Late' },
  { value: 'ABSENT_WITH_NOTICE',    label: 'Absent with notice' },
  { value: 'ABSENT_WITHOUT_NOTICE', label: 'Absent without notice' },
  { value: 'EXCUSED',               label: 'Excused' },
  { value: 'TEACHER_ABSENT',        label: 'Teacher absent' },
  { value: 'RESCHEDULED',           label: 'Rescheduled' },
]

const statusSelectOptions = computed((): SelectOption[] => [
  { value: '', label: 'All Statuses' },
  ...STATUS_OPTIONS.map(s => ({ value: s.value, label: s.label })),
])

const teacherSelectOptions = computed((): SelectOption[] => [
  { value: '', label: 'All Teachers' },
  ...usersStore.getTeachers().map(t => ({
    value: t.id,
    label: `${t.firstName} ${t.lastName}`,
  })),
])

// Build student options from the lessons visible to this user
const studentSelectOptions = computed((): SelectOption[] => {
  const seen = new Map<string, string>()
  for (const rec of baseRecords.value) {
    if (!seen.has(rec.lesson.studentId)) {
      seen.set(rec.lesson.studentId, rec.lesson.studentName)
    }
  }
  return [
    { value: '', label: 'All Students' },
    ...[...seen.entries()].map(([id, name]) => ({ value: id, label: name })),
  ]
})

const subjectSelectOptions = computed((): SelectOption[] => {
  const subjects = [...new Set(baseRecords.value.map(r => r.lesson.subject))].sort()
  return [
    { value: '', label: 'All Subjects' },
    ...subjects.map(s => ({ value: s, label: s })),
  ]
})

// ── Records ──
type AttendanceRecord = LessonAttendance & { lesson: ScheduleLesson }

const baseRecords = computed((): AttendanceRecord[] => {
  const userId = auth.user?.id
  const r = role.value
  if (r === 'STUDENT' && userId) return schedule.getAllAttendance({ studentId: userId })
  if (r === 'TEACHER' && userId) return schedule.getAllAttendance({ teacherId: userId })
  return schedule.getAllAttendance()
})

const filteredRecords = computed((): AttendanceRecord[] => {
  return baseRecords.value.filter(rec => {
    const lessonDate = rec.lesson.startTime.slice(0, 10)
    if (filterDateFrom.value && lessonDate < filterDateFrom.value) return false
    if (filterDateTo.value   && lessonDate > filterDateTo.value)   return false
    if (filterTeacherId.value && rec.lesson.teacherId !== filterTeacherId.value) return false
    if (filterStudentId.value && rec.lesson.studentId !== filterStudentId.value) return false
    if (filterStatus.value && rec.studentStatus !== filterStatus.value && rec.teacherStatus !== filterStatus.value) return false
    if (filterSubject.value && rec.lesson.subject !== filterSubject.value) return false
    return true
  })
})

// ── Table ──
const columns = computed((): DataTableColumn[] => {
  const cols: DataTableColumn[] = [
    { key: 'lesson',        label: 'Lesson',          sortable: true, sortKey: '_dateRaw', width: '22%' },
  ]
  if (canFilterByStudent.value) {
    cols.push({ key: 'student', label: 'Student', sortable: true, sortKey: '_studentName', width: '14%' })
  }
  if (canFilterByTeacher.value) {
    cols.push({ key: 'teacher', label: 'Teacher', sortable: true, sortKey: '_teacherName', width: '14%' })
  }
  cols.push(
    { key: 'studentStatus', label: 'Student Status', sortable: true,  sortKey: 'studentStatus', width: '15%' },
    { key: 'teacherStatus', label: 'Teacher Status', sortable: true,  sortKey: 'teacherStatus', width: '15%', hide: 'sm' },
    { key: 'comments',      label: 'Notes',          sortable: false,                           width: '20%', hide: 'md' },
  )
  if (canOverride.value) {
    cols.push({ key: 'actions', label: '', stopClick: true, width: '5%' })
  }
  return cols
})

const tableRows = computed(() =>
  filteredRecords.value.map(rec => ({
    ...rec,
    _subject:     rec.lesson.subject,
    _date:        formatDate(rec.lesson.startTime),
    _dateRaw:     rec.lesson.startTime,
    _studentName: rec.lesson.studentName,
    _teacherName: rec.lesson.teacherName,
  })),
)

// ── Stats ──
const ICONS = {
  total:   `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2.5" y="3.5" width="15" height="14" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M6.5 2v3M13.5 2v3M2.5 7.5h15" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="7" cy="13" r="1" fill="currentColor"/><circle cx="10" cy="13" r="1" fill="currentColor"/><circle cx="13" cy="13" r="1" fill="currentColor"/></svg>`,
  present: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.4"/><path d="M6.5 10l2.5 2.5 4-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  absent:  `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.4"/><path d="M7 7l6 6M13 7l-6 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  late:    `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.4"/><path d="M10 6v4l2.5 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  rate:    `<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 15L8 9l3 4 3-5 4 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
}

interface StatCard { label: string; value: string; sub: string; trendUp: boolean; icon: string; iconClass: string }

const statCards = computed((): StatCard[] => {
  const recs = filteredRecords.value
  const total   = recs.length
  const present = recs.filter(r => r.studentStatus === 'PRESENT').length
  const absent  = recs.filter(r => r.studentStatus === 'ABSENT_WITHOUT_NOTICE' || r.studentStatus === 'ABSENT_WITH_NOTICE').length
  const late    = recs.filter(r => r.studentStatus === 'LATE').length
  const rate    = total > 0 ? Math.round(((present + late) / total) * 100) : 0

  return [
    { label: 'Total Records',    value: String(total),    sub: 'Attendance entries',      trendUp: false, icon: ICONS.total,   iconClass: 'icon-badge--teal'    },
    { label: 'Present',          value: String(present),  sub: `${total > 0 ? Math.round((present / total) * 100) : 0}% of sessions`, trendUp: true,  icon: ICONS.present, iconClass: 'icon-badge--success' },
    { label: 'Absent',           value: String(absent),   sub: 'With or without notice',  trendUp: false, icon: ICONS.absent,  iconClass: 'icon-badge--warning' },
    { label: 'Late',             value: String(late),     sub: 'Arrived late',            trendUp: false, icon: ICONS.late,    iconClass: 'icon-badge--warning' },
    { label: 'Attendance Rate',  value: `${rate}%`,       sub: 'Present + late sessions', trendUp: rate >= 80, icon: ICONS.rate, iconClass: 'icon-badge--primary' },
  ]
})

// ── Helpers ──
function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-PH', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function attStatusLabel(status: AttendanceStatus): string {
  return STATUS_OPTIONS.find(s => s.value === status)?.label ?? status
}

function attBadgeVariant(status: AttendanceStatus): BadgeVariant {
  if (status === 'PRESENT') return 'active'
  if (status === 'LATE') return 'warning'
  if (status === 'ABSENT_WITHOUT_NOTICE' || status === 'TEACHER_ABSENT') return 'missed'
  if (status === 'ABSENT_WITH_NOTICE') return 'warning'
  if (status === 'RESCHEDULED') return 'pending'
  return 'neutral'
}

// ── Override modal ──
interface OverrideModal {
  open: boolean
  record: AttendanceRecord | null
}

const overrideModal = reactive<OverrideModal>({ open: false, record: null })
const overrideForm = reactive({
  studentStatus: 'PRESENT' as AttendanceStatus,
  teacherStatus: 'PRESENT' as AttendanceStatus,
  absenceReason: '',
  comments: '',
})

function openOverride(rec: AttendanceRecord) {
  overrideModal.record = rec
  overrideForm.studentStatus = rec.studentStatus
  overrideForm.teacherStatus = rec.teacherStatus
  overrideForm.absenceReason = rec.absenceReason ?? ''
  overrideForm.comments      = rec.comments ?? ''
  overrideModal.open = true
}

function closeOverride() {
  overrideModal.open   = false
  overrideModal.record = null
}

function submitOverride() {
  if (!overrideModal.record || !auth.user) return
  schedule.overrideAttendance(
    overrideModal.record.lessonId,
    overrideForm.teacherStatus,
    overrideForm.studentStatus,
    overrideForm.absenceReason,
    auth.user.id,
    overrideForm.comments,
  )
  closeOverride()
}

onMounted(() => {
  if (!usersStore.users.length) usersStore.fetchUsers()
})
</script>

<style scoped>
.att-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

/* Header */
.att-page__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--tv-space-4);
  flex-wrap: wrap;
}

.att-page__title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
  margin: 0 0 var(--tv-space-1);
}

.att-page__subtitle {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: 0;
}

/* Filters */
.att-page__filters {
  display: flex;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
  align-items: flex-end;
}
.att-page__filters > .tvdrp-wrap { width: 200px; flex-shrink: 0; }

.att-page__filter-selects {
  display: flex;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
  align-items: flex-end;
}

.att-page__filter-selects > * { width: 160px; flex-shrink: 0; }
.att-clear-btn {
  font-size: var(--tv-text-sm); color: var(--tv-text-muted); background: none;
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius);
  padding: 0 var(--tv-space-3); min-height: 42px; min-width: 72px; display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; white-space: nowrap; transition: color 0.15s, background 0.15s;
}
.att-clear-btn:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

/* Table cells */
.att-lesson-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.att-lesson-cell__subject {
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
}

.att-lesson-cell__date {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
}

.att-name {
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
}

.att-comments-cell {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.att-comments-cell__reason {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-secondary);
  line-height: 1.4;
}

.att-comments-cell__note {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  font-style: italic;
}

.att-override-pill {
  display: inline-block;
  font-size: 10px;
  font-weight: var(--tv-font-bold);
  padding: 1px 6px;
  border-radius: var(--tv-radius-full);
  background: hsl(38, 90%, 88%);
  color: hsl(30, 80%, 32%);
  border: 1px solid hsl(38, 75%, 72%);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.tv-dt-muted {
  color: var(--tv-text-muted);
}

/* Actions */
.att-actions {
  display: flex;
  gap: var(--tv-space-1);
  align-items: center;
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: var(--tv-radius-sm);
  border: 1px solid transparent;
  cursor: pointer;
  transition: background-color var(--tv-transition-fast), border-color var(--tv-transition-fast), filter var(--tv-transition-fast);
  flex-shrink: 0;
}

.icon-btn--edit {
  background: var(--tv-info-soft);
  border-color: var(--tv-info-border);
  color: var(--tv-info-fg);
}
.icon-btn--edit:hover { background: hsl(199, 89%, 88%); }

/* Empty state */
.att-empty-icon {
  width: 72px;
  height: 72px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
  display: flex;
  align-items: center;
  justify-content: center;
}

.att-empty-title {
  font-size: var(--tv-text-base);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
}

.att-empty-sub {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: 0;
}

/* Override modal */
.att-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: var(--tv-space-4);
}

.att-modal {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-lg);
  width: 100%;
  max-width: 520px;
  box-shadow: var(--tv-shadow-lg);
  display: flex;
  flex-direction: column;
}

.att-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--tv-space-4) var(--tv-space-5);
  border-bottom: 1px solid var(--tv-border);
}

.att-modal__title {
  font-size: var(--tv-text-base);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
}

.att-modal__close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: var(--tv-radius);
  color: var(--tv-text-muted);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background-color var(--tv-transition-fast);
}

.att-modal__close:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

.att-modal__body {
  padding: var(--tv-space-5);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
}

.att-modal__desc {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-secondary);
  line-height: 1.5;
  margin: 0;
}

.att-modal__date { color: var(--tv-text-muted); }

.att-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--tv-space-3);
}

.att-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.att-label {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-muted);
}

.att-input {
  height: 36px;
  padding: 0 var(--tv-space-3);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius);
  background: var(--tv-bg-input);
  color: var(--tv-text);
  font-size: var(--tv-text-sm);
  outline: none;
  transition: border-color var(--tv-transition-fast);
}

.att-input:focus { border-color: var(--tv-primary); }

.att-textarea {
  padding: var(--tv-space-2) var(--tv-space-3);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius);
  background: var(--tv-bg-input);
  color: var(--tv-text);
  font-size: var(--tv-text-sm);
  font-family: inherit;
  resize: vertical;
  outline: none;
  transition: border-color var(--tv-transition-fast);
}

.att-textarea:focus { border-color: var(--tv-primary); }

.att-modal__footer {
  display: flex;
  justify-content: flex-end;
  gap: var(--tv-space-2);
  padding: var(--tv-space-4) var(--tv-space-5);
  border-top: 1px solid var(--tv-border);
}

/* Stats — 5 in one line, collapses gracefully */
.att-stats {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: var(--tv-space-4);
}

.att-stat-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
  transition: box-shadow var(--tv-transition), transform var(--tv-transition);
}

.att-stat-card:hover { box-shadow: var(--tv-shadow); transform: translateY(-1px); }

.att-stat-card__content {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: var(--tv-space-4) var(--tv-space-4);
  gap: var(--tv-space-3);
}

.att-stat-card__text { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.att-stat-card__label { font-size: var(--tv-text-xs); color: var(--tv-text-secondary); font-weight: var(--tv-font-medium); }
.att-stat-card__value { font-size: var(--tv-text-2xl); font-weight: var(--tv-font-bold); color: var(--tv-text); line-height: 1.1; }
.att-stat-card__sub { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.att-stat-card__sub--up { color: var(--tv-success); }

.att-stat-card__icon-wrap {
  width: 38px;
  height: 38px;
  border-radius: var(--tv-radius-md);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.att-stat-card__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}

@media (max-width: 1280px) { .att-stats { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 900px)  { .att-stats { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 640px)  { .att-stats { grid-template-columns: repeat(2, 1fr); gap: var(--tv-space-3); } }
@media (max-width: 400px)  { .att-stats { grid-template-columns: 1fr; } }

@media (max-width: 767px) {
  .att-page { padding: var(--tv-space-4); }
  .att-page__filter-selects > * { width: auto; flex: 1; min-width: 130px; }
  .att-form-row { grid-template-columns: 1fr; }
}
</style>
