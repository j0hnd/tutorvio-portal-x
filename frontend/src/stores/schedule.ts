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
  | 'RESCHEDULED'
  | 'PENDING_CONFIRMATION'

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
    meetingUrl: isFuture || status === 'IN_PROGRESS'
      ? `https://meet.tutorvio.com/room/${id}`
      : undefined,
    notes: undefined,
    subject,
    isTrial,
    isRecurring: !isTrial,
    canReschedule: (['SCHEDULED', 'TRIAL', 'RESCHEDULED'] as LessonStatus[]).includes(status) && isFuture,
    canCancel: (['SCHEDULED', 'TRIAL', 'RESCHEDULED', 'PENDING_CONFIRMATION'] as LessonStatus[]).includes(status) && isFuture,
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

  // ── Sarah Lim (u6) ──
  makeLesson('ls1', 'u6', 'Sarah Lim', 'u14', 'Sofia Cruz',
    '2026-05-18', 10, 60, 'COMPLETED', 'IELTS Preparation'),
  makeLesson('ls2', 'u6', 'Sarah Lim', 'u15', 'Juan Ramos',
    '2026-05-19', 9, 60, 'COMPLETED', 'Academic English'),
  makeLesson('ls3', 'u6', 'Sarah Lim', 'u16', 'Grace Park',
    '2026-05-20', 10, 60, 'CANCELLED', 'IELTS Preparation',
    { notes: 'Student cancelled 2 hours before session.' }),
  makeLesson('ls4', 'u6', 'Sarah Lim', 'u14', 'Sofia Cruz',
    '2026-05-22', 9, 60, 'PENDING_CONFIRMATION', 'Academic English',
    { isRecurring: false }),
  makeLesson('ls5', 'u6', 'Sarah Lim', 'u15', 'Juan Ramos',
    '2026-05-25', 10, 60, 'SCHEDULED', 'IELTS Preparation'),
  makeLesson('ls6', 'u6', 'Sarah Lim', 'u16', 'Grace Park',
    '2026-05-26', 9, 60, 'RESCHEDULED', 'Academic Writing',
    { notes: 'Moved from May 24 at student request.' }),
  makeLesson('ls7', 'u6', 'Sarah Lim', 'u14', 'Sofia Cruz',
    '2026-05-27', 10, 30, 'TRIAL', 'IELTS Preparation'),
  makeLesson('ls8', 'u6', 'Sarah Lim', 'u15', 'Juan Ramos',
    '2026-05-28', 10, 60, 'SCHEDULED', 'Academic English'),
  makeLesson('ls9', 'u6', 'Sarah Lim', 'u16', 'Grace Park',
    '2026-05-29', 9, 60, 'SCHEDULED', 'IELTS Preparation'),
  makeLesson('ls10', 'u6', 'Sarah Lim', 'u14', 'Sofia Cruz',
    '2026-06-01', 9, 60, 'SCHEDULED', 'Academic English'),
  makeLesson('ls11', 'u6', 'Sarah Lim', 'u15', 'Juan Ramos',
    '2026-06-03', 10, 60, 'SCHEDULED', 'IELTS Preparation'),

  // ── Miguel Santos (u7) ──
  makeLesson('lm1', 'u7', 'Miguel Santos', 'u17', 'Rico Valdez',
    '2026-05-18', 9, 60, 'COMPLETED', 'Business English'),
  makeLesson('lm2', 'u7', 'Miguel Santos', 'u18', 'Lia Torres',
    '2026-05-19', 10, 60, 'MISSED_BY_STUDENT', 'Conversational English',
    { notes: 'Student did not show. Waiting for response.' }),
  makeLesson('lm3', 'u7', 'Miguel Santos', 'u17', 'Rico Valdez',
    '2026-05-20', 9, 60, 'COMPLETED', 'Business English'),
  makeLesson('lm4', 'u7', 'Miguel Santos', 'u18', 'Lia Torres',
    '2026-05-21', 10, 60, 'COMPLETED', 'Conversational English'),
  makeLesson('lm5', 'u7', 'Miguel Santos', 'u17', 'Rico Valdez',
    '2026-05-22', 9, 60, 'SCHEDULED', 'Business English'),
  makeLesson('lm6', 'u7', 'Miguel Santos', 'u18', 'Lia Torres',
    '2026-05-25', 9, 60, 'RESCHEDULED', 'Conversational English',
    { notes: 'Moved from May 23 due to teacher conflict.' }),
  makeLesson('lm7', 'u7', 'Miguel Santos', 'u17', 'Rico Valdez',
    '2026-05-26', 10, 60, 'PENDING_CONFIRMATION', 'Business English',
    { isRecurring: false }),
  makeLesson('lm8', 'u7', 'Miguel Santos', 'u18', 'Lia Torres',
    '2026-05-27', 9, 60, 'SCHEDULED', 'Conversational English'),
  makeLesson('lm9', 'u7', 'Miguel Santos', 'u17', 'Rico Valdez',
    '2026-05-28', 10, 60, 'SCHEDULED', 'Business English'),
  makeLesson('lm10', 'u7', 'Miguel Santos', 'u18', 'Lia Torres',
    '2026-05-29', 9, 60, 'SCHEDULED', 'Conversational English'),
  makeLesson('lm11', 'u7', 'Miguel Santos', 'u17', 'Rico Valdez',
    '2026-06-01', 9, 60, 'SCHEDULED', 'Business English'),
  makeLesson('lm12', 'u7', 'Miguel Santos', 'u18', 'Lia Torres',
    '2026-06-02', 10, 60, 'SCHEDULED', 'Conversational English'),
]

