<template>
  <div class="an-page">

    <!-- Header -->
    <div class="an-header">
      <div>
        <h1 class="an-title">Announcements</h1>
        <p class="an-subtitle">{{ subtitle }}</p>
      </div>
      <button v-if="canAdmin" class="an-create-btn" type="button" @click="openCreate">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        New Announcement
      </button>
    </div>

    <!-- Filters (admin only) -->
    <div v-if="canAdmin" class="an-filters">
      <div class="an-search-wrap">
        <svg class="an-search-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M10 10l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <input v-model="search" class="an-search" type="search" placeholder="Search announcements..." />
      </div>
      <TVSelect v-model="filterTarget" class="an-filter-ctrl" :options="targetFilterOptions" placeholder="All Targets" />
      <TVSelect v-model="filterStatus" class="an-filter-ctrl" :options="statusFilterOptions" placeholder="All Statuses" />
      <TVSelect v-model="sortOrder" class="an-filter-ctrl" :options="sortOptions" />
      <button v-if="hasFilters" class="an-clear-btn" type="button" @click="clearFilters">Clear</button>
      <TVPagination
        v-if="displayList.length > AN_PAGE_SIZE"
        v-model="anPage"
        :total="displayList.length"
        :page-size="AN_PAGE_SIZE"
        class="an-pagination"
      />
    </div>

    <!-- Non-admin: sort + pagination row -->
    <div v-if="!canAdmin" class="an-sort-row">
      <TVSelect v-model="sortOrder" class="an-filter-ctrl" :options="sortOptions" />
      <TVPagination
        v-if="displayList.length > AN_PAGE_SIZE"
        v-model="anPage"
        :total="displayList.length"
        :page-size="AN_PAGE_SIZE"
        style="margin-left:auto"
      />
    </div>

    <!-- Announcement list -->
    <div v-if="paginated.length" class="an-list">
      <article
        v-for="ann in paginated"
        :key="ann.id"
        :class="['an-card', `an-card--${ann.status}`]"
      >
        <!-- Badges + action all right-aligned, vertically centered -->
        <!-- Left: text content -->
        <div class="an-card__content">
          <h2 class="an-card__title">{{ ann.title }}</h2>
          <p class="an-card__body">{{ ann.body }}</p>
          <div class="an-card__footer">
            <span class="an-card__by">By {{ ann.createdByName }}</span>
            <span class="an-card__date">{{ formatDate(ann.scheduledAt ?? ann.publishedAt) }}</span>
          </div>
        </div>

        <!-- Right: badges + action centered -->
        <div class="an-card__badge-action">
          <span :class="['an-type-badge', targetClass(ann)]">{{ targetLabel(ann) }}</span>
          <span v-if="ann.status === 'archived'" class="an-status-pill an-status-pill--archived">Archived</span>
          <span v-if="ann.status === 'scheduled'" class="an-status-pill an-status-pill--scheduled">Scheduled</span>
          <button
            v-if="canAdmin"
            :class="['an-icon-btn', ann.status === 'archived' ? 'an-icon-btn--restore' : 'an-icon-btn--archive']"
            :title="ann.status === 'archived' ? 'Restore' : 'Archive'"
            type="button"
            @click="ann.status === 'archived' ? notifStore.restoreAnnouncement(ann.id) : (confirmingArchive = ann)"
          >
            <svg v-if="ann.status === 'archived'" width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M1.5 6.5h10M6.5 2l4.5 4.5-4.5 4.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <svg v-else width="13" height="13" viewBox="0 0 13 13" fill="none"><rect x="0.5" y="1" width="12" height="3" rx="1" stroke="currentColor" stroke-width="1.1"/><path d="M1.5 4v7a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/><path d="M5 7h3" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/></svg>
          </button>
        </div>
      </article>
    </div>

    <!-- Empty -->
    <div v-if="!displayList.length" class="an-empty">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true">
        <path d="M5 14h20l5-5v20a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V14z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        <path d="M12 20h12M12 25h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      <p>No announcements{{ hasFilters ? ' matching your filters' : ' yet' }}.</p>
    </div>

    <!-- ── Create Announcement Modal ── -->
    <TVModal v-model="showCreateModal" title="New Announcement" maxWidth="560px">
      <div class="an-form">
        <TVInput v-model="form.title" label="Title" placeholder="e.g. Upcoming maintenance" required />
        <div class="an-field">
          <label class="an-label">Message</label>
          <textarea v-model="form.body" class="an-textarea" rows="4" placeholder="Write your announcement here…" />
        </div>

        <!-- Targeting -->
        <div class="an-field">
          <label class="an-label">Target Audience</label>
          <div class="an-form-row">
            <TVSelect v-model="form.targetType" :options="targetTypeOptions" label="Target by" />
            <TVSelect
              v-if="form.targetType === 'role'"
              v-model="form.targetRole"
              :options="roleOptions"
              label="Role"
            />
            <TVSelect
              v-if="form.targetType === 'course'"
              v-model="form.targetCourseId"
              :options="courseOptions"
              label="Course"
            />
            <TVSelect
              v-if="form.targetType === 'user'"
              v-model="form.targetUserId"
              :options="userOptions"
              label="User"
            />
          </div>
        </div>

        <!-- Scheduled publish -->
        <div class="an-field">
          <label class="an-label">
            <input v-model="form.scheduled" type="checkbox" class="an-checkbox" />
            Schedule publication
          </label>
          <TVDatePicker
            v-if="form.scheduled"
            v-model="form.scheduledDate"
            label="Publish on"
            :min="todayStr"
          />
        </div>
      </div>
      <template #footer>
        <button class="an-btn an-btn--ghost" type="button" @click="showCreateModal = false">Cancel</button>
        <button
          class="an-btn an-btn--primary"
          type="button"
          :disabled="!form.title.trim() || !form.body.trim()"
          @click="saveAnnouncement"
        >Publish</button>
      </template>
    </TVModal>

    <!-- ── Archive confirmation ── -->
    <TVModal v-model="showConfirmArchive" title="Archive Announcement" maxWidth="400px">
      <p class="an-confirm-text">
        Are you sure you want to archive <strong>{{ confirmingArchive?.title }}</strong>?
        It will no longer be visible to users. You can restore it later.
      </p>
      <template #footer>
        <button class="an-btn an-btn--ghost" type="button" @click="showConfirmArchive = false">Cancel</button>
        <button class="an-btn an-btn--danger" type="button" @click="doArchive">Archive</button>
      </template>
    </TVModal>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue'
