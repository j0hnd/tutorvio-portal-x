<template>
  <div class="hw-page">

    <!-- Header -->
    <div class="hw-header">
      <div class="hw-header__left">
        <h1 class="hw-title">Homework</h1>
        <p class="hw-subtitle">{{ headerSubtitle }}</p>
      </div>
    </div>

    <!-- Stats (teacher / admin) -->
    <StatsGrid v-if="canViewAll" :stats="statCards" :columns="5" />

    <!-- Filters -->
    <div class="hw-filters">
      <div class="hw-search-wrap">
        <svg class="hw-search-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M10 10l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <input v-model="search" class="hw-search" type="search" placeholder="Search..." />
      </div>
      <TVSelect v-model="filterStatus" :options="statusOptions" placeholder="All Statuses" />
      <TVDatePicker v-model="filterDueDate" placeholder="Due date" />
      <TVSelect v-if="canViewAll" v-model="filterStudent" :options="studentOptions" placeholder="All Students" />
      <TVSelect v-if="role === 'ADMIN'" v-model="filterTeacher" :options="teacherOptions" placeholder="All Teachers" />
      <TVSelect v-if="canViewAll" v-model="filterLesson" :options="lessonOptions" placeholder="All Lessons" />
      <button v-if="hasActiveFilters" class="hw-clear-btn" type="button" @click="clearFilters">Clear</button>
    </div>

    <!-- Homework list -->
    <div v-if="filtered.length" class="hw-list">
      <div
        v-for="hw in paginated"
        :key="hw.id"
        :class="['hw-item', `hw-item--${hw.status.toLowerCase().replace('_', '-')}`]"
      >
        <!-- Status stripe -->
        <div :class="['hw-item__stripe', `hw-item__stripe--${hw.status.toLowerCase().replace('_', '-')}`]" />

        <div class="hw-item__main">
          <!-- Top row -->
          <div class="hw-item__top">
            <div class="hw-item__title-group">
              <span class="hw-item__title">{{ hw.title }}</span>
              <span :class="['hw-status', `hw-status--${hw.status.toLowerCase().replace('_', '-')}`]">
                {{ statusLabel(hw.status) }}
              </span>
              <span v-if="isOverdueWarning(hw)" class="hw-status hw-status--overdue">Overdue</span>
            </div>
            <div class="hw-item__actions">
              <!-- Student actions -->
              <template v-if="canStartHomework(hw)">
                <button class="hw-btn hw-btn--ghost" type="button" @click="doMarkInProgress(hw.id)">Start</button>
              </template>
              <template v-if="canSubmitHomework(hw)">
                <button class="hw-btn hw-btn--primary" type="button" @click="openSubmit(hw)">Submit</button>
              </template>
              <!-- Teacher/Admin actions -->
              <template v-if="canReviewHomework(hw) && hw.status === 'SUBMITTED'">
                <button class="hw-btn hw-btn--primary" type="button" @click="openFeedback(hw)">Review</button>
              </template>
              <template v-if="canReviewHomework(hw) && hw.status === 'REVIEWED'">
                <button class="hw-btn hw-btn--ghost" type="button" @click="openFeedback(hw)">Edit Feedback</button>
              </template>
            </div>
          </div>

          <!-- Meta -->
          <div class="hw-item__meta">
            <span v-if="canViewAll" class="hw-item__student">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <circle cx="6" cy="4" r="2.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M1.5 10.5c0-2.485 2.015-4.5 4.5-4.5s4.5 2.015 4.5 4.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
              {{ hw.studentName }}
            </span>
            <span v-if="role === 'ADMIN'" class="hw-item__teacher">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <rect x="1" y="2" width="10" height="8" rx="1.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M4 5h4M4 7h2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
              {{ hw.teacherName }}
            </span>
            <span class="hw-item__due" :class="{ 'hw-item__due--overdue': isOverdueWarning(hw) }">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.2"/>
                <path d="M6 3.5v3l2 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
              </svg>
              Due {{ formatDate(hw.dueDate) }}
            </span>
            <router-link v-if="hw.lessonId" :to="{ name: 'LessonDetail', params: { id: hw.lessonId } }" class="hw-lesson-link">
              View Lesson →
            </router-link>
          </div>

          <!-- Description -->
          <p class="hw-item__desc">{{ hw.description }}</p>

          <!-- Attached files -->
          <div v-if="hw.attachedFiles?.length" class="hw-item__files">
            <span class="hw-item__files-label">Attached:</span>
            <a
              v-for="(file, i) in hw.attachedFiles"
              :key="i"
              :href="file"
              target="_blank"
              rel="noopener"
              class="hw-file-link"
            >
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <path d="M2 1.5h5.5L10 4v6.5a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-9A.5.5 0 0 1 2 1.5z" stroke="currentColor" stroke-width="1.1"/>
                <path d="M7.5 1.5V4H10" stroke="currentColor" stroke-width="1.1"/>
              </svg>
              Attachment {{ i + 1 }}
            </a>
          </div>

          <!-- Submission -->
          <div v-if="hw.submissionUrl" class="hw-item__submission">
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
              <path d="M6 1v7M3 5l3 3 3-3M2 10h8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
            </svg>
            <a :href="hw.submissionUrl" target="_blank" rel="noopener" class="hw-submit-link">View Submission</a>
            <span v-if="hw.submittedAt" class="hw-item__submitted-at">· Submitted {{ formatDateTime(hw.submittedAt) }}</span>
          </div>

          <!-- Feedback -->
          <div v-if="hw.feedback" class="hw-item__feedback">
            <div class="hw-feedback__header">
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <path d="M1.5 2.5h9a.5.5 0 0 1 .5.5v5a.5.5 0 0 1-.5.5H7l-2 2-2-2H1.5a.5.5 0 0 1-.5-.5V3a.5.5 0 0 1 .5-.5z" stroke="currentColor" stroke-width="1.1"/>
              </svg>
              <span class="hw-feedback__label">Teacher Feedback</span>
              <span v-if="hw.grade" class="hw-grade">{{ hw.grade }}</span>
            </div>
            <p class="hw-feedback__text">{{ hw.feedback }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <TVPagination
      v-if="filtered.length > HW_PAGE_SIZE"
      v-model="hwPage"
      :total="filtered.length"
      :page-size="HW_PAGE_SIZE"
    />

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="hw-empty">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
        <rect x="6" y="4" width="24" height="32" rx="3" stroke="currentColor" stroke-width="1.5"/>
        <path d="M13 14h10M13 20h10M13 26h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
        <path d="M26 28l4 4M28 30l-4 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
      </svg>
      <p>No homework found{{ hasActiveFilters ? ' matching your filters' : '' }}.</p>
      <button v-if="hasActiveFilters" class="hw-clear-btn" type="button" @click="clearFilters">Clear filters</button>
    </div>

    <!-- Submit Modal (student) -->
    <TVModal v-model="showSubmitModal" title="Submit Homework" maxWidth="480px">
      <p class="hw-modal-desc">{{ submittingHw?.title }}</p>
      <TVInput v-model="submitUrl" label="Submission Link" placeholder="https://drive.google.com/..." hint="Paste a link to your file, Google Doc, or recording." />
      <template #footer>
        <button class="hw-btn hw-btn--ghost" type="button" @click="showSubmitModal = false">Cancel</button>
        <button class="hw-btn hw-btn--primary" type="button" @click="doSubmit">Submit</button>
      </template>
    </TVModal>

    <!-- Feedback Modal (teacher / admin) -->
    <TVModal v-model="showFeedbackModal" title="Add Feedback" maxWidth="480px">
      <p class="hw-modal-desc">{{ feedbackHw?.title }} — <strong>{{ feedbackHw?.studentName }}</strong></p>
      <div class="hw-modal-field">
        <label class="hw-modal-label">Feedback</label>
        <textarea v-model="feedbackText" class="hw-textarea" rows="4" placeholder="Write your feedback for the student..." />
      </div>
      <TVInput v-model="feedbackGrade" label="Grade (optional)" placeholder="e.g. A, B+, 90%" />
      <template #footer>
        <button class="hw-btn hw-btn--ghost" type="button" @click="showFeedbackModal = false">Cancel</button>
        <button class="hw-btn hw-btn--primary" type="button" :disabled="!feedbackText.trim()" @click="doFeedback">Save Feedback</button>
      </template>
    </TVModal>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useScheduleStore } from '@/stores/schedule'
