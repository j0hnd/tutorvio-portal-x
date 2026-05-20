import { defineStore } from 'pinia'
import { ref } from 'vue'
import type {
  ManagedUser,
  UserRole,
  StudentProfile,
  TeacherProfile,
  AdminStaffProfile,
} from '@/types'

const MOCK_USERS: ManagedUser[] = [
  {
    id: 'u1',
    firstName: 'Ana',
    lastName: 'Santos',
    email: 'ana.santos@example.com',
    role: 'STUDENT',
    timezone: 'Asia/Manila',
    createdAt: '2024-01-10T08:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-18T14:22:00Z',
    studentProfile: {
      englishLevel: 'INTERMEDIATE',
      program: 'Business English',
      assignedTeacherId: 'u5',
      classType: 'ONLINE',
      startDate: '2024-01-15',
      goals: 'Improve professional communication skills',
      learningConcerns: 'Grammar and business vocabulary',
      notes: 'Prefers morning sessions',
    },
  },
  {
    id: 'u2',
    firstName: 'Marco',
    lastName: 'Reyes',
    email: 'marco.reyes@example.com',
    role: 'STUDENT',
    timezone: 'Asia/Manila',
    createdAt: '2024-02-05T09:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-17T10:00:00Z',
    studentProfile: {
      englishLevel: 'BEGINNER',
      program: 'General English',
      assignedTeacherId: 'u6',
      classType: 'ONLINE',
      startDate: '2024-02-10',
      goals: 'Basic conversational English',
      learningConcerns: 'Pronunciation and confidence',
      notes: 'Works night shift, prefers weekend classes',
    },
  },
  {
    id: 'u3',
    firstName: 'Sofia',
    lastName: 'Lim',
    email: 'sofia.lim@example.com',
    role: 'STUDENT',
    timezone: 'Asia/Manila',
    createdAt: '2024-03-01T07:30:00Z',
    isActive: false,
    lastLoginAt: '2025-03-10T09:15:00Z',
    studentProfile: {
      englishLevel: 'UPPER_INTERMEDIATE',
      program: 'IELTS Preparation',
      classType: 'HYBRID',
      startDate: '2024-03-05',
      goals: 'Score 7.5 in IELTS',
      learningConcerns: 'Writing and speaking sections',
    },
  },
  {
    id: 'u4',
    firstName: 'David',
    lastName: 'Cruz',
    email: 'david.cruz@example.com',
    role: 'STUDENT',
    timezone: 'Asia/Manila',
    createdAt: '2024-04-12T10:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-19T16:45:00Z',
    studentProfile: {
      englishLevel: 'ADVANCED',
      program: 'Executive English',
      assignedTeacherId: 'u5',
      classType: 'ONLINE',
      startDate: '2024-04-15',
      goals: 'Presentation and negotiation skills',
    },
  },
  {
    id: 'u5',
    firstName: 'James',
    lastName: 'Walker',
    email: 'james.walker@example.com',
    role: 'TEACHER',
    timezone: 'Europe/London',
    createdAt: '2023-12-01T08:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-20T08:30:00Z',
    teacherProfile: {
      specialization: 'Business English, IELTS',
      availabilitySummary: 'Mon–Fri 9am–5pm GMT',
      internalStatus: 'ACTIVE',
      teachingNotes: 'Experienced with corporate clients. Prefers structured lesson plans.',
      assignedStudentIds: ['u1', 'u4'],
      documentStatus: 'APPROVED',
      contractStatus: 'APPROVED',
    },
  },
  {
    id: 'u6',
    firstName: 'Maria',
    lastName: 'Garcia',
    email: 'maria.garcia@example.com',
    role: 'TEACHER',
    timezone: 'America/New_York',
    createdAt: '2024-01-15T09:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-19T12:00:00Z',
    teacherProfile: {
      specialization: 'General English, Kids & Teens',
      availabilitySummary: 'Mon–Sat 8am–4pm EST',
      internalStatus: 'ACTIVE',
      teachingNotes: 'Great rapport with beginners. Very patient.',
      assignedStudentIds: ['u2'],
      documentStatus: 'APPROVED',
      contractStatus: 'APPROVED',
    },
  },
  {
    id: 'u7',
    firstName: 'Kevin',
    lastName: 'Tan',
    email: 'kevin.tan@example.com',
    role: 'TEACHER',
    timezone: 'Asia/Singapore',
    createdAt: '2024-06-01T08:00:00Z',
    isActive: false,
    teacherProfile: {
      specialization: 'Academic Writing',
      availabilitySummary: 'TBD',
      internalStatus: 'ON_LEAVE',
      documentStatus: 'SUBMITTED',
      contractStatus: 'PENDING',
    },
  },
  {
    id: 'u8',
    firstName: 'Admin',
    lastName: 'User',
    email: 'admin@tutorvio.com',
    role: 'ADMIN',
    timezone: 'Asia/Manila',
    createdAt: '2023-11-01T08:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-20T09:00:00Z',
    adminStaffProfile: {
      department: 'Operations',
      permissions: ['VIEW_BILLING', 'EDIT_STUDENTS', 'VIEW_PAYROLL', 'MANAGE_SCHEDULE', 'SEND_COMMUNICATIONS'],
      accessLimitations: 'None',
    },
  },
  {
    id: 'u9',
    firstName: 'Rachel',
    lastName: 'Mendoza',
    email: 'rachel.mendoza@example.com',
    role: 'STAFF',
    timezone: 'Asia/Manila',
    createdAt: '2024-02-20T08:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-19T17:00:00Z',
    adminStaffProfile: {
      department: 'Student Support',
      permissions: ['EDIT_STUDENTS', 'MANAGE_SCHEDULE', 'SEND_COMMUNICATIONS'],
      accessLimitations: 'Cannot access billing or payroll',
    },
  },
  {
    id: 'u10',
    firstName: 'Luis',
    lastName: 'Fernandez',
    email: 'luis.fernandez@example.com',
    role: 'STAFF',
    timezone: 'Asia/Manila',
    createdAt: '2024-05-01T08:00:00Z',
    isActive: false,
    adminStaffProfile: {
      department: 'Finance',
      permissions: ['VIEW_BILLING', 'VIEW_PAYROLL'],
    },
  },
]

