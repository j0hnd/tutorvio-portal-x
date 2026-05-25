<template>
  <div class="sv-page">

    <!-- Header -->
    <div class="sv-page__header">
      <div>
        <h1 class="sv-page__title">Students</h1>
        <p class="sv-page__subtitle">{{ rows.length }} student{{ rows.length !== 1 ? 's' : '' }}</p>
      </div>
      <TVButton v-if="canCreate" variant="primary" @click="router.push('/admin/users/create')">
        <template #icon>
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </template>
        Add Student
      </TVButton>
    </div>

    <!-- Filters -->
    <div class="sv-page__filters">
      <div class="sv-page__search">
        <TVInput v-model="search" placeholder="Search by name or email…" aria-label="Search students">
          <template #icon-start>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <circle cx="6.5" cy="6.5" r="4" stroke="currentColor" stroke-width="1.4"/>
              <path d="M11 11l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
          </template>
        </TVInput>
      </div>
      <div class="sv-page__filter-selects">
        <TVSelect v-model="levelFilter" :options="levelOptions" placeholder="All Levels" />
        <TVSelect v-model="statusFilter" :options="statusOptions" placeholder="All Statuses" />
        <TVSelect v-if="showTeacherFilter" v-model="teacherFilter" :options="teacherFilterOptions" placeholder="All Teachers" />
      </div>
    </div>

    <!-- Table -->
    <TVDataTable
      :columns="columns"
      :rows="rows"
      row-key="id"
      :loading="store.loading"
      loading-text="Loading students…"
      :page-size="15"
      :page-size-options="[10, 15, 25, 50]"
      clickable
      aria-label="Students list"
      empty-title="No students found"
      empty-subtitle="Try adjusting your search or filters"
      @row-click="(row) => router.push(`/students/${row.id}`)"
    >
      <template #cell-name="{ row }">
        <div class="sv-cell">
          <div class="sv-cell__avatar" aria-hidden="true">
            <img v-if="row.avatarUrl" :src="String(row.avatarUrl)" :alt="String(row._fullName)" />
            <span v-else>{{ row._initials }}</span>
          </div>
          <div class="sv-cell__info">
            <span class="sv-cell__name">{{ row._fullName }}</span>
            <span class="sv-cell__sub">{{ row.email }}</span>
          </div>
        </div>
      </template>

      <template #cell-level="{ row }">
        <span v-if="row._level" :class="['sv-level', `sv-level--${String(row._level).toLowerCase().replace(/_/g, '-')}`]">
          {{ levelLabel(String(row._level)) }}
        </span>
        <span v-else class="sv-muted">—</span>
      </template>

      <template #cell-teacher="{ row }">
        <span v-if="row._teacherName" class="sv-teacher">{{ row._teacherName }}</span>
        <span v-else class="sv-muted">Unassigned</span>
      </template>

      <template #cell-lessons="{ row }">
        <span class="sv-lessons-count">{{ row._lessonCount }}</span>
      </template>

      <template #cell-status="{ row }">
        <TVBadge
          :label="row.isActive ? 'Active' : 'Inactive'"
          :variant="row.isActive ? 'active' : 'neutral'"
          dot
        />
      </template>

      <template #cell-actions="{ row }">
        <div class="sv-actions" @click.stop>
          <button
            class="sv-icon-btn sv-icon-btn--view"
            :aria-label="`View ${row._fullName}`"
            title="View profile"
            @click="router.push(`/students/${row.id}`)"
          >
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
              <circle cx="7.5" cy="7.5" r="5.5" stroke="currentColor" stroke-width="1.4"/>
              <circle cx="7.5" cy="7.5" r="2" stroke="currentColor" stroke-width="1.3"/>
            </svg>
          </button>
          <button
            class="sv-icon-btn sv-icon-btn--edit"
            :aria-label="`Edit ${row._fullName}`"
            title="Edit"
            @click="router.push(`/admin/users/${row.id}/edit`)"
          >
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
              <path d="M10.5 2.5l2 2L5 12H3v-2l7.5-7.5z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
      </template>

      <template #empty>
        <div class="sv-empty-icon" aria-hidden="true">
          <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
            <circle cx="15" cy="12" r="6" stroke="currentColor" stroke-width="1.8"/>
            <path d="M4 32c0-6.627 4.925-10 11-10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M27 22v8M23 26h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <p class="sv-empty-title">No students found</p>
        <p class="sv-empty-sub">Try adjusting your search or filters</p>
      </template>
    </TVDataTable>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUsersStore } from '@/stores/users'
