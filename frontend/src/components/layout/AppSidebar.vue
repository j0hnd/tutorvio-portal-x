<template>
  <!-- Mobile backdrop -->
  <Transition name="backdrop">
    <div
      v-if="mobileOpen"
      class="sidebar-backdrop"
      aria-hidden="true"
      @click="$emit('mobile-close')"
    />
  </Transition>

  <aside
    :class="['sidebar', { 'sidebar--collapsed': collapsed, 'sidebar--mobile-open': mobileOpen }]"
    aria-label="Main navigation"
  >
    <!-- VIEW AS — admin only, hidden when sidebar is collapsed -->
    <Transition name="label-fade">
      <div v-if="showViewAs && !collapsed" class="sidebar__viewas">
        <span class="sidebar__viewas-label">VIEW AS</span>
        <TVSelect
          v-model="localViewAs"
          :options="roleOptions"
          @update:model-value="emit('role-change', $event as string)"
        />
      </div>
    </Transition>

    <!-- Nav items -->
    <nav class="sidebar__nav" aria-label="Primary navigation">
      <ul role="list">
        <li v-for="item in navItems" :key="item.path + item.label">
          <a
            :href="item.path"
            :class="['sidebar__nav-item', { 'sidebar__nav-item--active': active === item.path }]"
            :aria-current="active === item.path ? 'page' : undefined"
            :title="collapsed ? item.label : undefined"
            @click.prevent="$emit('navigate', item.path)"
          >
            <span class="sidebar__nav-icon" aria-hidden="true" v-html="item.icon" />
            <Transition name="label-fade">
              <span v-if="!collapsed" class="sidebar__nav-label">{{ item.label }}</span>
            </Transition>
            <Transition name="label-fade">
              <span
                v-if="!collapsed && item.badge"
                class="sidebar__nav-badge"
                :aria-label="`${item.badge} unread`"
              >
                {{ item.badge }}
              </span>
            </Transition>
          </a>
        </li>
      </ul>
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import TVSelect from '@/components/ui/TVSelect.vue'
import type { UserRole } from '@/types'

interface NavItem {
  path: string
  label: string
  icon: string
  badge?: number
}

interface Props {
  navItems: NavItem[]
  active?: string
  collapsed?: boolean
  mobileOpen?: boolean
  showViewAs?: boolean
  viewAsRole?: UserRole | string
}

const props = withDefaults(defineProps<Props>(), {
  active: '/dashboard',
  collapsed: false,
  mobileOpen: false,
  showViewAs: false,
  viewAsRole: 'ADMIN',
})

const emit = defineEmits<{
  navigate: [path: string]
  'mobile-close': []
  'role-change': [role: string]
}>()

const roleOptions = [
  { value: 'STUDENT', label: 'Student' },
  { value: 'TEACHER', label: 'Teacher' },
  { value: 'ADMIN',   label: 'Admin' },
  { value: 'STAFF',   label: 'Staff' },
]

const localViewAs = ref<string>(props.viewAsRole as string)

watch(() => props.viewAsRole, v => { localViewAs.value = v as string })
</script>

<style scoped>
.sidebar {
  grid-column: 1;
  grid-row: 2;
  width: var(--tv-sidebar-width);
  background: var(--tv-bg-card);
  border-right: 1px solid var(--tv-border);
  display: flex;
  flex-direction: column;
  position: sticky;
  top: var(--tv-header-height);
  height: calc(100dvh - var(--tv-header-height));
  overflow: hidden;
  transition: width 250ms cubic-bezier(0.4, 0, 0.2, 1);
  flex-shrink: 0;
}

.sidebar--collapsed { width: var(--tv-sidebar-collapsed); }

/* VIEW AS */
.sidebar__viewas {
  padding: var(--tv-space-3) var(--tv-space-4) var(--tv-space-3);
  border-bottom: 1px solid var(--tv-border);
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-2);
}

.sidebar__viewas-label {
  font-size: 10px;
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

/* Nav */
.sidebar__nav {
  flex: 1;
  overflow-y: auto;
  padding: var(--tv-space-3) var(--tv-space-2);
}

.sidebar__nav ul { list-style: none; display: flex; flex-direction: column; gap: 2px; }

.sidebar__nav-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  padding: var(--tv-space-2) var(--tv-space-3);
  border-radius: var(--tv-radius);
  color: var(--tv-text-secondary);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  text-decoration: none;
  transition: background-color var(--tv-transition-fast), color var(--tv-transition-fast);
  min-height: 44px;
  white-space: nowrap;
}

.sidebar--collapsed .sidebar__nav-item {
  justify-content: center;
  padding: var(--tv-space-2);
  gap: 0;
}

.sidebar__nav-item:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

.sidebar__nav-item--active { background: var(--tv-primary-soft); color: var(--tv-primary); }
.sidebar__nav-item--active:hover { background: var(--tv-primary-soft); color: var(--tv-primary); }

.sidebar__nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.sidebar__nav-label { flex: 1; }

.sidebar__nav-badge {
  background: var(--tv-primary);
  color: white;
  font-size: 10px;
  font-weight: var(--tv-font-bold);
  padding: 2px 6px;
  border-radius: var(--tv-radius-full);
  line-height: 1.4;
  flex-shrink: 0;
}

/* Transitions */
.label-fade-enter-active,
.label-fade-leave-active { transition: opacity 120ms ease; }
.label-fade-enter-from,
.label-fade-leave-to { opacity: 0; }

.backdrop-enter-active,
.backdrop-leave-active { transition: opacity 200ms ease; }
.backdrop-enter-from,
.backdrop-leave-to { opacity: 0; }

.sidebar-backdrop {
  position: fixed;
  inset: 0;
  background: hsla(215, 25%, 18%, 0.4);
  backdrop-filter: blur(2px);
  z-index: calc(var(--tv-z-sticky) - 1);
}

@media (max-width: 767px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100dvh;
    transform: translateX(-100%);
    transition: transform 250ms cubic-bezier(0.4, 0, 0.2, 1);
    z-index: var(--tv-z-sticky);
    width: var(--tv-sidebar-width) !important;
  }

  .sidebar--mobile-open { transform: translateX(0); }
}

@media (prefers-reduced-motion: no-preference) {
  .sidebar { transition: width 250ms cubic-bezier(0.4, 0, 0.2, 1); }
}
</style>
