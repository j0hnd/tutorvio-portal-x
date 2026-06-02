<template>
  <div class="cv-page">

    <!-- Header -->
    <div class="cv-header">
      <div>
        <h1 class="cv-title">Courses</h1>
        <p class="cv-subtitle">{{ subtitle }}</p>
      </div>
      <button v-if="canAdmin" class="cv-add-btn" type="button" @click="openCreate">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        Add Course
      </button>
    </div>

    <!-- Filters -->
    <div class="cv-filters">
      <div class="cv-search-wrap">
        <svg class="cv-search-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M10 10l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <input v-model="search" class="cv-search" type="search" placeholder="Search courses..." />
      </div>
      <TVSelect v-model="filterLevel" class="cv-filter-ctrl" :options="levelOptions" placeholder="All Levels" />
      <TVSelect v-if="canAdmin" v-model="filterStatus" class="cv-filter-ctrl" :options="statusOptions" placeholder="All Statuses" />
      <button v-if="hasFilters" class="cv-clear-btn" type="button" @click="clearFilters">Clear</button>
    </div>

    <!-- Course list -->
    <div v-if="filtered.length" class="cv-list">
      <article
        v-for="course in paginated"
        :key="course.id"
        :class="['cv-card', { 'cv-card--archived': course.status === 'archived' }]"
      >
        <!-- Card top -->
        <div class="cv-card__top">
          <div class="cv-card__left">
            <div class="cv-card__title-row">
              <span :class="['cv-level-badge', `cv-level-badge--${course.placementLevel.toLowerCase()}`]">
                {{ levelShort(course.placementLevel) }}
              </span>
              <h2 class="cv-card__name">{{ course.name }}</h2>
              <span v-if="course.status === 'archived'" class="cv-archived-pill">Archived</span>
            </div>
            <p class="cv-card__desc">{{ course.description }}</p>
          </div>

          <!-- Meta + actions -->
          <div class="cv-card__right">
            <div class="cv-card__stats">
              <div class="cv-card__stat">
                <span class="cv-card__stat-val">{{ course.numberOfSessions }}</span>
                <span class="cv-card__stat-label">Sessions</span>
              </div>
              <div class="cv-card__stat">
                <span class="cv-card__stat-val">{{ course.assignedStudentIds.length }}</span>
                <span class="cv-card__stat-label">Students</span>
              </div>
              <div class="cv-card__stat">
                <span class="cv-card__stat-val">{{ course.milestones.length }}</span>
                <span class="cv-card__stat-label">Milestones</span>
              </div>
            </div>
            <div v-if="canAdmin" class="cv-card__actions">
              <button class="cv-icon-btn cv-icon-btn--edit" title="Edit" type="button" @click="openEdit(course)">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M9.5 2l2.5 2.5L4 13H1.5v-2.5L9.5 2z" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <button
                :class="['cv-icon-btn', course.status === 'archived' ? 'cv-icon-btn--restore' : 'cv-icon-btn--danger']"
                :title="course.status === 'archived' ? 'Restore' : 'Archive'"
                type="button"
                @click="course.status === 'archived' ? toggleArchive(course) : (confirmingArchive = course)"
              >
                <svg v-if="course.status === 'archived'" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <path d="M2 7h10M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg v-else width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                  <rect x="1" y="1.5" width="12" height="3" rx="1" stroke="currentColor" stroke-width="1.2"/>
                  <path d="M2 4.5v7a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                  <path d="M5.5 7.5h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Details (expandable) -->
        <div v-if="expandedId === course.id" class="cv-card__detail">

          <!-- Row 1: Lesson Structure — full width -->
          <div class="cv-detail-card cv-detail-card--full">
            <h3 class="cv-detail-card__title">Lesson Structure</h3>
            <p class="cv-detail-text">{{ course.lessonStructure || '—' }}</p>
          </div>

          <!-- Row 2: cards — 3 cols (admin/teacher) or 2 cols (student) -->
          <div :class="['cv-detail-bottom', role === 'STUDENT' ? 'cv-detail-bottom--2' : 'cv-detail-bottom--3']">

            <!-- Milestones -->
            <div class="cv-detail-card">
              <h3 class="cv-detail-card__title">Milestones</h3>
              <ol v-if="course.milestones.length" class="cv-milestones">
                <li v-for="m in course.milestones" :key="m.id" class="cv-milestone-item">
                  <span class="cv-milestone-num">{{ m.order }}</span>
                  <span class="cv-milestone-text">{{ m.label }}</span>
                </li>
              </ol>
              <p v-else class="cv-detail-empty">No milestones set.</p>
            </div>

            <!-- Enrolled Students — admin/teacher only -->
            <div v-if="role !== 'STUDENT'" class="cv-detail-card">
              <div class="cv-detail-card__header">
                <h3 class="cv-detail-card__title">Enrolled Students ({{ course.assignedStudentIds.length }})</h3>
                <button v-if="canAdmin" class="cv-sm-btn cv-sm-btn--add" type="button" @click="openAssign(course)">+ Assign</button>
              </div>
              <div v-if="course.assignedStudentIds.length" class="cv-student-pills">
                <div v-for="sid in course.assignedStudentIds" :key="sid" class="cv-student-pill">
                  <span class="cv-student-pill__name">{{ studentName(sid) }}</span>
                  <button v-if="canAdmin" class="cv-student-pill__remove" type="button" @click="coursesStore.removeStudent(course.id, sid)">×</button>
                </div>
              </div>
              <p v-else class="cv-detail-empty">No students enrolled.</p>
            </div>

            <!-- Attached Resources -->
            <div class="cv-detail-card">
              <div class="cv-detail-card__header">
                <h3 class="cv-detail-card__title">Attached Resources ({{ course.attachedMaterialIds.length }})</h3>
                <button v-if="canAdmin" class="cv-sm-btn cv-sm-btn--add" type="button" @click="openAttach(course)">+ Attach</button>
              </div>
              <div v-if="course.attachedMaterialIds.length" class="cv-resource-pills">
                <div v-for="mid in course.attachedMaterialIds" :key="mid" class="cv-resource-pill">
                  <span class="cv-resource-pill__name">{{ materialTitle(mid) }}</span>
                  <button v-if="canAdmin" class="cv-resource-pill__remove" type="button" @click="coursesStore.detachMaterial(course.id, mid)">×</button>
                </div>
              </div>
              <p v-else class="cv-detail-empty">No resources attached.</p>
            </div>

          </div>
        </div>

        <!-- Expand toggle -->
        <button
          class="cv-card__expand"
          type="button"
          @click="expandedId = expandedId === course.id ? null : course.id"
        >
          {{ expandedId === course.id ? 'Hide details ↑' : 'View details ↓' }}
        </button>
      </article>
    </div>

    <!-- Pagination -->
    <TVPagination
      v-if="filtered.length > CV_PAGE_SIZE"
      v-model="cvPage"
      :total="filtered.length"
      :page-size="CV_PAGE_SIZE"
    />

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="cv-empty">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
        <rect x="5" y="5" width="30" height="30" rx="4" stroke="currentColor" stroke-width="1.6"/>
        <path d="M12 15h16M12 21h16M12 27h10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      <p>No courses found{{ hasFilters ? ' matching your filters' : '' }}.</p>
      <button v-if="hasFilters" class="cv-clear-btn" type="button" @click="clearFilters">Clear filters</button>
    </div>

    <!-- ── Create / Edit Modal ── -->
    <TVModal v-model="showFormModal" :title="editingCourse ? 'Edit Course' : 'Add Course'" maxWidth="600px">
      <div class="cv-form">
        <TVInput v-model="form.name" label="Course Name" placeholder="e.g. Business English" required />
        <div class="cv-field">
          <label class="cv-label">Description</label>
          <textarea v-model="form.description" class="cv-textarea" rows="3" placeholder="Brief description of this course…" />
        </div>
        <div class="cv-field">
          <label class="cv-label">Lesson Structure</label>
          <textarea v-model="form.lessonStructure" class="cv-textarea" rows="2" placeholder="e.g. 2 sessions per week, 60 minutes each…" />
        </div>
        <div class="cv-form-row">
          <TVInput v-model.number="form.numberOfSessions" label="Number of Sessions" type="number" placeholder="e.g. 24" />
          <TVSelect v-model="form.placementLevel" label="Placement Level" :options="levelFormOptions" />
        </div>
        <!-- Milestones -->
        <div class="cv-field">
          <label class="cv-label">Milestones</label>
          <div v-for="(m, i) in form.milestones" :key="i" class="cv-milestone-row">
            <span class="cv-milestone-row__num">{{ i + 1 }}</span>
            <input v-model="m.label" class="cv-milestone-input" type="text" :placeholder="`Milestone ${i + 1}…`" />
            <button class="cv-milestone-remove" type="button" @click="form.milestones.splice(i, 1)">×</button>
          </div>
          <button class="cv-add-milestone-btn" type="button" @click="addMilestone">+ Add milestone</button>
        </div>

        <!-- Attached Resources -->
        <div class="cv-field">
          <label class="cv-label">Attached Resources</label>
          <div v-if="form.attachedMaterialIds.length" class="cv-form-resources">
            <div v-for="mid in form.attachedMaterialIds" :key="mid" class="cv-resource-pill">
              <span class="cv-resource-pill__name">{{ materialTitle(mid) }}</span>
              <button
                class="cv-resource-pill__remove"
                type="button"
                @click="form.attachedMaterialIds.splice(form.attachedMaterialIds.indexOf(mid), 1)"
              >×</button>
            </div>
          </div>
          <div class="cv-form-resource-add">
            <TVSelect
              v-model="formMaterialPick"
              :options="formMaterialOptions"
              placeholder="Select a material to attach…"
              style="flex:1"
            />
            <button
              class="cv-btn cv-btn--primary"
              type="button"
              :disabled="!formMaterialPick"
              @click="addFormMaterial"
            >Add</button>
          </div>
        </div>
      </div>
      <template #footer>
        <button class="cv-btn cv-btn--ghost" type="button" @click="showFormModal = false">Cancel</button>
        <button class="cv-btn cv-btn--primary" type="button" :disabled="!form.name.trim()" @click="saveForm">
          {{ editingCourse ? 'Save Changes' : 'Create Course' }}
        </button>
      </template>
    </TVModal>

    <!-- ── Archive Confirmation Modal ── -->
    <TVModal v-model="showConfirmArchive" title="Archive Course" maxWidth="400px">
      <p class="cv-confirm-text">
        Are you sure you want to archive <strong>{{ confirmingArchive?.name }}</strong>?
        It will no longer appear in active course lists. You can restore it later.
      </p>
      <template #footer>
        <button class="cv-btn cv-btn--ghost" type="button" @click="showConfirmArchive = false">Cancel</button>
        <button class="cv-btn cv-btn--danger" type="button" @click="doArchive">Archive</button>
      </template>
    </TVModal>

    <!-- ── Assign Student Modal ── -->
    <TVModal v-model="showAssignModal" title="Assign Student" maxWidth="400px">
      <TVSelect v-model="assignStudentId" :options="availableStudentOptions" placeholder="Select a student" />
      <template #footer>
        <button class="cv-btn cv-btn--ghost" type="button" @click="showAssignModal = false">Cancel</button>
        <button class="cv-btn cv-btn--primary" type="button" :disabled="!assignStudentId" @click="doAssign">Assign</button>
      </template>
    </TVModal>

    <!-- ── Attach Material Modal ── -->
    <TVModal v-model="showAttachModal" title="Attach Resource" maxWidth="400px">
      <TVSelect v-model="attachMaterialId" :options="availableMaterialOptions" placeholder="Select a material" />
      <template #footer>
        <button class="cv-btn cv-btn--ghost" type="button" @click="showAttachModal = false">Cancel</button>
        <button class="cv-btn cv-btn--primary" type="button" :disabled="!attachMaterialId" @click="doAttach">Attach</button>
      </template>
    </TVModal>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, watch, onMounted } from 'vue'
