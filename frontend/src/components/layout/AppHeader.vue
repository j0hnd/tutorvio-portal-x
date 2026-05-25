<template>
  <header class="app-header" role="banner">
    <!-- Mobile hamburger -->
    <button
      class="app-header__menu-btn"
      aria-label="Open navigation"
      @click="$emit('open-menu')"
    >
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
    </button>

    <!-- Logo -->
    <div :class="['app-header__logo', { 'app-header__logo--collapsed': sidebarCollapsed }]">
      <img
        v-if="!sidebarCollapsed"
        src="/images/tutorvio-logo.png"
        alt="Tutorvio"
        class="app-header__logo-img"
      />
      <div v-else class="app-header__logo-icon" aria-label="Tutorvio">
        <img src="/images/logo.png" alt="Tutorvio" width="32" height="32" style="object-fit:contain;display:block;" />
      </div>
    </div>

    <!-- Collapse toggle -->
    <button
      :class="['app-header__collapse-btn', { 'app-header__collapse-btn--collapsed': sidebarCollapsed }]"
      :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      @click="$emit('toggle-sidebar')"
    >
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M10 12L6 8l4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <!-- Search (desktop: always visible; mobile: icon toggle) -->
    <div class="app-header__search">
      <!-- Desktop search -->
      <label for="global-search" class="sr-only">Search</label>
      <div class="app-header__search-wrap app-header__search-wrap--desktop">
        <svg class="app-header__search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
          <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M11 11l3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <input
          id="global-search"
          v-model="searchQuery"
          type="search"
          placeholder="Search..."
          class="app-header__search-input"
          autocomplete="off"
        />
      </div>

      <!-- Mobile search icon button -->
      <button
        class="app-header__search-icon-btn"
        aria-label="Open search"
        type="button"
        @click="openMobileSearch"
      >
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
          <circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.5"/>
          <path d="M12.5 12.5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    <!-- Mobile search overlay -->
    <Teleport to="body">
      <div v-if="mobileSearchOpen" class="app-header__mobile-search-overlay" @click.self="closeMobileSearch">
        <div class="app-header__mobile-search-bar">
          <svg class="app-header__mobile-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.4"/>
            <path d="M11 11l3 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
          </svg>
          <input
            ref="mobileSearchInputRef"
            v-model="searchQuery"
            type="search"
            placeholder="Search anything…"
            class="app-header__mobile-search-input"
            autocomplete="off"
            @keydown.escape="closeMobileSearch"
          />
          <button class="app-header__mobile-search-close" type="button" aria-label="Close search" @click="closeMobileSearch">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
          </button>
        </div>
      </div>
    </Teleport>

    <!-- Right: balance + bell + user -->
    <div class="app-header__right">
      <!-- Balance — always visible -->
      <div class="app-header__balance" aria-label="Wallet balance">
        <span class="app-header__balance-label">Balance:</span>
        <span class="app-header__balance-amount">{{ balance || 'Kč0' }}</span>
      </div>

      <!-- Notifications -->
      <button class="app-header__icon-btn" :aria-label="`${notificationCount} notifications`">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M10 2a5.5 5.5 0 0 0-5.5 5.5c0 3.2-1.5 4.8-1.5 4.8h14s-1.5-1.6-1.5-4.8A5.5 5.5 0 0 0 10 2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
          <path d="M11.8 16a2 2 0 0 1-3.6 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <span v-if="notificationCount > 0" class="app-header__notif-badge" aria-hidden="true">
          {{ notificationCount > 9 ? '9+' : notificationCount }}
        </span>
      </button>

      <!-- User menu -->
      <div class="app-header__user-wrap" ref="userMenuRef">
        <button
          class="app-header__user"
          :aria-label="`Open user menu for ${user.name}`"
          :aria-expanded="userMenuOpen"
          aria-haspopup="true"
          @click="userMenuOpen = !userMenuOpen"
        >
          <!-- Avatar with status dot -->
          <div class="app-header__user-avatar-wrap">
            <div class="app-header__user-avatar">
              <img v-if="user.avatar" :src="user.avatar" :alt="user.name" />
              <span v-else class="app-header__user-initials">{{ initials }}</span>
            </div>
            <span class="app-header__user-status" aria-label="Online" />
          </div>

          <!-- Name + role -->
          <div class="app-header__user-info">
            <span class="app-header__user-name">{{ user.name }}</span>
            <span v-if="formattedRole" class="app-header__user-role">{{ formattedRole }}</span>
          </div>

          <svg
            class="app-header__user-chevron"
            :class="{ 'app-header__user-chevron--open': userMenuOpen }"
            width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"
          >
            <path d="M4 5.5l3 3 3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>

        <!-- Dropdown -->
        <Transition name="dropdown">
          <div v-if="userMenuOpen" class="app-header__dropdown" role="menu">
            <button
              class="app-header__dropdown-item"
              role="menuitem"
              @click="userMenuOpen = false; router.push('/profile')"
            >
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.4"/>
                <circle cx="8" cy="6.5" r="2" stroke="currentColor" stroke-width="1.4"/>
                <path d="M3.5 13c.5-2 2.3-3.2 4.5-3.2s4 1.2 4.5 3.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
              </svg>
              My Profile
            </button>
            <div class="app-header__dropdown-divider" />
            <button
              class="app-header__dropdown-item app-header__dropdown-item--danger"
              role="menuitem"
              @click="handleLogout"
            >
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M6 2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3M10 11l4-4-4-4M14 7H6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Sign out
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import type { UserRole } from '@/types'