import { useNotificationsStore } from '@/stores/notifications'
import { useCoursesStore } from '@/stores/courses'
import { useUsersStore } from '@/stores/users'
import { useAuthStore } from '@/stores/auth'
import { useViewAs } from '@/composables/useViewAs'
import { useToast } from '@/composables/useToast'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVModal from '@/components/ui/TVModal.vue'
import TVDatePicker from '@/components/ui/TVDatePicker.vue'
import TVPagination from '@/components/ui/TVPagination.vue'
import type { Announcement, TargetType } from '@/stores/notifications'
import type { UserRole } from '@/types'

const notifStore  = useNotificationsStore()
const coursesStore = useCoursesStore()
const usersStore  = useUsersStore()
const auth        = useAuthStore()
const toast       = useToast()
const { effectiveRole } = useViewAs()

const role   = computed(() => effectiveRole.value)
const userId = computed(() => auth.user?.id ?? '')
const canAdmin = computed(() => role.value === 'ADMIN' || role.value === 'STAFF')

// ── Subtitle ──
const subtitle = computed(() => {
  if (canAdmin.value) return 'Create and manage announcements for your users'
  return 'Important updates and information from the Tutorvio team'
})

// ── Data (role-scoped) ──
const enrolledCourseIds = computed(() =>
  coursesStore.getCoursesForStudent(userId.value).map(c => c.id)
)

const allForView = computed(() => {
  if (canAdmin.value) return notifStore.getAllAnnouncements()
  return notifStore.getAnnouncementsForUser(userId.value, role.value, enrolledCourseIds.value)
})

// ── Filters (admin) ──
const search       = ref('')
const filterTarget = ref('')
const filterStatus = ref('')
const sortOrder    = ref('newest')
const todayStr     = new Date().toLocaleDateString('sv-SE')

const sortOptions = [
  { value: 'newest', label: 'Newest first' },
  { value: 'oldest', label: 'Oldest first' },
]

const AN_PAGE_SIZE = 5
const anPage = ref(1)