type CreateUserPayload = Omit<ManagedUser, 'id' | 'createdAt'>
type UpdateUserPayload = Partial<Omit<ManagedUser, 'id' | 'createdAt'>>

export const useUsersStore = defineStore('users', () => {
  const users = ref<ManagedUser[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchUsers(): Promise<void> {
    loading.value = true
    error.value = null
    await new Promise(resolve => setTimeout(resolve, 300))
    users.value = [...MOCK_USERS]
    loading.value = false
  }

  function getUserById(id: string): ManagedUser | undefined {
    return users.value.find(u => u.id === id)
  }

  function getTeachers(): ManagedUser[] {
    return users.value.filter(u => u.role === 'TEACHER' && u.isActive)
  }

  function filteredUsers(filters: {
    role?: UserRole | ''
    status?: 'active' | 'inactive' | ''
    search?: string
  }): ManagedUser[] {
    return users.value.filter(u => {
      if (filters.role && u.role !== filters.role) return false
      if (filters.status === 'active' && !u.isActive) return false
      if (filters.status === 'inactive' && u.isActive) return false
      if (filters.search) {
        const q = filters.search.toLowerCase()
        const full = `${u.firstName} ${u.lastName} ${u.email}`.toLowerCase()
        if (!full.includes(q)) return false
      }
      return true
    })
  }

  function createUser(data: CreateUserPayload): ManagedUser {
    const newUser: ManagedUser = {
      ...data,
      id: `u${Date.now()}`,
      createdAt: new Date().toISOString(),
    }
    users.value.push(newUser)
    return newUser
  }

  function updateUser(id: string, data: UpdateUserPayload): ManagedUser | null {
    const idx = users.value.findIndex(u => u.id === id)
    if (idx === -1) return null
    users.value[idx] = { ...users.value[idx], ...data }
    return users.value[idx]
  }

  function deleteUser(id: string): boolean {
    const idx = users.value.findIndex(u => u.id === id)
    if (idx === -1) return false
    users.value.splice(idx, 1)
    return true
  }

  function toggleActive(id: string): boolean | null {
    const user = users.value.find(u => u.id === id)
    if (!user) return null
    user.isActive = !user.isActive
    return user.isActive
  }

  function updateStudentProfile(id: string, profile: StudentProfile): void {
    const user = users.value.find(u => u.id === id)
    if (user) user.studentProfile = { ...user.studentProfile, ...profile }
  }

  function updateTeacherProfile(id: string, profile: TeacherProfile): void {
    const user = users.value.find(u => u.id === id)
    if (user) user.teacherProfile = { ...user.teacherProfile, ...profile }
  }

  function updateAdminStaffProfile(id: string, profile: AdminStaffProfile): void {
    const user = users.value.find(u => u.id === id)
    if (user) user.adminStaffProfile = { ...user.adminStaffProfile, ...profile }
  }

  return {
    users,
    loading,
    error,
    fetchUsers,
    getUserById,
    getTeachers,
    filteredUsers,
    createUser,
    updateUser,
    deleteUser,
    toggleActive,
    updateStudentProfile,
    updateTeacherProfile,
    updateAdminStaffProfile,
  }
})