interface HeaderUser {
  name: string
  avatar?: string
  role?: UserRole
}

interface Props {
  user: HeaderUser
  balance?: string
  notificationCount?: number
  sidebarCollapsed?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  balance: 'Kč0',
  notificationCount: 0,
  sidebarCollapsed: false,
})

const emit = defineEmits<{
  'toggle-sidebar': []
  'open-menu': []
  logout: []
}>()

const router = useRouter()
const searchQuery = ref('')
const userMenuOpen = ref(false)
const userMenuRef = ref<HTMLElement | null>(null)
const mobileSearchOpen = ref(false)
const mobileSearchInputRef = ref<HTMLInputElement | null>(null)

function openMobileSearch(): void {
  mobileSearchOpen.value = true
  nextTick(() => mobileSearchInputRef.value?.focus())
}

function closeMobileSearch(): void {
  mobileSearchOpen.value = false
}

const initials = computed(() =>
  props.user.name
    .split(' ')
    .map(n => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase(),
)

const formattedRole = computed(() => {
  const map: Record<string, string> = {
    STUDENT: 'Student',
    TEACHER: 'Teacher',
    ADMIN: 'Administrator',
    STAFF: 'Staff',
  }
  return props.user.role ? (map[props.user.role] ?? props.user.role) : ''
})

function handleLogout(): void {
  userMenuOpen.value = false
  emit('logout')
}

function handleClickOutside(e: MouseEvent): void {
  if (userMenuRef.value && !userMenuRef.value.contains(e.target as Node)) {
    userMenuOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<style scoped>
.app-header {
  grid-column: 1 / -1;
  grid-row: 1;
  display: flex;
  align-items: center;
  height: var(--tv-header-height);
  background: var(--tv-bg-card);
  border-bottom: 1px solid var(--tv-border);
  z-index: var(--tv-z-sticky);
  position: sticky;
  top: 0;
}

/* Logo */
.app-header__logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: var(--tv-sidebar-width);
  flex-shrink: 0;
  padding: 0 var(--tv-space-5);
  height: 100%;
  transition: width 250ms cubic-bezier(0.4, 0, 0.2, 1), padding 250ms cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}

.app-header__logo--collapsed {
  width: var(--tv-sidebar-collapsed);
  padding: 0 var(--tv-space-3);
}

.app-header__logo-img {
  height: 32px;
  width: auto;
  object-fit: contain;
  display: block;
  flex-shrink: 0;
}

.app-header__logo-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Collapse toggle */
.app-header__collapse-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: var(--tv-radius-sm);
  color: var(--tv-text-muted);
  flex-shrink: 0;
  transition: background-color var(--tv-transition-fast), color var(--tv-transition-fast);
}

.app-header__collapse-btn svg { transition: transform 250ms ease; }
.app-header__collapse-btn--collapsed svg { transform: rotate(180deg); }

.app-header__collapse-btn:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

/* Search */
.app-header__search {
  flex: 1;
  padding: 0 var(--tv-space-5);
}

.app-header__search-wrap {
  position: relative;
  max-width: 400px;
}

.app-header__search-icon {
  position: absolute;
  left: var(--tv-space-3);
  top: 50%;
  transform: translateY(-50%);
  color: var(--tv-text-muted);
  pointer-events: none;
}

.app-header__search-input {
  width: 100%;
  padding: var(--tv-space-2) var(--tv-space-4) var(--tv-space-2) 2.25rem;
  font-size: var(--tv-text-sm);
  color: var(--tv-text);
  background: var(--tv-bg-soft);
  border: 1.5px solid transparent;
  border-radius: var(--tv-radius-full);
  height: 38px;
  transition: border-color var(--tv-transition-fast), background-color var(--tv-transition-fast), box-shadow var(--tv-transition-fast);
}

.app-header__search-input::placeholder { color: var(--tv-text-muted); }

.app-header__search-input:focus {
  background: var(--tv-bg-card);
  border-color: var(--tv-primary);
  box-shadow: 0 0 0 3px hsla(var(--tv-primary-h), var(--tv-primary-s), var(--tv-primary-l), 0.12);
  outline: none;
}

/* Right */
.app-header__right {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  padding: 0 var(--tv-space-5);
  flex-shrink: 0;
}

/* Balance */
.app-header__balance {
  display: flex;
  align-items: center;
  gap: var(--tv-space-1);
  font-size: var(--tv-text-sm);
  background: var(--tv-bg);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius);
  padding: var(--tv-space-2) var(--tv-space-3);
}

