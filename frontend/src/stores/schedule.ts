import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useAuthStore } from './auth'
import { useViewAs } from '@/composables/useViewAs'

// ---- Types ----

export type LessonStatus =
  | 'SCHEDULED'
  | 'IN_PROGRESS'
  | 'COMPLETED'
  | 'CANCELLED'
  | 'MISSED_BY_STUDENT'
  | 'MISSED_BY_TEACHER'
  | 'TRIAL'

export interface ScheduleLesson {
  id: string
  title: string
  teacherId: string
  teacherName: string
  studentId: string
  studentName: string
  startTime: string   // ISO 8601
  endTime: string     // ISO 8601
  status: LessonStatus
  meetingUrl?: string
  notes?: string
  isTrial: boolean
  isRecurring: boolean
  subject: string
  canReschedule: boolean
  canCancel: boolean
}

export interface AvailabilitySlot {
  id: string
  teacherId: string
  teacherName: string
  date: string        // YYYY-MM-DD
  startTime: string   // HH:MM
  endTime: string     // HH:MM
  isBooked: boolean
  bookedByStudentId?: string
  bookedByStudentName?: string
  isTrial: boolean
}

export type UnavailableReason = 'PERSONAL' | 'NATIONAL_HOLIDAY' | 'SICK' | 'VACATION'

export interface UnavailableDate {
  id: string
  teacherId: string
  date: string   // YYYY-MM-DD
  reason: UnavailableReason
  label: string
}

export interface WeeklyAvailabilitySlot {
  dayOfWeek: number  // 0=Sun, 1=Mon … 6=Sat
  hour: number       // 7–20
}

export interface TeacherAvailability {
  teacherId: string
  teacherName: string
  weeklySlots: WeeklyAvailabilitySlot[]
}

// ---- Helpers ----

function dt(dateStr: string, hour: number, minute = 0): string {
  return `${dateStr}T${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:00+08:00`
}

function makeLesson(
  id: string,
  teacherId: string,
  teacherName: string,
  studentId: string,
  studentName: string,
  dateStr: string,
  startHour: number,
  durationMin: number,
  status: LessonStatus,
  subject: string,
  options: Partial<ScheduleLesson> = {},
): ScheduleLesson {
  const start = new Date(dt(dateStr, startHour))
  const end = new Date(start.getTime() + durationMin * 60_000)
  const now = new Date()
  const isFuture = start > now
  const isTrial = status === 'TRIAL'

  return {
    id,
    title: isTrial ? `Trial: ${subject}` : subject,
    teacherId,
    teacherName,
    studentId,
    studentName,
    startTime: start.toISOString(),
    endTime: end.toISOString(),
    status,
    meetingUrl: isFuture || status === 'SCHEDULED' || status === 'IN_PROGRESS'
      ? `https://meet.tutorvio.com/room/${id}`
      : undefined,
    notes: undefined,
    subject,
    isTrial,
    isRecurring: !isTrial,
    canReschedule: (status === 'SCHEDULED' || status === 'TRIAL') && isFuture,
    canCancel: (status === 'SCHEDULED' || status === 'TRIAL') && isFuture,
    ...options,
  }
}

// ---- Mock Data ----
// Current date: May 22, 2026 (Friday)
// Teacher: u2 — James Reyes | Students: u1 Emma, u10 Lea, u11 Carlos, u12 Marco, u13 Anna

