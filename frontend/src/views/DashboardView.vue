<template>
  <component :is="dashboardComponent" :key="effectiveRole.value" />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useViewAs } from '@/composables/useViewAs'
import StudentDashboard from './dashboard/StudentDashboard.vue'
import TeacherDashboard from './dashboard/TeacherDashboard.vue'
import AdminDashboard from './dashboard/AdminDashboard.vue'
import StaffDashboard from './dashboard/StaffDashboard.vue'
import type { UserRole } from '@/types'

const { effectiveRole } = useViewAs()

const DASHBOARDS: Record<UserRole, unknown> = {
  STUDENT: StudentDashboard,
  TEACHER: TeacherDashboard,
  ADMIN:   AdminDashboard,
  STAFF:   StaffDashboard,
}

const dashboardComponent = computed(() => DASHBOARDS[effectiveRole.value])
</script>
