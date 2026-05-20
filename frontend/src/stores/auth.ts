import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User, UserRole } from '@/types'

export type AuthErrorCode =
  | 'INVALID_CREDENTIALS'
  | 'INACTIVE_ACCOUNT'
  | 'NETWORK_ERROR'
  | 'EXPIRED_TOKEN'
  | 'INVALID_TOKEN'

export interface AuthError {
  code: AuthErrorCode
  message: string
}

export interface LoginCredentials {
  email: string
  password: string
}

interface MockRecord {
  password: string
  user: User & { isActive?: boolean }
}

/* Demo credentials — visible on the login screen */
const MOCK_USERS: Record<string, MockRecord> = {
  'student@tutorvio.com': {
    password: 'Tutorvio@2026',
    user: {
      id: 'u1',
      firstName: 'Emma',
      lastName: 'Santos',
      email: 'student@tutorvio.com',
      role: 'STUDENT',
      timezone: 'Asia/Manila',
      createdAt: '2024-01-15T00:00:00Z',
      isActive: true,
    },
  },
  'teacher@tutorvio.com': {
    password: 'Tutorvio@2026',
    user: {
      id: 'u2',
      firstName: 'James',
      lastName: 'Reyes',
      email: 'teacher@tutorvio.com',
      role: 'TEACHER',
      timezone: 'Asia/Manila',
      createdAt: '2024-01-10T00:00:00Z',
      isActive: true,
    },
  },
  'admin@tutorvio.com': {
    password: 'Tutorvio@2026',
    user: {
      id: 'u3',
      firstName: 'Maria',
      lastName: 'Cruz',
      email: 'admin@tutorvio.com',
      role: 'ADMIN',
      timezone: 'Asia/Manila',
      createdAt: '2023-12-01T00:00:00Z',
      isActive: true,
    },
  },
  'staff@tutorvio.com': {
    password: 'Tutorvio@2026',
    user: {
      id: 'u4',
      firstName: 'Carlos',
      lastName: 'Dela Rosa',
      email: 'staff@tutorvio.com',
      role: 'STAFF',
      timezone: 'Asia/Manila',
      createdAt: '2024-02-01T00:00:00Z',
      isActive: true,
    },
  },
  'inactive@tutorvio.com': {
    password: 'Tutorvio@2026',
    user: {
      id: 'u5',
      firstName: 'Pending',
      lastName: 'User',
      email: 'inactive@tutorvio.com',
      role: 'STUDENT',
      timezone: 'Asia/Manila',
      createdAt: '2024-01-01T00:00:00Z',
      isActive: false,
    },
  },
}

const DASHBOARD_ROUTE: Record<UserRole, string> = {
  STUDENT: '/dashboard',
  TEACHER: '/dashboard',
  ADMIN: '/dashboard',
  STAFF: '/dashboard',
}

function simulateDelay(ms = 900): Promise<void> {
  return new Promise(r => setTimeout(r, ms))
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('tv_token'))

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const userRole = computed(() => user.value?.role ?? null)
  const isAdmin = computed(() => user.value?.role === 'ADMIN')
  const isTeacher = computed(() => user.value?.role === 'TEACHER')
  const isStudent = computed(() => user.value?.role === 'STUDENT')
  const isStaff = computed(() => user.value?.role === 'STAFF')
  const fullName = computed(() =>
    user.value ? `${user.value.firstName} ${user.value.lastName}` : '',
  )

  function hasRole(roles: UserRole[]): boolean {
    if (!user.value) return false
    return roles.includes(user.value.role)
  }

  function getDashboardRoute(): string {
    return user.value ? DASHBOARD_ROUTE[user.value.role] : '/dashboard'
  }

  async function login(credentials: LoginCredentials): Promise<void> {
    await simulateDelay()

    const record = MOCK_USERS[credentials.email.trim().toLowerCase()]

    if (!record || record.password !== credentials.password) {
      throw {
        code: 'INVALID_CREDENTIALS',
        message: 'Invalid email or password. Please try again.',
      } satisfies AuthError
    }

    if (record.user.isActive === false) {
      throw {
        code: 'INACTIVE_ACCOUNT',
        message:
          'Your account is inactive. Please contact support@tutorvio.com.',
      } satisfies AuthError
    }

    const mockToken = `tv_mock_${record.user.id}_${Date.now()}`
    token.value = mockToken
    user.value = record.user

    localStorage.setItem('tv_token', mockToken)
    localStorage.setItem('tv_user', JSON.stringify(record.user))
  }

  function logout(): void {
    token.value = null
    user.value = null
    localStorage.removeItem('tv_token')
    localStorage.removeItem('tv_user')
  }

  async function requestPasswordReset(_email: string): Promise<void> {
    await simulateDelay()
    /* Security: never reveal whether email exists — always resolve */
  }

  async function resetPassword(resetToken: string, _newPassword: string): Promise<void> {
    await simulateDelay()
    if (resetToken.includes('expired')) {
      throw {
        code: 'EXPIRED_TOKEN',
        message: 'This reset link has expired. Please request a new one.',
      } satisfies AuthError
    }
    if (!resetToken || resetToken.length < 6) {
      throw {
        code: 'INVALID_TOKEN',
        message: 'This reset link is invalid. Please request a new one.',
      } satisfies AuthError
    }
  }

  async function activateAccount(
    activationToken: string,
    _data: { firstName: string; lastName: string; password: string },
  ): Promise<void> {
    await simulateDelay()
    if (activationToken.includes('expired')) {
      throw {
        code: 'EXPIRED_TOKEN',
        message:
          'This invitation link has expired. Please contact your administrator.',
      } satisfies AuthError
    }
    if (!activationToken || activationToken.length < 6) {
      throw {
        code: 'INVALID_TOKEN',
        message: 'This invitation link is invalid. Please contact your administrator.',
      } satisfies AuthError
    }
  }

  /* Restore session from localStorage on app boot */
  function restoreSession(): void {
    const stored = localStorage.getItem('tv_user')
    if (stored && token.value) {
      try {
        user.value = JSON.parse(stored) as User
      } catch {
        logout()
      }
    } else if (!token.value) {
      user.value = null
    }
  }

  restoreSession()

  return {
    user,
    token,
    isAuthenticated,
    userRole,
    isAdmin,
    isTeacher,
    isStudent,
    isStaff,
    fullName,
    hasRole,
    getDashboardRoute,
    login,
    logout,
    requestPasswordReset,
    resetPassword,
    activateAccount,
  }
})
