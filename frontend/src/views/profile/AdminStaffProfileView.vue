<template>
  <div class="ap-page">

    <button v-if="!isOwnProfile" class="ap-back" @click="router.back()">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
        <path d="M11 13l-4-4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Back
    </button>

    <!-- Hero -->
    <div class="ap-hero">
      <div class="ap-hero__avatar" aria-hidden="true">
        <img v-if="user.avatarUrl" :src="user.avatarUrl" :alt="fullName" />
        <span v-else>{{ initials }}</span>
      </div>
      <div class="ap-hero__info">
        <div class="ap-hero__name-row">
          <h1 class="ap-hero__name">{{ fullName }}</h1>
          <TVButton
            v-if="viewerRole === 'ADMIN' && !isOwnProfile"
            variant="ghost"
            size="sm"
            @click="router.push(`/admin/users/${user.id}/edit`)"
          >
            Edit profile
          </TVButton>
        </div>
        <div class="ap-hero__badges">
          <span class="ap-role-pill" :class="user.role === 'ADMIN' ? 'ap-role-pill--admin' : 'ap-role-pill--staff'">
            {{ user.role === 'ADMIN' ? 'Admin' : 'Staff' }}
          </span>
          <TVBadge :label="user.isActive ? 'Active' : 'Inactive'" :variant="user.isActive ? 'active' : 'neutral'" dot />
        </div>
        <div class="ap-hero__meta">
          <span>{{ user.email }}</span>
          <span class="ap-meta-dot">·</span>
          <span>{{ user.timezone }}</span>
          <span class="ap-meta-dot">·</span>
          <span>Joined {{ fmt(user.createdAt) }}</span>
        </div>
        <p v-if="profile.department" class="ap-hero__dept">{{ profile.department }}</p>
      </div>
    </div>

    <!-- Body -->
    <div class="ap-layout">
      <div class="ap-layout__main">

        <!-- Profile Details -->
        <section class="ap-section">
          <h2 class="ap-section__title">Profile Details</h2>
          <div class="ap-detail">
            <span class="ap-detail__label">Full Name</span>
            <span class="ap-detail__value">{{ fullName }}</span>
          </div>
          <div class="ap-detail">
            <span class="ap-detail__label">Email</span>
            <span class="ap-detail__value">{{ user.email }}</span>
          </div>
          <div class="ap-detail">
            <span class="ap-detail__label">Role</span>
            <span class="ap-detail__value">{{ user.role === 'ADMIN' ? 'Administrator' : 'Staff Member' }}</span>
          </div>
          <div class="ap-detail">
            <span class="ap-detail__label">Department</span>
            <span class="ap-detail__value">{{ profile.department || '—' }}</span>
          </div>
          <div class="ap-detail">
            <span class="ap-detail__label">Timezone</span>
            <span class="ap-detail__value">{{ user.timezone }}</span>
          </div>
          <div v-if="profile.accessLimitations" class="ap-detail">
            <span class="ap-detail__label">Access Limitations</span>
            <span class="ap-detail__value">{{ profile.accessLimitations }}</span>
          </div>
        </section>

        <!-- Portal Permissions -->
        <section class="ap-section">
          <h2 class="ap-section__title">Portal Permissions</h2>
          <div v-if="profile.permissions && profile.permissions.length" class="ap-permissions">
            <div
              v-for="perm in ALL_PERMISSIONS"
              :key="perm.key"
              class="ap-perm-row"
              :class="{ 'ap-perm-row--active': hasPermission(perm.key) }"
            >
              <div class="ap-perm-row__icon" :class="{ 'ap-perm-row__icon--active': hasPermission(perm.key) }">
                <svg v-if="hasPermission(perm.key)" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                  <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg v-else width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                  <path d="M3 3l6 6M9 3l-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
              </div>
              <div>
                <p class="ap-perm-row__name">{{ perm.label }}</p>
                <p class="ap-perm-row__desc">{{ perm.description }}</p>
              </div>
            </div>
          </div>
          <p v-else class="ap-empty-text">No permissions assigned.</p>
        </section>

        <!-- Activity Summary -->
        <section class="ap-section">
          <h2 class="ap-section__title">Activity Summary</h2>
          <div class="ap-activity-list">
            <div v-for="entry in MOCK_ACTIVITY" :key="entry.id" class="ap-activity-row">
              <div class="ap-activity-row__dot" />
              <div class="ap-activity-row__body">
                <p class="ap-activity-row__action">{{ entry.action }}</p>
                <p class="ap-activity-row__time">{{ entry.time }}</p>
              </div>
            </div>
          </div>
        </section>

      </div>

      <!-- Aside -->
      <aside class="ap-layout__aside">

        <!-- Account Info -->
        <div class="ap-card">
          <h3 class="ap-card__title">Account Info</h3>
          <div class="ap-card__row">
            <span class="ap-card__label">Member since</span>
            <span class="ap-card__val">{{ fmt(user.createdAt) }}</span>
          </div>
          <div class="ap-card__row">
            <span class="ap-card__label">Last login</span>
            <span class="ap-card__val">{{ user.lastLoginAt ? fmtRelative(user.lastLoginAt) : '—' }}</span>
          </div>
          <div class="ap-card__row">
            <span class="ap-card__label">Status</span>
            <TVBadge :label="user.isActive ? 'Active' : 'Inactive'" :variant="user.isActive ? 'active' : 'neutral'" dot />
          </div>
        </div>

      </aside>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { ManagedUser, UserRole, StaffPermission, AdminStaffProfile } from '@/types'
