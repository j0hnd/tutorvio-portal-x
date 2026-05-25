<template>
  <div v-if="lesson" class="ld-page">

    <!-- Back -->
    <button class="ld-back" type="button" @click="router.back()">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
        <path d="M9 2L4 7l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Back to Lessons
    </button>

    <!-- Header card -->
    <div class="ld-header-card">
      <div class="ld-header-card__top">
        <div class="ld-header-card__title-row">
          <h1 class="ld-lesson-title">{{ lesson.title }}</h1>
          <span :class="['ld-status', `ld-status--${statusClass}`]">{{ statusLabel }}</span>
          <span v-if="lesson.isTrial && lesson.status !== 'TRIAL'" class="ld-badge ld-badge--trial">Trial</span>
          <span v-if="lesson.isRecurring" class="ld-badge ld-badge--recur">↻ Recurring</span>
        </div>
        <div v-if="canJoin && lesson.meetingUrl" class="ld-header-card__actions">
          <a :href="lesson.meetingUrl" target="_blank" rel="noopener" class="ld-join-btn">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <rect x="1" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/>
              <path d="M9 5.5l4-2v7l-4-2V5.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
            </svg>
            Join Meeting
          </a>
        </div>
      </div>

      <!-- Info grid -->
      <div class="ld-info-grid">
        <div class="ld-info-item">
          <span class="ld-info-label">Date</span>
          <span class="ld-info-value">{{ formattedDate }}</span>
        </div>
        <div class="ld-info-item">
          <span class="ld-info-label">Time</span>
          <span class="ld-info-value">{{ formattedStart }} – {{ formattedEnd }} ({{ durationMin }} min)</span>
        </div>
        <div class="ld-info-item">
          <span class="ld-info-label">Timezone</span>
          <span class="ld-info-value">{{ userTimezone }}</span>
        </div>
        <div class="ld-info-item">
          <span class="ld-info-label">Teacher</span>
          <span class="ld-info-value">{{ lesson.teacherName }}</span>
        </div>
        <div class="ld-info-item">
          <span class="ld-info-label">Student</span>
          <span class="ld-info-value">{{ lesson.studentName }}</span>
        </div>
        <div class="ld-info-item">
          <span class="ld-info-label">Type</span>
          <span class="ld-info-value">{{ lesson.isTrial ? 'Trial' : lesson.isRecurring ? 'Recurring' : 'One-time' }}</span>
        </div>
        <div v-if="lesson.meetingUrl && !canJoin" class="ld-info-item ld-info-item--full">
          <span class="ld-info-label">Meeting Link</span>
          <span class="ld-info-value ld-info-value--muted">{{ lesson.meetingUrl }}</span>
        </div>
      </div>

      <!-- Cancelled/Rescheduled/Missed notice -->
      <div v-if="terminalStatus" :class="['ld-notice', `ld-notice--${statusClass}`]">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/>
          <path d="M7 4v3.5M7 9.5v.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
        </svg>
        <span v-if="lesson.status === 'CANCELLED'">This lesson has been cancelled.</span>
        <span v-else-if="lesson.status === 'RESCHEDULED'">This lesson has been rescheduled. Check the updated time above.</span>
        <span v-else-if="lesson.status === 'MISSED_BY_STUDENT'">Marked as missed — student did not attend.</span>
        <span v-else-if="lesson.status === 'MISSED_BY_TEACHER'">Marked as missed — teacher did not attend.</span>
        <span v-else-if="lesson.status === 'COMPLETED'">This lesson has been completed.</span>
      </div>
    </div>

    <!-- Tabs -->
    <div class="ld-tabs" role="tablist" aria-label="Lesson sections">
      <button
        v-for="tab in visibleTabs"
        :key="tab.key"
        :class="['ld-tab', { 'ld-tab--active': activeTab === tab.key }]"
        role="tab"
        :aria-selected="activeTab === tab.key"
        type="button"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
        <span v-if="tab.count" class="ld-tab__count">{{ tab.count }}</span>
      </button>
    </div>

    <!-- Tab content -->
    <div class="ld-tab-body">

      <!-- ── Notes ── -->
      <section v-if="activeTab === 'notes'" class="ld-section">
        <div class="ld-section__header">
          <h2 class="ld-section__title">Session Notes</h2>
          <button v-if="canManageNotes" class="ld-add-btn" type="button" @click="openNoteForm()">+ Add Note</button>
        </div>

        <!-- Add/Edit form -->
        <div v-if="showNoteForm" class="ld-note-form">
          <textarea
            v-model="noteContent"
            class="ld-textarea"
            rows="4"
            placeholder="Write your session notes here…"
          />
          <div v-if="canManageNotes" class="ld-note-form__public">
            <label class="ld-checkbox">
              <input v-model="noteIsPublic" type="checkbox" class="ld-checkbox__input" />
              <span class="ld-checkbox__box" />
              Visible to student
            </label>
          </div>
          <div class="ld-note-form__actions">
            <button class="ld-btn ld-btn--ghost" type="button" @click="cancelNoteForm">Cancel</button>
            <button class="ld-btn ld-btn--primary" type="button" :disabled="!noteContent.trim()" @click="saveNote">
              {{ editingNoteId ? 'Update Note' : 'Save Note' }}
            </button>
          </div>
        </div>

        <!-- Notes list -->
        <div v-if="visibleNotes.length" class="ld-notes-list">
          <div v-for="note in visibleNotes" :key="note.id" class="ld-note-item">
            <div class="ld-note-item__header">
              <span class="ld-note-item__author">{{ note.authorName }}</span>
              <span class="ld-note-item__date">{{ formatDateTime(note.updatedAt ?? note.createdAt) }}</span>
              <span v-if="!note.isPublic" class="ld-note-item__private">Private</span>
              <div v-if="canManageNotes" class="ld-note-item__actions">
                <button class="ld-icon-btn" type="button" title="Edit" @click="openNoteForm(note)">
                  <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M9 2l2 2-7 7H2V9L9 2z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
                </button>
                <button class="ld-icon-btn ld-icon-btn--danger" type="button" title="Delete" @click="removeNote(note.id)">
                  <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2.5 2.5l8 8M10.5 2.5l-8 8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                </button>
              </div>
            </div>
            <p class="ld-note-item__body">{{ note.content }}</p>
          </div>
        </div>
        <p v-else-if="!showNoteForm" class="ld-empty">No notes for this lesson yet.</p>
      </section>

      <!-- ── Attendance ── -->
      <section v-if="activeTab === 'attendance'" class="ld-section">
        <div class="ld-section__header">
          <h2 class="ld-section__title">Attendance</h2>
          <button v-if="canMarkAttendance && !showAttendanceForm" class="ld-add-btn" type="button" @click="openAttendanceForm">
            {{ attendance ? 'Edit' : 'Mark Attendance' }}
          </button>
        </div>

        <!-- Current attendance -->
        <div v-if="attendance && !showAttendanceForm" class="ld-attendance-card">
          <div class="ld-attendance-row">
            <span class="ld-attendance-who">Teacher</span>
            <span :class="['ld-attendance-status', `ld-attendance-status--${attendance.teacherStatus.toLowerCase()}`]">
              {{ attendance.teacherStatus }}
            </span>
          </div>
          <div class="ld-attendance-row">
            <span class="ld-attendance-who">Student</span>
            <span :class="['ld-attendance-status', `ld-attendance-status--${attendance.studentStatus.toLowerCase()}`]">
              {{ attendance.studentStatus }}
            </span>
          </div>
          <div v-if="attendance.absenceReason" class="ld-attendance-reason">
            <span class="ld-attendance-reason__label">Reason</span>
            <span class="ld-attendance-reason__text">{{ attendance.absenceReason }}</span>
          </div>
          <p class="ld-attendance-meta">Marked {{ formatDateTime(attendance.markedAt) }}</p>
        </div>
        <p v-else-if="!showAttendanceForm" class="ld-empty">Attendance has not been marked yet.</p>

        <!-- Attendance form -->
        <div v-if="showAttendanceForm" class="ld-attendance-form">
          <div class="ld-form-row">
            <TVSelect v-model="attTeacher" :options="attendanceOptions" label="Teacher" />
            <TVSelect v-model="attStudent" :options="attendanceOptions" label="Student" />
          </div>
          <div class="ld-field">
            <label class="ld-label">Absence Reason (optional)</label>
            <input v-model="attReason" type="text" class="ld-input" placeholder="e.g. Student did not show, no prior notice" />
          </div>
          <div class="ld-form-actions">
            <button class="ld-btn ld-btn--ghost" type="button" @click="showAttendanceForm = false">Cancel</button>
            <button class="ld-btn ld-btn--primary" type="button" @click="saveAttendance">Save Attendance</button>
          </div>
        </div>
      </section>

      <!-- ── Homework ── -->
      <section v-if="activeTab === 'homework'" class="ld-section">
        <div class="ld-section__header">
          <h2 class="ld-section__title">Homework</h2>
          <button v-if="canAssignHomework && !showHomeworkForm" class="ld-add-btn" type="button" @click="showHomeworkForm = true">+ Assign</button>
        </div>

        <!-- Assign form -->
        <div v-if="showHomeworkForm" class="ld-hw-form">
          <TVInput v-model="hwTitle" label="Title" placeholder="e.g. Write 2 business emails" required />
          <div class="ld-field">
            <label class="ld-label">Description</label>
            <textarea v-model="hwDescription" class="ld-textarea" rows="3" placeholder="Describe the assignment…" />
          </div>
          <div class="ld-form-row">
            <TVDatePicker v-model="hwDueDate" label="Due Date" :min="today" />
          </div>
          <div class="ld-form-actions">
            <button class="ld-btn ld-btn--ghost" type="button" @click="showHomeworkForm = false">Cancel</button>
            <button class="ld-btn ld-btn--primary" type="button" :disabled="!hwTitle.trim() || !hwDueDate" @click="saveHomework">Assign</button>
          </div>
        </div>

        <!-- Homework list -->
        <div v-if="homework.length" class="ld-hw-list">
          <div v-for="hw in homework" :key="hw.id" class="ld-hw-item">
            <div class="ld-hw-item__header">
              <span class="ld-hw-item__title">{{ hw.title }}</span>
              <span :class="['ld-hw-status', `ld-hw-status--${hw.status.toLowerCase()}`]">{{ hw.status }}</span>
            </div>
            <p class="ld-hw-item__desc">{{ hw.description }}</p>
            <div class="ld-hw-item__meta">
              <span>Due: <strong>{{ hw.dueDate }}</strong></span>
              <span>Student: {{ hw.studentName }}</span>
            </div>
            <div v-if="hw.feedback" class="ld-hw-feedback">
              <span class="ld-hw-feedback__label">Feedback</span>
              <p class="ld-hw-feedback__text">{{ hw.feedback }}</p>
              <span v-if="hw.grade" class="ld-hw-grade">{{ hw.grade }}</span>
            </div>
            <div v-if="hw.submissionUrl" class="ld-hw-submission">
              <a :href="hw.submissionUrl" target="_blank" rel="noopener" class="ld-link">View Submission →</a>
            </div>
          </div>
        </div>
        <p v-else-if="!showHomeworkForm" class="ld-empty">No homework assigned for this lesson.</p>
      </section>

      <!-- ── Materials ── -->
      <section v-if="activeTab === 'materials'" class="ld-section">
        <div class="ld-section__header">
          <h2 class="ld-section__title">Materials</h2>
          <button v-if="canUploadMaterials && !showMaterialForm" class="ld-add-btn" type="button" @click="showMaterialForm = true">+ Add</button>
        </div>

        <!-- Upload form (simulated) -->
        <div v-if="showMaterialForm" class="ld-mat-form">
          <TVInput v-model="matTitle" label="Title" placeholder="e.g. Vocabulary Workbook" required />
          <div class="ld-form-row">
            <TVSelect v-model="matType" :options="materialTypeOptions" label="Type" />
            <TVInput v-model="matUrl" label="URL / Link" placeholder="https://…" />
          </div>
          <div class="ld-form-actions">
            <button class="ld-btn ld-btn--ghost" type="button" @click="showMaterialForm = false">Cancel</button>
            <button class="ld-btn ld-btn--primary" type="button" :disabled="!matTitle.trim() || !matUrl.trim()" @click="saveMaterial">Add Material</button>
          </div>
        </div>

        <!-- Materials list -->
        <div v-if="materials.length" class="ld-mat-list">
          <div v-for="mat in materials" :key="mat.id" class="ld-mat-item">
            <span :class="['ld-mat-type-icon', `ld-mat-type-icon--${mat.type.toLowerCase()}`]">
              {{ typeIcon(mat.type) }}
            </span>
            <div class="ld-mat-item__body">
              <a :href="mat.url" target="_blank" rel="noopener" class="ld-mat-item__title">{{ mat.title }}</a>
              <span class="ld-mat-item__meta">{{ mat.type }} · Uploaded by {{ mat.uploadedByName }} · {{ formatDate(mat.uploadedAt) }}</span>
            </div>
            <button v-if="canUploadMaterials" class="ld-icon-btn ld-icon-btn--danger" type="button" title="Remove" @click="removeMaterial(mat.id)">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2.5 2.5l8 8M10.5 2.5l-8 8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
            </button>
          </div>
        </div>
        <p v-else-if="!showMaterialForm" class="ld-empty">No materials for this lesson yet.</p>
      </section>

    </div>
  </div>

  <!-- Not found -->
  <div v-else class="ld-not-found">
    <p>Lesson not found.</p>
    <button class="ld-btn ld-btn--ghost" type="button" @click="router.push({ name: 'Lessons' })">Back to Lessons</button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useScheduleStore } from '@/stores/schedule'