import { useCoursesStore } from '@/stores/courses'
import { useAuthStore } from '@/stores/auth'
import { useUsersStore } from '@/stores/users'
import { useScheduleStore } from '@/stores/schedule'
import { useViewAs } from '@/composables/useViewAs'
import { useToast } from '@/composables/useToast'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVModal from '@/components/ui/TVModal.vue'
import TVPagination from '@/components/ui/TVPagination.vue'
import type { Course, PlacementLevel } from '@/stores/courses'

const coursesStore = useCoursesStore()
const auth         = useAuthStore()
const usersStore   = useUsersStore()
const schedule     = useScheduleStore()
const toast        = useToast()
const { effectiveRole } = useViewAs()

const role   = computed(() => effectiveRole.value)
const userId = computed(() => auth.user?.id ?? '')

// ── Can functions ──
const canAdmin = computed(() => role.value === 'ADMIN' || role.value === 'STAFF')

// ── Source courses (role-scoped) ──
const actualRole = computed(() => auth.user?.role)
// True when admin is using view-as (effectiveRole differs from actual)
const isViewAs = computed(() =>
  (actualRole.value === 'ADMIN' || actualRole.value === 'STAFF') && role.value !== actualRole.value
)

const allCourses = computed(() => {
  const aRole = actualRole.value
  if (!isViewAs.value && (aRole === 'ADMIN' || aRole === 'STAFF')) {
    // Real admin/staff: see everything
    return coursesStore.getCourses()
  }
  if (role.value === 'TEACHER') {
    // Real teacher OR admin view-as teacher
    const myStudentIds = usersStore.getUserById(userId.value)?.teacherProfile?.assignedStudentIds ?? []
    // Admin has no teacher profile — show all active as a proxy view
    if (isViewAs.value && !myStudentIds.length) return coursesStore.getCourses().filter(c => c.status === 'active')
    return coursesStore.getCoursesForTeacher(myStudentIds).filter(c => c.status === 'active')
  }
  // Real student OR admin view-as student
  const studentCourses = coursesStore.getCoursesForStudent(userId.value)
  // Admin has no student profile — show all active as a proxy view
  if (isViewAs.value && !studentCourses.length) return coursesStore.getCourses().filter(c => c.status === 'active')
  return studentCourses
})