import { useAuthStore } from '@/stores/auth'
import { useUsersStore } from '@/stores/users'
import { useViewAs } from '@/composables/useViewAs'
import { useToast } from '@/composables/useToast'
import TVModal from '@/components/ui/TVModal.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVDatePicker from '@/components/ui/TVDatePicker.vue'
import StatsGrid from '@/components/dashboard/StatsGrid.vue'
import TVPagination from '@/components/ui/TVPagination.vue'
import type { StatItem } from '@/components/dashboard/StatsGrid.vue'
import type { LessonHomework, HomeworkStatus } from '@/stores/schedule'

const schedule   = useScheduleStore()
const auth       = useAuthStore()
const usersStore = useUsersStore()
const toast      = useToast()
const { effectiveRole } = useViewAs()

const role   = computed(() => effectiveRole.value)
const userId = computed(() => auth.user?.id ?? '')

// ── Can functions ──
const canViewAll = computed(() => ['ADMIN', 'TEACHER', 'STAFF'].includes(role.value))

function canStartHomework(hw: LessonHomework): boolean {
  return role.value === 'STUDENT' && hw.studentId === userId.value && hw.status === 'PENDING'
}

function canSubmitHomework(hw: LessonHomework): boolean {
  return role.value === 'STUDENT' && hw.studentId === userId.value &&
    ['PENDING', 'IN_PROGRESS'].includes(hw.status)
}