import { useAuthStore } from '@/stores/auth'
import { useViewAs } from '@/composables/useViewAs'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVDatePicker from '@/components/ui/TVDatePicker.vue'
import type { LessonNote, AttendanceStatus, MaterialType } from '@/stores/schedule'

const route    = useRoute()
const router   = useRouter()
const schedule = useScheduleStore()
const auth     = useAuthStore()
const { effectiveRole } = useViewAs()

const lessonId = route.params.id as string
const lesson   = computed(() => schedule.getLesson(lessonId))

// ── Role guards ──
const role = computed(() => effectiveRole.value)
const userId = computed(() => auth.user?.id ?? '')

const canManageNotes    = computed(() => ['ADMIN', 'TEACHER'].includes(role.value))
const canMarkAttendance = computed(() => ['ADMIN', 'TEACHER'].includes(role.value))
const canAssignHomework = computed(() => ['ADMIN', 'TEACHER'].includes(role.value))
const canUploadMaterials = computed(() => ['ADMIN', 'TEACHER'].includes(role.value))

// Staff with MANAGE_SCHEDULE permission can view but not edit
// (Notes/attendance/hw/materials are view-only for STAFF & STUDENT unless noted above)

// ── Status helpers ──
const statusClass = computed(() => {
  switch (lesson.value?.status) {
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
})

const statusLabel = computed(() => {
  switch (lesson.value?.status) {
    case 'SCHEDULED':            return 'Scheduled'
    case 'IN_PROGRESS':          return 'Live'
    case 'COMPLETED':            return 'Completed'
    case 'CANCELLED':            return 'Cancelled'
    case 'MISSED_BY_STUDENT':    return 'Missed by Student'
    case 'MISSED_BY_TEACHER':    return 'Missed by Teacher'
    case 'TRIAL':                return 'Trial'
    case 'RESCHEDULED':          return 'Rescheduled'
    case 'PENDING_CONFIRMATION': return 'Pending Confirmation'
    default:                     return lesson.value?.status ?? ''
  }
})

const terminalStatus = computed(() =>
  ['CANCELLED', 'RESCHEDULED', 'MISSED_BY_STUDENT', 'MISSED_BY_TEACHER', 'COMPLETED'].includes(lesson.value?.status ?? '')
)

// ── Formatted fields ──
const userTimezone = computed(() => auth.user?.timezone ?? 'Asia/Manila')

const formattedDate  = computed(() => lesson.value
  ? new Date(lesson.value.startTime).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) : '')