const MOCK_LESSONS_INITIAL: ScheduleLesson[] = [
  // --- This week: May 18–22 (past) ---
  makeLesson('l1', 'u2', 'James Reyes', 'u1', 'Emma Santos',
    '2026-05-18', 9, 60, 'COMPLETED', 'Business English',
    { notes: 'Great session. Focused on formal email writing and board-meeting phrases.' }),
  makeLesson('l2', 'u2', 'James Reyes', 'u10', 'Lea Mendoza',
    '2026-05-18', 14, 60, 'COMPLETED', 'General English'),
  makeLesson('l3', 'u2', 'James Reyes', 'u11', 'Carlos Rivera',
    '2026-05-19', 10, 60, 'MISSED_BY_STUDENT', 'IELTS Preparation',
    { notes: 'Student did not join the call. Reminder sent.' }),
  makeLesson('l4', 'u2', 'James Reyes', 'u12', 'Marco Tan',
    '2026-05-20', 9, 60, 'COMPLETED', 'Conversational English'),
  makeLesson('l5', 'u2', 'James Reyes', 'u1', 'Emma Santos',
    '2026-05-21', 9, 60, 'COMPLETED', 'Business English',
    { notes: 'Practiced presentation skills. Significant improvement in confidence.' }),
  makeLesson('l6', 'u2', 'James Reyes', 'u10', 'Lea Mendoza',
    '2026-05-21', 14, 60, 'CANCELLED', 'General English',
    { notes: 'Cancelled by student — family emergency.' }),
  // Today: May 22
  makeLesson('l7', 'u2', 'James Reyes', 'u1', 'Emma Santos',
    '2026-05-22', 9, 60, 'SCHEDULED', 'Business English'),

  // --- Next week: May 25–29 ---
  makeLesson('l8', 'u2', 'James Reyes', 'u1', 'Emma Santos',
    '2026-05-25', 9, 60, 'SCHEDULED', 'Business English'),
  makeLesson('l9', 'u2', 'James Reyes', 'u13', 'Anna Kim',
    '2026-05-26', 9, 30, 'TRIAL', 'General English'),
  makeLesson('l10', 'u2', 'James Reyes', 'u10', 'Lea Mendoza',
    '2026-05-27', 14, 60, 'SCHEDULED', 'General English'),
  makeLesson('l11', 'u2', 'James Reyes', 'u11', 'Carlos Rivera',
    '2026-05-28', 9, 60, 'SCHEDULED', 'IELTS Preparation'),
  makeLesson('l12', 'u2', 'James Reyes', 'u1', 'Emma Santos',
    '2026-05-29', 9, 60, 'SCHEDULED', 'Business English'),

  // --- Week after: June 1–5 ---
  makeLesson('l13', 'u2', 'James Reyes', 'u1', 'Emma Santos',
    '2026-06-01', 9, 60, 'SCHEDULED', 'Business English'),
  makeLesson('l14', 'u2', 'James Reyes', 'u10', 'Lea Mendoza',
    '2026-06-03', 14, 60, 'SCHEDULED', 'General English'),
  makeLesson('l15', 'u2', 'James Reyes', 'u12', 'Marco Tan',
    '2026-06-04', 9, 60, 'SCHEDULED', 'Conversational English'),
  makeLesson('l16', 'u2', 'James Reyes', 'u1', 'Emma Santos',
    '2026-06-05', 9, 60, 'SCHEDULED', 'Business English'),
]

const T = 'James Reyes'
const U2 = 'u2'
function sl(id: string, date: string, st: string, et: string, booked = false, sid?: string, sn?: string, trial = false): AvailabilitySlot {
  return { id, teacherId: U2, teacherName: T, date, startTime: st, endTime: et, isBooked: booked, bookedByStudentId: sid, bookedByStudentName: sn, isTrial: trial }
}

