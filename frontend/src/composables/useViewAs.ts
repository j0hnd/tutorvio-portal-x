import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types'

const viewAsRole = ref<UserRole | null>(null)

export function useViewAs() {
  const auth = useAuthStore()

  const effectiveRole = computed((): UserRole =>
    viewAsRole.value ?? auth.user?.role ?? 'STUDENT',
  )

  function setViewAsRole(role: UserRole): void {
    viewAsRole.value = role
  }

  function resetViewAs(): void {
    viewAsRole.value = null
  }

  return { viewAsRole, effectiveRole, setViewAsRole, resetViewAs }
}
