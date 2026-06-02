import type { UserRole } from '@/types'

export interface NavItem {
  path: string
  label: string
  icon: string
  roles: UserRole[]
  badge?: number
}

const ICONS = {
  // Dashboard — grid of 4 tiles
  dashboard: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2" y="2" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="11" y="2" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="2" y="11" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="11" y="11" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/></svg>`,
  // Schedule — calendar with clock
  schedule: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2.5" y="3.5" width="15" height="14" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M6.5 2v3M13.5 2v3M2.5 7.5h15" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="13" cy="13" r="3.5" fill="var(--tv-bg-card)" stroke="currentColor" stroke-width="1.3"/><path d="M13 11.5V13l1 1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>`,
  // Lessons — open book / play
  lessons: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 5c2.5-1 4.5-.5 7 1 2.5-1.5 4.5-2 7-1v11c-2.5-1-4.5-.5-7 1-2.5-1.5-4.5-2-7-1V5z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M10 6v11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  // Materials — folder with pages
  materials: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2 7a2 2 0 0 1 2-2h3.5l2 2H16a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7z" stroke="currentColor" stroke-width="1.4"/></svg>`,
  // Homework — pencil on clipboard
  homework: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M7 3h9a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7l3-4z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7 3v4H4" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M12 9.5l-3.5 3.5H7v-1.5L10.5 8l1.5 1.5z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>`,
  // Billing — wallet with coin
  billing: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 6a2 2 0 0 1 2-2h12a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2V6z" stroke="currentColor" stroke-width="1.4"/><path d="M3 9h16" stroke="currentColor" stroke-width="1.4"/><circle cx="14.5" cy="13" r="1.5" stroke="currentColor" stroke-width="1.2"/></svg>`,
  // Students — group of people
  students: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="7.5" cy="7" r="3" stroke="currentColor" stroke-width="1.4"/><path d="M1 17c0-3.314 2.91-5 6.5-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="14" cy="8.5" r="2.5" stroke="currentColor" stroke-width="1.3"/><path d="M11 17c0-2.5 1.3-4 3-4s3 1.5 3 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>`,
  // Availability — calendar with slot check
  availability: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.4"/><path d="M10 6v4l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 10h1" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>`,
  // Payroll — banknote
  payroll: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="1.5" y="5.5" width="17" height="10" rx="1.5" stroke="currentColor" stroke-width="1.4"/><circle cx="10" cy="10.5" r="2" stroke="currentColor" stroke-width="1.3"/><path d="M5 10.5h1M14 10.5h1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M4.5 7.5v1M4.5 12.5v1M15.5 7.5v1M15.5 12.5v1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>`,
  // Attendance — calendar with checkmark
  attendance: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2.5" y="3.5" width="15" height="14" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M6.5 2v3M13.5 2v3M2.5 7.5h15" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M6.5 12l2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  // Users (admin) — person silhouette with gear
  allUsers: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="6.5" r="3" stroke="currentColor" stroke-width="1.4"/><path d="M3 17c0-3.314 3.134-6 7-6s7 2.686 7 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  // Users management — person with + badge
  users: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="8" cy="7" r="3" stroke="currentColor" stroke-width="1.4"/><path d="M2 17c0-3.314 2.686-5 6-5s6 1.686 6 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M15 9v4M17 11h-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  // Profile — person in circle
  profile: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.4"/><circle cx="10" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M4.5 16c.5-2.5 2.8-4 5.5-4s5 1.5 5.5 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
}

const ALL_NAV: NavItem[] = [
  {
    path: '/dashboard',
    label: 'Dashboard',
    icon: ICONS.dashboard,
    roles: ['STUDENT', 'TEACHER', 'ADMIN', 'STAFF'],
  },
  {
    path: '/announcements',
    label: 'Announcements',
    icon: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 7h10l3-3v10l-3-3H3V7z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 11v4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
    roles: ['STUDENT', 'TEACHER', 'ADMIN', 'STAFF'],
  },
  {
    path: '/schedule',
    label: 'Schedule',
    icon: ICONS.schedule,
    roles: ['STUDENT', 'TEACHER', 'ADMIN', 'STAFF'],
  },
  {
    path: '/lessons',
    label: 'My Lessons',
    icon: ICONS.lessons,
    roles: ['STUDENT'],
  },
  {
    path: '/lessons',
    label: 'My Lessons',
    icon: ICONS.lessons,
    roles: ['TEACHER'],
  },
  {
    path: '/lessons',
    label: 'Lessons',
    icon: ICONS.lessons,
    roles: ['ADMIN', 'STAFF'],
  },
  {
    path: '/courses',
    label: 'Courses',
    icon: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 4h12a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/><path d="M7 8h6M7 11h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M10 4V2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
    roles: ['STUDENT', 'TEACHER', 'ADMIN', 'STAFF'],
  },
  {
    path: '/materials',
    label: 'Materials',
    icon: ICONS.materials,
    roles: ['STUDENT', 'TEACHER', 'ADMIN', 'STAFF'],
  },
  {
    path: '/homework',
    label: 'Homework',
    icon: ICONS.homework,
    roles: ['STUDENT', 'TEACHER', 'ADMIN', 'STAFF'],
  },
  {
    path: '/progress',
    label: 'Progress',
    icon: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 15L7 10l3 3 3-4 4 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><rect x="2" y="2" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.4"/></svg>`,
    roles: ['STUDENT', 'TEACHER', 'ADMIN', 'STAFF'],
  },
  {
    path: '/attendance',
    label: 'Attendance',
    icon: ICONS.attendance,
    roles: ['TEACHER', 'ADMIN', 'STAFF', 'STUDENT'],
  },
  {
    path: '/billing',
    label: 'Billing',
    icon: ICONS.billing,
    roles: ['STUDENT', 'ADMIN'],
  },
  {
    path: '/students',
    label: 'Students',
    icon: ICONS.students,
    roles: ['TEACHER', 'ADMIN', 'STAFF'],
  },
  {
    path: '/admin/users',
    label: 'Users',
    icon: ICONS.users,
    roles: ['ADMIN'],
  },
  {
    path: '/availability',
    label: 'Availability',
    icon: ICONS.availability,
    roles: ['TEACHER', 'ADMIN'],
  },
  {
    path: '/payroll',
    label: 'Payroll',
    icon: ICONS.payroll,
    roles: ['TEACHER', 'ADMIN'],
  },
]

export function useNavigation() {
  function getNavItems(role: UserRole): NavItem[] {
    return ALL_NAV.filter(item => item.roles.includes(role))
  }

  return { getNavItems }
}