// ── Subtitle ──
const subtitle = computed(() => {
  if (role.value === 'STUDENT') return 'Your enrolled course and learning path'
  if (role.value === 'TEACHER') return 'Courses relevant to your assigned students'
  return 'Manage all courses, programs, and student assignments'
})

// ── Filters ──
const search       = ref('')
const filterLevel  = ref('')
const filterStatus = ref('')

const levelOptions = [
  { value: '',                   label: 'All Levels' },
  { value: 'BEGINNER',           label: 'Beginner (A1)' },
  { value: 'ELEMENTARY',         label: 'Elementary (A2)' },
  { value: 'INTERMEDIATE',       label: 'Intermediate (B1)' },
  { value: 'UPPER_INTERMEDIATE', label: 'Upper Intermediate (B2)' },
  { value: 'ADVANCED',           label: 'Advanced (C1)' },
  { value: 'ALL',                label: 'All Levels' },
]

const statusOptions = [
  { value: '',         label: 'All Statuses' },
  { value: 'active',   label: 'Active' },
  { value: 'archived', label: 'Archived' },
]

const hasFilters = computed(() => !!search.value || !!filterLevel.value || !!filterStatus.value)
function clearFilters() { search.value = ''; filterLevel.value = ''; filterStatus.value = '' }

const filtered = computed(() => {
  const q = search.value.toLowerCase().trim()
  return allCourses.value.filter(c => {
    if (q && !c.name.toLowerCase().includes(q) && !c.description.toLowerCase().includes(q)) return false
    if (filterLevel.value && c.placementLevel !== filterLevel.value) return false
    if (filterStatus.value && c.status !== filterStatus.value) return false
    return true
  })
})