const hasFilters = computed(() => !!search.value || !!filterTarget.value || !!filterStatus.value)
function clearFilters() { search.value = ''; filterTarget.value = ''; filterStatus.value = '' }
watch([search, filterTarget, filterStatus, sortOrder], () => { anPage.value = 1 })

const targetFilterOptions = [
  { value: '',                label: 'All Targets' },
  { value: 'ALL',             label: 'Everyone' },
  { value: 'role',            label: 'By Role' },
  { value: 'course',          label: 'By Course' },
  { value: 'user',            label: 'Specific User' },
  { value: 'teacher_group',   label: 'Teacher Group' },
  { value: 'student_group',   label: 'Student Group' },
]

const statusFilterOptions = [
  { value: '',           label: 'All Statuses' },
  { value: 'active',     label: 'Active' },
  { value: 'scheduled',  label: 'Scheduled' },
  { value: 'archived',   label: 'Archived' },
]

const displayList = computed(() => {
  const q = search.value.toLowerCase().trim()
  const result = allForView.value.filter(a => {
    if (q && !a.title.toLowerCase().includes(q) && !a.body.toLowerCase().includes(q)) return false
    if (filterTarget.value && a.targetType !== filterTarget.value) return false
    if (filterStatus.value && a.status !== filterStatus.value) return false
    return true
  })
  if (sortOrder.value === 'oldest') {
    return [...result].sort((a, b) => new Date(a.publishedAt).getTime() - new Date(b.publishedAt).getTime())
  }
  return result
})

const paginated = computed(() =>
  displayList.value.slice((anPage.value - 1) * AN_PAGE_SIZE, anPage.value * AN_PAGE_SIZE)
)

// ── Helpers ──
function targetLabel(a: Announcement): string {
  if (a.targetType === 'ALL') return 'Everyone'
  if (a.targetType === 'role') return `${a.targetRole ?? 'All roles'}`
  if (a.targetType === 'course') {
    const c = coursesStore.getCourseById(a.targetCourseId ?? '')
    return c ? `Course: ${c.name}` : 'Course'
  }
  if (a.targetType === 'user') {
    const u = usersStore.getUserById(a.targetUserId ?? '')
    return u ? `${u.firstName} ${u.lastName}` : 'User'
  }
  if (a.targetType === 'teacher_group') return 'Teachers'
  if (a.targetType === 'student_group') return 'Students'
  return a.targetType
}

