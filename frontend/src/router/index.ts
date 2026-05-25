import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    roles?: UserRole[]
    layout?: 'auth' | 'default'
  }
}

const routes: RouteRecordRaw[] = [
  /* ── Root redirect ── */
  { path: '/', redirect: '/dashboard' },

  /* ── Auth pages (public, layout: auth) ── */
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { requiresAuth: false, layout: 'auth' },
  },
  {
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { requiresAuth: false, layout: 'auth' },
  },
  {
    path: '/reset-password',
    name: 'ResetPassword',
    component: () => import('@/views/auth/ResetPasswordView.vue'),
    meta: { requiresAuth: false, layout: 'auth' },
  },
  {
    path: '/activate',
    name: 'ActivateAccount',
    component: () => import('@/views/auth/ActivateAccountView.vue'),
    meta: { requiresAuth: false, layout: 'auth' },
  },

  /* ── Protected pages (all roles) ── */
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/schedule',
    name: 'Schedule',
    component: () => import('@/views/ScheduleView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/materials',
    name: 'Materials',
    component: () => import('@/views/MaterialsView.vue'),
    meta: { requiresAuth: true },
  },

  /* ── Lessons ── */
  {
    path: '/lessons',
    name: 'Lessons',
    component: () => import('@/views/LessonsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/lessons/:id',
    name: 'LessonDetail',
    component: () => import('@/views/LessonDetailView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/billing',
    name: 'Billing',
    component: () => import('@/views/BillingView.vue'),
    meta: { requiresAuth: true, roles: ['STUDENT', 'ADMIN'] },
  },

  /* ── Teacher / Admin / Staff ── */
  {
    path: '/students',
    name: 'Students',
    component: () => import('@/views/StudentsView.vue'),
    meta: { requiresAuth: true, roles: ['TEACHER', 'ADMIN', 'STAFF'] },
  },
  {
    path: '/availability',
    name: 'Availability',
    component: () => import('@/views/AvailabilityView.vue'),
    meta: { requiresAuth: true, roles: ['TEACHER', 'ADMIN'] },
  },
  {
    path: '/payroll',
    name: 'Payroll',
    component: () => import('@/views/PayrollView.vue'),
    meta: { requiresAuth: true, roles: ['TEACHER', 'ADMIN'] },
  },

  /* ── Admin: User Management ── */
  {
    path: '/admin/users',
    name: 'AdminUsers',
    component: () => import('@/views/admin/UsersView.vue'),
    meta: { requiresAuth: true, roles: ['ADMIN'] },
  },
  {
    path: '/admin/users/create',
    name: 'AdminUserCreate',
    component: () => import('@/views/admin/UserCreateView.vue'),
    meta: { requiresAuth: true, roles: ['ADMIN'] },
  },
  {
    path: '/admin/users/:id/edit',
    name: 'AdminUserEdit',
    component: () => import('@/views/admin/UserEditView.vue'),
    meta: { requiresAuth: true, roles: ['ADMIN'] },
  },

  /* ── Profile pages ── */
  {
    path: '/profile',
    name: 'Profile',
    component: () => import('@/views/profile/ProfileView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/profile/:id',
    name: 'UserProfile',
    component: () => import('@/views/profile/ProfileView.vue'),
    meta: { requiresAuth: true },
  },

  /* ── Error pages ── */
  {
    path: '/unauthorized',
    name: 'Unauthorized',
    component: () => import('@/views/errors/UnauthorizedView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(_to, _from, savedPosition) {
    if (savedPosition) return savedPosition
    return { top: 0, behavior: 'smooth' }
  },
})

/* ── Navigation guard ── */
router.beforeEach((to, _from) => {
  const auth = useAuthStore()

  const isAuthenticated = auth.isAuthenticated
  const requiresAuth = to.meta.requiresAuth !== false
  const allowedRoles = to.meta.roles

  /* 1. Unauthenticated user → protected page */
  if (requiresAuth && !isAuthenticated) {
    return { name: 'Login', query: { redirect: to.fullPath } }
  }

  /* 2. Authenticated user → auth page (login, forgot-password, etc.) */
  if (!requiresAuth && isAuthenticated && to.meta.layout === 'auth') {
    return { path: auth.getDashboardRoute() }
  }

  /* 3. Role-based access control */
  if (isAuthenticated && allowedRoles && auth.user) {
    if (!allowedRoles.includes(auth.user.role)) {
      return { name: 'Unauthorized' }
    }
  }
})

export default router