onMounted(async () => { if (!usersStore.users.length) await usersStore.fetchUsers() })

// ── Pagination ──
const CV_PAGE_SIZE = 5
const cvPage = ref(1)
const paginated = computed(() =>
  filtered.value.slice((cvPage.value - 1) * CV_PAGE_SIZE, cvPage.value * CV_PAGE_SIZE)
)
watch(filtered, () => { cvPage.value = 1 })

// ── Expand ──
const expandedId = ref<string | null>(null)

// ── Archive confirmation ──
const confirmingArchive = ref<Course | null>(null)
const showConfirmArchive = computed({
  get: () => !!confirmingArchive.value,
  set: (v) => { if (!v) confirmingArchive.value = null },
})
function doArchive() {
  if (confirmingArchive.value) toggleArchive(confirmingArchive.value)
  confirmingArchive.value = null
}

// ── Helpers ──
const LEVEL_SHORT: Record<string, string> = {
  BEGINNER: 'A1', ELEMENTARY: 'A2', INTERMEDIATE: 'B1',
  UPPER_INTERMEDIATE: 'B2', ADVANCED: 'C1', ALL: 'All',
}
function levelShort(level: string) { return LEVEL_SHORT[level] ?? level }

function studentName(id: string): string {
  const u = usersStore.getUserById(id)
  return u ? `${u.firstName} ${u.lastName}` : id
}

function materialTitle(id: string): string {
  const m = schedule.getLibraryMaterials('ADMIN').find(mat => mat.id === id)
  return m?.title ?? id
}

// ── Create / Edit form ──
const showFormModal = ref(false)
const editingCourse = ref<Course | null>(null)

const BLANK_FORM = () => ({
  name: '',
  description: '',
  lessonStructure: '',
  numberOfSessions: 12,
  placementLevel: 'INTERMEDIATE' as PlacementLevel,
  milestones: [] as { id: string; label: string; order: number }[],
  attachedMaterialIds: [] as string[],
  assignedStudentIds: [] as string[],
  createdById: userId.value,
})