import { useScheduleStore } from '@/stores/schedule'
import { useViewAs } from '@/composables/useViewAs'
import TVButton from '@/components/ui/TVButton.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVBadge from '@/components/ui/TVBadge.vue'
import TVDataTable from '@/components/ui/TVDataTable.vue'
import type { DataTableColumn } from '@/components/ui/TVDataTable.vue'

const router   = useRouter()
const store    = useUsersStore()
const schedule = useScheduleStore()
const { effectiveRole } = useViewAs()

const search       = ref('')
const levelFilter  = ref('')
const statusFilter = ref<'active' | 'inactive' | ''>('')
const teacherFilter = ref('')

const canCreate = computed(() => ['ADMIN', 'STAFF'].includes(effectiveRole.value))
const showTeacherFilter = computed(() => ['ADMIN', 'STAFF'].includes(effectiveRole.value))

onMounted(() => {
  if (!store.users.length) store.fetchUsers()
})

const teachers = computed(() =>
  store.users.filter(u => u.role === 'TEACHER' && u.isActive)
)

const teacherFilterOptions = computed(() =>
  teachers.value.map(t => ({ value: t.id, label: `${t.firstName} ${t.lastName}` }))
)

const levelOptions = [
  { value: 'BEGINNER',      label: 'Beginner' },
  { value: 'ELEMENTARY',    label: 'Elementary' },
  { value: 'INTERMEDIATE',  label: 'Intermediate' },
  { value: 'UPPER_INTERMEDIATE', label: 'Upper Intermediate' },
  { value: 'ADVANCED',      label: 'Advanced' },
]

const statusOptions = [
  { value: 'active',   label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
]

function levelLabel(level: string): string {
  const map: Record<string, string> = {
    BEGINNER: 'Beginner', ELEMENTARY: 'Elementary', INTERMEDIATE: 'Intermediate',
    UPPER_INTERMEDIATE: 'Upper Int.', ADVANCED: 'Advanced',
  }
  return map[level] ?? level
}

const lessonCountByStudent = computed(() => {
  const counts: Record<string, number> = {}
  for (const l of schedule.lessons) {
    counts[l.studentId] = (counts[l.studentId] ?? 0) + 1
  }
  return counts
})

const teacherNameById = computed(() => {
  const map: Record<string, string> = {}
  for (const t of teachers.value) map[t.id] = `${t.firstName} ${t.lastName}`
  return map
})

const filteredStudents = computed(() => {
  const q = search.value.toLowerCase()
  return store.users.filter(u => {
    if (u.role !== 'STUDENT') return false
    if (statusFilter.value === 'active' && !u.isActive) return false
    if (statusFilter.value === 'inactive' && u.isActive) return false
    if (levelFilter.value && u.studentProfile?.englishLevel !== levelFilter.value) return false
    if (teacherFilter.value && u.studentProfile?.assignedTeacherId !== teacherFilter.value) return false
    if (q && !`${u.firstName} ${u.lastName} ${u.email}`.toLowerCase().includes(q)) return false
    return true
  })
})

const columns: DataTableColumn[] = [
  { key: 'name',    label: 'Name',    sortable: true,  sortKey: '_fullName', width: '30%' },
  { key: 'level',   label: 'Level',   sortable: true,  sortKey: '_level', width: '14%' },
  { key: 'teacher', label: 'Teacher', sortable: false, hide: 'sm', width: '18%' },
  { key: 'lessons', label: 'Lessons', sortable: true,  sortKey: '_lessonCount', width: '10%' },
  { key: 'status',  label: 'Status',  sortable: false, width: '12%' },
  { key: 'actions', label: '',        stopClick: true, width: '10%' },
]

const rows = computed(() =>
  filteredStudents.value.map(u => ({
    ...u,
    _fullName:    `${u.firstName} ${u.lastName}`,
    _initials:    `${u.firstName[0]}${u.lastName[0]}`.toUpperCase(),
    _level:       u.studentProfile?.englishLevel ?? '',
    _teacherName: u.studentProfile?.assignedTeacherId
      ? (teacherNameById.value[u.studentProfile.assignedTeacherId] ?? '')
      : '',
    _lessonCount: lessonCountByStudent.value[u.id] ?? 0,
  }))
)
</script>

<style scoped>
.sv-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

.sv-page__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--tv-space-4);
  flex-wrap: wrap;
}