import TVButton from '@/components/ui/TVButton.vue'
import TVBadge from '@/components/ui/TVBadge.vue'

const props = defineProps<{
  user: ManagedUser
  viewerRole: UserRole
  isOwnProfile: boolean
}>()

const router = useRouter()

const profile = computed<AdminStaffProfile>(() => props.user.adminStaffProfile ?? {})
const fullName = computed(() => `${props.user.firstName} ${props.user.lastName}`)
const initials = computed(() =>
  `${props.user.firstName[0] ?? ''}${props.user.lastName[0] ?? ''}`.toUpperCase(),
)

function fmt(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

function fmtRelative(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'Just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  if (days < 7) return `${days}d ago`
  return fmt(iso)
}

interface PermDef { key: StaffPermission; label: string; description: string }
const ALL_PERMISSIONS: PermDef[] = [
  { key: 'VIEW_BILLING',          label: 'View Billing',          description: 'Access billing records and invoices' },
  { key: 'EDIT_STUDENTS',         label: 'Edit Students',         description: 'Modify student profiles and assignments' },
  { key: 'VIEW_PAYROLL',          label: 'View Payroll',          description: 'View teacher payroll and salary data' },
  { key: 'MANAGE_SCHEDULE',       label: 'Manage Schedule',       description: 'Create and edit lesson schedules' },
  { key: 'SEND_COMMUNICATIONS',   label: 'Send Communications',   description: 'Send messages and notifications to users' },
]

function hasPermission(key: StaffPermission): boolean {
  return profile.value.permissions?.includes(key) ?? false
}

function permLabel(key: StaffPermission): string {
  return ALL_PERMISSIONS.find(p => p.key === key)?.label ?? key
}

const grantedCount = computed(() => profile.value.permissions?.length ?? 0)
const permPercent = computed(() => Math.round((grantedCount.value / ALL_PERMISSIONS.length) * 100))

const MOCK_ACTIVITY = [
  { id: 1, action: 'Updated student profile — Emma Santos',        time: 'Today, 9:14 AM' },
  { id: 2, action: 'Added lesson schedule for May 22',             time: 'Today, 8:50 AM' },
  { id: 3, action: 'Sent communication to 12 parents',             time: 'Yesterday, 4:30 PM' },
  { id: 4, action: 'Viewed billing report for April',              time: 'Yesterday, 2:05 PM' },
  { id: 5, action: 'Created new student account — Diego Ramirez',  time: 'May 18, 11:00 AM' },
  { id: 6, action: 'Updated schedule — cancelled Thursday class',  time: 'May 17, 3:45 PM' },
]
</script>

<style scoped>
.ap-page {
  max-width: 1100px;
  margin: 0 auto;
  padding: var(--tv-space-6) var(--tv-space-4);
}

/* ── Back button ── */
.ap-back {
  display: inline-flex;
  align-items: center;
  gap: var(--tv-space-1);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  margin-bottom: var(--tv-space-5);
  transition: color 0.15s;
}
.ap-back:hover { color: var(--tv-text); }

/* ── Hero ── */
.ap-hero {
  display: flex;
  gap: var(--tv-space-5);
  align-items: flex-start;
  margin-bottom: var(--tv-space-8, 2rem);
  padding: var(--tv-space-6);
  background: var(--tv-surface);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-lg);
}

.ap-hero__avatar {
  width: 88px;
  height: 88px;
  border-radius: var(--tv-radius-full);
  background: hsl(262 60% 92%);
  color: hsl(262 60% 40%);
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}
.ap-hero__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ap-hero__info { flex: 1; min-width: 0; }

.ap-hero__name-row {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
  margin-bottom: var(--tv-space-2);
}

.ap-hero__name {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  margin: 0;
}

.ap-hero__badges {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  flex-wrap: wrap;
  margin-bottom: var(--tv-space-2);
}

.ap-role-pill {
  display: inline-flex;
  align-items: center;
  padding: 2px 10px;
  border-radius: var(--tv-radius-full);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  letter-spacing: 0.03em;
  text-transform: uppercase;
}
.ap-role-pill--admin {
  background: hsl(262 60% 92%);
  color: hsl(262 60% 38%);
}
.ap-role-pill--staff {
  background: hsl(210 60% 92%);
  color: hsl(210 60% 38%);
}