const MOCK_TEACHER_AVAILABILITIES_INITIAL: Record<string, TeacherAvailability> = {
  u2: {
    teacherId: 'u2',
    teacherName: 'James Reyes',
    weeklySlots: [
      { dayOfWeek: 1, hour: 9 }, { dayOfWeek: 1, hour: 10 }, { dayOfWeek: 1, hour: 14 },
      { dayOfWeek: 2, hour: 9 }, { dayOfWeek: 2, hour: 10 }, { dayOfWeek: 2, hour: 14 },
      { dayOfWeek: 3, hour: 9 }, { dayOfWeek: 3, hour: 14 },
      { dayOfWeek: 4, hour: 9 }, { dayOfWeek: 4, hour: 14 },
      { dayOfWeek: 5, hour: 9 }, { dayOfWeek: 5, hour: 10 },
    ],
  },
  u6: {
    teacherId: 'u6',
    teacherName: 'Sarah Lim',
    weeklySlots: [
      { dayOfWeek: 1, hour: 9 }, { dayOfWeek: 1, hour: 10 }, { dayOfWeek: 1, hour: 14 },
      { dayOfWeek: 2, hour: 9 }, { dayOfWeek: 2, hour: 11 }, { dayOfWeek: 2, hour: 14 },
      { dayOfWeek: 3, hour: 9 }, { dayOfWeek: 3, hour: 10 },
      { dayOfWeek: 4, hour: 10 }, { dayOfWeek: 4, hour: 14 },
      { dayOfWeek: 5, hour: 9 }, { dayOfWeek: 5, hour: 11 },
    ],
  },
  u7: {
    teacherId: 'u7',
    teacherName: 'Miguel Santos',
    weeklySlots: [
      { dayOfWeek: 1, hour: 9 }, { dayOfWeek: 1, hour: 15 },
      { dayOfWeek: 2, hour: 9 }, { dayOfWeek: 2, hour: 10 }, { dayOfWeek: 2, hour: 14 },
      { dayOfWeek: 3, hour: 9 }, { dayOfWeek: 3, hour: 14 },
      { dayOfWeek: 4, hour: 9 }, { dayOfWeek: 4, hour: 10 },
      { dayOfWeek: 5, hour: 9 }, { dayOfWeek: 5, hour: 14 }, { dayOfWeek: 5, hour: 15 },
    ],
  },
}

type BookingInfo = { sid: string; sn: string; trial: boolean } | null