const formattedStart = computed(() => lesson.value
  ? new Date(lesson.value.startTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '')
const formattedEnd   = computed(() => lesson.value
  ? new Date(lesson.value.endTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '')
const durationMin    = computed(() => lesson.value
  ? Math.round((new Date(lesson.value.endTime).getTime() - new Date(lesson.value.startTime).getTime()) / 60_000) : 0)

const canJoin = computed(() => {
  if (!lesson.value || !['SCHEDULED', 'IN_PROGRESS', 'TRIAL'].includes(lesson.value.status)) return false
  const now   = new Date()
  const start = new Date(lesson.value.startTime)
  const end   = new Date(lesson.value.endTime)
  return now >= new Date(start.getTime() - 15 * 60_000) && now <= end
})

// ── Data ──
const notes      = computed(() => schedule.getNotesForLesson(lessonId))
const attendance = computed(() => schedule.getAttendanceForLesson(lessonId))
const materials  = computed(() => schedule.getMaterialsForLesson(lessonId))
const homework   = computed(() => schedule.getHomeworkForLesson(lessonId))

// Notes visible to current user
const visibleNotes = computed(() => {
  if (canManageNotes.value) return notes.value
  // Students only see public notes
  return notes.value.filter(n => n.isPublic)
})

// ── Tabs ──
type TabKey = 'notes' | 'attendance' | 'homework' | 'materials'
const activeTab = ref<TabKey>('notes')

const visibleTabs = computed(() => {
  const tabs: { key: TabKey; label: string; count?: number }[] = [
    { key: 'notes',      label: 'Notes',      count: visibleNotes.value.length || undefined },
    { key: 'attendance', label: 'Attendance' },
    { key: 'homework',   label: 'Homework',   count: homework.value.length || undefined },
    { key: 'materials',  label: 'Materials',  count: materials.value.length || undefined },
  ]
  return tabs
})

// ── Notes form ──
const showNoteForm    = ref(false)
const editingNoteId   = ref<string | null>(null)
const noteContent     = ref('')
const noteIsPublic    = ref(true)

function openNoteForm(note?: LessonNote): void {
  editingNoteId.value = note?.id ?? null
  noteContent.value   = note?.content ?? ''
  noteIsPublic.value  = note?.isPublic ?? true
  showNoteForm.value  = true
}

function cancelNoteForm(): void {
  showNoteForm.value  = false
  editingNoteId.value = null
  noteContent.value   = ''
}

function saveNote(): void {
  if (!noteContent.value.trim()) return
  if (editingNoteId.value) {
    schedule.updateNote(editingNoteId.value, noteContent.value.trim(), noteIsPublic.value)
  } else {
    schedule.addNote(lessonId, noteContent.value.trim(), userId.value, auth.user ? `${auth.user.firstName} ${auth.user.lastName}` : 'Unknown', noteIsPublic.value)
  }
  cancelNoteForm()
}

function removeNote(noteId: string): void {
  schedule.deleteNote(noteId)
}

// ── Attendance form ──
const showAttendanceForm = ref(false)
const attTeacher = ref<AttendanceStatus>('PRESENT')
const attStudent = ref<AttendanceStatus>('PRESENT')
const attReason  = ref('')

const attendanceOptions = [
  { value: 'PRESENT', label: 'Present' },
  { value: 'ABSENT',  label: 'Absent' },
  { value: 'LATE',    label: 'Late' },
  { value: 'EXCUSED', label: 'Excused' },
]

function openAttendanceForm(): void {
  const a = attendance.value
  attTeacher.value = a?.teacherStatus ?? 'PRESENT'
  attStudent.value = a?.studentStatus ?? 'PRESENT'
  attReason.value  = a?.absenceReason ?? ''
  showAttendanceForm.value = true
}

function saveAttendance(): void {
  schedule.markAttendance(lessonId, attTeacher.value, attStudent.value, attReason.value, userId.value)
  showAttendanceForm.value = false
}

// ── Homework form ──
const showHomeworkForm = ref(false)
const hwTitle       = ref('')
const hwDescription = ref('')
const hwDueDate     = ref('')
const today         = new Date().toLocaleDateString('sv-SE')

function saveHomework(): void {
  if (!hwTitle.value.trim() || !hwDueDate.value) return
  const studentId   = lesson.value?.studentId ?? ''
  const studentName = lesson.value?.studentName ?? ''
  schedule.addHomework(lessonId, hwTitle.value.trim(), hwDescription.value.trim(), hwDueDate.value, studentId, studentName)
  hwTitle.value = ''; hwDescription.value = ''; hwDueDate.value = ''
  showHomeworkForm.value = false
}

// ── Materials form ──
const showMaterialForm = ref(false)
const matTitle = ref('')
const matType  = ref<MaterialType>('PDF')
const matUrl   = ref('')

const materialTypeOptions = [
  { value: 'PDF',      label: 'PDF' },
  { value: 'VIDEO',    label: 'Video' },
  { value: 'LINK',     label: 'Link' },
  { value: 'DOCUMENT', label: 'Document' },
  { value: 'IMAGE',    label: 'Image' },
]

function saveMaterial(): void {
  if (!matTitle.value.trim() || !matUrl.value.trim()) return
  const uploaderName = auth.user ? `${auth.user.firstName} ${auth.user.lastName}` : 'Unknown'
  schedule.addMaterial(lessonId, matTitle.value.trim(), matType.value, matUrl.value.trim(), uploaderName)
  matTitle.value = ''; matUrl.value = ''; matType.value = 'PDF'
  showMaterialForm.value = false
}

function removeMaterial(id: string): void {
  schedule.deleteMaterial(id)
}

function typeIcon(type: MaterialType): string {
  switch (type) {
    case 'PDF':      return '📄'
    case 'VIDEO':    return '🎬'
    case 'LINK':     return '🔗'
    case 'DOCUMENT': return '📝'
    case 'IMAGE':    return '🖼'
    default:         return '📎'
  }
}

// ── Helpers ──
function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })
}
</script>

