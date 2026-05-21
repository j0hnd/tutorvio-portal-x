import type { UserRole } from '@/types'

export interface NavItem {
  path: string
  label: string
  icon: string
  roles: UserRole[]
  badge?: number
}

const ICONS = {
  dashboard: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2" y="2" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="11" y="2" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="2" y="11" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><rect x="11" y="11" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/></svg>`,
  schedule: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2.5" y="3.5" width="15" height="14" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M6.5 2v3M13.5 2v3M2.5 7.5h15" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="7" cy="12" r="1" fill="currentColor"/><circle cx="10" cy="12" r="1" fill="currentColor"/><circle cx="13" cy="12" r="1" fill="currentColor"/></svg>`,
  lessons: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 4h12a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/><path d="M7 8h6M7 11h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  materials: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 3h10a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.4"/><path d="M7 7h6M7 10h6M7 13h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  billing: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2" y="5" width="16" height="11" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M2 9h16" stroke="currentColor" stroke-width="1.4"/><circle cx="6" cy="13" r="1" fill="currentColor"/></svg>`,
  students: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="8" cy="7" r="3" stroke="currentColor" stroke-width="1.4"/><path d="M2 17c0-3.314 2.686-5 6-5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="15" cy="9" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M11 17c0-2.5 1.8-4 4-4s4 1.5 4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  availability: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.4"/><path d="M10 6v4l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  payroll: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2" y="4" width="16" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/><circle cx="10" cy="10" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M5.5 10h1M13.5 10h1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
  allUsers: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="6.5" r="3" stroke="currentColor" stroke-width="1.4"/><path d="M3 17c0-3.314 3.134-6 7-6s7 2.686 7 6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
}

const ALL_NAV: NavItem[] = [
  {
    path: '/dashboard',
    label: 'Dashboard',
    icon: ICONS.dashboard,
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
    label: 'My Classes',
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
    path: '/materials',
    label: 'Materials',
    icon: ICONS.materials,
    roles: ['STUDENT', 'TEACHER', 'ADMIN', 'STAFF'],
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