const MOCK_AVAILABILITY_INITIAL: AvailabilitySlot[] = [
  // ── May 18 Mon (current week) ──
  sl('av_0518a', '2026-05-18', '09:00', '10:00', true,  'u1',  'Emma Santos'),
  sl('av_0518b', '2026-05-18', '10:00', '11:00'),
  sl('av_0518c', '2026-05-18', '14:00', '15:00', true,  'u10', 'Lea Mendoza'),
  sl('av_0518d', '2026-05-18', '15:00', '16:00'),
  // ── May 19 Tue ──
  sl('av_0519a', '2026-05-19', '09:00', '10:00'),
  sl('av_0519b', '2026-05-19', '10:00', '11:00', true,  'u11', 'Carlos Rivera'),
  sl('av_0519c', '2026-05-19', '14:00', '15:00'),
  // ── May 20 Wed ──
  sl('av_0520a', '2026-05-20', '09:00', '10:00', true,  'u12', 'Marco Tan'),
  sl('av_0520b', '2026-05-20', '10:00', '11:00'),
  sl('av_0520c', '2026-05-20', '14:00', '15:00'),
  // ── May 21 Thu ──
  sl('av_0521a', '2026-05-21', '09:00', '10:00', true,  'u1',  'Emma Santos'),
  sl('av_0521b', '2026-05-21', '14:00', '15:00', true,  'u10', 'Lea Mendoza'),
  // ── May 22 Fri (today) ──
  sl('av_0522a', '2026-05-22', '09:00', '10:00', true,  'u1',  'Emma Santos'),
  sl('av_0522b', '2026-05-22', '10:00', '11:00'),
  sl('av_0522c', '2026-05-22', '14:00', '15:00'),
  sl('av_0522d', '2026-05-22', '15:00', '16:00'),
  // ── May 25 Mon ──
  sl('av1',      '2026-05-25', '09:00', '10:00', true,  'u1',  'Emma Santos'),
  sl('av_0525b', '2026-05-25', '10:00', '11:00'),
  sl('av2',      '2026-05-25', '14:00', '15:00'),
  // ── May 26 Tue ──
  sl('av_0526a', '2026-05-26', '09:00', '10:00', true,  'u13', 'Anna Kim', true),
  sl('av3',      '2026-05-26', '10:00', '11:00'),
  sl('av4',      '2026-05-26', '14:00', '15:00'),
  // ── May 27 Wed ──
  sl('av5',      '2026-05-27', '09:00', '10:00'),
  sl('av_0527b', '2026-05-27', '14:00', '15:00', true,  'u10', 'Lea Mendoza'),
  // ── May 28 Thu ──
  sl('av_0528a', '2026-05-28', '09:00', '10:00', true,  'u11', 'Carlos Rivera'),
  sl('av6',      '2026-05-28', '14:00', '15:00'),
  // ── May 29 Fri ──
  sl('av_0529a', '2026-05-29', '09:00', '10:00', true,  'u1',  'Emma Santos'),
  sl('av7',      '2026-05-29', '10:00', '11:00'),
  sl('av_0529c', '2026-05-29', '14:00', '15:00'),
  // ── June 1 Mon ──
  sl('av_0601a', '2026-06-01', '09:00', '10:00', true,  'u1',  'Emma Santos'),
  sl('av8',      '2026-06-01', '10:00', '11:00'),
  sl('av9',      '2026-06-01', '14:00', '15:00'),
  // ── June 2 Tue ──
  sl('av10',     '2026-06-02', '09:00', '10:00'),
  sl('av11',     '2026-06-02', '10:00', '11:00'),
  sl('av12',     '2026-06-02', '14:00', '15:00'),
  // ── June 3 Wed ──
  sl('av13',     '2026-06-03', '09:00', '10:00'),
  sl('av_0603b', '2026-06-03', '14:00', '15:00', true,  'u10', 'Lea Mendoza'),
  // ── June 4 Thu ──
  sl('av_0604a', '2026-06-04', '09:00', '10:00', true,  'u12', 'Marco Tan'),
  sl('av14',     '2026-06-04', '14:00', '15:00'),
  // ── June 5 Fri ──
  sl('av_0605a', '2026-06-05', '09:00', '10:00', true,  'u1',  'Emma Santos'),
  sl('av15',     '2026-06-05', '10:00', '11:00'),
  sl('av_0605c', '2026-06-05', '14:00', '15:00'),
]