<style scoped>
.ld-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

@media (max-width: 767px) {
  .ld-page { padding: var(--tv-space-4); }
}

/* Back */
.ld-back {
  display: inline-flex; align-items: center; gap: var(--tv-space-1);
  font-size: var(--tv-text-sm); color: var(--tv-text-secondary); background: none; border: none;
  cursor: pointer; padding: 0; width: fit-content; transition: color 0.15s;
}
.ld-back:hover { color: var(--tv-primary); }

/* Header card */
.ld-header-card {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); padding: var(--tv-space-5);
  display: flex; flex-direction: column; gap: var(--tv-space-4);
}
.ld-header-card__top {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: var(--tv-space-4); flex-wrap: wrap;
}
.ld-header-card__title-row {
  display: flex; align-items: center; flex-wrap: wrap; gap: var(--tv-space-2); flex: 1;
}
.ld-lesson-title {
  font-size: var(--tv-text-xl); font-weight: var(--tv-font-bold); color: var(--tv-text); margin: 0;
}
.ld-status {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium);
  padding: 3px var(--tv-space-3); border-radius: var(--tv-radius-full); border: 1px solid transparent;
}
.ld-status--scheduled   { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); border-color: var(--tv-primary-muted); }
.ld-status--completed   { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.ld-status--cancelled   { background: var(--tv-neutral-soft); color: var(--tv-neutral); border-color: var(--tv-neutral-border); }
.ld-status--missed      { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }
.ld-status--trial       { background: var(--tv-purple-soft); color: var(--tv-purple); border-color: var(--tv-purple-border); }
.ld-status--live        { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.ld-status--rescheduled { background: hsl(24,88%,93%); color: hsl(24,75%,35%); border-color: hsl(24,70%,70%); }
.ld-status--pending     { background: hsl(220,65%,93%); color: hsl(220,52%,38%); border-color: hsl(220,52%,65%); border-style: dashed; }

.ld-badge {
  font-size: 10px; font-weight: var(--tv-font-semibold);
  padding: 2px 6px; border-radius: var(--tv-radius-full); border: 1px solid;
}
.ld-badge--trial { background: var(--tv-purple-soft); color: var(--tv-purple); border-color: var(--tv-purple-border); }
.ld-badge--recur { background: var(--tv-bg-soft); color: var(--tv-text-muted); border-color: var(--tv-border); }

/* Join button */
.ld-join-btn {
  display: inline-flex; align-items: center; gap: var(--tv-space-2);
  padding: var(--tv-space-2) var(--tv-space-5);
  background: var(--tv-primary); color: var(--tv-text-inverse);
  border-radius: var(--tv-radius); font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold);
  text-decoration: none; transition: background 0.15s; white-space: nowrap;
  box-shadow: 0 2px 8px hsla(var(--tv-primary-h), var(--tv-primary-s), 40%, 0.28);
}
.ld-join-btn:hover { background: var(--tv-primary-hover); }

/* Info grid */
.ld-info-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--tv-space-3) var(--tv-space-5);
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
}
.ld-info-item { display: flex; flex-direction: column; gap: 2px; }
.ld-info-item--full { grid-column: 1 / -1; }
.ld-info-label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em; }
.ld-info-value { font-size: var(--tv-text-sm); color: var(--tv-text); font-weight: var(--tv-font-medium); }
.ld-info-value--muted { font-weight: normal; color: var(--tv-text-secondary); word-break: break-all; }