function canReviewHomework(hw: LessonHomework): boolean {
  return role.value === 'ADMIN' ||
    (role.value === 'TEACHER' && hw.teacherId === userId.value)
}

function isOverdueWarning(hw: LessonHomework): boolean {
  return ['PENDING', 'IN_PROGRESS'].includes(hw.status) && hw.dueDate < today
}

// ── Data ──
const today = new Date().toLocaleDateString('sv-SE')

const homework = computed(() => {
  const actualRole = auth.user?.role
  if (actualRole === 'ADMIN' || actualRole === 'STAFF') return schedule.getAllHomework()
  if (actualRole === 'TEACHER') return schedule.getHomeworkForTeacher(userId.value)
  return schedule.getHomeworkForStudent(userId.value)
})

// ── Filter options ──
const statusOptions = [
  { value: '',            label: 'All Statuses' },
  { value: 'PENDING',     label: 'Pending' },
  { value: 'IN_PROGRESS', label: 'In Progress' },
  { value: 'SUBMITTED',   label: 'Submitted' },
  { value: 'REVIEWED',    label: 'Reviewed' },
  { value: 'OVERDUE',     label: 'Overdue' },
]

const studentOptions = computed(() => {
  const names = [...new Set(homework.value.map(h => h.studentName))]
  return [{ value: '', label: 'All Students' }, ...names.map(n => ({ value: n, label: n }))]
})

const teacherOptions = computed(() => {
  const names = [...new Set(homework.value.map(h => h.teacherName).filter(Boolean))] as string[]
  return [{ value: '', label: 'All Teachers' }, ...names.map(n => ({ value: n, label: n }))]
})

const lessonOptions = computed(() => {
  const seen = new Map<string, string>()
  homework.value.forEach(h => {
    if (h.lessonId && !seen.has(h.lessonId)) {
      const lesson = schedule.getLesson(h.lessonId)
      seen.set(h.lessonId, lesson?.title ?? h.lessonId)
    }
  })
  return [
    { value: '', label: 'All Lessons' },
    ...[...seen.entries()].map(([id, title]) => ({ value: id, label: title })),
  ]
})

// ── Filters ──
const search        = ref('')
const filterStatus  = ref<HomeworkStatus | ''>('')
const filterDueDate = ref('')
const filterStudent = ref('')
const filterTeacher = ref('')
const filterLesson  = ref('')

const hasActiveFilters = computed(() =>
  !!search.value || !!filterStatus.value || !!filterDueDate.value ||
  !!filterStudent.value || !!filterTeacher.value || !!filterLesson.value
)

function clearFilters(): void {
  search.value = ''; filterStatus.value = ''; filterDueDate.value = ''
  filterStudent.value = ''; filterTeacher.value = ''; filterLesson.value = ''
}

const HW_PAGE_SIZE = 10
const hwPage = ref(1)

const filtered = computed(() => {
  const q = search.value.toLowerCase().trim()
  return homework.value.filter(hw => {
    if (q && !hw.title.toLowerCase().includes(q) && !hw.studentName.toLowerCase().includes(q)) return false
    if (filterStatus.value && hw.status !== filterStatus.value) return false
    if (filterDueDate.value && hw.dueDate !== filterDueDate.value) return false
    if (filterStudent.value && hw.studentName !== filterStudent.value) return false
    if (filterTeacher.value && hw.teacherName !== filterTeacher.value) return false
    if (filterLesson.value && hw.lessonId !== filterLesson.value) return false
    return true
  })
})

