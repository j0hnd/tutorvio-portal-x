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

export interface LessonNote {
  id: string
  lessonId: string
  content: string
  authorId: string
  authorName: string
  isPublic: boolean   // visible to student
  createdAt: string
  updatedAt?: string
}

export type AttendanceStatus =
  | 'PRESENT'
  | 'ABSENT_WITH_NOTICE'
  | 'ABSENT_WITHOUT_NOTICE'
  | 'TEACHER_ABSENT'
  | 'RESCHEDULED'
  | 'LATE'
  | 'EXCUSED'

export interface LessonAttendance {
  lessonId: string
  teacherStatus: AttendanceStatus
  studentStatus: AttendanceStatus
  absenceReason?: string
  comments?: string
  markedAt: string
  markedById: string
  isOverridden?: boolean
  overriddenById?: string
  overriddenAt?: string
}

export interface AttendanceFilters {
  dateFrom?: string
  dateTo?: string
  teacherId?: string
  studentId?: string
  status?: AttendanceStatus
  subject?: string
}

export type MaterialType = 'PDF' | 'VIDEO' | 'LINK' | 'DOCUMENT' | 'IMAGE'

export interface LessonMaterial {
  id: string
  lessonId: string
  title: string
  type: MaterialType
  url: string
  uploadedByName: string
  uploadedAt: string
}

export type HomeworkStatus = 'PENDING' | 'SUBMITTED' | 'REVIEWED'

