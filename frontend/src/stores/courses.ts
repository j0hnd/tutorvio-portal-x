import { defineStore } from 'pinia'
import { ref } from 'vue'

export type CourseStatus = 'active' | 'archived'
export type PlacementLevel = 'BEGINNER' | 'ELEMENTARY' | 'INTERMEDIATE' | 'UPPER_INTERMEDIATE' | 'ADVANCED' | 'ALL'

export interface CourseMilestone {
  id: string
  label: string
  order: number
}

export interface Course {
  id: string
  name: string
  description: string
  lessonStructure: string
  numberOfSessions: number
  placementLevel: PlacementLevel
  milestones: CourseMilestone[]
  attachedMaterialIds: string[]
  assignedStudentIds: string[]
  status: CourseStatus
  createdAt: string
  createdById: string
}

const MOCK_COURSES: Course[] = [
  {
    id: 'c1',
    name: 'General English',
    description: 'A well-rounded English program covering all four skills: speaking, listening, reading, and writing. Designed for students who want to improve their overall fluency and confidence in everyday English.',
    lessonStructure: '2 sessions per week, 60 minutes each. Each session alternates between grammar focus and conversation practice.',
    numberOfSessions: 24,
    placementLevel: 'BEGINNER',
    milestones: [
      { id: 'm1', label: 'Complete introductory unit (Lesson 1–4)', order: 1 },
      { id: 'm2', label: 'Hold a 5-minute conversation without stopping', order: 2 },
      { id: 'm3', label: 'Write a short personal essay (200 words)', order: 3 },
      { id: 'm4', label: 'Pass mid-program assessment', order: 4 },
      { id: 'm5', label: 'Complete all 24 sessions', order: 5 },
    ],
    attachedMaterialIds: ['lib1', 'lib2', 'lib3'],
    assignedStudentIds: ['u11', 'u14', 'u16'],
    status: 'active',
    createdAt: '2024-01-10T00:00:00Z',
    createdById: 'u3',
  },
  {
    id: 'c2',
    name: 'Business English',
    description: 'Focused on professional English communication skills for corporate environments. Covers business vocabulary, email writing, presentations, negotiations, and meetings.',
    lessonStructure: '2 sessions per week, 60 minutes each. Structured around real-world business scenarios and role-playing exercises.',
    numberOfSessions: 32,
    placementLevel: 'INTERMEDIATE',
    milestones: [
      { id: 'm1', label: 'Write a professional business email independently', order: 1 },
      { id: 'm2', label: 'Deliver a 5-minute presentation in English', order: 2 },
      { id: 'm3', label: 'Complete a mock negotiation role-play', order: 3 },
      { id: 'm4', label: 'Pass business vocabulary assessment (80%+)', order: 4 },
      { id: 'm5', label: 'Lead a simulated business meeting', order: 5 },
    ],
    attachedMaterialIds: ['lib4', 'lib5', 'lib8'],
    assignedStudentIds: ['u1', 'u12', 'u4'],
    status: 'active',
    createdAt: '2024-01-15T00:00:00Z',
    createdById: 'u3',
  },
  {
    id: 'c3',
    name: 'Maturita Preparation',
    description: 'Targeted preparation for the Maturita English examination. Focuses on exam technique, reading comprehension, essay writing, and oral examination practice.',
    lessonStructure: '3 sessions per week, 60 minutes each. Intensive exam-focused drills and timed practice tests.',
    numberOfSessions: 40,
    placementLevel: 'UPPER_INTERMEDIATE',
    milestones: [
      { id: 'm1', label: 'Complete reading comprehension module', order: 1 },
      { id: 'm2', label: 'Write 3 full-length practice essays', order: 2 },
      { id: 'm3', label: 'Pass mock oral examination', order: 3 },
      { id: 'm4', label: 'Complete 2 full timed mock exams', order: 4 },
    ],
    attachedMaterialIds: ['lib9', 'lib14'],
    assignedStudentIds: ['u13'],
    status: 'active',
    createdAt: '2024-02-01T00:00:00Z',
    createdById: 'u3',
  },
  {
    id: 'c4',
    name: 'Sunshine Restart Program',
    description: 'A gentle re-introduction to English for students returning after a break. Builds confidence through low-pressure, conversational sessions focused on rebuilding fluency.',
    lessonStructure: '1 session per week, 60 minutes. Relaxed, conversation-focused format with no formal testing pressure.',
    numberOfSessions: 16,
    placementLevel: 'ELEMENTARY',
    milestones: [
      { id: 'm1', label: 'Re-establish comfortable classroom conversation', order: 1 },
      { id: 'm2', label: 'Complete confidence self-assessment improvement', order: 2 },
      { id: 'm3', label: 'Sustain a 10-minute conversation independently', order: 3 },
    ],
    attachedMaterialIds: [],
    assignedStudentIds: ['u15'],
    status: 'active',
    createdAt: '2024-03-01T00:00:00Z',
    createdById: 'u3',
  },
  {
    id: 'c5',
    name: 'English for Work Confidence',
    description: 'Designed for professionals who use English at work but need more confidence. Covers workplace communication, small talk, reporting, and cross-cultural communication.',
    lessonStructure: '2 sessions per week, 60 minutes each. Scenario-based learning with real workplace contexts.',
    numberOfSessions: 20,
    placementLevel: 'INTERMEDIATE',
    milestones: [
      { id: 'm1', label: 'Initiate and sustain workplace small talk', order: 1 },
      { id: 'm2', label: 'Give a verbal status report in a team setting', order: 2 },
      { id: 'm3', label: 'Handle a challenging work conversation in English', order: 3 },
    ],
    attachedMaterialIds: ['lib4', 'lib8', 'lib10'],
    assignedStudentIds: ['u13', 'u11'],
    status: 'active',
    createdAt: '2024-04-01T00:00:00Z',
    createdById: 'u3',
  },
  {
    id: 'c6',
    name: 'Interview Preparation',
    description: 'Short, intensive program for students preparing for English job interviews. Covers CV language, common interview questions, body language tips, and follow-up communication.',
    lessonStructure: '3 sessions per week, 45 minutes each. Fast-paced, practical, and highly personalized.',
    numberOfSessions: 12,
    placementLevel: 'UPPER_INTERMEDIATE',
    milestones: [
      { id: 'm1', label: 'Complete mock interview round 1', order: 1 },
      { id: 'm2', label: 'Answer STAR-method questions fluently', order: 2 },
      { id: 'm3', label: 'Pass final mock interview with feedback', order: 3 },
    ],
    attachedMaterialIds: [],
    assignedStudentIds: ['u15', 'u14'],
    status: 'active',
    createdAt: '2024-05-01T00:00:00Z',
    createdById: 'u3',
  },
  {
    id: 'c7',
    name: 'Travel English',
    description: 'Practical English for travel situations — airports, hotels, restaurants, directions, emergencies, and social interactions. Perfect for students planning international travel.',
    lessonStructure: '1–2 sessions per week, 60 minutes each. Role-play heavy, fun, and situational.',
    numberOfSessions: 16,
    placementLevel: 'BEGINNER',
    milestones: [
      { id: 'm1', label: 'Navigate an airport conversation independently', order: 1 },
      { id: 'm2', label: 'Order food and handle restaurant interactions', order: 2 },
      { id: 'm3', label: 'Handle a travel emergency scenario in English', order: 3 },
    ],
    attachedMaterialIds: [],
    assignedStudentIds: [],
    status: 'archived',
    createdAt: '2023-09-01T00:00:00Z',
    createdById: 'u3',
  },
]