.ap-hero__meta {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  flex-wrap: wrap;
}
.ap-meta-dot { color: var(--tv-border-strong); }

.ap-hero__dept {
  margin: var(--tv-space-2) 0 0;
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  font-style: italic;
}

/* ── Layout ── */
.ap-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: var(--tv-space-6);
  align-items: start;
}

/* ── Section ── */
.ap-section {
  background: var(--tv-surface);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-lg);
  padding: var(--tv-space-5);
  margin-bottom: var(--tv-space-4);
}

.ap-section__title {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0 0 var(--tv-space-4);
}

/* ── Detail row ── */
.ap-detail {
  display: flex;
  align-items: baseline;
  gap: var(--tv-space-3);
  padding: var(--tv-space-2) 0;
  border-bottom: 1px solid var(--tv-border);
}
.ap-detail:last-child { border-bottom: none; }

.ap-detail__label {
  flex: 0 0 160px;
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
}
.ap-detail__value {
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
}

/* ── Permissions list ── */
.ap-permissions { display: flex; flex-direction: column; gap: var(--tv-space-2); }

.ap-perm-row {
  display: flex;
  align-items: flex-start;
  gap: var(--tv-space-3);
  padding: var(--tv-space-3);
  border-radius: var(--tv-radius);
  background: var(--tv-bg);
  border: 1px solid var(--tv-border);
  opacity: 0.5;
}
.ap-perm-row--active {
  opacity: 1;
  background: hsl(262 60% 97%);
  border-color: hsl(262 60% 82%);
}

.ap-perm-row__icon {
  width: 22px;
  height: 22px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-border);
  color: var(--tv-text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 1px;
}
.ap-perm-row__icon--active {
  background: hsl(262 60% 55%);
  color: #fff;
}

.ap-perm-row__name {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  margin: 0 0 2px;
}
.ap-perm-row__desc {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  margin: 0;
}

.ap-empty-text {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: 0;
}

/* ── Activity ── */
.ap-activity-list { display: flex; flex-direction: column; gap: 0; }

.ap-activity-row {
  display: flex;
  gap: var(--tv-space-3);
  padding: var(--tv-space-3) 0;
  border-bottom: 1px solid var(--tv-border);
}
.ap-activity-row:last-child { border-bottom: none; }

.ap-activity-row__dot {
  width: 8px;
  height: 8px;
  border-radius: var(--tv-radius-full);
  background: hsl(262 60% 55%);
  flex-shrink: 0;
  margin-top: 6px;
}

.ap-activity-row__action {
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  margin: 0 0 2px;
}
.ap-activity-row__time {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  margin: 0;
}

/* ── Aside cards ── */
.ap-card {
  background: var(--tv-surface);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-lg);
  padding: var(--tv-space-4);
  margin-bottom: var(--tv-space-4);
}

.ap-card__title {
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--tv-text-muted);
  margin: 0 0 var(--tv-space-3);
}

.ap-card__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--tv-space-2) 0;
  border-bottom: 1px solid var(--tv-border);
  font-size: var(--tv-text-sm);
}
.ap-card__row:last-child { border-bottom: none; }

.ap-card__label { color: var(--tv-text-muted); }
.ap-card__val { color: var(--tv-text); font-weight: var(--tv-font-medium); }

/* ── Permission summary ── */
.ap-perm-summary { margin-bottom: var(--tv-space-3); }
.ap-perm-summary__count {
  display: flex;
  align-items: baseline;
  gap: var(--tv-space-2);
  margin-bottom: var(--tv-space-2);
}
.ap-perm-summary__num {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: hsl(262 60% 50%);
}
.ap-perm-summary__label {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
}

.ap-perm-bar {
  height: 6px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-border);
  overflow: hidden;
}
.ap-perm-bar__fill {
  height: 100%;
  border-radius: var(--tv-radius-full);
  background: hsl(262 60% 55%);
  transition: width 0.4s ease;
}

.ap-perm-tags {
  display: flex;
  flex-wrap: wrap;
  gap: var(--tv-space-2);
  margin-top: var(--tv-space-3);
}
.ap-perm-tag {
  padding: 2px 8px;
  border-radius: var(--tv-radius-full);
  background: hsl(262 60% 92%);
  color: hsl(262 60% 38%);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-medium);
}

/* ── Responsive ── */
@media (max-width: 1100px) {
  .ap-layout { grid-template-columns: 1fr; }
}

@media (max-width: 767px) {
  .ap-page { padding: var(--tv-space-4) var(--tv-space-3); }
  .ap-hero {
    flex-direction: column;
    gap: var(--tv-space-4);
    padding: var(--tv-space-4);
  }
  .ap-hero__avatar { width: 72px; height: 72px; font-size: var(--tv-text-xl); }
  .ap-hero__name { font-size: var(--tv-text-xl); }
  .ap-detail { flex-direction: column; gap: var(--tv-space-1); }
  .ap-detail__label { flex: none; }
}
</style>
