<template>
  <div :class="['app-layout', { 'app-layout--collapsed': sidebarCollapsed }]">
    <AppHeader
      :user="currentUser"
      balance="Kč1,250"
      :notification-count="2"
      :sidebar-collapsed="sidebarCollapsed"
      @toggle-sidebar="sidebarCollapsed = !sidebarCollapsed"
      @open-menu="mobileNavOpen = true"
    />

    <AppSidebar
      :nav-items="navItems"
      :user="currentUser"
      :active="activeNav"
      :collapsed="effectiveCollapsed"
      :mobile-open="mobileNavOpen"
      @navigate="activeNav = $event"
      @mobile-close="mobileNavOpen = false"
    />

    <main class="app-main" id="main-content">
      <div class="main-inner">

        <section class="welcome">
          <div>
            <h1 class="welcome__heading">Welcome back, Emma! 👋</h1>
            <p class="welcome__sub">Here's what's happening with your lessons today.</p>
          </div>
        </section>

        <StatsGrid :stats="stats" />

        <div class="content-grid">
          <UpcomingLessons :lessons="upcomingLessons" @join="onJoin" @more="onMore" />

          <div class="right-col">
            <QuickActions :actions="quickActions" @action="onQuickAction" />
            <RecentActivity :items="recentActivity" />
          </div>
        </div>

      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import StatsGrid from '@/components/dashboard/StatsGrid.vue'
import UpcomingLessons from '@/components/dashboard/UpcomingLessons.vue'
import QuickActions from '@/components/dashboard/QuickActions.vue'
import RecentActivity from '@/components/dashboard/RecentActivity.vue'
import type { StatItem } from '@/components/dashboard/StatsGrid.vue'
import type { Lesson } from '@/components/dashboard/UpcomingLessons.vue'
import type { QuickAction } from '@/components/dashboard/QuickActions.vue'
import type { ActivityItem } from '@/components/dashboard/RecentActivity.vue'

const sidebarCollapsed = ref(false)
const mobileNavOpen = ref(false)
const activeNav = ref('/dashboard')

const windowWidth = ref(window.innerWidth)
const onResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

const effectiveCollapsed = computed(() => windowWidth.value > 767 && sidebarCollapsed.value)

const currentUser = { name: 'Emma Johnson', role: 'Student', avatar: '' }

const icons = {
  dashboard: `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="1" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="10" y="1" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="1" y="10" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="10" y="10" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/></svg>`,
  classes:   `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 3h10a1 1 0 0 1 1 1v10l-5-2.5L5 14V4a1 1 0 0 1-1-1z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>`,
  calendar:  `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="3" width="16" height="13" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 1.5v3M13 1.5v3M1 7.5h16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  message:   `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M2 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H6l-4 3V3z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>`,
  email:     `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="3.5" width="16" height="11" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M1 5l8 5.5L17 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  wallet:    `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="4" width="16" height="11" rx="1.5" stroke="currentColor" stroke-width="1.4"/><circle cx="13" cy="9.5" r="1.5" fill="currentColor"/><path d="M1 7.5h16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  settings:  `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M9 1v2M9 15v2M1 9h2M15 9h2M3.2 3.2l1.4 1.4M13.4 13.4l1.4 1.4M3.2 14.8l1.4-1.4M13.4 4.6l1.4-1.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  book:      `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M4 2h10a1 1 0 0 1 1 1v12l-5-2.5L5 15V3a1 1 0 0 1-1-1z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>`,
  clock:     `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7.5" stroke="currentColor" stroke-width="1.4"/><path d="M9 5.5V9l2.5 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  star:      `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 2l2.09 4.26L16 7.27l-3.5 3.41.83 4.82L9 13.27l-4.33 2.23.83-4.82L2 7.27l4.91-.71L9 2z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>`,
  credit:    `<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><rect x="1" y="4" width="16" height="11" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M1 7.5h16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="5" cy="11" r="1" fill="currentColor"/></svg>`,
  check:     `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.3"/><path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  dollar:    `<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.3"/><path d="M8 4v8M10 6H7a1 1 0 0 0 0 2h2a1 1 0 0 1 0 2H6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>`,
}

const navItems = [
  { path: '/dashboard', label: 'Dashboard', icon: icons.dashboard, badge: 0 },
  { path: '/lessons',   label: 'My Classes', icon: icons.classes,   badge: 0 },
  { path: '/schedule',  label: 'Calendar',   icon: icons.calendar,  badge: 2 },
  { path: '/messages',  label: 'Messages',   icon: icons.message,   badge: 1 },
  { path: '/email',     label: 'Email',      icon: icons.email,     badge: 0 },
  { path: '/billing',   label: 'Wallet',     icon: icons.wallet,    badge: 0 },
  { path: '/settings',  label: 'Settings',   icon: icons.settings,  badge: 0 },
]