const paginated = computed(() =>
  filtered.value.slice((hwPage.value - 1) * HW_PAGE_SIZE, hwPage.value * HW_PAGE_SIZE)
)

watch(filtered, () => { hwPage.value = 1 })

// ── Stats ──
const stats = computed(() => {
  const all = homework.value
  return {
    total:      all.length,
    pending:    all.filter(h => h.status === 'PENDING').length,
    inProgress: all.filter(h => h.status === 'IN_PROGRESS').length,
    submitted:  all.filter(h => h.status === 'SUBMITTED').length,
    reviewed:   all.filter(h => h.status === 'REVIEWED').length,
    overdue:    all.filter(h => h.status === 'OVERDUE' || isOverdueWarning(h)).length,
  }
})

const ICONS_HW = {
  total:     `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><rect x="4" y="2" width="12" height="16" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M7 7h6M7 10h6M7 13h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  pending:   `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.4"/><path d="M10 6v4l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  submitted: `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M10 3v10M6 9l4 4 4-4M4 17h12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  reviewed:  `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 10l4 4 8-8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  overdue:   `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.4"/><path d="M10 6v4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="10" cy="14" r="1" fill="currentColor"/></svg>`,
}

const statCards = computed<StatItem[]>(() => {
  const s = stats.value
  const rate = s.total ? Math.round((s.reviewed / s.total) * 100) : 0
  return [
    { label: 'Total Assignments', value: String(s.total),     sub: `${s.inProgress} in progress`,   trendUp: false,    icon: ICONS_HW.total,     iconClass: 'icon-badge--blue' },
    { label: 'Pending',           value: String(s.pending),   sub: 'not started yet',                trendUp: false,    icon: ICONS_HW.pending,   iconClass: 'icon-badge--gray' },
    { label: 'Submitted',         value: String(s.submitted), sub: 'awaiting review',                trendUp: true,     icon: ICONS_HW.submitted, iconClass: 'icon-badge--primary' },
    { label: 'Reviewed',          value: String(s.reviewed),  sub: `${rate}% completion rate`,       trendUp: rate > 50, icon: ICONS_HW.reviewed,  iconClass: 'icon-badge--green' },
    { label: 'Overdue',           value: String(s.overdue),   sub: 'past due date',                  trendUp: false,    icon: ICONS_HW.overdue,   iconClass: 'icon-badge--red' },
  ]
})

// ── Header ──
const headerSubtitle = computed(() => {
  if (role.value === 'STUDENT') return 'Your assigned homework and tasks'
  if (role.value === 'TEACHER') return 'Homework assigned to your students'
  return 'All homework across all students and teachers'
})

// ── Actions ──
function doMarkInProgress(hwId: string): void {
  schedule.markHomeworkInProgress(hwId)
  toast.success('Marked as in progress.')
}

// Submit modal
const showSubmitModal = ref(false)
const submittingHw   = ref<LessonHomework | null>(null)
const submitUrl      = ref('')

function openSubmit(hw: LessonHomework): void {
  submittingHw.value = hw
  submitUrl.value = hw.submissionUrl ?? ''
  showSubmitModal.value = true
}

function doSubmit(): void {
  if (!submittingHw.value) return
  schedule.submitHomework(submittingHw.value.id, submitUrl.value.trim() || undefined)
  showSubmitModal.value = false
  toast.success('Homework submitted!')
}

// Feedback modal
const showFeedbackModal = ref(false)
const feedbackHw        = ref<LessonHomework | null>(null)
const feedbackText      = ref('')
const feedbackGrade     = ref('')

function openFeedback(hw: LessonHomework): void {
  feedbackHw.value    = hw
  feedbackText.value  = hw.feedback ?? ''
  feedbackGrade.value = hw.grade ?? ''
  showFeedbackModal.value = true
}

function doFeedback(): void {
  if (!feedbackHw.value || !feedbackText.value.trim()) return
  schedule.addHomeworkFeedback(feedbackHw.value.id, feedbackText.value.trim(), feedbackGrade.value.trim() || undefined)
  showFeedbackModal.value = false
  toast.success('Feedback saved!')
}

// ── Helpers ──
function statusLabel(s: HomeworkStatus): string {
  switch (s) {
    case 'PENDING':     return 'Pending'
    case 'IN_PROGRESS': return 'In Progress'
    case 'SUBMITTED':   return 'Submitted'
    case 'REVIEWED':    return 'Reviewed'
    case 'OVERDUE':     return 'Overdue'
  }
}

function formatDate(iso: string): string {
  return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })
}
</script>

