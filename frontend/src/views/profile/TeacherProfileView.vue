<template>
  <div class="tp-page">

    <button v-if="!isOwnProfile" class="tp-back" @click="router.back()">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
        <path d="M11 13l-4-4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Back
    </button>

    <!-- Hero -->
    <div class="tp-hero">
      <div class="tp-hero__avatar" aria-hidden="true">
        <img v-if="user.avatarUrl" :src="user.avatarUrl" :alt="fullName" />
        <span v-else>{{ initials }}</span>
      </div>
      <div class="tp-hero__info">
        <div class="tp-hero__name-row">
          <h1 class="tp-hero__name">{{ fullName }}</h1>
          <TVButton
            v-if="viewerRole === 'ADMIN'"
            variant="ghost"
            size="sm"
            @click="router.push(`/admin/users/${user.id}/edit`)"
          >
            Edit profile
          </TVButton>
        </div>
        <div class="tp-hero__badges">
          <span class="tp-role-pill">Teacher</span>
          <TVBadge :label="user.isActive ? 'Active' : 'Inactive'" :variant="user.isActive ? 'active' : 'neutral'" dot />
          <TVBadge
            v-if="profile.internalStatus"
            :label="internalStatusLabel"
            :variant="internalStatusVariant"
          />
        </div>
        <div class="tp-hero__meta">
          <span>{{ user.email }}</span>
          <span class="tp-meta-dot">·</span>
          <span>{{ user.timezone }}</span>
          <span class="tp-meta-dot">·</span>
          <span>Joined {{ fmt(user.createdAt) }}</span>
        </div>
        <p v-if="profile.specialization" class="tp-hero__spec">{{ profile.specialization }}</p>
      </div>
    </div>

    <!-- Body -->
    <div class="tp-layout">
      <div class="tp-layout__main">

        <!-- Personal Details + Teacher Bio (side by side) -->
        <div class="tp-row-2">
          <section class="tp-section">
            <h2 class="tp-section__title">Personal Details</h2>
            <div class="tp-detail">
              <span class="tp-detail__label">Full Name</span>
              <span class="tp-detail__value">{{ fullName }}</span>
            </div>
            <div class="tp-detail">
              <span class="tp-detail__label">Email Address</span>
              <span class="tp-detail__value">{{ user.email }}</span>
            </div>
            <div class="tp-detail">
              <span class="tp-detail__label">Timezone</span>
              <span class="tp-detail__value">{{ user.timezone }}</span>
            </div>
            <div class="tp-detail">
              <span class="tp-detail__label">Member Since</span>
              <span class="tp-detail__value">{{ fmt(user.createdAt) }}</span>
            </div>
            <div class="tp-detail">
              <span class="tp-detail__label">Status</span>
              <span class="tp-status-inline" :class="user.isActive ? 'tp-status-inline--active' : 'tp-status-inline--inactive'">
                {{ user.isActive ? 'Active' : 'Inactive' }}
              </span>
            </div>
          </section>

          <section class="tp-section">
            <h2 class="tp-section__title">Teacher Bio</h2>
            <div class="tp-detail">
              <span class="tp-detail__label">Specialization</span>
              <span class="tp-detail__value">{{ profile.specialization || '—' }}</span>
            </div>
            <div class="tp-detail">
              <span class="tp-detail__label">About</span>
              <p class="tp-detail__text">{{ MOCK_BIO }}</p>
            </div>
          </section>
        </div>

        <!-- Professional Profile (admin/staff: doc statuses) -->
        <section v-if="viewerRole === 'ADMIN' || viewerRole === 'STAFF'" class="tp-section">
          <h2 class="tp-section__title">Professional Profile</h2>
          <div class="tp-grid-2">
            <div class="tp-detail">
              <span class="tp-detail__label">Document Status</span>
              <TVBadge :label="docLabel(profile.documentStatus)" :variant="docVariant(profile.documentStatus)" />
            </div>
            <div class="tp-detail">
              <span class="tp-detail__label">Contract Status</span>
              <TVBadge :label="docLabel(profile.contractStatus)" :variant="docVariant(profile.contractStatus)" />
            </div>
          </div>
        </section>

        <!-- Assigned Students -->
        <section class="tp-section">
          <div class="tp-section__head">
            <h2 class="tp-section__title tp-section__title--inline">Assigned Students</h2>
            <span class="tp-section__badge">{{ assignedStudents.length }} student{{ assignedStudents.length !== 1 ? 's' : '' }}</span>
          </div>
          <ul v-if="assignedStudents.length" class="tp-students">
            <li
              v-for="student in assignedStudents"
              :key="student.id"
              class="tp-student"
              :class="{ 'tp-student--clickable': viewerRole === 'ADMIN' || isOwnProfile }"
              @click="(viewerRole === 'ADMIN' || isOwnProfile) && router.push(`/profile/${student.id}`)"
            >
              <div class="tp-student__avatar" aria-hidden="true">
                {{ `${student.firstName[0]}${student.lastName[0]}`.toUpperCase() }}
              </div>
              <div class="tp-student__info">
                <span class="tp-student__name">{{ student.firstName }} {{ student.lastName }}</span>
                <span class="tp-student__meta">
                  {{ levelLabel(student.studentProfile?.englishLevel) }}
                  <template v-if="student.studentProfile?.program"> · {{ student.studentProfile.program }}</template>
                </span>
              </div>
              <TVBadge
                :label="student.isActive ? 'Active' : 'Inactive'"
                :variant="student.isActive ? 'active' : 'neutral'"
              />
              <svg v-if="viewerRole === 'ADMIN' || isOwnProfile" width="14" height="14" viewBox="0 0 14 14" fill="none" class="tp-student__arrow" aria-hidden="true">
                <path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </li>
          </ul>
          <p v-else class="tp-empty-note">No students assigned yet.</p>
        </section>

        <!-- Internal Remarks — admin + own teacher -->
        <section
          v-if="(viewerRole === 'ADMIN' || isOwnProfile) && profile.teachingNotes"
          class="tp-section tp-section--internal"
        >
          <h2 class="tp-section__title tp-section__title--internal">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <rect x="2" y="6" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.3"/>
              <path d="M4.5 6V4.5a2.5 2.5 0 0 1 5 0V6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            Internal Remarks
          </h2>
          <p class="tp-detail__text">{{ profile.teachingNotes }}</p>
        </section>

      </div>

      <!-- Aside -->
      <div class="tp-layout__aside">

        <!-- Class Load -->
        <div class="tp-card">
          <h3 class="tp-card__title">Class Load</h3>
          <div class="tp-big-stat">
            <span class="tp-big-stat__num">{{ assignedStudents.length }}</span>
            <span class="tp-big-stat__label">Active Students</span>
          </div>
          <div class="tp-card__sub-title">Availability</div>
          <ul v-if="availabilitySlots.length" class="tp-avail-list">
            <li v-for="slot in availabilitySlots" :key="slot" class="tp-avail-item">
              <span class="tp-avail-dot" aria-hidden="true" />
              {{ slot }}
            </li>
          </ul>
          <p v-else class="tp-empty-note">Not set</p>
        </div>

        <!-- Performance -->
        <div class="tp-card">
          <h3 class="tp-card__title">Performance</h3>
          <div class="tp-stat-row">
            <span class="tp-stat__label">Avg. rating</span>
            <span class="tp-stat__value tp-stat__value--accent">★ {{ MOCK_PERF.avgRating }}</span>
          </div>
          <div class="tp-stat-row">
            <span class="tp-stat__label">Total lessons</span>
            <span class="tp-stat__value">{{ MOCK_PERF.totalLessons }}</span>
          </div>
          <div class="tp-stat-row">
            <span class="tp-stat__label">Completion rate</span>
            <span class="tp-stat__value">{{ MOCK_PERF.completionRate }}%</span>
          </div>
          <div class="tp-stat-row">
            <span class="tp-stat__label">Retention rate</span>
            <span class="tp-stat__value">{{ MOCK_PERF.retentionRate }}%</span>
          </div>
        </div>

        <!-- Account Info (admin/staff) -->
        <div v-if="viewerRole === 'ADMIN' || viewerRole === 'STAFF'" class="tp-card">
          <h3 class="tp-card__title">Account Info</h3>
          <div class="tp-stat-row">
            <span class="tp-stat__label">Internal status</span>
            <TVBadge :label="internalStatusLabel" :variant="internalStatusVariant" />
          </div>
          <div class="tp-stat-row">
            <span class="tp-stat__label">Last login</span>
            <span class="tp-stat__value">{{ user.lastLoginAt ? fmt(user.lastLoginAt) : '—' }}</span>
          </div>
          <div class="tp-stat-row">
            <span class="tp-stat__label">Member since</span>
            <span class="tp-stat__value">{{ fmt(user.createdAt) }}</span>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUsersStore } from '@/stores/users'