const SLOT_BOOKINGS: Record<string, BookingInfo> = {
  // ── James Reyes (u2) ──
  'u2_2026-05-18_9': { sid: 'u1', sn: 'Emma Santos', trial: false },
  'u2_2026-05-18_14': { sid: 'u10', sn: 'Lea Mendoza', trial: false },
  'u2_2026-05-19_10': { sid: 'u11', sn: 'Carlos Rivera', trial: false },
  'u2_2026-05-20_9': { sid: 'u12', sn: 'Marco Tan', trial: false },
  'u2_2026-05-21_9': { sid: 'u1', sn: 'Emma Santos', trial: false },
  'u2_2026-05-21_14': null,
  'u2_2026-05-22_9': { sid: 'u1', sn: 'Emma Santos', trial: false },
  'u2_2026-05-25_9': { sid: 'u1', sn: 'Emma Santos', trial: false },
  'u2_2026-05-26_9': { sid: 'u13', sn: 'Anna Kim', trial: true },
  'u2_2026-05-27_14': { sid: 'u10', sn: 'Lea Mendoza', trial: false },
  'u2_2026-05-28_9': { sid: 'u11', sn: 'Carlos Rivera', trial: false },
  'u2_2026-05-29_9': { sid: 'u1', sn: 'Emma Santos', trial: false },
  'u2_2026-06-01_9': { sid: 'u1', sn: 'Emma Santos', trial: false },
  'u2_2026-06-03_14': { sid: 'u10', sn: 'Lea Mendoza', trial: false },
  'u2_2026-06-04_9': { sid: 'u12', sn: 'Marco Tan', trial: false },
  'u2_2026-06-05_9': { sid: 'u1', sn: 'Emma Santos', trial: false },
  // ── Sarah Lim (u6) ──
  'u6_2026-05-18_10': { sid: 'u14', sn: 'Sofia Cruz', trial: false },
  'u6_2026-05-19_9': { sid: 'u15', sn: 'Juan Ramos', trial: false },
  'u6_2026-05-20_10': null,
  'u6_2026-05-22_9': { sid: 'u14', sn: 'Sofia Cruz', trial: false },
  'u6_2026-05-25_10': { sid: 'u15', sn: 'Juan Ramos', trial: false },
  'u6_2026-05-26_9': { sid: 'u16', sn: 'Grace Park', trial: false },
  'u6_2026-05-27_10': { sid: 'u14', sn: 'Sofia Cruz', trial: true },
  'u6_2026-05-28_10': { sid: 'u15', sn: 'Juan Ramos', trial: false },
  'u6_2026-05-29_9': { sid: 'u16', sn: 'Grace Park', trial: false },
  'u6_2026-06-01_9': { sid: 'u14', sn: 'Sofia Cruz', trial: false },
  'u6_2026-06-03_10': { sid: 'u15', sn: 'Juan Ramos', trial: false },
  // ── Miguel Santos (u7) ──
  'u7_2026-05-18_9': { sid: 'u17', sn: 'Rico Valdez', trial: false },
  'u7_2026-05-19_10': { sid: 'u18', sn: 'Lia Torres', trial: false },
  'u7_2026-05-20_9': { sid: 'u17', sn: 'Rico Valdez', trial: false },
  'u7_2026-05-21_10': { sid: 'u18', sn: 'Lia Torres', trial: false },
  'u7_2026-05-22_9': { sid: 'u17', sn: 'Rico Valdez', trial: false },
  'u7_2026-05-25_9': { sid: 'u18', sn: 'Lia Torres', trial: false },
  'u7_2026-05-26_10': { sid: 'u17', sn: 'Rico Valdez', trial: false },
  'u7_2026-05-27_9': { sid: 'u18', sn: 'Lia Torres', trial: false },
  'u7_2026-05-28_10': { sid: 'u17', sn: 'Rico Valdez', trial: false },
  'u7_2026-05-29_9': { sid: 'u18', sn: 'Lia Torres', trial: false },
  'u7_2026-06-01_9': { sid: 'u17', sn: 'Rico Valdez', trial: false },
  'u7_2026-06-02_10': { sid: 'u18', sn: 'Lia Torres', trial: false },
}