<style scoped>
.hw-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}
@media (max-width: 767px) { .hw-page { padding: var(--tv-space-4); } }

/* Header */
.hw-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: var(--tv-space-4); }
.hw-title { font-size: var(--tv-text-2xl); font-weight: var(--tv-font-bold); color: var(--tv-text); margin: 0; }
.hw-subtitle { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: var(--tv-space-1) 0 0; }

/* Stats */
.hw-stats {
  display: flex;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
}
.hw-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-3) var(--tv-space-5);
  min-width: 80px;
  flex: 1;
}
.hw-stat__val   { font-size: var(--tv-text-2xl); font-weight: var(--tv-font-bold); color: var(--tv-text); line-height: 1; }
.hw-stat__label { font-size: var(--tv-text-xs); color: var(--tv-text-muted); font-weight: var(--tv-font-medium); white-space: nowrap; }
.hw-stat--pending   .hw-stat__val { color: var(--tv-text-muted); }
.hw-stat--progress  .hw-stat__val { color: hsl(220, 65%, 50%); }
.hw-stat--submitted .hw-stat__val { color: var(--tv-primary); }
.hw-stat--reviewed  .hw-stat__val { color: var(--tv-success-fg); }
.hw-stat--overdue   .hw-stat__val { color: var(--tv-danger-fg); }

/* Filters */
.hw-filters { display: flex; flex-wrap: wrap; gap: var(--tv-space-2); align-items: center; }
.hw-search-wrap { position: relative; width: 200px; flex-shrink: 0; }
.hw-search-icon { position: absolute; left: var(--tv-space-3); top: 50%; transform: translateY(-50%); color: var(--tv-text-muted); pointer-events: none; }
.hw-search {
  width: 100%; padding: var(--tv-space-2) var(--tv-space-3) var(--tv-space-2) calc(var(--tv-space-3) + 20px);
  font-size: var(--tv-text-sm); color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius); min-height: 38px;
  outline: none; transition: border-color 0.15s; font-family: inherit; box-sizing: border-box;
}
.hw-search:focus { border-color: var(--tv-primary); }
.hw-clear-btn {
  font-size: var(--tv-text-sm); color: var(--tv-text-muted);
  background: none; border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-2) var(--tv-space-3);
  cursor: pointer; white-space: nowrap; transition: color 0.15s, background 0.15s;
}
.hw-clear-btn:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

/* Meta */
.hw-meta { display: flex; align-items: center; gap: var(--tv-space-2); }
.hw-count { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }
.hw-filtered-note { font-size: var(--tv-text-sm); color: var(--tv-text-muted); }

/* List */
.hw-list { display: flex; flex-direction: column; gap: var(--tv-space-3); }

/* Item */
.hw-item {
  display: flex;
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  overflow: hidden;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.hw-item:hover { border-color: var(--tv-primary-muted); box-shadow: 0 2px 8px hsla(var(--tv-primary-h), var(--tv-primary-s), 50%, 0.07); }

.hw-item__stripe { width: 4px; flex-shrink: 0; }
.hw-item__stripe--pending     { background: var(--tv-border); }
.hw-item__stripe--in-progress { background: hsl(220, 65%, 55%); }
.hw-item__stripe--submitted   { background: var(--tv-primary); }
.hw-item__stripe--reviewed    { background: var(--tv-success-fg); }
.hw-item__stripe--overdue     { background: var(--tv-danger-fg); }

.hw-item__main {
  flex: 1;
  min-width: 0;
  padding: var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.hw-item__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
}
.hw-item__title-group { display: flex; align-items: center; gap: var(--tv-space-2); flex-wrap: wrap; flex: 1; }
.hw-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }

