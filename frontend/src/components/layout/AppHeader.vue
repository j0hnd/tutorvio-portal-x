<template>
  <header class="app-header" role="banner">
    <!-- Mobile hamburger — shown before the logo on small screens -->
    <button
      class="app-header__menu-btn"
      aria-label="Open navigation"
      @click="$emit('open-menu')"
    >
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
    </button>

    <!-- Logo section — same width as sidebar -->
    <div :class="['app-header__logo', { 'app-header__logo--collapsed': sidebarCollapsed }]">
      <!-- Full logo when expanded -->
      <img
        v-if="!sidebarCollapsed"
        src="/images/tutorvio-logo.png"
        alt="Tutorvio"
        class="app-header__logo-img"
      />
      <!-- Temporary icon logo when collapsed -->
      <div v-else class="app-header__logo-icon" aria-label="Tutorvio">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
          <rect width="28" height="28" rx="7" fill="var(--tv-primary)"/>
          <path d="M8 8h12M14 8v12" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
      </div>
    </div>

    <!-- Collapse toggle — always on the boundary between logo and search -->
    <button
      class="app-header__collapse-btn"
      :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
      @click="$emit('toggle-sidebar')"
    >
      <svg
        width="16" height="16" viewBox="0 0 16 16" fill="none"
        :style="{ transform: sidebarCollapsed ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform 250ms ease' }"
        aria-hidden="true"
      >
        <path d="M10 12L6 8l4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>

    <!-- Search -->
    <div class="app-header__search">
      <label for="global-search" class="sr-only">Search</label>
      <div class="app-header__search-wrap">
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
    </div>

    <!-- Right: balance + bell + user -->
    <div class="app-header__right">
      <div class="app-header__balance" aria-label="Wallet balance">
        <span class="app-header__balance-label">Balance:</span>
        <span class="app-header__balance-amount">{{ balance }}</span>
      </div>

      <button class="app-header__icon-btn" :aria-label="`${notificationCount} notifications`">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M10 2a5.5 5.5 0 0 0-5.5 5.5c0 3.2-1.5 4.8-1.5 4.8h14s-1.5-1.6-1.5-4.8A5.5 5.5 0 0 0 10 2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
          <path d="M11.8 16a2 2 0 0 1-3.6 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <span v-if="notificationCount > 0" class="app-header__notif-badge" aria-hidden="true">
          {{ notificationCount > 9 ? '9+' : notificationCount }}
        </span>
      </button>

      <button class="app-header__user" aria-label="Open user menu" aria-haspopup="true">
        <div class="app-header__user-avatar">
          <img v-if="user.avatar" :src="user.avatar" :alt="user.name" />
          <span v-else class="app-header__user-initials">{{ initials }}</span>
        </div>
        <span class="app-header__user-name">{{ user.name }}</span>
        <svg class="app-header__user-chevron" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <path d="M4 5.5l3 3 3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

interface HeaderUser {
  name: string
  avatar?: string
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

defineEmits<{
  'toggle-sidebar': []
  'open-menu': []
}>()

const searchQuery = ref('')

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

/* Logo section — matches sidebar width */
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
  flex-shrink: 0;
}

/* Collapse toggle — icon only, no border */
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

.app-header__collapse-btn:hover {
  background: var(--tv-bg-soft);
  color: var(--tv-text);
}

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
  flex-shrink: 0;
}

.app-header__user-avatar img { width: 100%; height: 100%; object-fit: cover; }

.app-header__user-initials {
  font-size: 11px;
  font-weight: var(--tv-font-bold);
  color: var(--tv-primary);
}

.app-header__user-name {
  font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium);
  color: var(--tv-text);
  white-space: nowrap;
}

.app-header__user-chevron { color: var(--tv-text-muted); flex-shrink: 0; }

/* Mobile hamburger — hidden on desktop */
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

.app-header__menu-btn:hover {
  background: var(--tv-bg-soft);
  color: var(--tv-text);
}

@media (max-width: 767px) {
  .app-header__menu-btn { display: flex; }
  .app-header__logo {
    width: auto;
    border-right: none;
    padding: 0 var(--tv-space-2);
  }
  .app-header__collapse-btn { display: none; }
  .app-header__balance { display: none; }
  .app-header__user-name { display: none; }
  .app-header__right { gap: var(--tv-space-2); padding: 0 var(--tv-space-3); }
  .app-header__search { padding: 0 var(--tv-space-3); }
}
</style>