.app-header__balance-label { color: var(--tv-text-muted); }
.app-header__balance-amount { font-weight: var(--tv-font-semibold); color: var(--tv-text); }

/* Notifications */
.app-header__icon-btn {
  position: relative;
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--tv-radius);
  color: var(--tv-text-secondary);
  transition: background-color var(--tv-transition-fast), color var(--tv-transition-fast);
}

.app-header__icon-btn:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

.app-header__notif-badge {
  position: absolute;
  top: 4px;
  right: 4px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  background: var(--tv-danger);
  color: white;
  font-size: 10px;
  font-weight: var(--tv-font-bold);
  border-radius: var(--tv-radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--tv-bg-card);
  line-height: 1;
}

/* User button */
.app-header__user-wrap { position: relative; }

.app-header__user {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  padding: var(--tv-space-1) var(--tv-space-2);
  border-radius: var(--tv-radius);
  min-height: 44px;
  transition: background-color var(--tv-transition-fast);
}

.app-header__user:hover { background: var(--tv-bg-soft); }

/* Avatar with status dot */
.app-header__user-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.app-header__user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--tv-primary-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border: 2px solid var(--tv-primary-muted);
}

.app-header__user-avatar img { width: 100%; height: 100%; object-fit: cover; }

.app-header__user-initials {
  font-size: 11px;
  font-weight: var(--tv-font-bold);
  color: var(--tv-primary);
}

.app-header__user-status {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 9px;
  height: 9px;
  background: var(--tv-success);
  border-radius: 50%;
  border: 2px solid var(--tv-bg-card);
}

/* Name + role stacked */
.app-header__user-info {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1px;
}

.app-header__user-name {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  white-space: nowrap;
  line-height: 1.2;
}