/* Notice banner */
.ld-notice {
  display: flex; align-items: center; gap: var(--tv-space-2);
  padding: var(--tv-space-3) var(--tv-space-4);
  border-radius: var(--tv-radius); font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
}
.ld-notice--cancelled   { background: var(--tv-neutral-soft); color: var(--tv-neutral); border: 1px solid var(--tv-neutral-border); }
.ld-notice--missed      { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border: 1px solid var(--tv-danger-border); }
.ld-notice--rescheduled { background: hsl(24,88%,93%); color: hsl(24,75%,35%); border: 1px solid hsl(24,70%,70%); }
.ld-notice--completed   { background: var(--tv-success-soft); color: var(--tv-success-fg); border: 1px solid var(--tv-success-border); }

/* Tabs */
.ld-tabs {
  display: flex; gap: 0;
  border-bottom: 2px solid var(--tv-border);
}
.ld-tab {
  display: inline-flex; align-items: center; gap: var(--tv-space-2);
  padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
  color: var(--tv-text-muted); background: none; border: none; border-bottom: 2px solid transparent;
  margin-bottom: -2px; cursor: pointer; transition: color 0.15s;
  white-space: nowrap;
}
.ld-tab:hover { color: var(--tv-text); }
.ld-tab--active { color: var(--tv-primary); border-bottom-color: var(--tv-primary); }
.ld-tab__count {
  font-size: 10px; background: var(--tv-bg-soft); color: var(--tv-text-muted);
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius-full);
  padding: 0 5px; min-width: 18px; text-align: center; line-height: 16px;
}
.ld-tab--active .ld-tab__count { background: var(--tv-primary-soft); color: var(--tv-primary); border-color: var(--tv-primary-muted); }

