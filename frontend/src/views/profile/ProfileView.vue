<template>
  <div class="pv-page">
    <div v-if="loading" class="pv-loading">
      <span class="pv-spinner" aria-hidden="true" />
      Loading profile…
    </div>

    <div v-else-if="!targetUser" class="pv-empty">
      <div class="pv-empty__icon" aria-hidden="true">
        <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
          <circle cx="18" cy="18" r="14" stroke="currentColor" stroke-width="1.8"/>
          <path d="M18 12v7M18 23v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <p class="pv-empty__title">User not found</p>
      <p class="pv-empty__sub">The profile you're looking for doesn't exist.</p>
      <TVButton variant="ghost" @click="router.push('/dashboard')">Back to Dashboard</TVButton>
    </div>

    <div v-else-if="!canView" class="pv-empty">
      <div class="pv-empty__icon" aria-hidden="true">
        <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
          <circle cx="18" cy="18" r="14" stroke="currentColor" stroke-width="1.8"/>
          <path d="M14 14l8 8M22 14l-8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>
      <p class="pv-empty__title">Access restricted</p>
      <p class="pv-empty__sub">You don't have permission to view this profile.</p>
      <TVButton variant="ghost" @click="router.back()">Go back</TVButton>
    </div>

    <StudentProfileView
      v-else-if="targetUser.role === 'STUDENT'"
      :user="targetUser"
      :viewer-role="auth.user!.role"
      :is-own-profile="isOwnProfile"
    />
    <TeacherProfileView
      v-else-if="targetUser.role === 'TEACHER'"
      :user="targetUser"
      :viewer-role="auth.user!.role"
      :is-own-profile="isOwnProfile"
    />
    <AdminStaffProfileView
      v-else-if="targetUser.role === 'ADMIN' || targetUser.role === 'STAFF'"
      :user="targetUser"
      :viewer-role="auth.user!.role"
      :is-own-profile="isOwnProfile"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUsersStore } from '@/stores/users'
import TVButton from '@/components/ui/TVButton.vue'
import StudentProfileView from './StudentProfileView.vue'
import TeacherProfileView from './TeacherProfileView.vue'
import AdminStaffProfileView from './AdminStaffProfileView.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useUsersStore()

const loading = ref(true)

onMounted(async () => {
  if (!store.users.length) await store.fetchUsers()
  loading.value = false
})

const targetUser = computed(() => {
  if (route.params.id) return store.getUserById(route.params.id as string)
  return store.getUserByEmail(auth.user?.email ?? '')
})

const isOwnProfile = computed(() =>
  !!auth.user && !!targetUser.value && auth.user.email === targetUser.value.email,
)

const canView = computed(() => {
  const viewer = auth.user
  const target = targetUser.value
  if (!viewer || !target) return false
  if (isOwnProfile.value) return true
  if (viewer.role === 'ADMIN') return true
  if (viewer.role === 'TEACHER') {
    const teacherRecord = store.getUserByEmail(viewer.email)
    return teacherRecord?.teacherProfile?.assignedStudentIds?.includes(target.id) ?? false
  }
  if (viewer.role === 'STAFF') {
    const staffRecord = store.getUserByEmail(viewer.email)
    return (
      staffRecord?.adminStaffProfile?.permissions?.includes('EDIT_STUDENTS') === true &&
      target.role === 'STUDENT'
    )
  }
  return false
})
</script>

<style scoped>
.pv-page { min-height: calc(100dvh - var(--tv-header-height)); }

.pv-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--tv-space-3);
  min-height: 300px;
  color: var(--tv-text-muted);
  font-size: var(--tv-text-sm);
}

.pv-spinner {
  width: 20px;
  height: 20px;
  border: 2px solid var(--tv-border);
  border-top-color: var(--tv-primary);
  border-radius: 50%;
  animation: pv-spin 0.7s linear infinite;
  flex-shrink: 0;
}

@keyframes pv-spin { to { transform: rotate(360deg); } }

.pv-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--tv-space-4);
  min-height: 400px;
  text-align: center;
  padding: var(--tv-space-6);
}

.pv-empty__icon {
  width: 80px;
  height: 80px;
  border-radius: var(--tv-radius-full);
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
  display: flex;
  align-items: center;
  justify-content: center;
}

.pv-empty__title {
  font-size: var(--tv-text-lg);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  margin: 0;
}

.pv-empty__sub {
  font-size: var(--tv-text-sm);
  color: var(--tv-text-muted);
  margin: 0;
}
</style>
