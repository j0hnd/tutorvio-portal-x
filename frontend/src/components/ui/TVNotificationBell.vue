<template>
  <div class="nb-wrap" ref="wrapperRef">

    <!-- Bell trigger -->
    <button
      ref="triggerRef"
      class="nb-trigger"
      type="button"
      :aria-label="`Notifications${unreadCount ? ` (${unreadCount} unread)` : ''}`"
      :class="{ 'nb-trigger--active': isOpen }"
      @click="toggleOpen"
    >
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M10 2.5a6 6 0 0 1 6 6v2.5l1.5 2v1H2.5v-1L4 11V8.5a6 6 0 0 1 6-6z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
        <path d="M8 16.5a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
      <span v-if="unreadCount" class="nb-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
    </button>

    <!-- Dropdown -->
    <Teleport to="body">
      <Transition name="nb-drop">
        <div
          v-if="isOpen"
          ref="dropdownRef"
          class="nb-dropdown"
          :style="dropdownStyle"
          role="dialog"
          aria-label="Notifications"
        >
          <!-- Header -->
          <div class="nb-dropdown__header">
            <span class="nb-dropdown__title">Notifications</span>
            <div class="nb-dropdown__header-actions">
              <button v-if="unreadCount" class="nb-text-btn" type="button" @click="markAll">Mark all read</button>
              <router-link class="nb-text-btn" to="/announcements" @click="isOpen = false">View all</router-link>
            </div>
          </div>

          <!-- List -->
          <div class="nb-list" role="list">
            <button
              v-for="n in recentNotifications"
              :key="n.id"
              :class="['nb-item', { 'nb-item--unread': !n.isRead }]"
              type="button"
              role="listitem"
              @click="handleClick(n)"
            >
              <div :class="['nb-item__icon', `nb-item__icon--${n.type}`]">
                <!-- system -->
                <svg v-if="n.type === 'system'" width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.2"/><path d="M7 4.5v3M7 9.5v.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                <!-- class_reminder -->
                <svg v-else-if="n.type === 'class_reminder'" width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="1" y="2" width="12" height="11" rx="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M4 1v2M10 1v2M1 5.5h12" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                <!-- reschedule -->
                <svg v-else-if="n.type === 'reschedule'" width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7a5 5 0 1 0 5-5 5 5 0 0 0-3.54 1.46" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M2 3.5V7h3.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <!-- homework -->
                <svg v-else-if="n.type === 'homework'" width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 2h10a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.2"/><path d="M4 6h6M4 8.5h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                <!-- announcement -->
                <svg v-else width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M1 4h8l2-2v8l-2-2H1V4z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path d="M4 8v3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
              </div>

              <div class="nb-item__body">
                <p class="nb-item__title">{{ n.title }}</p>
                <p class="nb-item__body-text">{{ n.body }}</p>
                <span class="nb-item__time">{{ timeAgo(n.createdAt) }}</span>
              </div>

              <div v-if="!n.isRead" class="nb-item__dot" aria-hidden="true" />
            </button>

            <div v-if="!recentNotifications.length" class="nb-empty">
              <svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <path d="M14 3.5a8.5 8.5 0 0 1 8.5 8.5v3.5l2 2.5v1H3.5v-1l2-2.5V12A8.5 8.5 0 0 1 14 3.5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                <path d="M11.5 22a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
              </svg>
              <p>No notifications</p>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationsStore } from '@/stores/notifications'
import { useAuthStore } from '@/stores/auth'
import type { AppNotification } from '@/stores/notifications'

const router = useRouter()
const notifStore = useNotificationsStore()
const auth = useAuthStore()

const isOpen = ref(false)
const wrapperRef  = ref<HTMLElement | null>(null)
const triggerRef  = ref<HTMLButtonElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const dropdownStyle = ref<Record<string, string>>({})

const userId = computed(() => auth.user?.id ?? '')
const role   = computed(() => auth.user?.role ?? 'STUDENT')

const allNotifs = computed(() => notifStore.getNotificationsForUser(userId.value, role.value))
const unreadCount = computed(() => allNotifs.value.filter(n => !n.isRead).length)
const recentNotifications = computed(() => allNotifs.value.slice(0, 8))

function positionDropdown() {
  const trigger = triggerRef.value
  if (!trigger) return
  const rect = trigger.getBoundingClientRect()
  const dropW = 360
  const left = Math.max(8, Math.min(rect.right - dropW, window.innerWidth - dropW - 8))
  dropdownStyle.value = {
    position: 'fixed',
    top:    `${rect.bottom + 6}px`,
    left:   `${left}px`,
    width:  `${dropW}px`,
    zIndex: '1200',
  }
}

async function toggleOpen() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    await nextTick()
    positionDropdown()
  } else {
    triggerRef.value?.blur()
  }
}

function handleClick(n: AppNotification) {
  notifStore.markAsRead(n.id)
  if (n.link) router.push(n.link)
  isOpen.value = false
}