/* Tab body */
.ld-tab-body { min-height: 200px; }

/* Section */
.ld-section { display: flex; flex-direction: column; gap: var(--tv-space-4); }
.ld-section__header { display: flex; align-items: center; justify-content: space-between; }
.ld-section__title { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }

.ld-add-btn {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
  color: var(--tv-primary); background: var(--tv-primary-soft);
  border: 1px solid var(--tv-primary-muted); border-radius: var(--tv-radius-sm);
  padding: var(--tv-space-1) var(--tv-space-3); cursor: pointer; transition: background 0.15s;
}
.ld-add-btn:hover { background: hsl(var(--tv-primary-h), 70%, 90%); }

.ld-empty { color: var(--tv-text-muted); font-size: var(--tv-text-sm); margin: 0; padding: var(--tv-space-4) 0; }

/* Notes */
.ld-note-form {
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-3);
}
.ld-note-form__public { display: flex; align-items: center; }
.ld-note-form__actions { display: flex; gap: var(--tv-space-2); justify-content: flex-end; }

.ld-notes-list { display: flex; flex-direction: column; gap: var(--tv-space-3); }
.ld-note-item {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
}
.ld-note-item__header {
  display: flex; align-items: center; gap: var(--tv-space-2); flex-wrap: wrap;
  margin-bottom: var(--tv-space-2);
}
.ld-note-item__author { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }
.ld-note-item__date   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.ld-note-item__private {
  font-size: 10px; font-weight: var(--tv-font-semibold);
  background: var(--tv-warning-soft, hsl(38,90%,93%)); color: hsl(38,75%,35%);
  border: 1px solid hsl(38,70%,75%); border-radius: var(--tv-radius-full);
  padding: 1px 6px;
}
.ld-note-item__actions { margin-left: auto; display: flex; gap: var(--tv-space-1); }
.ld-note-item__body { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.6; margin: 0; white-space: pre-wrap; }