const form = reactive(BLANK_FORM())

const levelFormOptions = levelOptions.filter(o => o.value !== '')

function openCreate() {
  editingCourse.value = null
  Object.assign(form, BLANK_FORM())
  showFormModal.value = true
}

function openEdit(course: Course) {
  editingCourse.value = course
  Object.assign(form, {
    name: course.name,
    description: course.description,
    lessonStructure: course.lessonStructure,
    numberOfSessions: course.numberOfSessions,
    placementLevel: course.placementLevel,
    milestones: course.milestones.map(m => ({ ...m })),
    attachedMaterialIds: [...course.attachedMaterialIds],
    assignedStudentIds: [...course.assignedStudentIds],
    createdById: course.createdById,
  })
  showFormModal.value = true
}

function addMilestone() {
  form.milestones.push({ id: `m${Date.now()}`, label: '', order: form.milestones.length + 1 })
}

// ── Form: resources picker ──
const formMaterialPick = ref('')

const formMaterialOptions = computed(() => {
  const attached = new Set(form.attachedMaterialIds)
  return schedule.getLibraryMaterials('ADMIN')
    .filter(m => !attached.has(m.id))
    .map(m => ({ value: m.id, label: m.title }))
})

function addFormMaterial() {
  if (formMaterialPick.value && !form.attachedMaterialIds.includes(formMaterialPick.value)) {
    form.attachedMaterialIds.push(formMaterialPick.value)
  }
  formMaterialPick.value = ''
}

// Reset picker when modal closes
watch(showFormModal, (v) => { if (!v) formMaterialPick.value = '' })

function saveForm() {
  if (!form.name.trim()) return
  const milestones = form.milestones
    .filter(m => m.label.trim())
    .map((m, i) => ({ ...m, order: i + 1 }))

  if (editingCourse.value) {
    coursesStore.updateCourse(editingCourse.value.id, {
      name: form.name.trim(),
      description: form.description.trim(),
      lessonStructure: form.lessonStructure.trim(),
      numberOfSessions: Number(form.numberOfSessions),
      placementLevel: form.placementLevel,
      milestones,
      attachedMaterialIds: [...form.attachedMaterialIds],
    })
    toast.success('Course updated.')
  } else {
    coursesStore.createCourse({
      name: form.name.trim(),
      description: form.description.trim(),
      lessonStructure: form.lessonStructure.trim(),
      numberOfSessions: Number(form.numberOfSessions),
      placementLevel: form.placementLevel,
      milestones,
      attachedMaterialIds: [...form.attachedMaterialIds],
      assignedStudentIds: [],
      createdById: userId.value,
    })
    toast.success('Course created.')
  }
  showFormModal.value = false
}

// ── Archive / Restore ──
function toggleArchive(course: Course) {
  if (course.status === 'archived') {
    coursesStore.restoreCourse(course.id)
    toast.success('Course restored.')
  } else {
    coursesStore.archiveCourse(course.id)
    toast.success('Course archived.')
  }
}

// ── Assign student ──
const showAssignModal   = ref(false)
const assigningCourse   = ref<Course | null>(null)
const assignStudentId   = ref('')

const availableStudentOptions = computed(() => {
  const enrolled = new Set(assigningCourse.value?.assignedStudentIds ?? [])
  return usersStore.filteredUsers({ role: 'STUDENT' })
    .filter(s => !enrolled.has(s.id))
    .map(s => ({ value: s.id, label: `${s.firstName} ${s.lastName}` }))
})

function openAssign(course: Course) {
  assigningCourse.value = course
  assignStudentId.value = ''
  showAssignModal.value = true
}

function doAssign() {
  if (!assigningCourse.value || !assignStudentId.value) return
  coursesStore.assignStudent(assigningCourse.value.id, assignStudentId.value)
  showAssignModal.value = false
  toast.success('Student assigned to course.')
}

// ── Attach material ──
const showAttachModal   = ref(false)
const attachingCourse   = ref<Course | null>(null)
const attachMaterialId  = ref('')

const availableMaterialOptions = computed(() => {
  const attached = new Set(attachingCourse.value?.attachedMaterialIds ?? [])
  return schedule.getLibraryMaterials('ADMIN')
    .filter(m => !attached.has(m.id))
    .map(m => ({ value: m.id, label: m.title }))
})

function openAttach(course: Course) {
  attachingCourse.value = course
  attachMaterialId.value = ''
  showAttachModal.value = true
}