import TVButton from '@/components/ui/TVButton.vue'
import TVBadge from '@/components/ui/TVBadge.vue'
import type { ManagedUser, UserRole } from '@/types'

const props = defineProps<{
  user: ManagedUser
  viewerRole: UserRole
  isOwnProfile: boolean
}>()

const router = useRouter()
const store = useUsersStore()

const MOCK_PERF = { avgRating: 4.8, totalLessons: 312, completionRate: 96, retentionRate: 89 }
const MOCK_BIO = 'Experienced English language instructor with a focus on professional communication and business contexts. Passionate about helping students build confidence and fluency through practical, real-world scenarios.'

const profile = computed(() => props.user.teacherProfile ?? {})
const fullName = computed(() => `${props.user.firstName} ${props.user.lastName}`)
const initials = computed(() => `${props.user.firstName[0]}${props.user.lastName[0]}`.toUpperCase())

const assignedStudents = computed(() =>
  (profile.value.assignedStudentIds ?? [])
    .map(id => store.getUserById(id))
    .filter((u): u is ManagedUser => !!u),
)

const availabilitySlots = computed(() => {
  const raw = profile.value.availabilitySummary
  if (!raw || raw === 'TBD') return raw ? [raw] : []
  return raw.split(/,\s*/).map(s => s.trim()).filter(Boolean)
})

