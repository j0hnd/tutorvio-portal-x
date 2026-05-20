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
          placeholder="Search by name or email..."
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

    <!-- Data table -->
    <TVDataTable
      :columns="columns"
      :rows="rows"
      :loading="store.loading"
      loading-text="Loading users..."
      row-key="id"
      :page-size="10"
      :page-size-options="[10, 25, 50]"
      clickable
      aria-label="User list"
      empty-title="No users found"
      empty-subtitle="Try adjusting your search or filters"
      @row-click="(row) => router.push(`/admin/users/${row.id}/edit`)"
    >
      <template #cell-name="{ row }">
        <div class="user-cell">
          <div class="user-cell__avatar" aria-hidden="true">
            <img v-if="row.avatarUrl" :src="String(row.avatarUrl)" :alt="String(row._fullName)" />
            <span v-else>{{ row._initials }}</span>
          </div>
          <div class="user-cell__info">
            <span class="user-cell__name">{{ row._fullName }}</span>
            <span class="user-cell__email-sub">{{ row.email }}</span>
          </div>
        </div>
      </template>

      <template #cell-email="{ row }">
        <span class="tv-dt-muted">{{ row.email }}</span>
      </template>

      <template #cell-role="{ row }">
        <span class="role-pill" :class="`role-pill--${String(row.role).toLowerCase()}`">
          {{ roleLabel(String(row.role)) }}
        </span>
      </template>

      <template #cell-status="{ row }">
        <TVBadge
          :label="row.isActive ? 'Active' : 'Inactive'"
          :variant="row.isActive ? 'active' : 'neutral'"
          dot
        />
      </template>

      <template #cell-createdAt="{ row }">
        <span class="tv-dt-muted">{{ formatDate(String(row.createdAt)) }}</span>
      </template>

      <template #cell-actions="{ row }">
        <div class="user-actions" @click.stop>
          <button
            class="icon-btn icon-btn--edit"
            :aria-label="`Edit ${row._fullName}`"
            :title="`Edit ${row._fullName}`"
            @click="router.push(`/admin/users/${row.id}/edit`)"
          >
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
              <path d="M10.5 2.5l2 2L5 12H3v-2l7.5-7.5z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <button
            class="icon-btn"
            :class="row.isActive ? 'icon-btn--deactivate' : 'icon-btn--activate'"
            :aria-label="row.isActive ? `Deactivate ${row._fullName}` : `Activate ${row._fullName}`"
            :title="row.isActive ? 'Deactivate' : 'Activate'"
            @click="handleToggleActive(String(row.id))"
          >
            <svg v-if="row.isActive" width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
              <circle cx="7.5" cy="7.5" r="5.5" stroke="currentColor" stroke-width="1.4"/>
              <path d="M5 7.5h5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            <svg v-else width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden="true">
              <circle cx="7.5" cy="7.5" r="5.5" stroke="currentColor" stroke-width="1.4"/>
              <path d="M5.5 7.5l1.5 1.5 2.5-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
      </template>

      <template #empty>
        <div class="users-page__empty-icon" aria-hidden="true">
          <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
            <circle cx="15" cy="12" r="6" stroke="currentColor" stroke-width="1.8"/>
            <path d="M4 32c0-6.627 4.925-10 11-10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M27 22v8M23 26h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <p class="users-page__empty-title">No users found</p>
        <p class="users-page__empty-sub">Try adjusting your search or filters</p>
      </template>
    </TVDataTable>
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
import TVDataTable from '@/components/ui/TVDataTable.vue'
import type { DataTableColumn } from '@/components/ui/TVDataTable.vue'
import type { UserRole, SelectOption, ManagedUser } from '@/types'

const router = useRouter()
const store = useUsersStore()
const toast = useToast()

const search = ref('')
const roleFilter = ref<UserRole | ''>('')
const statusFilter = ref<'active' | 'inactive' | ''>('')

const columns: DataTableColumn[] = [
  { key: 'name',      label: 'Name',    sortable: true,  sortKey: '_fullName' },
  { key: 'email',     label: 'Email',   sortable: true,  hide: 'sm' },
  { key: 'role',      label: 'Role',    sortable: true },
  { key: 'status',    label: 'Status',  sortable: false },
  { key: 'createdAt', label: 'Joined',  sortable: true,  hide: 'md', muted: true },
  { key: 'actions',   label: 'Actions', align: 'right',  stopClick: true },
]

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
  store
    .filteredUsers({
      role: roleFilter.value,
      status: statusFilter.value,
      search: search.value,
    })
    .map(user => ({
      ...user,
      _fullName: `${user.firstName} ${user.lastName}`,
      _initials: `${user.firstName[0]}${user.lastName[0]}`.toUpperCase(),
    })),
)

function roleLabel(role: string): string {
  const map: Record<string, string> = {
    STUDENT: 'Student', TEACHER: 'Teacher', ADMIN: 'Admin', STAFF: 'Staff',
  }
  return map[role] ?? role
}


function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

async function handleToggleActive(id: string): Promise<void> {
  const user = store.getUserById(id) as ManagedUser | undefined
  if (!user) return
  const wasActive = user.isActive
  store.toggleActive(id)
  const name = `${user.firstName} ${user.lastName}`
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

.user-cell {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
}

.user-cell__avatar {
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

.user-cell__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-cell__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.user-cell__name {
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
}

.user-cell__email-sub {
  display: none;
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
}

.user-actions {
  display: flex;
  gap: var(--tv-space-1);
  justify-content: flex-end;
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
  background: transparent;
  cursor: pointer;
  transition: background-color var(--tv-transition-fast), border-color var(--tv-transition-fast), color var(--tv-transition-fast);
  color: var(--tv-text-muted);
  flex-shrink: 0;
}

.icon-btn--edit:hover {
  background: var(--tv-primary-soft);
  border-color: var(--tv-primary-muted);
  color: var(--tv-primary);
}

.icon-btn--deactivate:hover {
  background: hsl(0, 72%, 96%);
  border-color: hsl(0, 72%, 85%);
  color: var(--tv-danger);
}

.icon-btn--activate:hover {
  background: hsl(142, 70%, 94%);
  border-color: hsl(142, 70%, 75%);
  color: var(--tv-success);
}

.tv-dt-muted {
  color: var(--tv-text-muted);
}

/* Role pills */
.role-pill {
  display: inline-flex;
  align-items: center;
  padding: 2px var(--tv-space-2);
  border-radius: var(--tv-radius-full);
  font-size: var(--tv-text-xs);
  font-weight: var(--tv-font-semibold);
  letter-spacing: 0.02em;
  white-space: nowrap;
}

.role-pill--student {
  background: hsl(199, 89%, 92%);
  color: hsl(199, 89%, 28%);
}

.role-pill--teacher {
  background: hsl(142, 70%, 90%);
  color: hsl(142, 70%, 25%);
}

.role-pill--admin {
  background: hsl(262, 70%, 93%);
  color: hsl(262, 70%, 38%);
}

.role-pill--staff {
  background: hsl(38, 92%, 91%);
  color: hsl(38, 92%, 28%);
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

@media (max-width: 767px) {
  .users-page { padding: var(--tv-space-4); }
  .users-page__search { max-width: 100%; width: 100%; }
  .users-page__filter-selects { width: 100%; }
  .users-page__filter-selects > * { flex: 1; }
  .user-cell__email-sub { display: block; }
  .user-actions { flex-direction: column; gap: var(--tv-space-1); }
}
</style>