function markAll() {
  notifStore.markAllAsRead(userId.value, role.value)
}

function handleClickOutside(e: MouseEvent) {
  const target = e.target as Node
  if (
    isOpen.value &&
    wrapperRef.value  && !wrapperRef.value.contains(target) &&
    dropdownRef.value && !dropdownRef.value.contains(target)
  ) {
    isOpen.value = false
    triggerRef.value?.blur()
  }
}

function timeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const m = Math.floor(diff / 60000)
  if (m < 1)   return 'just now'
  if (m < 60)  return `${m}m ago`
  const h = Math.floor(m / 60)
  if (h < 24)  return `${h}h ago`
  const d = Math.floor(h / 24)
  return `${d}d ago`
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<style scoped>
.nb-wrap { position: relative; display: flex; align-items: center; }

.nb-trigger {
  position: relative;
  display: flex; align-items: center; justify-content: center;
  width: 36px; height: 36px;
  border: none; background: transparent; border-radius: var(--tv-radius);
  color: var(--tv-text-muted); cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.nb-trigger:hover { background: var(--tv-bg-soft); color: var(--tv-text); }
.nb-trigger--active { background: var(--tv-primary-soft); color: var(--tv-primary); }

.nb-badge {
  position: absolute; top: 2px; right: 2px;
  min-width: 16px; height: 16px;
  background: var(--tv-danger); color: white;
  font-size: 9px; font-weight: 700; border-radius: 999px;
  display: flex; align-items: center; justify-content: center;
  padding: 0 4px; pointer-events: none;
}

/* Transition */
.nb-drop-enter-active { transition: opacity 150ms ease, transform 150ms ease; }
.nb-drop-leave-active { transition: opacity 100ms ease, transform 100ms ease; }
.nb-drop-enter-from  { opacity: 0; transform: translateY(-6px); }
.nb-drop-leave-to    { opacity: 0; transform: translateY(-4px); }
</style>

<style>
/* Unscoped — dropdown is teleported */
.nb-dropdown {
  background: var(--tv-bg-card);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md);
  box-shadow: var(--tv-shadow-lg);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 480px;
}

.nb-dropdown__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: var(--tv-space-3) var(--tv-space-4);
  border-bottom: 1px solid var(--tv-border);
  flex-shrink: 0;
}
.nb-dropdown__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-bold); color: var(--tv-text); }
.nb-dropdown__header-actions { display: flex; align-items: center; gap: var(--tv-space-3); }

.nb-text-btn {
  font-size: var(--tv-text-xs); color: var(--tv-primary); background: none; border: none;
  cursor: pointer; text-decoration: none; font-family: inherit; font-weight: var(--tv-font-medium);
}
.nb-text-btn:hover { text-decoration: underline; }

.nb-list { overflow-y: auto; flex: 1; }
.nb-list::-webkit-scrollbar { width: 4px; }
.nb-list::-webkit-scrollbar-thumb { background: var(--tv-border); border-radius: 9999px; }

.nb-item {
  display: flex; align-items: center; gap: var(--tv-space-3);
  width: 100%; padding: var(--tv-space-3) var(--tv-space-4);
  background: transparent; border: none; text-align: left; cursor: pointer;
  border-bottom: 1px solid var(--tv-border);
  transition: background 0.12s;
}
.nb-item:last-child { border-bottom: none; }
.nb-item:hover { background: var(--tv-bg-soft); }
.nb-item--unread { background: hsl(var(--tv-primary-h), var(--tv-primary-s), 99%); }
.nb-item--unread:hover { background: var(--tv-primary-soft); }

.nb-item__icon {
  width: 30px; height: 30px; flex-shrink: 0;
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
}
.nb-item__icon--system        { background: hsl(38,90%,92%);  color: hsl(38,70%,32%); }
.nb-item__icon--class_reminder{ background: var(--tv-primary-soft); color: var(--tv-primary); }
.nb-item__icon--reschedule    { background: hsl(270,65%,92%); color: hsl(270,55%,38%); }
.nb-item__icon--homework      { background: var(--tv-success-soft); color: var(--tv-success-fg); }
.nb-item__icon--announcement  { background: var(--tv-info-soft); color: var(--tv-info-fg); }

.nb-item__body { flex: 1; min-width: 0; }
.nb-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nb-item__body-text { font-size: var(--tv-text-xs); color: var(--tv-text-secondary); line-height: 1.45; margin: 0 0 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.nb-item__time { font-size: 10px; color: var(--tv-text-muted); }
.nb-item__dot { width: 8px; height: 8px; border-radius: 50%; background: var(--tv-primary); flex-shrink: 0; }

.nb-empty { display: flex; flex-direction: column; align-items: center; gap: var(--tv-space-2); padding: var(--tv-space-8) var(--tv-space-4); color: var(--tv-text-muted); text-align: center; }
.nb-empty svg { opacity: 0.3; }
.nb-empty p { font-size: var(--tv-text-sm); margin: 0; }
</style>
