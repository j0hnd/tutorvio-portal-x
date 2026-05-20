import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from './auth'

/* Stub localStorage */
const storage: Record<string, string> = {}
vi.stubGlobal('localStorage', {
  getItem: (k: string) => storage[k] ?? null,
  setItem: (k: string, v: string) => { storage[k] = v },
  removeItem: (k: string) => { delete storage[k] },
  clear: () => Object.keys(storage).forEach(k => delete storage[k]),
})

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  afterEach(() => {
    localStorage.clear()
  })

  describe('initial state', () => {
    it('starts unauthenticated when localStorage is empty', () => {
      const auth = useAuthStore()
      expect(auth.isAuthenticated).toBe(false)
      expect(auth.user).toBeNull()
      expect(auth.token).toBeNull()
    })
  })

  describe('login', () => {
    it('authenticates a student with correct credentials', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'student@tutorvio.com', password: 'Tutorvio@2026' })

      expect(auth.isAuthenticated).toBe(true)
      expect(auth.user?.role).toBe('STUDENT')
      expect(auth.isStudent).toBe(true)
      expect(auth.token).toBeTruthy()
    })

    it('authenticates a teacher with correct credentials', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'teacher@tutorvio.com', password: 'Tutorvio@2026' })

      expect(auth.isAuthenticated).toBe(true)
      expect(auth.user?.role).toBe('TEACHER')
      expect(auth.isTeacher).toBe(true)
    })

    it('authenticates an admin with correct credentials', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'admin@tutorvio.com', password: 'Tutorvio@2026' })

      expect(auth.isAuthenticated).toBe(true)
      expect(auth.user?.role).toBe('ADMIN')
      expect(auth.isAdmin).toBe(true)
    })

    it('authenticates staff with correct credentials', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'staff@tutorvio.com', password: 'Tutorvio@2026' })

      expect(auth.isAuthenticated).toBe(true)
      expect(auth.user?.role).toBe('STAFF')
      expect(auth.isStaff).toBe(true)
    })

    it('throws INVALID_CREDENTIALS for unknown email', async () => {
      const auth = useAuthStore()
      await expect(
        auth.login({ email: 'nobody@tutorvio.com', password: 'Tutorvio@2026' }),
      ).rejects.toMatchObject({ code: 'INVALID_CREDENTIALS' })
    })

    it('throws INVALID_CREDENTIALS for wrong password', async () => {
      const auth = useAuthStore()
      await expect(
        auth.login({ email: 'student@tutorvio.com', password: 'wrongpass' }),
      ).rejects.toMatchObject({ code: 'INVALID_CREDENTIALS' })
    })

    it('throws INACTIVE_ACCOUNT for inactive user', async () => {
      const auth = useAuthStore()
      await expect(
        auth.login({ email: 'inactive@tutorvio.com', password: 'Tutorvio@2026' }),
      ).rejects.toMatchObject({ code: 'INACTIVE_ACCOUNT' })
    })

    it('is case-insensitive for email', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'STUDENT@Tutorvio.COM', password: 'Tutorvio@2026' })
      expect(auth.isAuthenticated).toBe(true)
    })

    it('persists token and user to localStorage after login', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'student@tutorvio.com', password: 'Tutorvio@2026' })

      expect(localStorage.getItem('tv_token')).toBeTruthy()
      expect(localStorage.getItem('tv_user')).toBeTruthy()
    })
  })

  describe('logout', () => {
    it('clears user and token state', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'student@tutorvio.com', password: 'Tutorvio@2026' })
      auth.logout()

      expect(auth.isAuthenticated).toBe(false)
      expect(auth.user).toBeNull()
      expect(auth.token).toBeNull()
    })

    it('removes items from localStorage', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'student@tutorvio.com', password: 'Tutorvio@2026' })
      auth.logout()

      expect(localStorage.getItem('tv_token')).toBeNull()
      expect(localStorage.getItem('tv_user')).toBeNull()
    })
  })

  describe('getters', () => {
    it('fullName returns combined first and last name', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'student@tutorvio.com', password: 'Tutorvio@2026' })
      expect(auth.fullName).toBe('Emma Santos')
    })

    it('hasRole returns true when user has the specified role', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'teacher@tutorvio.com', password: 'Tutorvio@2026' })
      expect(auth.hasRole(['TEACHER', 'ADMIN'])).toBe(true)
      expect(auth.hasRole(['STUDENT'])).toBe(false)
    })

    it('getDashboardRoute returns /dashboard for any role', async () => {
      const auth = useAuthStore()
      await auth.login({ email: 'admin@tutorvio.com', password: 'Tutorvio@2026' })
      expect(auth.getDashboardRoute()).toBe('/dashboard')
    })
  })

  describe('resetPassword', () => {
    it('resolves for a valid token', async () => {
      const auth = useAuthStore()
      await expect(auth.resetPassword('validtoken123', 'NewPass@123')).resolves.toBeUndefined()
    })

    it('throws EXPIRED_TOKEN for an expired token', async () => {
      const auth = useAuthStore()
      await expect(auth.resetPassword('expired_abc', 'NewPass@123')).rejects.toMatchObject({
        code: 'EXPIRED_TOKEN',
      })
    })
  })

  describe('activateAccount', () => {
    it('resolves for a valid activation token', async () => {
      const auth = useAuthStore()
      await expect(
        auth.activateAccount('validtoken123', { firstName: 'Emma', lastName: 'S', password: 'Pass@123' }),
      ).resolves.toBeUndefined()
    })

    it('throws EXPIRED_TOKEN for an expired activation token', async () => {
      const auth = useAuthStore()
      await expect(
        auth.activateAccount('expired_invite', { firstName: 'Emma', lastName: 'S', password: 'Pass@123' }),
      ).rejects.toMatchObject({ code: 'EXPIRED_TOKEN' })
    })
  })
})