function doAttach() {
  if (!attachingCourse.value || !attachMaterialId.value) return
  coursesStore.attachMaterial(attachingCourse.value.id, attachMaterialId.value)
  showAttachModal.value = false
  toast.success('Resource attached.')
}
</script>

<style scoped>
.cv-page {
  padding: var(--tv-space-6);
  display: flex; flex-direction: column; gap: var(--tv-space-5);
}
@media (max-width: 767px) { .cv-page { padding: var(--tv-space-4); } }

/* Header */
.cv-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: var(--tv-space-3); }
.cv-title  { font-size: var(--tv-text-2xl); font-weight: var(--tv-font-bold); color: var(--tv-text); margin: 0; letter-spacing: -0.025em; }
.cv-subtitle { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: var(--tv-space-1) 0 0; }
.cv-add-btn {
  display: inline-flex; align-items: center; gap: var(--tv-space-2);
  background: var(--tv-primary); color: var(--tv-text-inverse);
  border: none; border-radius: var(--tv-radius); padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); font-family: inherit;
  cursor: pointer; transition: background 0.15s; white-space: nowrap;
}
.cv-add-btn:hover { background: var(--tv-primary-hover); }

/* Filters */
.cv-filters { display: flex; flex-wrap: wrap; gap: var(--tv-space-2); align-items: center; }
.cv-search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 360px; }
.cv-search-icon { position: absolute; left: var(--tv-space-3); top: 50%; transform: translateY(-50%); color: var(--tv-text-muted); pointer-events: none; }
.cv-search {
  width: 100%; padding: 0 var(--tv-space-3) 0 calc(var(--tv-space-3) + 20px);
  font-size: var(--tv-text-sm); color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  min-height: 42px; outline: none; transition: border-color 0.15s; font-family: inherit; box-sizing: border-box;
}
.cv-search:focus { border-color: var(--tv-primary); }
.cv-filter-ctrl { width: 160px; flex-shrink: 0; }
.cv-clear-btn {
  font-size: var(--tv-text-sm); color: var(--tv-text-muted); background: none;
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius);
  padding: 0 var(--tv-space-3); min-height: 42px; display: inline-flex; align-items: center;
  cursor: pointer; white-space: nowrap; transition: color 0.15s, background 0.15s;
}
.cv-clear-btn:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

/* Course list */
.cv-list { display: flex; flex-direction: column; gap: var(--tv-space-3); }

/* Course card */
.cv-card {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); box-shadow: var(--tv-shadow-sm);
  overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s;
}
.cv-card:hover { border-color: var(--tv-primary-muted); box-shadow: 0 2px 8px hsla(var(--tv-primary-h), var(--tv-primary-s), 50%, 0.07); }
.cv-card--archived { opacity: 0.75; }