const TEACHERS_META: Array<{ id: string; name: string }> = [
  { id: 'u2', name: 'James Reyes' },
  { id: 'u6', name: 'Sarah Lim' },
  { id: 'u7', name: 'Miguel Santos' },
]

function generateAvailability(): AvailabilitySlot[] {
  const slots: AvailabilitySlot[] = []
  const start = new Date('2026-05-01')
  const end = new Date('2026-06-30')
  for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    const dateStr = d.toLocaleDateString('sv-SE')
    const dow = d.getDay()
    for (const teacher of TEACHERS_META) {
      const weekly = MOCK_TEACHER_AVAILABILITIES_INITIAL[teacher.id]?.weeklySlots ?? []
      for (const ws of weekly.filter(w => w.dayOfWeek === dow)) {
        const key = `${teacher.id}_${dateStr}_${ws.hour}`
        const info = SLOT_BOOKINGS[key]
        const isBooked = info != null
        const st = `${String(ws.hour).padStart(2, '0')}:00`
        const et = `${String(ws.hour + 1).padStart(2, '0')}:00`
        slots.push({
          id: `av_${teacher.id}_${dateStr.replace(/-/g, '')}_${ws.hour}`,
          teacherId: teacher.id,
          teacherName: teacher.name,
          date: dateStr,
          startTime: st,
          endTime: et,
          isBooked,
          bookedByStudentId: isBooked ? info!.sid : undefined,
          bookedByStudentName: isBooked ? info!.sn : undefined,
          isTrial: isBooked ? info!.trial : false,
        })
      }
    }
  }
  return slots
}

const MOCK_AVAILABILITY_INITIAL = generateAvailability()

const MOCK_UNAVAILABLE_INITIAL: UnavailableDate[] = [
  { id: 'ud2', teacherId: 'u2', date: '2026-06-12', reason: 'NATIONAL_HOLIDAY', label: 'Independence Day (PH)' },
]

// ---- Store ----