function targetClass(a: Announcement): string {
  if (a.targetType === 'ALL') return 'an-type-badge--all'
  if (a.targetType === 'role') {
    if (a.targetRole === 'STUDENT') return 'an-type-badge--student'
    if (a.targetRole === 'TEACHER') return 'an-type-badge--teacher'
    if (a.targetRole === 'ADMIN')   return 'an-type-badge--admin'
  }
  if (a.targetType === 'course')        return 'an-type-badge--course'
  if (a.targetType === 'teacher_group') return 'an-type-badge--teacher'
  if (a.targetType === 'student_group') return 'an-type-badge--student'
  return 'an-type-badge--all'
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

// ── Create modal ──
const showCreateModal = ref(false)

const BLANK_FORM = () => ({
  title: '',
  body: '',
  targetType: 'ALL' as TargetType,
  targetRole: '' as UserRole | '',
  targetCourseId: '',
  targetUserId: '',
  scheduled: false,
  scheduledDate: '',
})
const form = reactive(BLANK_FORM())

const targetTypeOptions = [
  { value: 'ALL',           label: 'Everyone' },
  { value: 'role',          label: 'By Role' },
  { value: 'teacher_group', label: 'All Teachers' },
  { value: 'student_group', label: 'All Students' },
  { value: 'course',        label: 'By Course' },
  { value: 'user',          label: 'Specific User' },
]

const roleOptions = [
  { value: 'STUDENT', label: 'Students' },
  { value: 'TEACHER', label: 'Teachers' },
  { value: 'ADMIN',   label: 'Admins' },
  { value: 'STAFF',   label: 'Staff' },
]

const courseOptions = computed(() =>
  coursesStore.getCourses('active').map(c => ({ value: c.id, label: c.name }))
)

const userOptions = computed(() =>
  usersStore.filteredUsers({}).map(u => ({ value: u.id, label: `${u.firstName} ${u.lastName}` }))
)

function openCreate() {
  Object.assign(form, BLANK_FORM())
  showCreateModal.value = true
}

function saveAnnouncement() {
  if (!form.title.trim() || !form.body.trim()) return
  const now = new Date().toISOString()
  const isScheduled = form.scheduled && !!form.scheduledDate
  notifStore.createAnnouncement({
    title: form.title.trim(),
    body: form.body.trim(),
    targetType: form.targetType,
    targetRole: (form.targetType === 'role' ? form.targetRole as UserRole : undefined),
    targetCourseId: form.targetType === 'course' ? form.targetCourseId : undefined,
    targetUserId: form.targetType === 'user' ? form.targetUserId : undefined,
    status: isScheduled ? 'scheduled' : 'active',
    scheduledAt: isScheduled ? `${form.scheduledDate}T09:00:00Z` : undefined,
    publishedAt: isScheduled ? `${form.scheduledDate}T09:00:00Z` : now,
    createdById: userId.value,
    createdByName: `${auth.user?.firstName ?? ''} ${auth.user?.lastName ?? ''}`.trim(),
  })
  showCreateModal.value = false
  toast.success('Announcement published.')
}

// ── Archive ──
const confirmingArchive = ref<Announcement | null>(null)
const showConfirmArchive = computed({
  get: () => !!confirmingArchive.value,
  set: (v) => { if (!v) confirmingArchive.value = null },
})
function doArchive() {
  if (confirmingArchive.value) {
    notifStore.archiveAnnouncement(confirmingArchive.value.id)
    toast.success('Announcement archived.')
  }
  confirmingArchive.value = null
}
</script>

<style scoped>
.an-page { padding: var(--tv-space-6); display: flex; flex-direction: column; gap: var(--tv-space-5); }
@media (max-width: 767px) { .an-page { padding: var(--tv-space-4); } }

/* Header */
.an-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: var(--tv-space-3); }
.an-title    { font-size: var(--tv-text-2xl); font-weight: var(--tv-font-bold); color: var(--tv-text); margin: 0; letter-spacing: -0.025em; }
.an-subtitle { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: var(--tv-space-1) 0 0; }
.an-create-btn {
  display: inline-flex; align-items: center; gap: var(--tv-space-2);
  background: var(--tv-primary); color: white; border: none;
  border-radius: var(--tv-radius); padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); font-family: inherit;
  cursor: pointer; transition: background 0.15s;
}
.an-create-btn:hover { background: var(--tv-primary-hover); }

/* Filters */
.an-filters { display: flex; flex-wrap: wrap; gap: var(--tv-space-2); align-items: center; }
.an-search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 360px; }
.an-search-icon { position: absolute; left: var(--tv-space-3); top: 50%; transform: translateY(-50%); color: var(--tv-text-muted); pointer-events: none; }
.an-search {
  width: 100%; padding: 0 var(--tv-space-3) 0 calc(var(--tv-space-3) + 20px);
  font-size: var(--tv-text-sm); color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius); min-height: 42px;
  outline: none; transition: border-color 0.15s; font-family: inherit; box-sizing: border-box;
}
.an-search:focus { border-color: var(--tv-primary); }
.an-filter-ctrl { width: 160px; flex-shrink: 0; }
.an-clear-btn {
  font-size: var(--tv-text-sm); color: var(--tv-text-muted); background: none;
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius);
  padding: 0 var(--tv-space-3); min-height: 42px; min-width: 72px; display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: color 0.15s, background 0.15s;
}
.an-clear-btn:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

/* Sort row (non-admin) */
.an-sort-row { display: flex; align-items: center; gap: var(--tv-space-2); }
.an-pagination { margin-left: auto; flex-shrink: 0; }

/* List */
.an-list { display: flex; flex-direction: column; gap: var(--tv-space-3); }

/* Card */
.an-card {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); padding: var(--tv-space-4) var(--tv-space-5);
  box-shadow: var(--tv-shadow-sm);
  display: flex; flex-direction: row; align-items: center; gap: var(--tv-space-5);
  transition: border-color 0.15s, box-shadow 0.15s;
}
.an-card:hover { border-color: var(--tv-primary-muted); box-shadow: 0 2px 8px hsla(var(--tv-primary-h), var(--tv-primary-s), 50%, 0.07); }
.an-card--archived { opacity: 0.6; }
.an-card--scheduled { border-left: 3px solid var(--tv-warning); }

