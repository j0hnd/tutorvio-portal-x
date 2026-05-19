import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  {
    // TEMP: Skip login for Phase 1 preview — redirect to dashboard directly
    path: '/',
    redirect: '/dashboard',
  },
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
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: false }, // TEMP: open for Phase 1 preview
  },
  {
    path: '/schedule',
    name: 'Schedule',
    component: () => import('@/views/ScheduleView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/lessons',
    name: 'Lessons',
    component: () => import('@/views/LessonsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/materials',
    name: 'Materials',
    component: () => import('@/views/MaterialsView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/billing',
    name: 'Billing',
    component: () => import('@/views/BillingView.vue'),
    meta: { requiresAuth: true, roles: ['STUDENT', 'ADMIN'] },
  },
  {
    path: '/students',
    name: 'Students',
    component: () => import('@/views/StudentsView.vue'),
    meta: { requiresAuth: true, roles: ['TEACHER', 'ADMIN'] },
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

router.beforeEach((to, _from, next) => {
  const token = localStorage.getItem('tv_token')
  const isAuthenticated = !!token

  if (to.meta.requiresAuth && !isAuthenticated) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
  } else if (!to.meta.requiresAuth && isAuthenticated && to.name === 'Login') {
    next({ name: 'Dashboard' })
  } else {
    next()
  }
})

export default router