export const useScheduleStore = defineStore('schedule', () => {
  const auth = useAuthStore()
  const { effectiveRole } = useViewAs()

  const lessons = ref<ScheduleLesson[]>(MOCK_LESSONS_INITIAL.map(l => ({ ...l })))
  const availabilitySlots = ref<AvailabilitySlot[]>(MOCK_AVAILABILITY_INITIAL.map(s => ({ ...s })))
  const unavailableDates = ref<UnavailableDate[]>(MOCK_UNAVAILABLE_INITIAL.map(d => ({ ...d })))
  const teacherAvailabilities = ref<Record<string, TeacherAvailability>>(
    Object.fromEntries(
      Object.entries(MOCK_TEACHER_AVAILABILITIES_INITIAL).map(([k, v]) => [
        k, { ...v, weeklySlots: [...v.weeklySlots] },
      ]),
    ),
  )

  // Admin/Staff teacher filter
  const selectedTeacherId = ref<string | null>(null)

  // ---- Computed filtered views ----

  const myLessons = computed((): ScheduleLesson[] => {
    const role = effectiveRole.value
    const userId = auth.user?.id
    switch (role) {
      case 'STUDENT': {
        const base = userId ? lessons.value.filter(l => l.studentId === userId) : []
        return selectedTeacherId.value ? base.filter(l => l.teacherId === selectedTeacherId.value) : base
      }
      case 'TEACHER': {
        // Admin/Staff impersonating teacher role: respect selectedTeacherId filter
        if (auth.user?.role !== 'TEACHER') {
          return selectedTeacherId.value
            ? lessons.value.filter(l => l.teacherId === selectedTeacherId.value)
            : lessons.value
        }
        return userId ? lessons.value.filter(l => l.teacherId === userId) : []
      }
      case 'ADMIN':
      case 'STAFF':
        return selectedTeacherId.value
          ? lessons.value.filter(l => l.teacherId === selectedTeacherId.value)
          : lessons.value
      default: return []
    }
  })

  const myAvailabilitySlots = computed((): AvailabilitySlot[] => {
    const role = effectiveRole.value
    const userId = auth.user?.id
    const tz = auth.user?.timezone ?? 'Asia/Manila'
    const tzNow = new Date(new Date().toLocaleString('en-US', { timeZone: tz }))
    const tzToday = new Date().toLocaleDateString('sv-SE', { timeZone: tz })
    const nowMins = tzNow.getHours() * 60 + tzNow.getMinutes()

    // Past open slots are no longer visible — keep booked ones for history
    const visible = availabilitySlots.value.filter(s => s.isBooked || s.date >= tzToday)

    function isPastSlot(s: AvailabilitySlot): boolean {
      if (s.date > tzToday) return false
      if (s.date < tzToday) return true
      const [h, m] = s.startTime.split(':').map(Number)
      return (h * 60 + m) <= nowMins
    }

    switch (role) {
      case 'STUDENT': {
        const openSlots = visible.filter(s => !s.isBooked && !isPastSlot(s))
        return selectedTeacherId.value ? openSlots.filter(s => s.teacherId === selectedTeacherId.value) : openSlots
      }
      case 'TEACHER': {
        if (auth.user?.role !== 'TEACHER') {
          // Admin impersonating teacher: respect selectedTeacherId filter, hide past open slots
          const base = visible.filter(s => s.isBooked || !isPastSlot(s))
          return selectedTeacherId.value ? base.filter(s => s.teacherId === selectedTeacherId.value) : base
        }
        return visible.filter(s => s.teacherId === userId && (s.isBooked || !isPastSlot(s)))
      }
      case 'ADMIN':
      case 'STAFF':
        return selectedTeacherId.value
          ? visible.filter(s => s.teacherId === selectedTeacherId.value)
          : visible
      default: return visible
    }
  })

  const myUnavailableDates = computed((): UnavailableDate[] => {
    const role = effectiveRole.value
    const userId = auth.user?.id
    switch (role) {
      case 'TEACHER':
        if (auth.user?.role !== 'TEACHER') {
          return selectedTeacherId.value
            ? unavailableDates.value.filter(d => d.teacherId === selectedTeacherId.value)
            : unavailableDates.value
        }
        return unavailableDates.value.filter(d => d.teacherId === userId)
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

    slot.isBooked = true
    slot.bookedByStudentId = studentId
    slot.bookedByStudentName = studentName
    slot.isTrial = isTrial

    const durationMin = isTrial ? 30 : 60
    const startISO = `${slot.date}T${slot.startTime}:00+08:00`
    const startDate = new Date(startISO)
    const endDate = new Date(startDate.getTime() + durationMin * 60_000)
    const newId = `l${Date.now()}`

    const newLesson: ScheduleLesson = {
      id: newId,
      title: isTrial ? `Trial: ${subject}` : subject,
      teacherId: slot.teacherId,
      teacherName: slot.teacherName,
      studentId,
      studentName,
      startTime: startDate.toISOString(),
      endTime: endDate.toISOString(),
      status: isTrial ? 'TRIAL' : 'SCHEDULED',
      meetingUrl: `https://meet.tutorvio.com/room/${newId}`,
      subject,
      isTrial,
      isRecurring: false,
      canReschedule: true,
      canCancel: true,
    }

    lessons.value.push(newLesson)
    return newLesson
  }

  function cancelLesson(lessonId: string): void {
    const lesson = lessons.value.find(l => l.id === lessonId)
    if (!lesson) return
    lesson.status = 'CANCELLED'
    lesson.canReschedule = false
    lesson.canCancel = false

    // Free the availability slot if it exists
    const slotDate = new Date(lesson.startTime).toLocaleDateString('sv-SE') // YYYY-MM-DD
    const slotStart = lesson.startTime.slice(11, 16)
    const slot = availabilitySlots.value.find(
      s => s.date === slotDate && s.startTime === slotStart && s.bookedByStudentId === lesson.studentId,
    )
    if (slot) {
      slot.isBooked = false
      slot.bookedByStudentId = undefined
      slot.bookedByStudentName = undefined
    }
  }

  function rescheduleLesson(lessonId: string, newStartTime: string, newEndTime: string): void {
    const lesson = lessons.value.find(l => l.id === lessonId)
    if (!lesson) return
    lesson.startTime = newStartTime
    lesson.endTime = newEndTime
    lesson.status = 'RESCHEDULED'
    lesson.canReschedule = true
    lesson.canCancel = true
  }

  function getWeeklySlots(tid: string): WeeklyAvailabilitySlot[] {
    return teacherAvailabilities.value[tid]?.weeklySlots ?? []
  }

  function addWeeklySlot(teacherId: string, dayOfWeek: number, hour: number): void {
    const ta = teacherAvailabilities.value[teacherId]
    if (!ta) return
    if (!ta.weeklySlots.some(s => s.dayOfWeek === dayOfWeek && s.hour === hour)) {
      ta.weeklySlots.push({ dayOfWeek, hour })
    }
  }

  function removeWeeklySlot(teacherId: string, dayOfWeek: number, hour: number): void {
    const ta = teacherAvailabilities.value[teacherId]
    if (!ta) return
    ta.weeklySlots = ta.weeklySlots.filter(s => !(s.dayOfWeek === dayOfWeek && s.hour === hour))
  }

  function addUnavailableDate(teacherId: string, date: string, reason: UnavailableReason, label: string): void {
    unavailableDates.value.push({ id: `ud${Date.now()}`, teacherId, date, reason, label })
  }

  function removeUnavailableDate(id: string): void {
    unavailableDates.value = unavailableDates.value.filter(d => d.id !== id)
  }

  function removeAvailabilitySlot(slotId: string): void {
    availabilitySlots.value = availabilitySlots.value.filter(
      s => !(s.id === slotId && !s.isBooked),
    )
  }

  function syncTeacherWeeklySlots(teacherId: string, newSlots: WeeklyAvailabilitySlot[]): void {
    function ds(d: Date): string { return d.toLocaleDateString('sv-SE') }
    const today = new Date('2026-05-22')
    const todayStr = ds(today)
    // Remove all future open slots for this teacher
    availabilitySlots.value = availabilitySlots.value.filter(
      s => !(s.teacherId === teacherId && !s.isBooked && s.date >= todayStr),
    )
    const tn = teacherAvailabilities.value[teacherId]?.teacherName ?? ''
    // Generate slots for the next 6 weeks
    for (let i = 0; i < 42; i++) {
      const d = new Date(today)
      d.setDate(today.getDate() + i)
      const dow = d.getDay()
      const dateStr = ds(d)
      for (const slot of newSlots.filter(s => s.dayOfWeek === dow)) {
        const st = `${String(slot.hour).padStart(2, '0')}:00`
        const et = `${String(slot.hour + 1).padStart(2, '0')}:00`
        availabilitySlots.value.push({
          id: `gen_${teacherId}_${dateStr}_${slot.hour}`,
          teacherId, teacherName: tn, date: dateStr,
          startTime: st, endTime: et, isBooked: false, isTrial: false,
        })
      }
    }
    const ta = teacherAvailabilities.value[teacherId]
    if (ta) {
      ta.weeklySlots = [...newSlots]
    } else {
      teacherAvailabilities.value[teacherId] = { teacherId, teacherName: tn, weeklySlots: [...newSlots] }
    }
  }

  return {
    lessons,
    availabilitySlots,
    unavailableDates,
    teacherAvailabilities,
    selectedTeacherId,
    myLessons,
    myAvailabilitySlots,
    myUnavailableDates,
    getWeeklySlots,
    bookSlot,
    cancelLesson,
    rescheduleLesson,
    addWeeklySlot,
    removeWeeklySlot,
    addUnavailableDate,
    removeUnavailableDate,
    removeAvailabilitySlot,
    syncTeacherWeeklySlots,
  }
})