const INTERNAL_STATUS: Record<string, { label: string; variant: string }> = {
  ACTIVE:    { label: 'Active',    variant: 'active' },
  ON_LEAVE:  { label: 'On Leave',  variant: 'warning' },
  PROBATION: { label: 'Probation', variant: 'pending' },
  INACTIVE:  { label: 'Inactive',  variant: 'neutral' },
}

const internalStatusLabel = computed(() =>
  INTERNAL_STATUS[profile.value.internalStatus ?? '']?.label ?? profile.value.internalStatus ?? '',
)
const internalStatusVariant = computed(() =>
  (INTERNAL_STATUS[profile.value.internalStatus ?? '']?.variant ?? 'neutral') as UserRole,
)

const LEVEL_LABELS: Record<string, string> = {
  BEGINNER: 'A1', ELEMENTARY: 'A2', INTERMEDIATE: 'B1',
  UPPER_INTERMEDIATE: 'B2', ADVANCED: 'C1', PROFICIENCY: 'C2',
}
function levelLabel(level?: string): string {
  return level ? (LEVEL_LABELS[level] ?? level) : '—'
}

const DOC_STATUS: Record<string, { label: string; variant: string }> = {
  APPROVED:  { label: 'Approved',  variant: 'active' },
  SUBMITTED: { label: 'Submitted', variant: 'info' },
  PENDING:   { label: 'Pending',   variant: 'pending' },
  REJECTED:  { label: 'Rejected',  variant: 'missed' },
}
function docLabel(status?: string): string {
  return DOC_STATUS[status ?? '']?.label ?? status ?? '—'
}
function docVariant(status?: string): string {
  return DOC_STATUS[status ?? '']?.variant ?? 'neutral'
}

function fmt(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<style scoped>
.tp-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

.tp-back {
  display: inline-flex;
  align-items: center;
  gap: var(--tv-space-1);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  transition: color var(--tv-transition-fast);
  align-self: flex-start;
}
.tp-back:hover { color: var(--tv-primary); }

/* Hero */
.tp-hero {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-6);
  display: flex;
  align-items: flex-start;
  gap: var(--tv-space-5);
}

.tp-hero__avatar {
  width: 80px;
  height: 80px;
  border-radius: var(--tv-radius-full);
  background: hsl(142, 70%, 90%);
  color: hsl(142, 70%, 25%);
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}
.tp-hero__avatar img { width: 100%; height: 100%; object-fit: cover; }

.tp-hero__info { flex: 1; min-width: 0; }

.tp-hero__name-row {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
  margin-bottom: var(--tv-space-2);
}

.tp-hero__name {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  letter-spacing: -0.025em;
  color: var(--tv-text);
  margin: 0;
}

.tp-hero__badges {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  margin-bottom: var(--tv-space-3);
  flex-wrap: wrap;
}

.tp-role-pill {
  display: inline-flex;
  align-items: center;
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  background: hsl(142, 70%, 90%);
  color: hsl(142, 70%, 25%);
}

.tp-hero__meta {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  flex-wrap: wrap;
}
.tp-meta-dot { color: var(--tv-border); }

.tp-hero__spec {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-secondary);
  margin: var(--tv-space-2) 0 0;
  font-style: italic;
}