const MOCK_UNAVAILABLE_INITIAL: UnavailableDate[] = [
  { id: 'ud1', teacherId: 'u2', date: '2026-05-30', reason: 'PERSONAL',         label: 'Personal day off' },
  { id: 'ud2', teacherId: 'u2', date: '2026-06-12', reason: 'NATIONAL_HOLIDAY', label: 'Independence Day (PH)' },
]

const MOCK_TEACHER_AVAILABILITY_INITIAL: TeacherAvailability = {
  teacherId: 'u2',
  teacherName: 'James Reyes',
  weeklySlots: [
    { dayOfWeek: 1, hour: 9  },
    { dayOfWeek: 1, hour: 10 },
    { dayOfWeek: 1, hour: 14 },
    { dayOfWeek: 2, hour: 9  },
    { dayOfWeek: 2, hour: 10 },
    { dayOfWeek: 2, hour: 14 },
    { dayOfWeek: 3, hour: 9  },
    { dayOfWeek: 3, hour: 14 },
    { dayOfWeek: 4, hour: 9  },
    { dayOfWeek: 4, hour: 14 },
    { dayOfWeek: 5, hour: 9  },
    { dayOfWeek: 5, hour: 10 },
  ],
}

// ---- Store ----

export const useScheduleStore = defineStore('schedule', () => {
  const auth = useAuthStore()
  const { effectiveRole } = useViewAs()

  const lessons           = ref<ScheduleLesson[]>(MOCK_LESSONS_INITIAL.map(l => ({ ...l })))
  const availabilitySlots = ref<AvailabilitySlot[]>(MOCK_AVAILABILITY_INITIAL.map(s => ({ ...s })))
  const unavailableDates  = ref<UnavailableDate[]>(MOCK_UNAVAILABLE_INITIAL.map(d => ({ ...d })))
  const teacherAvailability = ref<TeacherAvailability>({
    ...MOCK_TEACHER_AVAILABILITY_INITIAL,
    weeklySlots: [...MOCK_TEACHER_AVAILABILITY_INITIAL.weeklySlots],
  })

  // Admin/Staff teacher filter
  const selectedTeacherId = ref<string | null>(null)

  // ---- Computed filtered views ----

  const myLessons = computed((): ScheduleLesson[] => {
    const role   = effectiveRole.value
    const userId = auth.user?.id
    switch (role) {
      case 'STUDENT': return lessons.value.filter(l => l.studentId === userId)
      case 'TEACHER': return lessons.value.filter(l => l.teacherId === userId)
      case 'ADMIN':
      case 'STAFF':
        return selectedTeacherId.value
          ? lessons.value.filter(l => l.teacherId === selectedTeacherId.value)
          : lessons.value
      default: return lessons.value
    }
  })

  const myAvailabilitySlots = computed((): AvailabilitySlot[] => {
    const role   = effectiveRole.value
    const userId = auth.user?.id
    switch (role) {
      case 'STUDENT':
        // Student sees open slots from all teachers (their assigned teacher in real app)
        return availabilitySlots.value.filter(s => !s.isBooked)
      case 'TEACHER':
        return availabilitySlots.value.filter(s => s.teacherId === userId)
      case 'ADMIN':
      case 'STAFF':
        return selectedTeacherId.value
          ? availabilitySlots.value.filter(s => s.teacherId === selectedTeacherId.value)
          : availabilitySlots.value
      default: return availabilitySlots.value
    }
  })

  const myUnavailableDates = computed((): UnavailableDate[] => {
    const role   = effectiveRole.value
    const userId = auth.user?.id
    switch (role) {
      case 'TEACHER': return unavailableDates.value.filter(d => d.teacherId === userId)
      case 'ADMIN':
      case 'STAFF':
        return selectedTeacherId.value
          ? unavailableDates.value.filter(d => d.teacherId === selectedTeacherId.value)
          : unavailableDates.value
      default: return unavailableDates.value
    }
  })

  // ---- Actions ----

  function bookSlot(
    slotId: string,
    studentId: string,
    studentName: string,
    subject: string,
    isTrial: boolean,
  ): ScheduleLesson | null {
    const slot = availabilitySlots.value.find(s => s.id === slotId)
    if (!slot || slot.isBooked) return null

    slot.isBooked           = true
    slot.bookedByStudentId  = studentId
    slot.bookedByStudentName = studentName
    slot.isTrial            = isTrial

    const durationMin  = isTrial ? 30 : 60
    const startISO     = `${slot.date}T${slot.startTime}:00+08:00`
    const startDate    = new Date(startISO)
    const endDate      = new Date(startDate.getTime() + durationMin * 60_000)
    const newId        = `l${Date.now()}`

    const newLesson: ScheduleLesson = {
      id:          newId,
      title:       isTrial ? `Trial: ${subject}` : subject,
      teacherId:   slot.teacherId,
      teacherName: slot.teacherName,
      studentId,
      studentName,
      startTime:   startDate.toISOString(),
      endTime:     endDate.toISOString(),
      status:      isTrial ? 'TRIAL' : 'SCHEDULED',
      meetingUrl:  `https://meet.tutorvio.com/room/${newId}`,
      subject,
      isTrial,
      isRecurring: false,
      canReschedule: true,
      canCancel:     true,
    }

    lessons.value.push(newLesson)
    return newLesson
  }

  function cancelLesson(lessonId: string): void {
    const lesson = lessons.value.find(l => l.id === lessonId)
    if (!lesson) return
    lesson.status        = 'CANCELLED'
    lesson.canReschedule = false
    lesson.canCancel     = false

    // Free the availability slot if it exists
    const slotDate  = new Date(lesson.startTime).toLocaleDateString('sv-SE') // YYYY-MM-DD
    const slotStart = lesson.startTime.slice(11, 16)
    const slot = availabilitySlots.value.find(
      s => s.date === slotDate && s.startTime === slotStart && s.bookedByStudentId === lesson.studentId,
    )
    if (slot) {
      slot.isBooked            = false
      slot.bookedByStudentId   = undefined
      slot.bookedByStudentName = undefined
    }
  }

  function rescheduleLesson(lessonId: string, newStartTime: string, newEndTime: string): void {
    const lesson = lessons.value.find(l => l.id === lessonId)
    if (!lesson) return
    lesson.startTime     = newStartTime
    lesson.endTime       = newEndTime
    lesson.status        = 'SCHEDULED'
    lesson.canReschedule = true
    lesson.canCancel     = true
  }

  function addWeeklySlot(teacherId: string, dayOfWeek: number, hour: number): void {
    if (teacherAvailability.value.teacherId !== teacherId) return
    const exists = teacherAvailability.value.weeklySlots.some(
      s => s.dayOfWeek === dayOfWeek && s.hour === hour,
    )
    if (!exists) teacherAvailability.value.weeklySlots.push({ dayOfWeek, hour })
  }

  function removeWeeklySlot(teacherId: string, dayOfWeek: number, hour: number): void {
    if (teacherAvailability.value.teacherId !== teacherId) return
    teacherAvailability.value.weeklySlots = teacherAvailability.value.weeklySlots.filter(
      s => !(s.dayOfWeek === dayOfWeek && s.hour === hour),
    )
  }

  function addUnavailableDate(teacherId: string, date: string, reason: UnavailableReason, label: string): void {
    unavailableDates.value.push({ id: `ud${Date.now()}`, teacherId, date, reason, label })
  }

  function removeUnavailableDate(id: string): void {
    unavailableDates.value = unavailableDates.value.filter(d => d.id !== id)
  }

  return {
    lessons,
    availabilitySlots,
    unavailableDates,
    teacherAvailability,
    selectedTeacherId,
    myLessons,
    myAvailabilitySlots,
    myUnavailableDates,
    bookSlot,
    cancelLesson,
    rescheduleLesson,
    addWeeklySlot,
    removeWeeklySlot,
    addUnavailableDate,
    removeUnavailableDate,
  }
})
