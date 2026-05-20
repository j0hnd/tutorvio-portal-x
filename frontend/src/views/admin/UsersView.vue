<template>
  <div class="users-page">
    <!-- Header -->
    <div class="users-page__header">
      <div>
        <h1 class="users-page__title">Users</h1>
        <p class="users-page__subtitle">Manage students, teachers, admins, and staff</p>
      </div>
      <TVButton variant="primary" @click="router.push('/admin/users/create')">
        <template #icon>
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </template>
        Add User
      </TVButton>
    </div>

    <!-- Filters -->
    <div class="users-page__filters">
      <div class="users-page__search">
        <TVInput
          v-model="search"
          placeholder="Search by name or email…"
          aria-label="Search users"
        >
          <template #icon-start>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <circle cx="6.5" cy="6.5" r="4" stroke="currentColor" stroke-width="1.4"/>
              <path d="M11 11l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
          </template>
        </TVInput>
      </div>
      <div class="users-page__filter-selects">
        <TVSelect
          v-model="roleFilter"
          :options="roleOptions"
          placeholder="All roles"
          aria-label="Filter by role"
        />
        <TVSelect
          v-model="statusFilter"
          :options="statusOptions"
          placeholder="All statuses"
          aria-label="Filter by status"
        />
      </div>
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="users-page__loading" aria-live="polite">
      <span class="users-page__spinner" aria-hidden="true" />
      Loading users…
    </div>

    <!-- Table -->
    <div v-else-if="rows.length" class="users-page__table-wrap">
      <table class="users-table" aria-label="User list">
        <thead>
          <tr>
            <th class="users-table__th">Name</th>
            <th class="users-table__th users-table__th--hide-sm">Email</th>
            <th class="users-table__th">Role</th>
            <th class="users-table__th">Status</th>
            <th class="users-table__th users-table__th--hide-md">Joined</th>
            <th class="users-table__th users-table__th--actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in rows"
            :key="user.id"
            class="users-table__row"
            @click="router.push(`/admin/users/${user.id}/edit`)"
          >
            <!-- Name + avatar -->
            <td class="users-table__td">
              <div class="users-table__user">
                <div class="users-table__avatar" :data-initials="initials(user)" aria-hidden="true">
                  <img v-if="user.avatarUrl" :src="user.avatarUrl" :alt="fullName(user)" />
                  <span v-else>{{ initials(user) }}</span>
                </div>
                <div class="users-table__user-info">
                  <span class="users-table__name">{{ fullName(user) }}</span>
                  <span class="users-table__email-mobile">{{ user.email }}</span>
                </div>
              </div>
            </td>

            <!-- Email (hidden on small screens) -->
            <td class="users-table__td users-table__td--hide-sm users-table__td--muted">
              {{ user.email }}
            </td>

            <!-- Role -->
            <td class="users-table__td">
              <TVBadge :label="roleLabel(user.role)" :variant="roleBadgeVariant(user.role)" />
            </td>

            <!-- Status -->
            <td class="users-table__td">
              <TVBadge
                :label="user.isActive ? 'Active' : 'Inactive'"
                :variant="user.isActive ? 'active' : 'neutral'"
                dot
              />
            </td>

            <!-- Joined (hidden on medium screens) -->
            <td class="users-table__td users-table__td--hide-md users-table__td--muted">
              {{ formatDate(user.createdAt) }}
            </td>

            <!-- Actions -->
            <td class="users-table__td users-table__td--actions" @click.stop>
              <TVButton
                variant="ghost"
                size="sm"
                aria-label="Edit user"
                @click="router.push(`/admin/users/${user.id}/edit`)"
              >
                Edit
              </TVButton>
              <TVButton
                :variant="user.isActive ? 'danger' : 'success'"
                size="sm"
                :aria-label="user.isActive ? 'Deactivate user' : 'Activate user'"
                @click="handleToggleActive(user.id)"
              >
                {{ user.isActive ? 'Deactivate' : 'Activate' }}
              </TVButton>
            </td>
          </tr>
        </tbody>
      </table>

      <p class="users-page__count" aria-live="polite">
        Showing {{ rows.length }} of {{ store.users.length }} users
      </p>
    </div>

    <!-- Empty state -->
    <div v-else class="users-page__empty" aria-live="polite">
      <div class="users-page__empty-icon" aria-hidden="true">
        <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
          <circle cx="15" cy="12" r="6" stroke="currentColor" stroke-width="1.8"/>
          <path d="M4 32c0-6.627 4.925-10 11-10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <path d="M27 22v8M23 26h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </div>
      <p class="users-page__empty-title">No users found</p>
      <p class="users-page__empty-sub">Try adjusting your search or filters</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUsersStore } from '@/stores/users'
import { useToast } from '@/composables/useToast'
import TVButton from '@/components/ui/TVButton.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVBadge from '@/components/ui/TVBadge.vue'
import type { UserRole, BadgeVariant, SelectOption, ManagedUser } from '@/types'

const router = useRouter()
const store = useUsersStore()
const toast = useToast()

const search = ref('')
const roleFilter = ref<UserRole | ''>('')
const statusFilter = ref<'active' | 'inactive' | ''>('')

