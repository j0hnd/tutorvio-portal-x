/* ============================================================
   Tutorvio Global TypeScript Types & Interfaces
   ============================================================ */

/* --- User & Auth --- */
export type UserRole = 'STUDENT' | 'TEACHER' | 'ADMIN' | 'STAFF'

export interface User {
  id: string
  firstName: string
  lastName: string
  email: string
  role: UserRole
  avatarUrl?: string
  timezone: string
  createdAt: string
  isActive?: boolean
}

export interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
}

/* --- Lesson & Scheduling --- */
export type LessonStatus =
  | 'SCHEDULED'
  | 'IN_PROGRESS'
  | 'COMPLETED'
  | 'CANCELLED'
  | 'MISSED_BY_STUDENT'
  | 'MISSED_BY_TEACHER'
  | 'TRIAL'

export interface Lesson {
  id: string
  title: string
  teacherId: string
  studentId: string
  startTime: string
  endTime: string
  status: LessonStatus
  meetingUrl?: string
  notes?: string
  timezone: string
}

export interface TimeSlot {
  id: string
  teacherId: string
  startTime: string
  endTime: string
  isBooked: boolean
  isTrial: boolean
}

/* --- Materials & Homework --- */
export type MaterialType = 'PDF' | 'VIDEO' | 'DOCUMENT' | 'LINK' | 'IMAGE'

export interface Material {
  id: string
  title: string
  type: MaterialType
  url: string
  uploadedBy: string
  courseId?: string
  lessonId?: string
  createdAt: string
}

export interface HomeworkAssignment {
  id: string
  title: string
  description: string
  lessonId: string
  dueDate: string
  studentId: string
  status: 'PENDING' | 'SUBMITTED' | 'REVIEWED'
  submissionUrl?: string
  feedback?: string
}

/* --- Billing & Credits --- */
export type PackageStatus = 'ACTIVE' | 'EXPIRED' | 'PAUSED'

export interface Package {
  id: string
  name: string
  totalCredits: number
  price: number
  currency: string
  durationDays: number
}

export interface StudentSubscription {
  id: string
  studentId: string
  packageId: string
  status: PackageStatus
  remainingCredits: number
  expiresAt: string
  startedAt: string
}

/* --- Payroll --- */
export interface PayrollRecord {
  id: string
  teacherId: string
  periodStart: string
  periodEnd: string
  completedLessons: number
  ratePerLesson: number
  totalEarnings: number
  status: 'PENDING' | 'PAID'
}

/* --- UI Utilities --- */
export type BadgeVariant =
  | 'scheduled'
  | 'completed'
  | 'cancelled'
  | 'missed'
  | 'trial'
  | 'active'
  | 'pending'
  | 'warning'
  | 'info'
  | 'neutral'

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success'
export type ButtonSize = 'sm' | 'md' | 'lg'

export type CardVariant = 'default' | 'glass' | 'outlined' | 'elevated'

export interface SelectOption {
  value: string | number
  label: string
  disabled?: boolean
}

/* --- Navigation --- */
export interface NavItem {
  label: string
  path: string
  icon: string
  roles: UserRole[]
  children?: NavItem[]
}