const stats: StatItem[] = [
  { label: 'Completed Lessons', value: '24',       sub: '+12% this month',     trendUp: true,  icon: icons.book,   iconClass: 'icon-badge--teal' },
  { label: 'Hours Learned',     value: '36.5',     sub: 'Total study time',    trendUp: false, icon: icons.clock,  iconClass: 'icon-badge--teal' },
  { label: 'Wallet Balance',    value: 'Kč1,250',  sub: 'In CZK',             trendUp: false, icon: icons.credit, iconClass: 'icon-badge--success' },
  { label: 'Average Rating',    value: '4.9',      sub: 'Based on 18 reviews', trendUp: false, icon: icons.star,   iconClass: 'icon-badge--warning' },
]

const upcomingLessons: Lesson[] = [
  { id: 1, subject: 'English Conversation', teacher: 'Maria Santos', avatar: '', day: 'Today',    time: '10:00 AM', duration: '60 min', soon: true },
  { id: 2, subject: 'Business English',     teacher: 'John Smith',   avatar: '', day: 'Today',    time: '2:00 PM',  duration: '45 min', soon: true },
  { id: 3, subject: 'Grammar Review',       teacher: 'Emma Johnson', avatar: '', day: 'Tomorrow', time: '11:00 AM', duration: '30 min', soon: false },
]

const quickActions: QuickAction[] = [
  { id: 'book',    label: 'Book Lesson',      sub: 'Schedule a new session', icon: icons.calendar, primary: true },
  { id: 'message', label: 'Message Teacher',  sub: 'Send a quick message',   icon: icons.message },
  { id: 'funds',   label: 'Add Funds',        sub: 'Top up your wallet',     icon: icons.wallet },
]

const recentActivity: ActivityItem[] = [
  { id: 1, title: 'Lesson Completed', desc: 'English Conversation with Maria Santos', time: '2 hours ago', icon: icons.check,    iconClass: 'icon-badge--success' },
  { id: 2, title: 'Payment Received', desc: 'Kč500 added to your wallet',             time: '5 hours ago', icon: icons.dollar,   iconClass: 'icon-badge--primary' },
  { id: 3, title: 'New Material',     desc: 'IELTS Vocabulary List added',            time: 'Yesterday',   icon: icons.book,     iconClass: 'icon-badge--teal' },
  { id: 4, title: 'Lesson Booked',    desc: 'Grammar Review scheduled for tomorrow', time: '2 days ago',  icon: icons.calendar, iconClass: 'icon-badge--warning' },
]

function onJoin(id: number) { console.log('Join lesson', id) }
function onMore(id: number) { console.log('More for lesson', id) }
function onQuickAction(id: string) { console.log('Quick action', id) }
</script>

<style scoped>
.app-layout {
  display: grid;
  grid-template-columns: var(--tv-sidebar-width) 1fr;
  grid-template-rows: var(--tv-header-height) 1fr;
  min-height: 100dvh;
  transition: grid-template-columns 250ms cubic-bezier(0.4, 0, 0.2, 1);
}

.app-layout--collapsed {
  grid-template-columns: var(--tv-sidebar-collapsed) 1fr;
}

.app-main {
  grid-column: 2;
  grid-row: 2;
  min-width: 0;
  background: var(--tv-bg);
  overflow-y: auto;
}

.main-inner {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

.welcome__heading {
  font-size: var(--tv-text-2xl);
  font-weight: var(--tv-font-bold);
  color: var(--tv-text);
}
.welcome__sub {
  margin-top: var(--tv-space-1);
  font-size: var(--tv-text-sm);
  color: var(--tv-text-secondary);
}

/* Content grid — same 4-column base as StatsGrid for perfect alignment */
.content-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--tv-space-4);
  align-items: start;
}

.content-grid > :first-child { grid-column: 1 / 4; }
.content-grid > .right-col   { grid-column: 4; }

.right-col { display: flex; flex-direction: column; gap: var(--tv-space-4); }

@media (max-width: 1024px) {
  .content-grid { grid-template-columns: 1fr; }
  .content-grid > :first-child { grid-column: 1; }
  .content-grid > .right-col   { grid-column: 1; }
  .right-col { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--tv-space-4); }
}

@media (max-width: 767px) {
  .app-layout { grid-template-columns: 1fr; }
  .app-main { grid-column: 1; grid-row: 2; }
  .main-inner { padding: var(--tv-space-4); }
  .content-grid { grid-template-columns: 1fr; }
  .content-grid > :first-child { grid-column: 1; }
  .content-grid > .right-col   { grid-column: 1; }
  .right-col { display: flex; flex-direction: column; }
}

@media (max-width: 480px) {
  .welcome__heading { font-size: var(--tv-text-xl); }
}

@media (prefers-reduced-motion: reduce) {
  .app-layout { transition: none; }
}
</style>
