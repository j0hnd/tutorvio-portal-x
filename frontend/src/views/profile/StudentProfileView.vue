<template>
  <div class="sp-page">

    <!-- Back (when admin/teacher viewing someone else) -->
    <button v-if="!isOwnProfile" class="sp-back" @click="router.back()">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
        <path d="M11 13l-4-4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Back
    </button>

    <!-- Hero -->
    <div class="sp-hero">
      <div class="sp-hero__avatar" aria-hidden="true">
        <img v-if="user.avatarUrl" :src="user.avatarUrl" :alt="fullName" />
        <span v-else>{{ initials }}</span>
      </div>
      <div class="sp-hero__info">
        <div class="sp-hero__name-row">
          <h1 class="sp-hero__name">{{ fullName }}</h1>
          <TVButton
            v-if="viewerRole === 'ADMIN'"
            variant="ghost"
            size="sm"
            @click="router.push(`/admin/users/${user.id}/edit`)"
          >
            Edit profile
          </TVButton>
        </div>
        <div class="sp-hero__badges">
          <span class="sp-role-pill">Student</span>
          <TVBadge :label="user.isActive ? 'Active' : 'Inactive'" :variant="user.isActive ? 'active' : 'neutral'" dot />
        </div>
        <div class="sp-hero__meta">
          <span>{{ user.email }}</span>
          <span class="sp-meta-dot">·</span>
          <span>{{ user.timezone }}</span>
          <span class="sp-meta-dot">·</span>
          <span>Joined {{ fmt(user.createdAt) }}</span>
        </div>
      </div>
    </div>

    <!-- Body -->
    <div class="sp-layout">
      <div class="sp-layout__main">

        <!-- Personal Details -->
        <section class="sp-section">
          <h2 class="sp-section__title">Personal Details</h2>
          <div class="sp-grid-2">
            <div class="sp-detail">
              <span class="sp-detail__label">Full Name</span>
              <span class="sp-detail__value">{{ fullName }}</span>
            </div>
            <div class="sp-detail">
              <span class="sp-detail__label">Timezone</span>
              <span class="sp-detail__value">{{ user.timezone }}</span>
            </div>
            <div class="sp-detail">
              <span class="sp-detail__label">Member Since</span>
              <span class="sp-detail__value">{{ fmt(user.createdAt) }}</span>
            </div>
            <div class="sp-detail">
              <span class="sp-detail__label">Status</span>
              <TVBadge :label="user.isActive ? 'Active' : 'Inactive'" :variant="user.isActive ? 'active' : 'neutral'" dot />
            </div>
          </div>
        </section>

        <!-- Contact Information -->
        <section class="sp-section">
          <h2 class="sp-section__title">Contact Information</h2>
          <div class="sp-grid-2">
            <div class="sp-detail">
              <span class="sp-detail__label">Email Address</span>
              <span class="sp-detail__value">{{ user.email }}</span>
            </div>
            <div class="sp-detail">
              <span class="sp-detail__label">Phone</span>
              <span class="sp-detail__value sp-detail__value--muted">Not provided</span>
            </div>
          </div>
        </section>

        <!-- Learning Profile -->
        <section class="sp-section">
          <h2 class="sp-section__title">Learning Profile</h2>
          <div class="sp-grid-2">
            <div class="sp-detail">
              <span class="sp-detail__label">English Level</span>
              <span class="sp-level" :class="`sp-level--${levelClass}`">{{ levelLabel }}</span>
            </div>
            <div class="sp-detail">
              <span class="sp-detail__label">Class Type</span>
              <span class="sp-detail__value">{{ classTypeLabel }}</span>
            </div>
            <div class="sp-detail">
              <span class="sp-detail__label">Program / Course</span>
              <span class="sp-detail__value">{{ profile.program || '—' }}</span>
            </div>
            <div class="sp-detail">
              <span class="sp-detail__label">Start Date</span>
              <span class="sp-detail__value">{{ profile.startDate ? fmt(profile.startDate) : '—' }}</span>
            </div>
          </div>
          <div class="sp-detail">
            <span class="sp-detail__label">Assigned Teacher</span>
            <div v-if="assignedTeacher" class="sp-teacher">
              <div class="sp-teacher__avatar" aria-hidden="true">{{ teacherInitials }}</div>
              <div>
                <button
                  v-if="viewerRole === 'ADMIN'"
                  class="sp-link-btn"
                  @click="router.push(`/profile/${assignedTeacher.id}`)"
                >{{ teacherName }}</button>
                <span v-else class="sp-detail__value">{{ teacherName }}</span>
                <p class="sp-teacher__spec">{{ assignedTeacher.teacherProfile?.specialization }}</p>
              </div>
            </div>
            <span v-else class="sp-detail__value sp-detail__value--muted">No teacher assigned</span>
          </div>
        </section>

        <!-- Internal Notes — hidden from student -->
        <section
          v-if="canSeeInternalNotes && profile.notes"
          class="sp-section sp-section--internal"
        >
          <h2 class="sp-section__title sp-section__title--internal">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <rect x="2" y="6" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.3"/>
              <path d="M4.5 6V4.5a2.5 2.5 0 0 1 5 0V6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
            </svg>
            Internal Notes
          </h2>
          <p class="sp-detail__text">{{ profile.notes }}</p>
        </section>

        <!-- Lesson History -->
        <section class="sp-section">
          <div class="sp-section__head">
            <h2 class="sp-section__title sp-section__title--inline">Lesson History</h2>
            <span class="sp-section__badge">Last 6 sessions</span>
          </div>
          <div class="sp-table-wrap">
            <table class="sp-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Topic</th>
                  <th>Duration</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="lesson in MOCK_LESSONS" :key="lesson.id">
                  <td>{{ fmt(lesson.date) }}</td>
                  <td>{{ lesson.topic }}</td>
                  <td>{{ lesson.duration }} min</td>
                  <td>
                    <span class="sp-status" :class="`sp-status--${lesson.status.toLowerCase()}`">
                      {{ lesson.statusLabel }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Goals & Concerns -->
        <section class="sp-section">
          <h2 class="sp-section__title">Goals & Concerns</h2>
          <div class="sp-detail">
            <span class="sp-detail__label">Learning Goals</span>
            <p class="sp-detail__text">{{ profile.goals || 'Not specified' }}</p>
          </div>
          <div class="sp-detail">
            <span class="sp-detail__label">Areas of Concern</span>
            <p class="sp-detail__text">{{ profile.learningConcerns || 'None specified' }}</p>
          </div>
        </section>

      </div>

      <!-- Aside -->
      <div class="sp-layout__aside">

        <!-- Attendance Record -->
        <div class="sp-card">
          <h3 class="sp-card__title">Attendance Record</h3>
          <div class="sp-attend-big">
            <span class="sp-attend-big__num">{{ MOCK_ATTENDANCE.percentage }}%</span>
            <span class="sp-attend-big__label">Attendance Rate</span>
          </div>
          <div class="sp-stat-row">
            <span class="sp-stat__label">Total sessions</span>
            <span class="sp-stat__value">{{ MOCK_ATTENDANCE.total }}</span>
          </div>
          <div class="sp-stat-row">
            <span class="sp-stat__label">Attended</span>
            <span class="sp-stat__value sp-stat__value--success">{{ MOCK_ATTENDANCE.attended }}</span>
          </div>
          <div class="sp-stat-row">
            <span class="sp-stat__label">Missed</span>
            <span class="sp-stat__value sp-stat__value--danger">{{ MOCK_ATTENDANCE.missed }}</span>
          </div>
        </div>

        <!-- Subscription Package -->
        <div class="sp-card">
          <h3 class="sp-card__title">Subscription Package</h3>
          <p class="sp-card__plan">{{ MOCK_PACKAGE.name }}</p>
          <div class="sp-progress">
            <div
              class="sp-progress__fill"
              :style="{ width: `${(MOCK_PACKAGE.lessonsUsed / MOCK_PACKAGE.lessonsIncluded) * 100}%` }"
            />
          </div>
          <div class="sp-progress__meta">
            <span>{{ MOCK_PACKAGE.lessonsUsed }} used</span>
            <span>{{ MOCK_PACKAGE.remaining }} remaining</span>
          </div>
          <div class="sp-stat-row">
            <span class="sp-stat__label">Total lessons</span>
            <span class="sp-stat__value">{{ MOCK_PACKAGE.lessonsIncluded }}</span>
          </div>
          <div class="sp-stat-row">
            <span class="sp-stat__label">Expires</span>
            <span class="sp-stat__value">{{ fmt(MOCK_PACKAGE.expiresAt) }}</span>
          </div>
        </div>

        <!-- Recent Materials -->
        <div class="sp-card">
          <h3 class="sp-card__title">Recent Materials</h3>
          <ul class="sp-materials">
            <li v-for="mat in MOCK_MATERIALS" :key="mat.id" class="sp-material">
              <div class="sp-material__icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M4 2h6l4 4v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>
                  <path d="M9 2v4h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <div class="sp-material__info">
                <span class="sp-material__name">{{ mat.title }}</span>
                <span class="sp-material__meta">Accessed {{ fmt(mat.accessedAt) }}</span>
              </div>
              <span class="sp-material__type-tag">{{ mat.type }}</span>
            </li>
          </ul>
        </div>

        <!-- Account Info (admin/teacher) -->
        <div v-if="canSeeInternalNotes" class="sp-card">
          <h3 class="sp-card__title">Account Info</h3>
          <div class="sp-stat-row">
            <span class="sp-stat__label">Last login</span>
            <span class="sp-stat__value">{{ user.lastLoginAt ? fmt(user.lastLoginAt) : '—' }}</span>
          </div>
          <div class="sp-stat-row">
            <span class="sp-stat__label">Member since</span>
            <span class="sp-stat__value">{{ fmt(user.createdAt) }}</span>
          </div>
          <div class="sp-stat-row">
            <span class="sp-stat__label">Status</span>
            <TVBadge :label="user.isActive ? 'Active' : 'Inactive'" :variant="user.isActive ? 'active' : 'neutral'" dot />
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

/* ── Mock data ── */
const MOCK_LESSONS = [
  { id: 'l1', date: '2026-05-15', topic: 'Business Presentation Skills', duration: 60, status: 'COMPLETED', statusLabel: 'Completed' },
  { id: 'l2', date: '2026-05-12', topic: 'Email Writing — Formal Tone', duration: 60, status: 'COMPLETED', statusLabel: 'Completed' },
  { id: 'l3', date: '2026-05-08', topic: 'Negotiation Language', duration: 60, status: 'COMPLETED', statusLabel: 'Completed' },
  { id: 'l4', date: '2026-05-05', topic: 'Active Listening in Meetings', duration: 60, status: 'MISSED', statusLabel: 'Missed' },
  { id: 'l5', date: '2026-05-01', topic: 'Business Report Writing', duration: 60, status: 'COMPLETED', statusLabel: 'Completed' },
  { id: 'l6', date: '2026-04-28', topic: 'Telephoning & Conference Calls', duration: 60, status: 'COMPLETED', statusLabel: 'Completed' },
]

const MOCK_MATERIALS = [
  { id: 'm1', title: 'Business English Workbook Ch. 5', type: 'PDF', accessedAt: '2026-05-15' },
  { id: 'm2', title: 'Presentation Skills Slides', type: 'PPTX', accessedAt: '2026-05-15' },
  { id: 'm3', title: 'Negotiation Phrases Reference', type: 'PDF', accessedAt: '2026-05-08' },
  { id: 'm4', title: 'Professional Email Templates', type: 'DOCX', accessedAt: '2026-05-06' },
]

const MOCK_PACKAGE = {
  name: 'Standard Plan — 8 Lessons/Month',
  lessonsIncluded: 8,
  lessonsUsed: 5,
  remaining: 3,
  expiresAt: '2026-06-01',
}

const MOCK_ATTENDANCE = { total: 24, attended: 22, missed: 2, percentage: 92 }

/* ── Computed ── */
const profile = computed(() => props.user.studentProfile ?? {})
const fullName = computed(() => `${props.user.firstName} ${props.user.lastName}`)
const initials = computed(() =>
  `${props.user.firstName[0]}${props.user.lastName[0]}`.toUpperCase(),
)

const assignedTeacher = computed(() =>
  profile.value.assignedTeacherId
    ? store.getUserById(profile.value.assignedTeacherId)
    : undefined,
)
const teacherName = computed(() =>
  assignedTeacher.value
    ? `${assignedTeacher.value.firstName} ${assignedTeacher.value.lastName}`
    : '',
)
const teacherInitials = computed(() =>
  assignedTeacher.value
    ? `${assignedTeacher.value.firstName[0]}${assignedTeacher.value.lastName[0]}`.toUpperCase()
    : '',
)

const canSeeInternalNotes = computed(() =>
  props.viewerRole === 'ADMIN' || props.viewerRole === 'TEACHER',
)

const LEVEL_MAP: Record<string, { label: string; cls: string }> = {
  BEGINNER:           { label: 'Beginner (A1)',           cls: 'beginner' },
  ELEMENTARY:         { label: 'Elementary (A2)',         cls: 'elementary' },
  INTERMEDIATE:       { label: 'Intermediate (B1)',       cls: 'intermediate' },
  UPPER_INTERMEDIATE: { label: 'Upper Intermediate (B2)', cls: 'upper-intermediate' },
  ADVANCED:           { label: 'Advanced (C1)',           cls: 'advanced' },
  PROFICIENCY:        { label: 'Proficiency (C2)',        cls: 'proficiency' },
}

const levelLabel = computed(() =>
  LEVEL_MAP[profile.value.englishLevel ?? '']?.label ?? profile.value.englishLevel ?? '—',
)
const levelClass = computed(() =>
  LEVEL_MAP[profile.value.englishLevel ?? '']?.cls ?? '',
)

const CLASS_TYPE_MAP: Record<string, string> = {
  ONLINE: 'Online', IN_PERSON: 'In-Person', HYBRID: 'Hybrid',
}
const classTypeLabel = computed(() =>
  CLASS_TYPE_MAP[profile.value.classType ?? ''] ?? profile.value.classType ?? '—',
)

function fmt(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>

<style scoped>
.sp-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

/* Back */
.sp-back {
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
.sp-back:hover { color: var(--tv-primary); }

/* Hero */
.sp-hero {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-6);
  display: flex;
  align-items: flex-start;
  gap: var(--tv-space-5);
}

.sp-hero__avatar {
  width: 80px;
  height: 80px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}
.sp-hero__avatar img { width: 100%; height: 100%; object-fit: cover; }

.sp-hero__info { flex: 1; min-width: 0; }

.sp-hero__name-row {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
  margin-bottom: var(--tv-space-2);
}

.sp-hero__name {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  letter-spacing: -0.025em;
  color: var(--tv-text);
  margin: 0;
}

.sp-hero__badges {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  margin-bottom: var(--tv-space-3);
  flex-wrap: wrap;
}

.sp-role-pill {
  display: inline-flex;
  align-items: center;
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  background: hsl(199, 89%, 92%);
  color: hsl(199, 89%, 28%);
}

.sp-hero__meta {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  flex-wrap: wrap;
}
.sp-meta-dot { color: var(--tv-border); }

/* Layout */
.sp-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: var(--tv-space-5);
  align-items: start;
}
.sp-layout__main { display: flex; flex-direction: column; gap: var(--tv-space-4); }
.sp-layout__aside { display: flex; flex-direction: column; gap: var(--tv-space-4); position: sticky; top: calc(var(--tv-header-height) + var(--tv-space-4)); }

/* Sections */
.sp-section {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-5);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
}