.sv-page__title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
  margin: 0 0 var(--tv-space-1);
}

.sv-page__subtitle { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: 0; }

.sv-page__filters {
  display: flex; gap: var(--tv-space-3); flex-wrap: wrap; align-items: flex-end;
}

.sv-page__search { flex: 1; min-width: 200px; max-width: 360px; }

.sv-page__filter-selects { display: flex; gap: var(--tv-space-3); flex-wrap: wrap; }
.sv-page__filter-selects > * { width: 160px; flex-shrink: 0; }

/* Name cell */
.sv-cell { display: flex; align-items: center; gap: var(--tv-space-3); }

.sv-cell__avatar {
  width: 36px; height: 36px; border-radius: var(--tv-radius-full);
  background: hsl(199, 89%, 92%); color: hsl(199, 89%, 28%);
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-bold);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; overflow: hidden;
}
.sv-cell__avatar img { width: 100%; height: 100%; object-fit: cover; }

.sv-cell__info { display: flex; flex-direction: column; gap: 2px; }
.sv-cell__name { font-weight: var(--tv-font-medium); color: var(--tv-text); }
.sv-cell__sub  { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }

/* Level badge */
.sv-level {
  display: inline-flex; align-items: center;
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold);
  padding: 2px 8px; border-radius: var(--tv-radius-full); border: 1px solid;
  white-space: nowrap;
}
.sv-level--beginner         { background: hsl(38,88%,93%); color: hsl(38,75%,30%); border-color: hsl(38,70%,72%); }
.sv-level--elementary       { background: hsl(48,88%,92%); color: hsl(42,72%,28%); border-color: hsl(42,68%,68%); }
.sv-level--intermediate     { background: hsl(199,89%,92%); color: hsl(199,89%,28%); border-color: hsl(199,70%,68%); }
.sv-level--upper-intermediate { background: hsl(262,70%,93%); color: hsl(262,65%,38%); border-color: hsl(262,60%,72%); }
.sv-level--advanced         { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }

.sv-teacher { font-size: var(--tv-text-sm); color: var(--tv-text); }
.sv-muted   { font-size: var(--tv-text-sm); color: var(--tv-text-muted); font-style: italic; }

.sv-lessons-count {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
  color: var(--tv-text); background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius-full);
  padding: 1px 8px; display: inline-block;
}

/* Actions */
.sv-actions { display: flex; gap: var(--tv-space-1); align-items: center; }

.sv-icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: var(--tv-radius-sm);
  border: 1px solid transparent; cursor: pointer;
  transition: background-color var(--tv-transition-fast), filter var(--tv-transition-fast);
  flex-shrink: 0;
}

.sv-icon-btn--view {
  background: hsl(199,89%,93%); border-color: hsl(199,70%,72%); color: hsl(199,89%,28%);
}
.sv-icon-btn--view:hover { filter: brightness(0.92); }

.sv-icon-btn--edit {
  background: var(--tv-primary-soft); border-color: var(--tv-primary-muted); color: var(--tv-primary);
}
.sv-icon-btn--edit:hover { filter: brightness(0.92); }

/* Empty state */
.sv-empty-icon {
  width: 72px; height: 72px; border-radius: var(--tv-radius-full);
  background: hsl(199,89%,92%); color: hsl(199,89%,28%);
  display: flex; align-items: center; justify-content: center;
}
.sv-empty-title { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }
.sv-empty-sub   { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: 0; }

@media (max-width: 767px) {
  .sv-page { padding: var(--tv-space-4); }
  .sv-page__search { max-width: 100%; width: 100%; }
  .sv-page__filter-selects { width: 100%; }
  .sv-page__filter-selects > * { width: auto; flex: 1; }
  .sv-cell__sub { display: none; }
}
</style>
