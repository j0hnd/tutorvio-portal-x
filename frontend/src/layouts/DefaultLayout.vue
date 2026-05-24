<template>
  <div class="app-grid">
    <AppHeader
      :user="headerUser"
      :balance="balance"
      :notification-count="0"
      :sidebar-collapsed="sidebarCollapsed"
      @toggle-sidebar="sidebarCollapsed = !sidebarCollapsed"
      @open-menu="mobileOpen = true"
      @logout="handleLogout"
    />

    <AppSidebar
      :nav-items="navItems"
      :active="route.path"
      :collapsed="sidebarCollapsed"
      :mobile-open="mobileOpen"
      :show-view-as="auth.isAdmin"
      :view-as-role="effectiveRole"
      @navigate="handleNavigate"
      @mobile-close="mobileOpen = false"
      @role-change="handleRoleChange"
      @logout="handleLogout"
    />

    <main class="app-main" id="main-content" tabindex="-1">
      <router-view v-slot="{ Component }">
        <Transition name="page" mode="out-in">
          <component :is="Component" :key="route.path" />
        </Transition>
      </router-view>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import { useAuthStore } from '@/stores/auth'
import { useNavigation } from '@/composables/useNavigation'
import { useViewAs } from '@/composables/useViewAs'
import { useToast } from '@/composables/useToast'
import type { UserRole } from '@/types'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { getNavItems } = useNavigation()
const { effectiveRole, setViewAsRole } = useViewAs()
const toast = useToast()

const sidebarCollapsed = ref(false)
const mobileOpen = ref(false)

const navItems = computed(() => getNavItems(effectiveRole.value))

const headerUser = computed(() => ({
  name: auth.fullName || 'User',
  role: auth.user?.role,
  avatar: auth.user?.avatarUrl,
}))

/* Balance placeholder — billing module will replace with real values */
const balance = computed(() => auth.isStudent ? 'Kč 0' : 'Kč1,250')

function handleNavigate(path: string): void {
  mobileOpen.value = false
  router.push(path)
}

function handleRoleChange(role: string): void {
  setViewAsRole(role as UserRole)
  if (route.path !== '/dashboard') router.push('/dashboard')
}

async function handleLogout(): Promise<void> {
  auth.logout()
  toast.success('You have been logged out.')
  await router.push('/login')
}
</script>

<style scoped>
.app-grid {
  display: grid;
  grid-template-columns: auto 1fr;
  grid-template-rows: var(--tv-header-height) 1fr;
  min-height: 100dvh;
}

.app-main {
  grid-column: 2;
  grid-row: 2;
  overflow: auto;
  background: var(--tv-bg);
  outline: none;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

@media (max-width: 767px) {
  .app-grid {
    grid-template-columns: 1fr;
    grid-template-rows: var(--tv-header-height) 1fr;
  }

  .app-main { grid-column: 1; }
}
</style>