.sp-section--internal { border-color: hsl(38, 92%, 80%); background: hsl(38, 92%, 99%); }

.sp-section__title {
  font-size: var(--tv-text-base);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
  padding-bottom: var(--tv-space-3);
  border-bottom: 1px solid var(--tv-border);
}

.sp-section__title--internal {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  color: hsl(38, 70%, 35%);
  border-bottom-color: hsl(38, 92%, 85%);
}

.sp-section__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: var(--tv-space-3);
  border-bottom: 1px solid var(--tv-border);
}

.sp-section__title--inline { margin: 0; padding: 0; border: none; }

.sp-section__badge {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
}

/* Detail rows */
.sp-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--tv-space-4);
}

.sp-detail { display: flex; flex-direction: column; gap: var(--tv-space-1); }

.sp-detail__label {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.sp-detail__value { font-size: var(--tv-text-sm); color: var(--tv-text); }
.sp-detail__value--muted { color: var(--tv-text-muted); font-style: italic; }
.sp-detail__text { font-size: var(--tv-text-sm); color: var(--tv-text); margin: 0; line-height: 1.6; }

/* English Level badge */
.sp-level {
  display: inline-flex;
  align-items: center;
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  width: fit-content;
}
.sp-level--beginner           { background: hsl(0,0%,93%);       color: hsl(0,0%,38%); }
.sp-level--elementary         { background: hsl(210,89%,92%);    color: hsl(210,89%,28%); }
.sp-level--intermediate       { background: hsl(142,70%,90%);    color: hsl(142,70%,25%); }
.sp-level--upper-intermediate { background: hsl(177,65%,88%);    color: hsl(177,65%,22%); }
.sp-level--advanced           { background: hsl(262,70%,93%);    color: hsl(262,70%,38%); }
.sp-level--proficiency        { background: hsl(38,92%,91%);     color: hsl(38,70%,28%); }

/* Teacher link */
.sp-teacher {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
}
.sp-teacher__avatar {
  width: 36px;
  height: 36px;
  border-radius: var(--tv-radius-full);
  background: hsl(142, 70%, 90%);
  color: hsl(142, 70%, 25%);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.sp-teacher__spec { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }

.sp-link-btn {
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-primary);
  text-decoration: underline;
  text-underline-offset: 2px;
}
.sp-link-btn:hover { color: var(--tv-primary-hover); }

/* Lesson table */
.sp-table-wrap { overflow-x: auto; border-radius: var(--tv-radius-sm); }
.sp-table { width: 100%; border-collapse: collapse; font-size: var(--tv-text-sm); }
.sp-table th {
  text-align: left;
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: var(--tv-space-2) var(--tv-space-3);
  background: var(--tv-bg-soft);
  border-bottom: 1px solid var(--tv-border);
  white-space: nowrap;
}
.sp-table td {
  padding: var(--tv-space-2) var(--tv-space-3);
  color: var(--tv-text);
  border-bottom: 1px solid var(--tv-border);
  vertical-align: middle;
}
.sp-table tbody tr:last-child td { border-bottom: none; }
.sp-table tbody tr:hover { background: var(--tv-bg-soft); }

.sp-status {
  display: inline-flex;
  align-items: center;
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
}
.sp-status--completed { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.sp-status--missed    { background: var(--tv-danger-soft);  color: var(--tv-danger-fg); }
.sp-status--upcoming  { background: var(--tv-primary-soft); color: var(--tv-primary); }

/* Aside cards */
.sp-card {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  padding: var(--tv-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-3);
}

.sp-card__title {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0;
  padding-bottom: var(--tv-space-2);
  border-bottom: 1px solid var(--tv-border);
}

.sp-card__plan {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
}

.sp-progress {
  height: 8px;
  background: var(--tv-bg-soft);
  border-radius: var(--tv-radius-full);
  overflow: hidden;
}
.sp-progress__fill {
  height: 100%;
  background: var(--tv-primary);
  border-radius: var(--tv-radius-full);
  transition: width 0.4s ease;
}
.sp-progress__meta {
  display: flex;
  justify-content: space-between;
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
}

.sp-attend-big {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: var(--tv-space-2) 0;
}
.sp-attend-big__num {
  font-size: var(--tv-text-3xl, 1.875rem);
  font-weight: var(--tv-font-bold);
  color: var(--tv-success-fg);
  line-height: 1;
}
.sp-attend-big__label {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  margin-top: var(--tv-space-1);
}

.sp-stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: var(--tv-text-sm);
  padding: var(--tv-space-1) 0;
  border-bottom: 1px solid var(--tv-bg-soft);
}
.sp-stat-row:last-child { border-bottom: none; }
.sp-stat__label { color: var(--tv-text-muted); }
.sp-stat__value { font-weight: var(--tv-font-medium); color: var(--tv-text); }
.sp-stat__value--success { color: var(--tv-success-fg); }
.sp-stat__value--danger  { color: var(--tv-danger-fg); }

/* Materials list (in aside card) */
.sp-materials { list-style: none; display: flex; flex-direction: column; gap: 0; margin: 0; padding: 0; }
.sp-material {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  padding: var(--tv-space-2) 0;
  border-bottom: 1px solid var(--tv-border);
}
.sp-material:last-child { border-bottom: none; }
.sp-material__icon {
  width: 28px;
  height: 28px;
  border-radius: var(--tv-radius-sm);
  background: var(--tv-bg-soft);
  color: var(--tv-text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.sp-material__info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.sp-material__name { font-size: var(--tv-text-xs); color: var(--tv-text); font-weight: var(--tv-font-medium); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sp-material__meta { font-size: 11px; color: var(--tv-text-muted); }
.sp-material__type-tag {
  font-size: 10px;
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  background: var(--tv-bg-soft);
  padding: 2px var(--tv-space-1);
  border-radius: var(--tv-radius-sm);
  flex-shrink: 0;
}

/* Responsive */
@media (max-width: 1100px) {
  .sp-layout { grid-template-columns: 1fr; }
  .sp-layout__aside { position: static; flex-direction: row; flex-wrap: wrap; }
  .sp-card { flex: 1; min-width: 200px; }
}

@media (max-width: 767px) {
  .sp-page { padding: var(--tv-space-4); }
  .sp-hero { flex-direction: column; }
  .sp-grid-2 { grid-template-columns: 1fr; }
  .sp-layout__aside { flex-direction: column; }
}
</style>
