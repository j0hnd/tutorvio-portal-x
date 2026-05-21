<template>
  <component :is="dashboardComponent" />
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types'

const auth = useAuthStore()

const DASHBOARDS: Record<UserRole, ReturnType<typeof defineAsyncComponent>> = {
  STUDENT: defineAsyncComponent(() => import('./dashboard/StudentDashboard.vue')),
  TEACHER: defineAsyncComponent(() => import('./dashboard/TeacherDashboard.vue')),
  ADMIN:   defineAsyncComponent(() => import('./dashboard/AdminDashboard.vue')),
  STAFF:   defineAsyncComponent(() => import('./dashboard/StaffDashboard.vue')),
}

const dashboardComponent = computed(() =>
  auth.user?.role ? DASHBOARDS[auth.user.role] : DASHBOARDS.STUDENT,
)
</script>