.app-header__user-role {
  font-size: 11px;
  color: var(--tv-text-muted);
  white-space: nowrap;
  line-height: 1.2;
}

.app-header__user-chevron { color: var(--tv-text-muted); flex-shrink: 0; transition: transform 200ms ease; }
.app-header__user-chevron--open { transform: rotate(180deg); }

/* Dropdown */
.app-header__dropdown {
  position: absolute;
  top: calc(100% + var(--tv-space-2));
  right: 0;
  min-width: 160px;
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-lg);
  overflow: hidden;
  z-index: var(--tv-z-dropdown);
}

.app-header__dropdown-item {
  display: flex;
  align-items: center;
  gap: var(--tv-space-3);
  width: 100%;
  padding: var(--tv-space-3) var(--tv-space-4);
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text-secondary);
  transition: background-color var(--tv-transition-fast), color var(--tv-transition-fast);
  text-align: left;
}

.app-header__dropdown-item:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

.app-header__dropdown-item--danger { color: var(--tv-danger-fg); }
.app-header__dropdown-item--danger:hover { background: var(--tv-danger-soft); color: var(--tv-danger-fg); }

.app-header__dropdown-divider {
  height: 1px;
  background: var(--tv-border);
  margin: var(--tv-space-1) 0;
}

/* Dropdown transition */
.dropdown-enter-active { transition: opacity 150ms ease, transform 150ms cubic-bezier(0.34, 1.56, 0.64, 1); }
.dropdown-leave-active { transition: opacity 120ms ease, transform 120ms ease; }
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: translateY(-6px) scale(0.97); }

/* Mobile hamburger */
.app-header__menu-btn {
  display: none;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: var(--tv-radius);
  color: var(--tv-text-secondary);
  flex-shrink: 0;
  transition: background-color var(--tv-transition-fast), color var(--tv-transition-fast);
}

.app-header__menu-btn:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

/* Mobile search icon btn — hidden on desktop */
.app-header__search-icon-btn {
  display: none;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: var(--tv-radius);
  color: var(--tv-text-secondary);
  transition: background-color var(--tv-transition-fast);
}
.app-header__search-icon-btn:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

/* Mobile search overlay */
.app-header__mobile-search-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.35);
  z-index: var(--tv-z-modal);
  display: flex;
  flex-direction: column;
  align-items: stretch;
}

.app-header__mobile-search-bar {
  display: flex;
  align-items: center;
  gap: var(--tv-space-2);
  padding: var(--tv-space-3) var(--tv-space-4);
  background: var(--tv-bg-card);
  border-bottom: 1px solid var(--tv-border);
  box-shadow: var(--tv-shadow-md);
}

.app-header__mobile-search-icon {
  color: var(--tv-text-muted);
  flex-shrink: 0;
}

.app-header__mobile-search-input {
  flex: 1;
  font-size: var(--tv-text-base);
  color: var(--tv-text);
  background: transparent;
  border: none;
  outline: none;
  font-family: inherit;
  height: 36px;
}
.app-header__mobile-search-input::placeholder { color: var(--tv-text-muted); }

.app-header__mobile-search-close {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--tv-radius-sm);
  color: var(--tv-text-muted);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background 0.15s;
  flex-shrink: 0;
}
.app-header__mobile-search-close:hover { background: var(--tv-bg-soft); color: var(--tv-text); }

@media (max-width: 767px) {
  .app-header__menu-btn { display: flex; }
  .app-header__logo { width: auto; border-right: none; padding: 0 var(--tv-space-2); }
  .app-header__collapse-btn { display: none; }
  .app-header__balance { display: none; }
  .app-header__user-role { display: none; }
  .app-header__right { gap: var(--tv-space-2); padding: 0 var(--tv-space-3); }
  /* Hide desktop search, show icon btn */
  .app-header__search { padding: 0; flex: none; }
  .app-header__search-wrap--desktop { display: none; }
  .app-header__search-icon-btn { display: flex; }
}
</style>