/* Left: text */
.an-card__content { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: var(--tv-space-1); }
/* Right: badges + action, vertically centered */
.an-card__badge-action { display: flex; align-items: center; gap: var(--tv-space-2); flex-shrink: 0; }

.an-card__title { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.an-card__body  { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); line-height: 1.6; margin: 0; }
.an-card__footer { display: flex; align-items: center; gap: var(--tv-space-3); flex-wrap: wrap; }
.an-card__by   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.an-card__date { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }

/* Target badge */
.an-type-badge {
  font-size: 10px; font-weight: var(--tv-font-bold); padding: 2px 8px;
  border-radius: var(--tv-radius-full); letter-spacing: .05em; text-transform: uppercase;
}
.an-type-badge--all     { background: var(--tv-bg-soft);   color: var(--tv-text-muted); border: 1px solid var(--tv-border); }
.an-type-badge--student { background: var(--tv-primary-soft); color: var(--tv-primary); }
.an-type-badge--teacher { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.an-type-badge--admin   { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }
.an-type-badge--course  { background: var(--tv-info-soft);  color: var(--tv-info-fg); }

/* Status pill */
.an-status-pill { font-size: 10px; font-weight: var(--tv-font-bold); padding: 2px 8px; border-radius: var(--tv-radius-full); text-transform: uppercase; letter-spacing: .05em; }
.an-status-pill--archived  { background: var(--tv-bg-soft); color: var(--tv-text-muted); border: 1px solid var(--tv-border); }
.an-status-pill--scheduled { background: var(--tv-warning-soft); color: var(--tv-warning-fg); }

/* Actions */
.an-card__actions { display: flex; gap: var(--tv-space-1); }
.an-icon-btn {
  width: 28px; height: 28px; border-radius: var(--tv-radius-sm);
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid; cursor: pointer; transition: background 0.12s;
}
.an-icon-btn--archive { background: var(--tv-danger-soft); border-color: var(--tv-danger-border); color: var(--tv-danger-fg); }
.an-icon-btn--archive:hover { background: hsl(0,72%,88%); }
.an-icon-btn--restore { background: var(--tv-success-soft); border-color: var(--tv-success-border); color: var(--tv-success-fg); }
.an-icon-btn--restore:hover { background: hsl(142,70%,87%); }

/* Empty */
.an-empty { display: flex; flex-direction: column; align-items: center; gap: var(--tv-space-3); padding: var(--tv-space-10) var(--tv-space-4); color: var(--tv-text-muted); text-align: center; }
.an-empty svg { opacity: 0.35; }
.an-empty p { font-size: var(--tv-text-sm); margin: 0; }

/* Form */
.an-form { display: flex; flex-direction: column; gap: var(--tv-space-4); }
.an-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--tv-space-3); }
.an-field { display: flex; flex-direction: column; gap: var(--tv-space-2); }
.an-label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); display: flex; align-items: center; gap: var(--tv-space-2); }
.an-checkbox { width: 15px; height: 15px; accent-color: var(--tv-primary); cursor: pointer; }
.an-textarea {
  padding: var(--tv-space-2) var(--tv-space-3); font-size: var(--tv-text-sm);
  color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  resize: vertical; outline: none; font-family: inherit; line-height: 1.5;
  transition: border-color 0.15s;
}
.an-textarea:focus { border-color: var(--tv-primary); }

/* Confirm */
.an-confirm-text { font-size: var(--tv-text-sm); color: var(--tv-text-secondary); line-height: 1.6; margin: 0; }

/* Buttons */
.an-btn {
  display: inline-flex; align-items: center; padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold);
  border-radius: var(--tv-radius); border: 1px solid transparent;
  cursor: pointer; font-family: inherit; transition: background 0.15s;
}
.an-btn--primary { background: var(--tv-primary); color: white; }
.an-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.an-btn--ghost { background: transparent; border-color: var(--tv-border); color: var(--tv-text-secondary); }
.an-btn--ghost:hover { background: var(--tv-bg-soft); }
.an-btn--danger { background: var(--tv-danger); color: white; }
.an-btn--danger:hover:not(:disabled) { background: var(--tv-danger-hover); }
.an-btn:disabled { opacity: 0.45; cursor: not-allowed; }
</style>