/* Attendance */
.ld-attendance-card {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-3);
}
.ld-attendance-row { display: flex; align-items: center; gap: var(--tv-space-3); }
.ld-attendance-who { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text-secondary); width: 60px; }
.ld-attendance-status {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold);
  padding: 2px var(--tv-space-3); border-radius: var(--tv-radius-full); border: 1px solid;
}
.ld-attendance-status--present { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.ld-attendance-status--absent  { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }
.ld-attendance-status--late    { background: hsl(38,88%,93%); color: hsl(38,75%,35%); border-color: hsl(38,70%,70%); }
.ld-attendance-status--excused { background: var(--tv-neutral-soft); color: var(--tv-neutral); border-color: var(--tv-neutral-border); }
.ld-attendance-reason { display: flex; flex-direction: column; gap: 2px; padding-top: var(--tv-space-2); border-top: 1px solid var(--tv-border); }
.ld-attendance-reason__label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em; }
.ld-attendance-reason__text  { font-size: var(--tv-text-sm); color: var(--tv-text); }
.ld-attendance-meta { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }
.ld-attendance-form {
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-3);
}

/* Homework */
.ld-hw-form {
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-3);
}
.ld-hw-list { display: flex; flex-direction: column; gap: var(--tv-space-3); }
.ld-hw-item {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-2);
}
.ld-hw-item__header { display: flex; align-items: center; gap: var(--tv-space-3); flex-wrap: wrap; }
.ld-hw-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); flex: 1; }
.ld-hw-status {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold);
  padding: 2px var(--tv-space-2); border-radius: var(--tv-radius-full); border: 1px solid;
}
.ld-hw-status--pending   { background: hsl(38,88%,93%); color: hsl(38,75%,35%); border-color: hsl(38,70%,70%); }
.ld-hw-status--submitted { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); border-color: var(--tv-primary-muted); }
.ld-hw-status--reviewed  { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.ld-hw-item__desc { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.6; margin: 0; }
.ld-hw-item__meta { display: flex; gap: var(--tv-space-4); font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.ld-hw-feedback {
  background: var(--tv-success-soft); border: 1px solid var(--tv-success-border);
  border-radius: var(--tv-radius-sm); padding: var(--tv-space-3);
  display: flex; flex-direction: column; gap: 4px;
}
.ld-hw-feedback__label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-success-fg); text-transform: uppercase; letter-spacing: .05em; }
.ld-hw-feedback__text  { font-size: var(--tv-text-sm); color: var(--tv-text); margin: 0; line-height: 1.5; }
.ld-hw-grade {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-bold);
  color: var(--tv-success-fg); align-self: flex-start;
  background: white; border: 1px solid var(--tv-success-border);
  border-radius: var(--tv-radius-sm); padding: 1px 8px;
}
.ld-hw-submission { font-size: var(--tv-text-xs); }