/* Layout */
.tp-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: var(--tv-space-5);
  align-items: start;
}
.tp-layout__main { display: flex; flex-direction: column; gap: var(--tv-space-4); }
.tp-layout__aside { display: flex; flex-direction: column; gap: var(--tv-space-4); position: sticky; top: calc(var(--tv-header-height) + var(--tv-space-4)); }

/* Row of two sections */
.tp-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--tv-space-4);
  align-items: start;
}

/* Sections */
.tp-section {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-5);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
}
.tp-section--internal { border-color: hsl(38, 92%, 80%); background: hsl(38, 92%, 99%); }

.tp-section__title {
  font-size: var(--tv-text-base);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
  padding-bottom: var(--tv-space-3);
  border-bottom: 1px solid var(--tv-border);
}
.tp-section__title--internal {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  color: hsl(38, 70%, 35%);
  border-bottom-color: hsl(38, 92%, 85%);
}
.tp-section__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: var(--tv-space-3);
  border-bottom: 1px solid var(--tv-border);
}
.tp-section__title--inline { margin: 0; padding: 0; border: none; }
.tp-section__badge {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}

.tp-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: var(--tv-space-4); }

.tp-detail { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.tp-detail__label {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.tp-detail__value { font-size: var(--tv-text-sm); color: var(--tv-text); }
.tp-detail__text { font-size: var(--tv-text-sm); color: var(--tv-text); margin: 0; line-height: 1.6; }

/* Status inline */
.tp-status-inline {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
}
.tp-status-inline::before {
  content: '';
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}
.tp-status-inline--active { color: var(--tv-success-fg); }
.tp-status-inline--active::before { background: var(--tv-success); }
.tp-status-inline--inactive { color: var(--tv-text-muted); }
.tp-status-inline--inactive::before { background: var(--tv-text-muted); }

/* Student list */
.tp-students { list-style: none; display: flex; flex-direction: column; gap: 0; }
.tp-student {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-3) 0;
  border-bottom: 1px solid var(--tv-border);
}
.tp-student:last-child { border-bottom: none; }
.tp-student--clickable { cursor: pointer; border-radius: var(--tv-radius-sm); }
.tp-student--clickable:hover { background: var(--tv-bg-soft); }

.tp-student__avatar {
  width: 36px;
  height: 36px;
  border-radius: var(--tv-radius-full);
  background: hsl(199, 89%, 92%);
  color: hsl(199, 89%, 28%);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.tp-student__info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.tp-student__name { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); }
.tp-student__meta { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.tp-student__arrow { color: var(--tv-text-muted); flex-shrink: 0; }

.tp-empty-note { font-size: var(--tv-text-sm); color: var(--tv-text-muted); margin: 0; font-style: italic; }

/* Cards */
.tp-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
}
.tp-card__title {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0;
  padding-bottom: var(--tv-space-2);
  border-bottom: 1px solid var(--tv-border);
}
.tp-card__sub-title {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-top: var(--tv-space-1);
}

.tp-big-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: var(--tv-space-2) 0;
}
.tp-big-stat__num {
  font-size: var(--tv-text-3xl, 1.875rem);
  font-weight: var(--tv-font-bold);
  color: hsl(142, 70%, 25%);
  line-height: 1;
}
.tp-big-stat__label { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin-top: var(--tv-space-1); }

/* Availability bullet list */
.tp-avail-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
  margin: 0;
  padding: 0;
}
.tp-avail-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
}
.tp-avail-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: hsl(142, 70%, 40%);
  flex-shrink: 0;
}

.tp-stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: var(--tv-text-sm);
  padding: var(--tv-space-1) 0;
  border-bottom: 1px solid var(--tv-bg-soft);
}
.tp-stat-row:last-child { border-bottom: none; }
.tp-stat__label { color: var(--tv-text-muted); }
.tp-stat__value { font-weight: var(--tv-font-medium); color: var(--tv-text); }
.tp-stat__value--accent { color: hsl(38, 80%, 40%); }

/* Responsive */
@media (max-width: 1100px) {
  .tp-layout { grid-template-columns: 1fr; }
  .tp-layout__aside { position: static; flex-direction: row; flex-wrap: wrap; }
  .tp-card { flex: 1; min-width: 200px; }
}
@media (max-width: 767px) {
  .tp-page { padding: var(--tv-space-4); }
  .tp-hero { flex-direction: column; }
  .tp-row-2 { grid-template-columns: 1fr; }
  .tp-grid-2 { grid-template-columns: 1fr; }
  .tp-layout__aside { flex-direction: column; }
}
</style>