.cv-card__top {
  display: flex; align-items: center; justify-content: space-between;
  gap: var(--tv-space-4); padding: var(--tv-space-4) var(--tv-space-5);
  flex-wrap: wrap;
}
.cv-card__left { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: var(--tv-space-2); }
.cv-card__title-row { display: flex; align-items: center; gap: var(--tv-space-2); flex-wrap: wrap; }
.cv-card__name { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.cv-card__desc { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); line-height: 1.55; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* Level badges */
.cv-level-badge {
  font-size: 11px; font-weight: var(--tv-font-bold); padding: 2px 8px;
  border-radius: var(--tv-radius-full); letter-spacing: .04em; flex-shrink: 0;
}
.cv-level-badge--beginner           { background: hsl(210,80%,94%); color: hsl(210,70%,38%); }
.cv-level-badge--elementary         { background: hsl(190,80%,92%); color: hsl(190,70%,32%); }
.cv-level-badge--intermediate       { background: hsl(155,70%,90%); color: hsl(155,60%,30%); }
.cv-level-badge--upper_intermediate { background: hsl(270,65%,92%); color: hsl(270,55%,38%); }
.cv-level-badge--advanced           { background: hsl(30,90%,92%);  color: hsl(30,70%,32%); }
.cv-level-badge--all                { background: var(--tv-bg-soft); color: var(--tv-text-muted); }

.cv-archived-pill { font-size: 10px; font-weight: var(--tv-font-bold); padding: 1px 8px; border-radius: var(--tv-radius-full); background: var(--tv-bg-soft); color: var(--tv-text-muted); border: 1px solid var(--tv-border); }

/* Right side */
.cv-card__right { display: flex; flex-direction: row; align-items: center; gap: var(--tv-space-4); flex-shrink: 0; }
.cv-card__stats { display: flex; gap: var(--tv-space-5); }
.cv-card__stat  { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.cv-card__stat-val   { font-size: var(--tv-text-2xl); font-weight: var(--tv-font-bold); color: var(--tv-text); line-height: 1; }
.cv-card__stat-label { font-size: var(--tv-text-xs); color: var(--tv-text-muted); font-weight: var(--tv-font-medium); white-space: nowrap; }
.cv-card__actions {
  display: flex; gap: var(--tv-space-1);
  padding-left: var(--tv-space-4);
  border-left: 1px solid var(--tv-border);
}
.cv-icon-btn {
  width: 30px; height: 30px; border-radius: var(--tv-radius-sm);
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid; cursor: pointer; transition: background 0.12s, color 0.12s, border-color 0.12s;
}
/* Edit — info color */
.cv-icon-btn--edit { background: var(--tv-info-soft); border-color: var(--tv-info-border); color: var(--tv-info-fg); }
.cv-icon-btn--edit:hover { background: hsl(199, 89%, 88%); }
/* Danger / archive — red */
.cv-icon-btn--danger { background: var(--tv-danger-soft); border-color: var(--tv-danger-border); color: var(--tv-danger-fg); }
.cv-icon-btn--danger:hover { background: hsl(0, 72%, 88%); }
/* Restore — success/green */
.cv-icon-btn--restore { background: var(--tv-success-soft); border-color: var(--tv-success-border); color: var(--tv-success-fg); }
.cv-icon-btn--restore:hover { background: hsl(142, 70%, 87%); }

/* Expand button */
.cv-card__expand {
  width: 100%; padding: var(--tv-space-2) var(--tv-space-5);
  font-size: var(--tv-text-xs); color: var(--tv-text-muted); font-weight: var(--tv-font-medium);
  background: var(--tv-bg-soft); border: none; border-top: 1px solid var(--tv-border);
  cursor: pointer; text-align: left; transition: background 0.12s, color 0.12s;
}
.cv-card__expand:hover { background: var(--tv-primary-soft); color: var(--tv-primary); }

/* Detail section */
.cv-card__detail { padding: var(--tv-space-4) var(--tv-space-5); border-top: 1px solid var(--tv-border); background: var(--tv-bg-soft); display: flex; flex-direction: column; gap: var(--tv-space-3); }
/* Row 1: lesson structure full width */
.cv-detail-card--full { width: 100%; }
/* Row 2: card grid — all same height via stretch */
.cv-detail-bottom       { display: grid; gap: var(--tv-space-3); align-items: stretch; }
.cv-detail-bottom--3    { grid-template-columns: repeat(3, 1fr); }
.cv-detail-bottom--2    { grid-template-columns: repeat(2, 1fr); }
/* Bottom-row cards are flex columns so content can scroll inside */
.cv-detail-bottom > .cv-detail-card { display: flex; flex-direction: column; }
/* Milestones list: cap at 5 items (~32px each), then scroll */
.cv-detail-bottom .cv-milestones { max-height: 180px; overflow-y: auto; }
/* Pills containers: natural wrap, scroll if needed, no stretching */
.cv-detail-bottom .cv-student-pills,
.cv-detail-bottom .cv-resource-pills { overflow-y: auto; max-height: 160px; align-content: flex-start; }
@media (max-width: 900px) { .cv-detail-bottom--3 { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px) { .cv-detail-bottom--3, .cv-detail-bottom--2 { grid-template-columns: 1fr; } }
.cv-detail-card { background: var(--tv-bg-card); border: 1px solid var(--tv-border); border-radius: var(--tv-radius-md); padding: var(--tv-space-3); display: flex; flex-direction: column; gap: var(--tv-space-2); }
.cv-detail-card__header { display: flex; align-items: center; justify-content: space-between; gap: var(--tv-space-2); }
.cv-detail-card__title { font-size: var(--tv-text-xs); font-weight: var(--tv-font-bold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em; margin: 0; }
.cv-detail-text { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); line-height: 1.55; margin: 0; }
.cv-detail-empty { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

/* Milestones list */
.cv-milestones { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: var(--tv-space-1); }
.cv-milestone-item { display: flex; align-items: flex-start; gap: var(--tv-space-2); }
.cv-milestone-num { width: 20px; height: 20px; border-radius: 50%; background: var(--tv-primary-soft); color: var(--tv-primary); font-size: 10px; font-weight: var(--tv-font-bold); display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
.cv-milestone-text { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.4; }

/* Pills */
.cv-student-pills, .cv-resource-pills { display: flex; flex-wrap: wrap; gap: var(--tv-space-1); }
.cv-student-pill, .cv-resource-pill {
  display: inline-flex; align-items: center; gap: 4px;
  background: var(--tv-primary-soft); border: 1px solid var(--tv-primary-muted);
  border-radius: var(--tv-radius-full); padding: 2px 8px 2px 10px;
  font-size: var(--tv-text-xs); color: var(--tv-primary);
}
.cv-resource-pill { background: var(--tv-bg-soft); border-color: var(--tv-border); color: var(--tv-text); }
.cv-student-pill__remove, .cv-resource-pill__remove {
  background: none; border: none; cursor: pointer; color: inherit; opacity: 0.6;
  font-size: 14px; line-height: 1; padding: 0; display: flex;
}
.cv-student-pill__remove:hover, .cv-resource-pill__remove:hover { opacity: 1; }

/* Small buttons */
.cv-sm-btn {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold);
  border-radius: var(--tv-radius-sm); padding: 2px var(--tv-space-2);
  cursor: pointer; transition: background 0.12s; border: 1px solid;
}
.cv-sm-btn--add { background: var(--tv-success-soft); border-color: var(--tv-success-border); color: var(--tv-success-fg); }
.cv-sm-btn--add:hover { background: hsl(142, 70%, 87%); }

/* Confirm text */
.cv-confirm-text { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); line-height: 1.6; margin: 0; }

/* Empty */
.cv-empty {
  display: flex; flex-direction: column; align-items: center;
  gap: var(--tv-space-3); padding: var(--tv-space-10) var(--tv-space-4);
  color: var(--tv-text-muted); text-align: center;
}
.cv-empty svg { opacity: 0.35; }
.cv-empty p { font-size: var(--tv-text-sm); margin: 0; }

/* Modal form */
.cv-form { display: flex; flex-direction: column; gap: var(--tv-space-4); }
.cv-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--tv-space-3); }
.cv-field { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.cv-label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); }
.cv-textarea {
  padding: var(--tv-space-2) var(--tv-space-3); font-size: var(--tv-text-sm);
  color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  resize: vertical; outline: none; font-family: inherit; line-height: 1.5;
  transition: border-color 0.15s;
}
.cv-textarea:focus { border-color: var(--tv-primary); }