const roleOptions: SelectOption[] = [
  { value: '', label: 'All roles' },
  { value: 'STUDENT', label: 'Student' },
  { value: 'TEACHER', label: 'Teacher' },
  { value: 'ADMIN', label: 'Admin' },
  { value: 'STAFF', label: 'Staff' },
]

const statusOptions: SelectOption[] = [
  { value: '', label: 'All statuses' },
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
]

const rows = computed(() =>
  store.filteredUsers({
    role: roleFilter.value,
    status: statusFilter.value,
    search: search.value,
  }),
)

function fullName(user: ManagedUser): string {
  return `${user.firstName} ${user.lastName}`
}

function initials(user: ManagedUser): string {
  return `${user.firstName[0]}${user.lastName[0]}`.toUpperCase()
}

function roleLabel(role: UserRole): string {
  const map: Record<UserRole, string> = {
    STUDENT: 'Student',
    TEACHER: 'Teacher',
    ADMIN: 'Admin',
    STAFF: 'Staff',
  }
  return map[role]
}

function roleBadgeVariant(role: UserRole): BadgeVariant {
  const map: Record<UserRole, BadgeVariant> = {
    STUDENT: 'info',
    TEACHER: 'scheduled',
    ADMIN: 'warning',
    STAFF: 'pending',
  }
  return map[role]
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

async function handleToggleActive(id: string): Promise<void> {
  const user = store.getUserById(id)
  if (!user) return
  const wasActive = user.isActive
  store.toggleActive(id)
  const name = fullName(user)
  if (wasActive) {
    toast.warning(`${name} has been deactivated.`)
  } else {
    toast.success(`${name} has been activated.`)
  }
}

onMounted(() => {
  if (!store.users.length) store.fetchUsers()
})
</script>

<style scoped>
.users-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

/* Header */
.users-page__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--tv-space-4);
  flex-wrap: wrap;
}

.users-page__title {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
  letter-spacing: -0.025em;
  margin: 0 0 var(--tv-space-1);
}

.users-page__subtitle {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: 0;
}

/* Filters */
.users-page__filters {
  display: flex;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
  align-items: flex-end;
}

.users-page__search {
  flex: 1;
  min-width: 200px;
  max-width: 360px;
}

.users-page__filter-selects {
  display: flex;
  gap: var(--tv-space-3);
  flex-wrap: wrap;
}

/* Loading */
.users-page__loading {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-10);
  justify-content: center;
  color: var(--tv-text-muted);
  font-size: var(--tv-text-sm);
}

.users-page__spinner {
  width: 20px;
  height: 20px;
  border: 2px solid var(--tv-border);
  border-top-color: var(--tv-primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  flex-shrink: 0;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* Table wrapper */
.users-page__table-wrap {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-sm);
  overflow: hidden;
}

/* Table */
.users-table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--tv-text-sm);
}

.users-table__th {
  padding: var(--tv-space-3) var(--tv-space-4);
  text-align: left;
  font-weight: var(--tv-font-semibold);
  font-size: var(--tv-text-xs);
  color: var(--tv-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: var(--tv-bg-soft);
  border-bottom: 1px solid var(--tv-border);
  white-space: nowrap;
}

.users-table__th--actions {
  text-align: right;
}

.users-table__row {
  cursor: pointer;
  transition: background-color var(--tv-transition-fast);
}

.users-table__row:hover {
  background: var(--tv-bg-soft);
}

.users-table__row:not(:last-child) .users-table__td {
  border-bottom: 1px solid var(--tv-border);
}

.users-table__td {
  padding: var(--tv-space-3) var(--tv-space-4);
  vertical-align: middle;
}

.users-table__td--muted {
  color: var(--tv-text-muted);
}

.users-table__td--actions {
  text-align: right;
}

.users-table__td--actions {
  display: flex;
  gap: var(--tv-space-2);
  justify-content: flex-end;
  align-items: center;
}

/* User cell */
.users-table__user {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
}

.users-table__avatar {
  width: 36px;
  height: 36px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-bold);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}

.users-table__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.users-table__user-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.users-table__name {
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
}

.users-table__email-mobile {
  display: none;
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
}

/* Count */
.users-page__count {
  padding: var(--tv-space-3) var(--tv-space-4);
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  border-top: 1px solid var(--tv-border);
  margin: 0;
}

/* Empty */
.users-page__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-16) var(--tv-space-6);
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  text-align: center;
}

.users-page__empty-icon {
  width: 72px;
  height: 72px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
  display: flex;
  align-items: center;
  justify-content: center;
}

.users-page__empty-title {
  font-size: var(--tv-text-base);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
}

.users-page__empty-sub {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: 0;
}

/* Responsive */
@media (max-width: 1100px) {
  .users-table__th--hide-md,
  .users-table__td--hide-md {
    display: none;
  }
}

@media (max-width: 767px) {
  .users-page {
    padding: var(--tv-space-4);
  }

  .users-page__search {
    max-width: 100%;
    width: 100%;
  }

  .users-page__filter-selects {
    width: 100%;
  }

  .users-page__filter-selects > * {
    flex: 1;
  }

  .users-table__th--hide-sm,
  .users-table__td--hide-sm {
    display: none;
  }

  .users-table__email-mobile {
    display: block;
  }

  .users-table__td--actions {
    flex-direction: column;
    gap: var(--tv-space-1);
  }
}
</style>
