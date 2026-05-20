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
    firstName: 'Emma',
    lastName: 'Santos',
    email: 'student@tutorvio.com',
    role: 'STUDENT',
    timezone: 'Asia/Manila',
    createdAt: '2024-01-15T00:00:00Z',
    isActive: true,
    lastLoginAt: '2026-05-20T14:22:00Z',
    studentProfile: {
      englishLevel: 'INTERMEDIATE',
      program: 'Business English',
      assignedTeacherId: 'u26',
      classType: 'ONLINE',
      startDate: '2024-01-20',
      goals: 'Improve professional communication and presentation skills for corporate settings',
      learningConcerns: 'Grammar in formal writing, business vocabulary, and confident speaking in meetings',
      notes: 'Quick learner. Prefers morning sessions. Needs challenging material to stay engaged.',
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
      assignedStudentIds: ['u4'],
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
    firstName: 'Maria',
    lastName: 'Cruz',
    email: 'admin@tutorvio.com',
    role: 'ADMIN',
    timezone: 'Asia/Manila',
    createdAt: '2023-12-01T00:00:00Z',
    isActive: true,
    lastLoginAt: '2026-05-20T09:00:00Z',
    adminStaffProfile: {
      department: 'Operations',
      permissions: ['VIEW_BILLING', 'EDIT_STUDENTS', 'VIEW_PAYROLL', 'MANAGE_SCHEDULE', 'SEND_COMMUNICATIONS'],
      accessLimitations: 'None — Full administrative access',
    },
  },
  {
    id: 'u9',
    firstName: 'Carlos',
    lastName: 'Dela Rosa',
    email: 'staff@tutorvio.com',
    role: 'STAFF',
    timezone: 'Asia/Manila',
    createdAt: '2024-02-01T00:00:00Z',
    isActive: true,
    lastLoginAt: '2026-05-20T10:00:00Z',
    adminStaffProfile: {
      department: 'Student Support',
      permissions: ['EDIT_STUDENTS', 'MANAGE_SCHEDULE', 'SEND_COMMUNICATIONS'],
      accessLimitations: 'Cannot access billing or payroll records',
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
  {
    id: 'u11',
    firstName: 'Isabella',
    lastName: 'Morales',
    email: 'isabella.morales@example.com',
    role: 'STUDENT',
    timezone: 'America/New_York',
    createdAt: '2024-06-10T08:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-15T11:00:00Z',
    studentProfile: {
      englishLevel: 'ELEMENTARY',
      program: 'Conversational English',
      classType: 'ONLINE',
      startDate: '2024-06-15',
      goals: 'Speak confidently in daily situations',
    },
  },
  {
    id: 'u12',
    firstName: 'Carlos',
    lastName: 'Dela Cruz',
    email: 'carlos.delacruz@example.com',
    role: 'STUDENT',
    timezone: 'Asia/Manila',
    createdAt: '2024-07-01T09:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-20T07:30:00Z',
    studentProfile: {
      englishLevel: 'UPPER_INTERMEDIATE',
      program: 'IELTS Preparation',
      assignedTeacherId: 'u5',
      classType: 'HYBRID',
      startDate: '2024-07-05',
      goals: 'Score band 7+ in IELTS',
      learningConcerns: 'Task 2 essay writing',
    },
  },
  {
    id: 'u13',
    firstName: 'Priya',
    lastName: 'Nair',
    email: 'priya.nair@example.com',
    role: 'STUDENT',
    timezone: 'Asia/Kolkata',
    createdAt: '2024-08-12T10:00:00Z',
    isActive: false,
    studentProfile: {
      englishLevel: 'ADVANCED',
      program: 'Executive English',
      classType: 'ONLINE',
      startDate: '2024-08-15',
      goals: 'Boardroom and presentation skills',
    },
  },
  {
    id: 'u14',
    firstName: 'Yuki',
    lastName: 'Tanaka',
    email: 'yuki.tanaka@example.com',
    role: 'STUDENT',
    timezone: 'Asia/Tokyo',
    createdAt: '2024-09-03T08:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-18T09:00:00Z',
    studentProfile: {
      englishLevel: 'BEGINNER',
      program: 'General English',
      assignedTeacherId: 'u6',
      classType: 'ONLINE',
      startDate: '2024-09-10',
      goals: 'Basic reading and listening',
    },
  },
  {
    id: 'u15',
    firstName: 'Fatima',
    lastName: 'Al-Hassan',
    email: 'fatima.alhassan@example.com',
    role: 'STUDENT',
    timezone: 'Asia/Dubai',
    createdAt: '2024-10-01T07:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-19T14:00:00Z',
    studentProfile: {
      englishLevel: 'INTERMEDIATE',
      program: 'Business English',
      assignedTeacherId: 'u5',
      classType: 'ONLINE',
      startDate: '2024-10-05',
      goals: 'Professional email and report writing',
      learningConcerns: 'Formal vocabulary',
    },
  },
  {
    id: 'u16',
    firstName: 'Liam',
    lastName: 'O\'Brien',
    email: 'liam.obrien@example.com',
    role: 'TEACHER',
    timezone: 'Europe/Dublin',
    createdAt: '2024-03-15T09:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-20T07:00:00Z',
    teacherProfile: {
      specialization: 'Conversational English, Pronunciation',
      availabilitySummary: 'Tue–Sat 10am–6pm IST',
      internalStatus: 'ACTIVE',
      teachingNotes: 'Native Irish speaker. Excellent with beginners.',
      assignedStudentIds: ['u11', 'u14'],
      documentStatus: 'APPROVED',
      contractStatus: 'APPROVED',
    },
  },
  {
    id: 'u17',
    firstName: 'Sarah',
    lastName: 'Johnson',
    email: 'sarah.johnson@example.com',
    role: 'TEACHER',
    timezone: 'America/Chicago',
    createdAt: '2024-04-01T08:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-19T20:00:00Z',
    teacherProfile: {
      specialization: 'IELTS, Academic Writing',
      availabilitySummary: 'Mon–Fri 2pm–10pm CST',
      internalStatus: 'ACTIVE',
      teachingNotes: 'High success rate with IELTS students.',
      assignedStudentIds: ['u12', 'u15'],
      documentStatus: 'APPROVED',
      contractStatus: 'APPROVED',
    },
  },
  {
    id: 'u18',
    firstName: 'Chen',
    lastName: 'Wei',
    email: 'chen.wei@example.com',
    role: 'TEACHER',
    timezone: 'Asia/Shanghai',
    createdAt: '2024-05-20T08:00:00Z',
    isActive: false,
    teacherProfile: {
      specialization: 'Business English, Executive Coaching',
      availabilitySummary: 'TBD',
      internalStatus: 'PROBATION',
      documentStatus: 'SUBMITTED',
      contractStatus: 'PENDING',
    },
  },
  {
    id: 'u19',
    firstName: 'Amara',
    lastName: 'Okafor',
    email: 'amara.okafor@example.com',
    role: 'STAFF',
    timezone: 'Africa/Lagos',
    createdAt: '2024-06-01T08:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-20T08:00:00Z',
    adminStaffProfile: {
      department: 'Marketing',
      permissions: ['SEND_COMMUNICATIONS', 'EDIT_STUDENTS'],
      accessLimitations: 'No access to financial data',
    },
  },
  {
    id: 'u20',
    firstName: 'Diego',
    lastName: 'Vargas',
    email: 'diego.vargas@example.com',
    role: 'STAFF',
    timezone: 'America/Bogota',
    createdAt: '2024-07-15T09:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-17T13:00:00Z',
    adminStaffProfile: {
      department: 'Curriculum',
      permissions: ['EDIT_STUDENTS', 'MANAGE_SCHEDULE'],
      accessLimitations: 'Read-only billing access',
    },
  },
  {
    id: 'u21',
    firstName: 'Nina',
    lastName: 'Petrov',
    email: 'nina.petrov@example.com',
    role: 'ADMIN',
    timezone: 'Europe/Moscow',
    createdAt: '2024-01-05T08:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-19T10:00:00Z',
    adminStaffProfile: {
      department: 'Technology',
      permissions: ['VIEW_BILLING', 'EDIT_STUDENTS', 'VIEW_PAYROLL', 'MANAGE_SCHEDULE', 'SEND_COMMUNICATIONS'],
      accessLimitations: 'None',
    },
  },
  {
    id: 'u22',
    firstName: 'Tom',
    lastName: 'Nguyen',
    email: 'tom.nguyen@example.com',
    role: 'STUDENT',
    timezone: 'Asia/Ho_Chi_Minh',
    createdAt: '2024-11-01T09:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-20T06:00:00Z',
    studentProfile: {
      englishLevel: 'INTERMEDIATE',
      program: 'General English',
      assignedTeacherId: 'u16',
      classType: 'ONLINE',
      startDate: '2024-11-05',
      goals: 'Improve fluency for travel',
    },
  },
  {
    id: 'u23',
    firstName: 'Elena',
    lastName: 'Kovac',
    email: 'elena.kovac@example.com',
    role: 'STUDENT',
    timezone: 'Europe/Zagreb',
    createdAt: '2024-12-01T08:00:00Z',
    isActive: false,
    studentProfile: {
      englishLevel: 'PROFICIENCY',
      program: 'Executive English',
      classType: 'ONLINE',
      startDate: '2024-12-05',
      goals: 'Academic publication writing',
    },
  },
  {
    id: 'u24',
    firstName: 'Ben',
    lastName: 'Adeyemi',
    email: 'ben.adeyemi@example.com',
    role: 'STUDENT',
    timezone: 'Africa/Lagos',
    createdAt: '2025-01-10T08:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-16T15:00:00Z',
    studentProfile: {
      englishLevel: 'ELEMENTARY',
      program: 'General English',
      assignedTeacherId: 'u17',
      classType: 'ONLINE',
      startDate: '2025-01-15',
      goals: 'Everyday communication skills',
    },
  },
  {
    id: 'u25',
    firstName: 'Maya',
    lastName: 'Patel',
    email: 'maya.patel@example.com',
    role: 'STUDENT',
    timezone: 'Asia/Kolkata',
    createdAt: '2025-02-01T09:00:00Z',
    isActive: true,
    lastLoginAt: '2025-05-20T10:30:00Z',
    studentProfile: {
      englishLevel: 'UPPER_INTERMEDIATE',
      program: 'Business English',
      assignedTeacherId: 'u5',
      classType: 'ONLINE',
      startDate: '2025-02-05',
      goals: 'Job interview and networking skills',
      learningConcerns: 'Idioms and informal expressions',
    },
  },
  {
    id: 'u26',
    firstName: 'James',
    lastName: 'Reyes',
    email: 'teacher@tutorvio.com',
    role: 'TEACHER',
    timezone: 'Asia/Manila',
    createdAt: '2024-01-10T00:00:00Z',
    isActive: true,
    lastLoginAt: '2026-05-20T08:30:00Z',
    teacherProfile: {
      specialization: 'Business English, Executive Communication',
      availabilitySummary: 'Mon–Fri 8am–5pm PHT, Sat 9am–12pm PHT',
      internalStatus: 'ACTIVE',
      teachingNotes: 'Excellent with corporate professionals. Adapts lesson content to individual pace. Consistently high student satisfaction scores.',
      assignedStudentIds: ['u1'],
      documentStatus: 'APPROVED',
      contractStatus: 'APPROVED',
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

  function getUserByEmail(email: string): ManagedUser | undefined {
    return users.value.find(u => u.email.toLowerCase() === email.toLowerCase())
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
    getUserByEmail,
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