/* Status badges */
.hw-status {
  font-size: 10px; font-weight: var(--tv-font-semibold);
  padding: 2px 8px; border-radius: var(--tv-radius-full); border: 1px solid;
  white-space: nowrap;
}
.hw-status--pending     { background: var(--tv-bg-soft); color: var(--tv-text-muted); border-color: var(--tv-border); }
.hw-status--in-progress { background: hsl(220,80%,95%); color: hsl(220,65%,40%); border-color: hsl(220,65%,80%); }
.hw-status--submitted   { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h),var(--tv-primary-s),38%); border-color: var(--tv-primary-muted); }
.hw-status--reviewed    { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.hw-status--overdue     { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }

/* Item meta */
.hw-item__meta {
  display: flex; align-items: center; gap: var(--tv-space-3);
  flex-wrap: wrap; font-size: var(--tv-text-xs); color: var(--tv-text-muted);
}
.hw-item__student, .hw-item__teacher, .hw-item__due {
  display: inline-flex; align-items: center; gap: 4px;
  font-weight: var(--tv-font-medium);
}
.hw-item__due--overdue { color: var(--tv-danger-fg); }
.hw-lesson-link { color: var(--tv-primary); text-decoration: none; font-size: var(--tv-text-xs); }
.hw-lesson-link:hover { text-decoration: underline; }

/* Description */
.hw-item__desc {
  font-size: var(--tv-text-sm); color: var(--tv-text-secondary);
  line-height: 1.6; margin: 0;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* Files */
.hw-item__files { display: flex; align-items: center; gap: var(--tv-space-2); flex-wrap: wrap; }
.hw-item__files-label { font-size: var(--tv-text-xs); color: var(--tv-text-muted); font-weight: var(--tv-font-medium); }
.hw-file-link {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: var(--tv-text-xs); color: var(--tv-primary);
  background: var(--tv-primary-soft); border: 1px solid var(--tv-primary-muted);
  border-radius: var(--tv-radius-sm); padding: 2px 8px; text-decoration: none;
  transition: background 0.15s;
}
.hw-file-link:hover { background: hsl(var(--tv-primary-h),70%,90%); }

/* Submission */
.hw-item__submission {
  display: flex; align-items: center; gap: var(--tv-space-2);
  font-size: var(--tv-text-xs);
}
.hw-submit-link { color: var(--tv-primary); text-decoration: none; font-weight: var(--tv-font-medium); }
.hw-submit-link:hover { text-decoration: underline; }
.hw-item__submitted-at { color: var(--tv-text-muted); }

/* Feedback */
.hw-item__feedback {
  background: var(--tv-success-soft);
  border: 1px solid var(--tv-success-border);
  border-radius: var(--tv-radius);
  padding: var(--tv-space-3);
  display: flex; flex-direction: column; gap: var(--tv-space-1);
}
.hw-feedback__header { display: flex; align-items: center; gap: var(--tv-space-2); }
.hw-feedback__label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-bold); color: var(--tv-success-fg); text-transform: uppercase; letter-spacing: .05em; flex: 1; }
.hw-grade {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-bold); color: var(--tv-success-fg);
  background: white; border: 1px solid var(--tv-success-border);
  border-radius: var(--tv-radius-sm); padding: 1px 8px;
}
.hw-feedback__text { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.5; margin: 0; }

/* Actions */
.hw-item__actions { display: flex; align-items: center; gap: var(--tv-space-2); flex-shrink: 0; }

/* Empty */
.hw-empty {
  display: flex; flex-direction: column; align-items: center;
  gap: var(--tv-space-3); padding: var(--tv-space-10) var(--tv-space-4);
  color: var(--tv-text-muted); text-align: center;
}
.hw-empty svg { opacity: 0.35; }
.hw-empty p { font-size: var(--tv-text-sm); margin: 0; }

/* Buttons */
.hw-btn {
  display: inline-flex; align-items: center; gap: var(--tv-space-1);
  padding: var(--tv-space-2) var(--tv-space-3);
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold);
  border-radius: var(--tv-radius-sm); border: 1px solid transparent;
  cursor: pointer; font-family: inherit; transition: background 0.15s; white-space: nowrap;
}
.hw-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.hw-btn--primary { background: var(--tv-primary); color: var(--tv-text-inverse); }
.hw-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.hw-btn--ghost { background: transparent; border-color: var(--tv-border); color: var(--tv-text-secondary); }
.hw-btn--ghost:hover:not(:disabled) { background: var(--tv-bg-soft); }

/* Modal */
.hw-modal-desc { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); margin: 0; line-height: 1.5; }
.hw-modal-field { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.hw-modal-label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); }
.hw-textarea {
  padding: var(--tv-space-2) var(--tv-space-3); font-size: var(--tv-text-sm);
  color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  resize: vertical; outline: none; font-family: inherit; line-height: 1.5;
  transition: border-color 0.15s;
}
.hw-textarea:focus { border-color: var(--tv-primary); }

@media (max-width: 600px) {
  .hw-stats { gap: var(--tv-space-2); }
  .hw-stat { min-width: 60px; padding: var(--tv-space-2) var(--tv-space-3); }
  .hw-search-wrap { width: 100%; }
  .hw-item__top { flex-direction: column; }
}
</style>