export const useCoursesStore = defineStore('courses', () => {
  const courses = ref<Course[]>(MOCK_COURSES.map(c => ({ ...c })))

  function getCourses(status?: CourseStatus): Course[] {
    if (status) return courses.value.filter(c => c.status === status)
    return courses.value
  }

  function getCourseById(id: string): Course | undefined {
    return courses.value.find(c => c.id === id)
  }

  function getCoursesForStudent(studentId: string): Course[] {
    return courses.value.filter(c => c.assignedStudentIds.includes(studentId))
  }

  function getCoursesForTeacher(teacherStudentIds: string[]): Course[] {
    return courses.value.filter(c =>
      c.assignedStudentIds.some(sid => teacherStudentIds.includes(sid))
    )
  }

  function createCourse(data: Omit<Course, 'id' | 'createdAt' | 'status'>): Course {
    const course: Course = {
      ...data,
      id: `c${Date.now()}`,
      status: 'active',
      createdAt: new Date().toISOString(),
    }
    courses.value.unshift(course)
    return course
  }

  function updateCourse(id: string, data: Partial<Omit<Course, 'id' | 'createdAt' | 'createdById'>>): void {
    const idx = courses.value.findIndex(c => c.id === id)
    if (idx < 0) return
    courses.value[idx] = { ...courses.value[idx], ...data }
  }

  function archiveCourse(id: string): void {
    updateCourse(id, { status: 'archived' })
  }

  function restoreCourse(id: string): void {
    updateCourse(id, { status: 'active' })
  }

  function assignStudent(courseId: string, studentId: string): void {
    const c = courses.value.find(x => x.id === courseId)
    if (!c || c.assignedStudentIds.includes(studentId)) return
    c.assignedStudentIds.push(studentId)
  }

  function removeStudent(courseId: string, studentId: string): void {
    const c = courses.value.find(x => x.id === courseId)
    if (!c) return
    c.assignedStudentIds = c.assignedStudentIds.filter(id => id !== studentId)
  }

  function attachMaterial(courseId: string, materialId: string): void {
    const c = courses.value.find(x => x.id === courseId)
    if (!c || c.attachedMaterialIds.includes(materialId)) return
    c.attachedMaterialIds.push(materialId)
  }

  function detachMaterial(courseId: string, materialId: string): void {
    const c = courses.value.find(x => x.id === courseId)
    if (!c) return
    c.attachedMaterialIds = c.attachedMaterialIds.filter(id => id !== materialId)
  }

  return {
    courses,
    getCourses,
    getCourseById,
    getCoursesForStudent,
    getCoursesForTeacher,
    createCourse,
    updateCourse,
    archiveCourse,
    restoreCourse,
    assignStudent,
    removeStudent,
    attachMaterial,
    detachMaterial,
  }
})