/* Materials */
.ld-mat-form {
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-3);
}
.ld-mat-list { display: flex; flex-direction: column; gap: var(--tv-space-2); }
.ld-mat-item {
  display: flex; align-items: center; gap: var(--tv-space-3);
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-3) var(--tv-space-4);
}
.ld-mat-type-icon { font-size: 20px; flex-shrink: 0; }
.ld-mat-item__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.ld-mat-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-primary); text-decoration: none; }
.ld-mat-item__title:hover { text-decoration: underline; }
.ld-mat-item__meta { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }

/* Shared form elements */
.ld-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--tv-space-3); }
.ld-form-actions { display: flex; gap: var(--tv-space-2); justify-content: flex-end; }
.ld-field { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.ld-label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); }
.ld-input {
  padding: var(--tv-space-2) var(--tv-space-3); font-size: var(--tv-text-sm);
  color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  min-height: 42px; outline: none; transition: border-color 0.15s; font-family: inherit;
}
.ld-input:focus { border-color: var(--tv-primary); }
.ld-textarea {
  padding: var(--tv-space-2) var(--tv-space-3); font-size: var(--tv-text-sm);
  color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  resize: vertical; outline: none; transition: border-color 0.15s;
  font-family: inherit; line-height: 1.5;
}
.ld-textarea:focus { border-color: var(--tv-primary); }

/* Checkbox */
.ld-checkbox { display: flex; align-items: center; gap: var(--tv-space-2); cursor: pointer; font-size: var(--tv-text-sm); color: var(--tv-text); }
.ld-checkbox__input { position: absolute; opacity: 0; width: 0; height: 0; }
.ld-checkbox__box {
  width: 16px; height: 16px; border: 1.5px solid var(--tv-border);
  border-radius: 3px; background: var(--tv-bg-card); flex-shrink: 0;
  transition: background 0.15s, border-color 0.15s;
}
.ld-checkbox__input:checked + .ld-checkbox__box { background: var(--tv-primary); border-color: var(--tv-primary); }

/* Buttons */
.ld-btn {
  display: inline-flex; align-items: center; gap: var(--tv-space-1);
  padding: var(--tv-space-2) var(--tv-space-4); font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium); border-radius: var(--tv-radius-sm);
  border: 1px solid transparent; cursor: pointer; transition: background 0.15s;
  font-family: inherit;
}
.ld-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.ld-btn--primary { background: var(--tv-primary); color: var(--tv-text-inverse); }
.ld-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.ld-btn--ghost { background: transparent; border-color: var(--tv-border); color: var(--tv-text-secondary); }
.ld-btn--ghost:hover:not(:disabled) { background: var(--tv-bg-soft); }

/* Icon buttons */
.ld-icon-btn {
  width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
  background: transparent; border: 1px solid var(--tv-border); border-radius: var(--tv-radius-sm);
  color: var(--tv-text-secondary); cursor: pointer; transition: background 0.15s;
}
.ld-icon-btn:hover { background: var(--tv-bg-soft); }
.ld-icon-btn--danger { border-color: var(--tv-danger-border); color: var(--tv-danger-fg); }
.ld-icon-btn--danger:hover { background: var(--tv-danger-soft); }

/* Link */
.ld-link { color: var(--tv-primary); font-size: var(--tv-text-sm); text-decoration: none; }
.ld-link:hover { text-decoration: underline; }

/* Not found */
.ld-not-found {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: var(--tv-space-4); padding: var(--tv-space-8); color: var(--tv-text-muted);
}

@media (max-width: 600px) {
  .ld-form-row { grid-template-columns: 1fr; }
  .ld-info-grid { grid-template-columns: 1fr 1fr; }
}
</style>