export interface LessonHomework {
  id: string
  lessonId: string
  title: string
  description: string
  dueDate: string
  studentId: string
  studentName: string
  status: HomeworkStatus
  submissionUrl?: string
  feedback?: string
  grade?: string
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
  startMinute = 0,
): ScheduleLesson {
  const start = new Date(dt(dateStr, startHour, startMinute))
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
  // Sub-hour demo lessons — May 25 (PM)
  makeLesson('ldemo1', 'u2', 'James Reyes', 'u10', 'Lea Mendoza',
    '2026-05-25', 16, 45, 'SCHEDULED', 'General English', {}, 30),
  makeLesson('ldemo2', 'u6', 'Sarah Lim', 'u14', 'Sofia Cruz',
    '2026-05-25', 17, 60, 'SCHEDULED', 'IELTS Preparation', {}, 45),
  makeLesson('ldemo3', 'u7', 'Miguel Santos', 'u17', 'Rico Valdez',
    '2026-05-25', 18, 45, 'TRIAL', 'Business English', {}, 15),
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

// ---- Lesson Detail Mock Data ----

const MOCK_NOTES_INITIAL: LessonNote[] = [
  { id: 'n1', lessonId: 'l1', content: 'Emma did an excellent job with formal email writing. She constructed three different business email templates with minimal errors. Focus next session on board-meeting phrases and presentations.', authorId: 'u2', authorName: 'James Reyes', isPublic: true, createdAt: '2026-05-18T10:05:00Z' },
  { id: 'n2', lessonId: 'l1', content: 'Internal: Student is progressing faster than average. Consider recommending advanced track.', authorId: 'u2', authorName: 'James Reyes', isPublic: false, createdAt: '2026-05-18T10:10:00Z' },
  { id: 'n3', lessonId: 'l4', content: 'Marco showed strong improvement in conversational fluency. Practiced ordering food, shopping, and directions scenarios. Recommended: 30 min daily shadowing exercises.', authorId: 'u2', authorName: 'James Reyes', isPublic: true, createdAt: '2026-05-20T10:00:00Z' },
  { id: 'n4', lessonId: 'l5', content: 'Worked on presentation skills for Emma\'s upcoming board meeting. Excellent progress — confidence level noticeably higher. Next: practice Q&A handling.', authorId: 'u2', authorName: 'James Reyes', isPublic: true, createdAt: '2026-05-21T10:00:00Z' },
  { id: 'n5', lessonId: 'ls1', content: 'Sofia completed Listening section practice test — scored 7.0. Reading still needs improvement, particularly matching headings. Assigned extra practice materials.', authorId: 'u6', authorName: 'Sarah Lim', isPublic: true, createdAt: '2026-05-18T11:00:00Z' },
  { id: 'n6', lessonId: 'lm1', content: 'Rico tackled negotiation language and deal-closing phrases. Role-played a supplier negotiation scenario. Strong performance. Homework: write a follow-up email after a negotiation.', authorId: 'u7', authorName: 'Miguel Santos', isPublic: true, createdAt: '2026-05-18T10:00:00Z' },
  { id: 'n7', lessonId: 'ldemo1', content: 'Lea reviewed present perfect tense in business contexts. Good grasp of structure. Next focus: polite request phrases for client meetings.', authorId: 'u2', authorName: 'James Reyes', isPublic: true, createdAt: '2026-05-25T16:35:00Z' },
  { id: 'n8', lessonId: 'ldemo2', content: 'Sofia completed a full IELTS Speaking mock test. Band 6.5 estimated. Key areas to improve: topic development in Part 2 and using more complex vocabulary. Will assign speaking recordings for homework.', authorId: 'u6', authorName: 'Sarah Lim', isPublic: true, createdAt: '2026-05-25T17:50:00Z' },
  { id: 'n9', lessonId: 'ldemo3', content: 'Trial session with Rico. He has strong listening comprehension and a good active vocabulary for business contexts. Recommend starting with Business English intermediate track.', authorId: 'u7', authorName: 'Miguel Santos', isPublic: true, createdAt: '2026-05-25T18:20:00Z' },
  { id: 'n10', lessonId: 'ldemo3', content: 'Internal: Rico is a strong candidate for fast-track. Consider pairing with Miguel for continuity since he already has rapport.', authorId: 'u7', authorName: 'Miguel Santos', isPublic: false, createdAt: '2026-05-25T18:25:00Z' },
  // James Reyes — additional lessons
  { id: 'n11', lessonId: 'l2', content: 'Lea worked on phrasal verbs in workplace contexts. Good retention from last session. Assigned 10 fill-in-the-blank exercises for home practice.', authorId: 'u2', authorName: 'James Reyes', isPublic: true, createdAt: '2026-05-18T15:05:00Z' },
  { id: 'n12', lessonId: 'l3', content: 'Carlos was absent — no prior notice. Reminder sent via email. Will discuss rescheduling at next available slot.', authorId: 'u2', authorName: 'James Reyes', isPublic: false, createdAt: '2026-05-19T10:25:00Z' },
  { id: 'n13', lessonId: 'l8', content: 'Emma worked on executive summary writing and key business report phrases. Excellent understanding of structure. Next session: stakeholder presentation language.', authorId: 'u2', authorName: 'James Reyes', isPublic: true, createdAt: '2026-05-25T10:05:00Z' },
  { id: 'n14', lessonId: 'l8', content: 'Internal: Emma is consistently top of class. Flag for testimonial request.', authorId: 'u2', authorName: 'James Reyes', isPublic: false, createdAt: '2026-05-25T10:07:00Z' },
  // Sarah Lim — additional lessons
  { id: 'n15', lessonId: 'ls2', content: 'Juan completed Academic Writing Task 1 — line graphs. Score improved to band 6.0. Main issue: incorrect use of comparative language. Drilled contrast connectors.', authorId: 'u6', authorName: 'Sarah Lim', isPublic: true, createdAt: '2026-05-19T10:05:00Z' },
  { id: 'n16', lessonId: 'ls5', content: 'Juan focused on IELTS Listening sections 3 and 4 — academic discussions and monologues. Strong section 3 performance. Section 4 needs work on note completion tasks.', authorId: 'u6', authorName: 'Sarah Lim', isPublic: true, createdAt: '2026-05-25T11:05:00Z' },
  // Miguel Santos — additional lessons
  { id: 'n17', lessonId: 'lm3', content: 'Rico worked through advanced negotiation phrases and cross-cultural business communication. Role-played a multi-party deal scenario. Very strong performance — ready for advanced module.', authorId: 'u7', authorName: 'Miguel Santos', isPublic: true, createdAt: '2026-05-20T10:00:00Z' },
  { id: 'n18', lessonId: 'lm4', content: 'Lia focused on small-talk and relationship-building language for business contexts. Practiced 3 different opening scenarios. Homework: record a 2-minute self-introduction.', authorId: 'u7', authorName: 'Miguel Santos', isPublic: true, createdAt: '2026-05-21T11:05:00Z' },
  { id: 'n19', lessonId: 'lm6', content: 'Rescheduled session with Lia. Focused on conversational fluency — speed and natural phrasing improved noticeably. Recommended daily 15-min podcast shadowing.', authorId: 'u7', authorName: 'Miguel Santos', isPublic: true, createdAt: '2026-05-25T10:05:00Z' },
]

const MOCK_ATTENDANCE_INITIAL: LessonAttendance[] = [
  { lessonId: 'l1', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', comments: 'Good focus throughout.', markedAt: '2026-05-18T09:02:00Z', markedById: 'u2' },
  { lessonId: 'l2', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-18T14:02:00Z', markedById: 'u2' },
  { lessonId: 'l3', teacherStatus: 'PRESENT', studentStatus: 'ABSENT_WITHOUT_NOTICE', absenceReason: 'Student did not join. No prior notice.', comments: 'Sent follow-up message via portal.', markedAt: '2026-05-19T10:20:00Z', markedById: 'u2' },
  { lessonId: 'l4', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-20T09:02:00Z', markedById: 'u2' },
  { lessonId: 'l5', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-21T09:02:00Z', markedById: 'u2' },
  { lessonId: 'ls1', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-18T10:02:00Z', markedById: 'u6' },
  { lessonId: 'lm1', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-18T09:02:00Z', markedById: 'u7' },
  { lessonId: 'lm2', teacherStatus: 'PRESENT', studentStatus: 'ABSENT_WITHOUT_NOTICE', absenceReason: 'Student did not show. Waiting for response.', markedAt: '2026-05-19T10:30:00Z', markedById: 'u7', isOverridden: true, overriddenById: 'u1admin', overriddenAt: '2026-05-19T14:00:00Z' },
  { lessonId: 'ldemo1', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-25T17:05:00Z', markedById: 'u2' },
  { lessonId: 'ldemo2', teacherStatus: 'PRESENT', studentStatus: 'LATE', absenceReason: 'Student joined 8 minutes late due to internet issues.', markedAt: '2026-05-25T18:50:00Z', markedById: 'u6' },
  { lessonId: 'l8', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-25T10:02:00Z', markedById: 'u2' },
  { lessonId: 'ls2', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-19T09:02:00Z', markedById: 'u6' },
  { lessonId: 'ls3', teacherStatus: 'PRESENT', studentStatus: 'ABSENT_WITH_NOTICE', absenceReason: 'Student cancelled 2 hours before session. No makeup scheduled yet.', markedAt: '2026-05-20T10:05:00Z', markedById: 'u6' },
  { lessonId: 'ls5', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-25T10:02:00Z', markedById: 'u6' },
  { lessonId: 'lm3', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-20T09:02:00Z', markedById: 'u7' },
  { lessonId: 'lm4', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-21T10:02:00Z', markedById: 'u7' },
  { lessonId: 'lm6', teacherStatus: 'PRESENT', studentStatus: 'PRESENT', markedAt: '2026-05-25T09:02:00Z', markedById: 'u7' },
]

const MOCK_MATERIALS_INITIAL: LessonMaterial[] = [
  { id: 'm1', lessonId: 'l1', title: 'Formal Email Templates Workbook', type: 'PDF', url: 'https://materials.tutorvio.com/files/email-templates.pdf', uploadedByName: 'James Reyes', uploadedAt: '2026-05-18T08:30:00Z' },
  { id: 'm2', lessonId: 'l1', title: 'Board Meeting Vocabulary List', type: 'DOCUMENT', url: 'https://materials.tutorvio.com/files/board-vocab.docx', uploadedByName: 'James Reyes', uploadedAt: '2026-05-18T08:32:00Z' },
  { id: 'm3', lessonId: 'l5', title: 'Presentation Skills Video Guide', type: 'VIDEO', url: 'https://materials.tutorvio.com/videos/presentation-skills.mp4', uploadedByName: 'James Reyes', uploadedAt: '2026-05-21T08:45:00Z' },
  { id: 'm4', lessonId: 'ls1', title: 'IELTS Reading Practice Test 4', type: 'PDF', url: 'https://materials.tutorvio.com/files/ielts-reading-4.pdf', uploadedByName: 'Sarah Lim', uploadedAt: '2026-05-18T09:50:00Z' },
  { id: 'm5', lessonId: 'l8', title: 'Business Presentation Framework', type: 'PDF', url: 'https://materials.tutorvio.com/files/biz-framework.pdf', uploadedByName: 'James Reyes', uploadedAt: '2026-05-25T08:00:00Z' },
  { id: 'm6', lessonId: 'lm1', title: 'Negotiation Language Cheat Sheet', type: 'DOCUMENT', url: 'https://materials.tutorvio.com/files/negotiation.docx', uploadedByName: 'Miguel Santos', uploadedAt: '2026-05-18T08:55:00Z' },
  { id: 'm7', lessonId: 'ldemo1', title: 'Business Email Phrases — Intermediate', type: 'PDF', url: 'https://materials.tutorvio.com/files/biz-email-phrases.pdf', uploadedByName: 'James Reyes', uploadedAt: '2026-05-25T16:28:00Z' },
  { id: 'm8', lessonId: 'ldemo2', title: 'IELTS Speaking Band Descriptors', type: 'PDF', url: 'https://materials.tutorvio.com/files/ielts-speaking-bands.pdf', uploadedByName: 'Sarah Lim', uploadedAt: '2026-05-25T17:43:00Z' },
  { id: 'm9', lessonId: 'ldemo2', title: 'Speaking Part 2 — Topic Cards Set B', type: 'DOCUMENT', url: 'https://materials.tutorvio.com/files/speaking-p2-setb.docx', uploadedByName: 'Sarah Lim', uploadedAt: '2026-05-25T17:44:00Z' },
  { id: 'm10', lessonId: 'ldemo3', title: 'Business English Placement Overview', type: 'LINK', url: 'https://materials.tutorvio.com/links/be-placement', uploadedByName: 'Miguel Santos', uploadedAt: '2026-05-25T18:10:00Z' },
  { id: 'm11', lessonId: 'l2', title: 'Phrasal Verbs in the Workplace — Exercise Set', type: 'PDF', url: 'https://materials.tutorvio.com/files/phrasal-verbs-work.pdf', uploadedByName: 'James Reyes', uploadedAt: '2026-05-18T13:55:00Z' },
  { id: 'm12', lessonId: 'l8', title: 'Executive Summary Writing Guide', type: 'DOCUMENT', url: 'https://materials.tutorvio.com/files/exec-summary.docx', uploadedByName: 'James Reyes', uploadedAt: '2026-05-25T09:00:00Z' },
  { id: 'm13', lessonId: 'l8', title: 'Stakeholder Presentation Phrases', type: 'PDF', url: 'https://materials.tutorvio.com/files/stakeholder-phrases.pdf', uploadedByName: 'James Reyes', uploadedAt: '2026-05-25T09:02:00Z' },
  { id: 'm14', lessonId: 'ls2', title: 'IELTS Writing Task 1 — Graphs & Charts', type: 'PDF', url: 'https://materials.tutorvio.com/files/ielts-writing-t1.pdf', uploadedByName: 'Sarah Lim', uploadedAt: '2026-05-19T08:50:00Z' },
  { id: 'm15', lessonId: 'ls5', title: 'IELTS Listening Practice — Sections 3 & 4', type: 'PDF', url: 'https://materials.tutorvio.com/files/ielts-listening-34.pdf', uploadedByName: 'Sarah Lim', uploadedAt: '2026-05-25T09:45:00Z' },
  { id: 'm16', lessonId: 'lm3', title: 'Advanced Negotiation Language — Module 2', type: 'PDF', url: 'https://materials.tutorvio.com/files/negotiation-m2.pdf', uploadedByName: 'Miguel Santos', uploadedAt: '2026-05-20T08:55:00Z' },
  { id: 'm17', lessonId: 'lm4', title: 'Small Talk & Business Relationship Phrases', type: 'DOCUMENT', url: 'https://materials.tutorvio.com/files/small-talk.docx', uploadedByName: 'Miguel Santos', uploadedAt: '2026-05-21T09:55:00Z' },
  { id: 'm18', lessonId: 'lm6', title: 'Conversational Fluency — Podcast List', type: 'LINK', url: 'https://materials.tutorvio.com/links/fluency-podcasts', uploadedByName: 'Miguel Santos', uploadedAt: '2026-05-25T09:00:00Z' },
]

const MOCK_HOMEWORK_INITIAL: LessonHomework[] = [
  { id: 'hw1', lessonId: 'l1', title: 'Write 2 formal business emails', description: 'Write one email requesting a meeting and one email following up after a board presentation. Minimum 150 words each. Use templates from today\'s session.', dueDate: '2026-05-22', studentId: 'u1', studentName: 'Emma Santos', status: 'REVIEWED', feedback: 'Excellent work! Both emails were well-structured and professional. Minor grammar corrections noted inline.', grade: 'A' },
  { id: 'hw2', lessonId: 'l4', title: 'Shadowing exercise — 3 scenarios', description: 'Record yourself completing the food ordering, shopping, and directions scenarios. Submit audio files via the student portal.', dueDate: '2026-05-23', studentId: 'u12', studentName: 'Marco Tan', status: 'SUBMITTED', submissionUrl: 'https://portal.tutorvio.com/submissions/hw2' },
  { id: 'hw3', lessonId: 'l5', title: 'Practice board meeting Q&A responses', description: 'Prepare 5 Q&A response scripts for typical board meeting questions. Use the STAR method for at least 3 responses.', dueDate: '2026-05-25', studentId: 'u1', studentName: 'Emma Santos', status: 'SUBMITTED', submissionUrl: 'https://portal.tutorvio.com/submissions/hw3' },
  { id: 'hw4', lessonId: 'ls1', title: 'IELTS Reading — Matching Headings practice', description: 'Complete exercises 1-5 from the Reading Practice Test 4 PDF. Focus on matching headings. Submit answers by next session.', dueDate: '2026-05-23', studentId: 'u14', studentName: 'Sofia Cruz', status: 'REVIEWED', feedback: 'Good improvement! Scored 6/8 on matching headings. Keep practicing paragraph topic identification.', grade: 'B+' },
  { id: 'hw5', lessonId: 'l8', title: 'Prepare 5-minute presentation outline', description: 'Create an outline for a 5-minute business presentation on any topic of your choice. Include intro, 3 key points, and conclusion.', dueDate: '2026-05-28', studentId: 'u1', studentName: 'Emma Santos', status: 'PENDING' },
  { id: 'hw6', lessonId: 'lm1', title: 'Write a post-negotiation follow-up email', description: 'Write a professional follow-up email after a supplier negotiation. Include deal summary, agreed terms, and next steps. 200+ words.', dueDate: '2026-05-22', studentId: 'u17', studentName: 'Rico Valdez', status: 'REVIEWED', feedback: 'Well done! Clear structure and professional tone. A few vocabulary suggestions noted.', grade: 'A-' },
  { id: 'hw7', lessonId: 'ldemo1', title: 'Write 3 polite request phrases using present perfect', description: 'Using today\'s vocabulary, write 3 sentences making polite business requests. E.g. "I was wondering if you could…" Submit via the student portal before the next session.', dueDate: '2026-05-28', studentId: 'u10', studentName: 'Lea Mendoza', status: 'PENDING' },
  { id: 'hw8', lessonId: 'ldemo2', title: 'IELTS Speaking — 3-minute Part 2 recording', description: 'Choose any topic from the Topic Cards Set B. Record a 3-minute Part 2 response. Focus on topic development and complex vocabulary. Submit audio file via the student portal.', dueDate: '2026-05-29', studentId: 'u14', studentName: 'Sofia Cruz', status: 'PENDING' },
  { id: 'hw9', lessonId: 'l2', title: 'Phrasal verbs — 10 fill-in-the-blank exercises', description: 'Complete the exercise set from the PDF. Write 5 original sentences using 5 different phrasal verbs from today\'s lesson. Submit via student portal.', dueDate: '2026-05-21', studentId: 'u10', studentName: 'Lea Mendoza', status: 'REVIEWED', feedback: 'Great work! All exercises correct. Original sentences show good understanding of context.', grade: 'A' },
  { id: 'hw10', lessonId: 'l8', title: 'Write an executive summary (300 words)', description: 'Choose any business scenario (product launch, quarterly review, etc.) and write a 300-word executive summary. Use today\'s guide as your template. Submit before next session.', dueDate: '2026-05-29', studentId: 'u1', studentName: 'Emma Santos', status: 'SUBMITTED', submissionUrl: 'https://portal.tutorvio.com/submissions/hw10' },
  { id: 'hw11', lessonId: 'ls2', title: 'IELTS Writing Task 1 — 2 graph descriptions', description: 'Complete exercises 3 and 4 from the Writing Task 1 PDF. Write full responses (minimum 150 words each). Focus on accurate comparative language and proper sequencing.', dueDate: '2026-05-23', studentId: 'u15', studentName: 'Juan Ramos', status: 'REVIEWED', feedback: 'Good improvement on comparative language! Exercise 3 was excellent. Exercise 4 needed more specific data reference.', grade: 'B' },
  { id: 'hw12', lessonId: 'ls5', title: 'IELTS Listening Section 4 practice x2', description: 'Complete listening exercises for sections 4a and 4b from the Listening Practice PDF. Focus on note completion accuracy. Submit answers via portal.', dueDate: '2026-05-29', studentId: 'u15', studentName: 'Juan Ramos', status: 'PENDING' },
  { id: 'hw13', lessonId: 'lm3', title: 'Multi-party negotiation summary memo', description: 'Write a 250-word summary memo of the role-play negotiation from today\'s session. Include agreed terms, outstanding issues, and proposed next steps. Professional tone required.', dueDate: '2026-05-23', studentId: 'u17', studentName: 'Rico Valdez', status: 'REVIEWED', feedback: 'Outstanding memo. Professional tone, clear structure, and excellent use of advanced vocabulary. Ready for advanced module.', grade: 'A+' },
  { id: 'hw14', lessonId: 'lm4', title: 'Record a 2-minute self-introduction', description: 'Record a 2-minute business self-introduction as if meeting a new client for the first time. Include: name, role, company background, and 1 recent achievement. Submit audio file.', dueDate: '2026-05-24', studentId: 'u18', studentName: 'Lia Torres', status: 'SUBMITTED', submissionUrl: 'https://portal.tutorvio.com/submissions/hw14' },
]

// ---- Store ----

export const useScheduleStore = defineStore('schedule', () => {
  const auth = useAuthStore()
  const { effectiveRole } = useViewAs()

  const lessons = ref<ScheduleLesson[]>(MOCK_LESSONS_INITIAL.map(l => ({ ...l })))
  const availabilitySlots = ref<AvailabilitySlot[]>(MOCK_AVAILABILITY_INITIAL.map(s => ({ ...s })))
  const unavailableDates = ref<UnavailableDate[]>(MOCK_UNAVAILABLE_INITIAL.map(d => ({ ...d })))
  const lessonNotes = ref<LessonNote[]>(MOCK_NOTES_INITIAL.map(n => ({ ...n })))
  const lessonAttendance = ref<LessonAttendance[]>(MOCK_ATTENDANCE_INITIAL.map(a => ({ ...a })))
  const lessonMaterials = ref<LessonMaterial[]>(MOCK_MATERIALS_INITIAL.map(m => ({ ...m })))
  const lessonHomework = ref<LessonHomework[]>(MOCK_HOMEWORK_INITIAL.map(h => ({ ...h })))
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

  function blockHoliday(date: string, label: string): void {
    const teacherIds = Object.keys(teacherAvailabilities.value)
    for (const tid of teacherIds) {
      if (!unavailableDates.value.some(u => u.teacherId === tid && u.date === date)) {
        unavailableDates.value.push({
          id: `hol${Date.now()}_${tid}`,
          teacherId: tid, date,
          reason: 'NATIONAL_HOLIDAY', label,
        })
      }
    }
  }

  function removeHoliday(date: string): void {
    unavailableDates.value = unavailableDates.value.filter(
      u => !(u.date === date && u.reason === 'NATIONAL_HOLIDAY'),
    )
  }

  function removeUnavailableDate(id: string): void {
    unavailableDates.value = unavailableDates.value.filter(d => d.id !== id)
  }

  function createLesson(
    teacherId: string,
    teacherName: string,
    studentId: string,
    studentName: string,
    dateStr: string,
    startHH: string,
    endHH: string,
    subject: string,
    isTrial: boolean,
    isRecurring: boolean,
  ): ScheduleLesson {
    const newId = `l${Date.now()}`
    const startISO = `${dateStr}T${startHH}:00+08:00`
    const endISO   = `${dateStr}T${endHH}:00+08:00`
    const newLesson: ScheduleLesson = {
      id: newId,
      title: isTrial ? `Trial: ${subject}` : subject,
      teacherId, teacherName, studentId, studentName,
      startTime: new Date(startISO).toISOString(),
      endTime:   new Date(endISO).toISOString(),
      status: isTrial ? 'TRIAL' : 'SCHEDULED',
      meetingUrl: `https://meet.tutorvio.com/room/${newId}`,
      subject, isTrial, isRecurring,
      canReschedule: true, canCancel: true,
    }
    lessons.value.push(newLesson)
    return newLesson
  }

  // ---- Lesson detail actions ----

  function getLesson(id: string): ScheduleLesson | undefined {
    return lessons.value.find(l => l.id === id)
  }

  function getNotesForLesson(lessonId: string): LessonNote[] {
    return lessonNotes.value.filter(n => n.lessonId === lessonId)
  }

  function addNote(lessonId: string, content: string, authorId: string, authorName: string, isPublic: boolean): void {
    lessonNotes.value.push({
      id: `n${Date.now()}`, lessonId, content, authorId, authorName, isPublic,
      createdAt: new Date().toISOString(),
    })
  }

  function updateNote(noteId: string, content: string, isPublic: boolean): void {
    const note = lessonNotes.value.find(n => n.id === noteId)
    if (note) { note.content = content; note.isPublic = isPublic; note.updatedAt = new Date().toISOString() }
  }

  function deleteNote(noteId: string): void {
    lessonNotes.value = lessonNotes.value.filter(n => n.id !== noteId)
  }

  function getAttendanceForLesson(lessonId: string): LessonAttendance | undefined {
    return lessonAttendance.value.find(a => a.lessonId === lessonId)
  }

  function markAttendance(
    lessonId: string,
    teacherStatus: AttendanceStatus,
    studentStatus: AttendanceStatus,
    absenceReason: string,
    markerId: string,
    comments?: string,
  ): void {
    const existing = lessonAttendance.value.find(a => a.lessonId === lessonId)
    if (existing) {
      existing.teacherStatus = teacherStatus
      existing.studentStatus = studentStatus
      existing.absenceReason = absenceReason || undefined
      existing.comments = comments || undefined
      existing.markedAt = new Date().toISOString()
      existing.markedById = markerId
    } else {
      lessonAttendance.value.push({
        lessonId, teacherStatus, studentStatus,
        absenceReason: absenceReason || undefined,
        comments: comments || undefined,
        markedAt: new Date().toISOString(), markedById: markerId,
      })
    }
  }

  function overrideAttendance(
    lessonId: string,
    teacherStatus: AttendanceStatus,
    studentStatus: AttendanceStatus,
    absenceReason: string,
    adminId: string,
    comments?: string,
  ): void {
    const existing = lessonAttendance.value.find(a => a.lessonId === lessonId)
    const now = new Date().toISOString()
    if (existing) {
      existing.teacherStatus = teacherStatus
      existing.studentStatus = studentStatus
      existing.absenceReason = absenceReason || undefined
      existing.comments = comments || undefined
      existing.isOverridden = true
      existing.overriddenById = adminId
      existing.overriddenAt = now
    } else {
      lessonAttendance.value.push({
        lessonId, teacherStatus, studentStatus,
        absenceReason: absenceReason || undefined,
        comments: comments || undefined,
        markedAt: now, markedById: adminId,
        isOverridden: true, overriddenById: adminId, overriddenAt: now,
      })
    }
  }

  function getAllAttendance(filters: AttendanceFilters = {}): (LessonAttendance & { lesson: ScheduleLesson })[] {
    return lessonAttendance.value
      .map(a => {
        const lesson = lessons.value.find(l => l.id === a.lessonId)
        return lesson ? { ...a, lesson } : null
      })
      .filter((a): a is LessonAttendance & { lesson: ScheduleLesson } => a !== null)
      .filter(a => {
        const lessonDate = a.lesson.startTime.slice(0, 10)
        if (filters.dateFrom && lessonDate < filters.dateFrom) return false
        if (filters.dateTo && lessonDate > filters.dateTo) return false
        if (filters.teacherId && a.lesson.teacherId !== filters.teacherId) return false
        if (filters.studentId && a.lesson.studentId !== filters.studentId) return false
        if (filters.status && a.studentStatus !== filters.status && a.teacherStatus !== filters.status) return false
        if (filters.subject && !a.lesson.subject.toLowerCase().includes(filters.subject.toLowerCase())) return false
        return true
      })
      .sort((a, b) => b.lesson.startTime.localeCompare(a.lesson.startTime))
  }

  function getMaterialsForLesson(lessonId: string): LessonMaterial[] {
    return lessonMaterials.value.filter(m => m.lessonId === lessonId)
  }

  function addMaterial(lessonId: string, title: string, type: MaterialType, url: string, uploaderName: string): void {
    lessonMaterials.value.push({
      id: `m${Date.now()}`, lessonId, title, type, url,
      uploadedByName: uploaderName, uploadedAt: new Date().toISOString(),
    })
  }

  function deleteMaterial(materialId: string): void {
    lessonMaterials.value = lessonMaterials.value.filter(m => m.id !== materialId)
  }

  function getHomeworkForLesson(lessonId: string): LessonHomework[] {
    return lessonHomework.value.filter(h => h.lessonId === lessonId)
  }

  function addHomework(lessonId: string, title: string, description: string, dueDate: string, studentId: string, studentName: string): void {
    lessonHomework.value.push({
      id: `hw${Date.now()}`, lessonId, title, description, dueDate,
      studentId, studentName, status: 'PENDING',
    })
  }

  function updateHomeworkStatus(hwId: string, status: HomeworkStatus, feedback?: string, grade?: string): void {
    const hw = lessonHomework.value.find(h => h.id === hwId)
    if (hw) { hw.status = status; if (feedback) hw.feedback = feedback; if (grade) hw.grade = grade }
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

  function addLesson(
    teacherId: string, teacherName: string,
    studentId: string, studentName: string,
    subject: string, startTime: string, durationMin: number,
    isTrial: boolean, isRecurring: boolean,
  ): void {
    const id = `lc${Date.now()}`
    const start = new Date(startTime)
    const end = new Date(start.getTime() + durationMin * 60_000)
    const now = new Date()
    const status: LessonStatus = isTrial ? 'TRIAL' : 'SCHEDULED'
    lessons.value.push({
      id,
      title: isTrial ? `Trial: ${subject}` : subject,
      subject,
      teacherId, teacherName,
      studentId, studentName,
      startTime: start.toISOString(),
      endTime: end.toISOString(),
      status,
      meetingUrl: start > now ? `https://meet.tutorvio.com/room/${id}` : undefined,
      notes: undefined,
      isTrial,
      isRecurring,
      canReschedule: start > now,
      canCancel: start > now,
    })
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
    createLesson,
    cancelLesson,
    rescheduleLesson,
    addWeeklySlot,
    removeWeeklySlot,
    addUnavailableDate,
    blockHoliday,
    removeHoliday,
    removeUnavailableDate,
    removeAvailabilitySlot,
    syncTeacherWeeklySlots,
    getLesson,
    lessonNotes,
    lessonAttendance,
    lessonMaterials,
    lessonHomework,
    getNotesForLesson,
    addNote,
    updateNote,
    deleteNote,
    getAttendanceForLesson,
    getAllAttendance,
    markAttendance,
    overrideAttendance,
    getMaterialsForLesson,
    addMaterial,
    deleteMaterial,
    getHomeworkForLesson,
    addHomework,
    updateHomeworkStatus,
    addLesson,
  }
})
