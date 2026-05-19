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
    <!-- View As selector -->
    <div class="sidebar__view-as">
      <span class="sidebar__view-as-label">VIEW AS</span>
      <TVSelect
        v-model="selectedRole"
        :options="roleOptions"
        @update:model-value="$emit('role-change', $event as string)"
      />
    </div>

    <!-- Nav items -->
    <nav class="sidebar__nav" aria-label="Primary navigation">
      <ul role="list">
        <li v-for="item in navItems" :key="item.path">
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
              <span v-if="!collapsed && item.badge" class="sidebar__nav-badge" :aria-label="`${item.badge} unread`">
                {{ item.badge }}
              </span>
            </Transition>
          </a>
        </li>
      </ul>
    </nav>

    <!-- User profile at bottom -->
    <div class="sidebar__footer">
      <div class="sidebar__user">
        <div class="sidebar__user-avatar-wrap">
          <div class="sidebar__user-avatar">
            <img v-if="user.avatar" :src="user.avatar" :alt="user.name" />
            <span v-else>{{ initials }}</span>
          </div>
          <span class="sidebar__user-dot" aria-label="Online" />
        </div>
        <Transition name="label-fade">
          <div v-if="!collapsed" class="sidebar__user-info">
            <span class="sidebar__user-name">{{ user.name }}</span>
            <span class="sidebar__user-role">{{ user.role }}</span>
          </div>
        </Transition>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import TVSelect from '@/components/ui/TVSelect.vue'

interface NavItem {
  path: string
  label: string
  icon: string
  badge?: number
}

interface SidebarUser {
  name: string
  role: string
  avatar?: string
}

interface Props {
  navItems: NavItem[]
  user: SidebarUser
  active?: string
  collapsed?: boolean
  mobileOpen?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  active: '/dashboard',
  collapsed: false,
  mobileOpen: false,
})

defineEmits<{
  navigate: [path: string]
  'mobile-close': []
  'role-change': [role: string]
}>()

const selectedRole = ref(props.user.role.toUpperCase().replace(' ', '_'))

const roleOptions = [
  { value: 'STUDENT', label: 'Student' },
  { value: 'TEACHER', label: 'Teacher' },
  { value: 'ADMIN',   label: 'Admin' },
  { value: 'STAFF',   label: 'Staff' },
]

const initials = computed(() =>
  props.user.name
    .split(' ')
    .map(n => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
)
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

/* View As */
.sidebar__view-as {
  padding: var(--tv-space-4) var(--tv-space-4) var(--tv-space-3);
  border-bottom: 1px solid var(--tv-border);
  flex-shrink: 0;
  transition: opacity 150ms ease;
}

.sidebar--collapsed .sidebar__view-as {
  display: none;
}

.sidebar__view-as-label {
  display: block;
  font-size: 10px;
  font-weight: var(--tv-font-bold);
  color: var(--tv-text-muted);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: var(--tv-space-2);
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

.sidebar__nav-item:hover {
  background: var(--tv-bg-soft);
  color: var(--tv-text);
}

.sidebar__nav-item--active {
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
}

.sidebar__nav-item--active:hover {
  background: var(--tv-primary-soft);
  color: var(--tv-primary);
}

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

/* Footer / User */
.sidebar__footer {
  display: flex;
  align-items: center;
  padding: var(--tv-space-3) var(--tv-space-3);
  border-top: 1px solid var(--tv-border);
  flex-shrink: 0;
  gap: var(--tv-space-2);
}

.sidebar--collapsed .sidebar__footer {
  justify-content: center;
  padding: var(--tv-space-3) var(--tv-space-2);
}

.sidebar--collapsed .sidebar__user {
  justify-content: center;
  flex: unset;
}

.sidebar__user {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  min-width: 0;
  flex: 1;
}

.sidebar__user-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.sidebar__user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--tv-primary-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  font-size: 11px;
  font-weight: var(--tv-font-bold);
  color: var(--tv-primary);
  border: 2px solid var(--tv-primary-muted);
}

.sidebar__user-avatar img { width: 100%; height: 100%; object-fit: cover; }

.sidebar__user-dot {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 11px;
  height: 11px;
  background: var(--tv-success);
  border-radius: 50%;
  border: 2px solid var(--tv-bg-card);
}

.sidebar__user-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
  overflow: hidden;
}

.sidebar__user-name {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-semibold);
  color: var(--tv-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar__user-role {
  font-size: var(--tv-text-xs);
  color: var(--tv-text-muted);
  white-space: nowrap;
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

/* Backdrop */
.sidebar-backdrop {
  position: fixed;
  inset: 0;
  background: hsla(215, 25%, 18%, 0.4);
  backdrop-filter: blur(2px);
  z-index: calc(var(--tv-z-sticky) - 1);
}

/* Mobile */
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