/* Resources in form */
.cv-form-resources { display: flex; flex-wrap: wrap; gap: var(--tv-space-1); margin-bottom: var(--tv-space-2); }
.cv-form-resource-add { display: flex; gap: var(--tv-space-2); align-items: center; }

/* Milestone rows in form */
.cv-milestone-row { display: flex; align-items: center; gap: var(--tv-space-2); }
.cv-milestone-row__num { width: 22px; text-align: center; font-size: var(--tv-text-xs); font-weight: var(--tv-font-bold); color: var(--tv-primary); flex-shrink: 0; }
.cv-milestone-input {
  flex: 1; height: 36px; padding: 0 var(--tv-space-3);
  font-size: var(--tv-text-sm); color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  outline: none; font-family: inherit; transition: border-color 0.15s;
}
.cv-milestone-input:focus { border-color: var(--tv-primary); }
.cv-milestone-remove { background: none; border: none; cursor: pointer; color: var(--tv-text-muted); font-size: 18px; line-height: 1; padding: 0; }
.cv-milestone-remove:hover { color: var(--tv-danger-fg); }
.cv-add-milestone-btn {
  font-size: var(--tv-text-sm); color: var(--tv-primary); background: none;
  border: 1px dashed var(--tv-primary-muted); border-radius: var(--tv-radius);
  padding: var(--tv-space-2) var(--tv-space-3); cursor: pointer;
  transition: background 0.12s; width: 100%; text-align: left;
}
.cv-add-milestone-btn:hover { background: var(--tv-primary-soft); }

/* Buttons */
.cv-btn {
  display: inline-flex; align-items: center; padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold);
  border-radius: var(--tv-radius); border: 1px solid transparent;
  cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.cv-btn--primary { background: var(--tv-primary); color: white; border-color: transparent; }
.cv-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.cv-btn--ghost { background: transparent; border-color: var(--tv-border); color: var(--tv-text-secondary); }
.cv-btn--ghost:hover { background: var(--tv-bg-soft); }
.cv-btn--danger { background: var(--tv-danger); color: white; border-color: transparent; }
.cv-btn--danger:hover:not(:disabled) { background: var(--tv-danger-hover); }
.cv-btn:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
