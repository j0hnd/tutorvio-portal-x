import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { UserRole } from '@/types'

export type NotificationType = 'system' | 'class_reminder' | 'reschedule' | 'homework' | 'announcement'
export type AnnouncementStatus = 'active' | 'archived' | 'scheduled'
export type TargetType = 'ALL' | 'role' | 'course' | 'user' | 'teacher_group' | 'student_group'

export interface AppNotification {
  id: string
  type: NotificationType
  title: string
  body: string
  isRead: boolean
  createdAt: string
  targetUserId?: string
  targetRole?: UserRole | 'ALL'
  targetCourseId?: string
  link?: string
}

export interface Announcement {
  id: string
  title: string
  body: string
  targetType: TargetType
  targetRole?: UserRole | 'ALL'
  targetCourseId?: string
  targetUserId?: string
  status: AnnouncementStatus
  scheduledAt?: string
  publishedAt: string
  createdById: string
  createdByName: string
}

const MOCK_NOTIFICATIONS: AppNotification[] = [
  // ALL users
  { id: 'n1',  type: 'system',        title: 'Platform maintenance',        body: 'Scheduled maintenance on Jun 10 from 2:00–4:00 AM PHT. The portal will be briefly unavailable.', isRead: false, createdAt: '2026-06-01T08:00:00Z', targetRole: 'ALL' },
  // Students
  { id: 'n2',  type: 'class_reminder', title: 'Class starting in 1 hour',   body: 'Business English with James Reyes starts at 2:00 PM today. Make sure your camera and mic are ready.', isRead: false, createdAt: '2026-06-02T05:00:00Z', targetUserId: 'u1', link: '/lessons' },
  { id: 'n3',  type: 'homework',       title: 'Homework due tomorrow',       body: 'Your homework "Write a professional business email" is due on Jun 3. Submit before your next class.', isRead: false, createdAt: '2026-06-01T10:00:00Z', targetUserId: 'u1', link: '/homework' },
  { id: 'n4',  type: 'announcement',   title: 'New course materials added',  body: 'New worksheets have been added to the Business English library. Check the Materials page.', isRead: true,  createdAt: '2026-05-30T09:00:00Z', targetRole: 'STUDENT', link: '/materials' },
  { id: 'n5',  type: 'homework',       title: 'Homework reviewed',           body: 'Your teacher has reviewed your submission for "Practice modal verbs". Check the feedback.', isRead: true,  createdAt: '2026-05-28T14:00:00Z', targetUserId: 'u1', link: '/homework' },
  // Teachers
  { id: 'n6',  type: 'class_reminder', title: 'Class starting in 30 min',   body: 'Business English with Emma Santos starts at 2:00 PM. Open the lesson detail for the join link.', isRead: false, createdAt: '2026-06-02T05:30:00Z', targetUserId: 'u2', link: '/lessons' },
  { id: 'n7',  type: 'announcement',   title: 'New lesson notes policy',     body: 'Please submit lesson notes within 24 hours of each completed class. See the updated guidelines.', isRead: false, createdAt: '2026-06-01T09:00:00Z', targetRole: 'TEACHER' },
  { id: 'n8',  type: 'homework',       title: 'Pending homework reviews',    body: 'You have 3 submitted homework assignments awaiting your review.', isRead: false, createdAt: '2026-06-01T11:00:00Z', targetUserId: 'u2', link: '/homework' },
  { id: 'n9',  type: 'reschedule',     title: 'Class rescheduled',           body: 'Emma Santos has requested to reschedule her Jun 5 session. Please confirm the new time.', isRead: true,  createdAt: '2026-05-29T16:00:00Z', targetUserId: 'u2' },
  // Admin
  { id: 'n10', type: 'system',         title: '2 inactive accounts',         body: '2 student accounts have been inactive for over 30 days. Review them in user management.', isRead: false, createdAt: '2026-06-01T07:00:00Z', targetRole: 'ADMIN', link: '/admin/users' },
  { id: 'n11', type: 'announcement',   title: 'End-of-month payroll due',    body: 'Teacher payroll submissions are due by Jun 5. Remind teachers to submit their invoices.', isRead: false, createdAt: '2026-06-01T08:30:00Z', targetRole: 'ADMIN' },
  // Sarah Lim (u6)
  { id: 'n12', type: 'class_reminder', title: 'Class in 1 hour',             body: 'General English with Yuki Tanaka starts at 9:00 AM.', isRead: false, createdAt: '2026-06-02T00:00:00Z', targetUserId: 'u6', link: '/lessons' },
  // Marco / u11
  { id: 'n13', type: 'homework',       title: 'New homework assigned',       body: 'Your teacher has assigned new homework: "Practice introductions". Due Jun 5.', isRead: false, createdAt: '2026-06-01T12:00:00Z', targetUserId: 'u11', link: '/homework' },
  { id: 'n14', type: 'class_reminder', title: 'Class tomorrow at 9:00 AM',  body: 'General English with Sarah Lim is scheduled for tomorrow at 9:00 AM.', isRead: true,  createdAt: '2026-06-01T08:00:00Z', targetUserId: 'u11' },
]

const MOCK_ANNOUNCEMENTS: Announcement[] = [
  {
    id: 'a1', title: 'New lesson notes policy', status: 'active',
    body: 'Effective immediately, all lesson notes must be submitted within 24 hours of a completed class. Late submissions will be flagged for admin review.',
    targetType: 'role', targetRole: 'TEACHER',
    publishedAt: '2026-06-01T09:00:00Z', createdById: 'u3', createdByName: 'Admin',
  },
  {
    id: 'a2', title: 'New course materials added to library', status: 'active',
    body: 'We have added 5 new worksheets and 2 pronunciation guides to the Materials Library. Log in and check the Materials page to access them.',
    targetType: 'role', targetRole: 'STUDENT',
    publishedAt: '2026-05-30T09:00:00Z', createdById: 'u3', createdByName: 'Admin',
  },
  {
    id: 'a3', title: 'Platform maintenance — Jun 10', status: 'active',
    body: 'The Tutorvio portal will undergo scheduled maintenance on June 10, 2026 from 2:00 AM to 4:00 AM PHT. Please plan your sessions accordingly.',
    targetType: 'ALL',
    publishedAt: '2026-06-01T08:00:00Z', createdById: 'u3', createdByName: 'Admin',
  },
  {
    id: 'a4', title: 'End-of-month payroll due Jun 5', status: 'active',
    body: 'All teachers should submit their June payroll invoices by June 5. Contact admin if you have questions about the cut-off period.',
    targetType: 'role', targetRole: 'TEACHER',
    publishedAt: '2026-06-01T08:30:00Z', createdById: 'u3', createdByName: 'Admin',
  },
  {
    id: 'a5', title: 'Business English cohort — updated materials', status: 'active',
    body: 'New resources have been attached to the Business English course. Please review the updated Workbook Module 1 before your next session.',
    targetType: 'course', targetCourseId: 'c2',
    publishedAt: '2026-05-28T10:00:00Z', createdById: 'u3', createdByName: 'Admin',
  },
  {
    id: 'a6', title: 'Summer schedule changes', status: 'scheduled',
    body: 'Starting July 1, Tutorvio will adjust operating hours for the summer period. Details will be shared closer to the date.',
    targetType: 'ALL',
    scheduledAt: '2026-06-25T09:00:00Z',
    publishedAt: '2026-06-25T09:00:00Z', createdById: 'u3', createdByName: 'Admin',
  },
  {
    id: 'a7', title: 'March platform update (archived)', status: 'archived',
    body: 'The March 2026 platform update has been released. Highlights include improved lesson detail pages and better attendance tracking.',
    targetType: 'ALL',
    publishedAt: '2026-03-01T09:00:00Z', createdById: 'u3', createdByName: 'Admin',
  },
]

export const useNotificationsStore = defineStore('notifications', () => {
  const notifications = ref<AppNotification[]>(MOCK_NOTIFICATIONS.map(n => ({ ...n })))
  const announcements = ref<Announcement[]>(MOCK_ANNOUNCEMENTS.map(a => ({ ...a })))

  // ── Notifications ──
  function getNotificationsForUser(userId: string, role: UserRole): AppNotification[] {
    return notifications.value.filter(n => {
      if (n.targetUserId && n.targetUserId !== userId) return false
      if (n.targetRole && n.targetRole !== 'ALL' && n.targetRole !== role && !n.targetUserId) return false
      return true
    }).sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
  }

  function getUnreadCount(userId: string, role: UserRole): number {
    return getNotificationsForUser(userId, role).filter(n => !n.isRead).length
  }

  function markAsRead(id: string): void {
    const n = notifications.value.find(x => x.id === id)
    if (n) n.isRead = true
  }

  function markAllAsRead(userId: string, role: UserRole): void {
    getNotificationsForUser(userId, role).forEach(n => { n.isRead = true })
  }

  // ── Announcements ──
  function getAnnouncementsForUser(userId: string, role: UserRole, enrolledCourseIds: string[] = []): Announcement[] {
    return announcements.value.filter(a => {
      if (a.status !== 'active') return false
      if (a.targetType === 'ALL') return true
      if (a.targetType === 'role' && a.targetRole === role) return true
      if (a.targetType === 'teacher_group' && role === 'TEACHER') return true
      if (a.targetType === 'student_group' && role === 'STUDENT') return true
      if (a.targetType === 'user' && a.targetUserId === userId) return true
      if (a.targetType === 'course' && a.targetCourseId && enrolledCourseIds.includes(a.targetCourseId)) return true
      return false
    }).sort((a, b) => new Date(b.publishedAt).getTime() - new Date(a.publishedAt).getTime())
  }

  function getAllAnnouncements(): Announcement[] {
    return [...announcements.value].sort((a, b) => new Date(b.publishedAt).getTime() - new Date(a.publishedAt).getTime())
  }

  function createAnnouncement(data: Omit<Announcement, 'id'>): Announcement {
    const ann: Announcement = { ...data, id: `a${Date.now()}` }
    announcements.value.unshift(ann)
    // Also create notifications for targeted users
    const notif: AppNotification = {
      id: `n${Date.now()}`,
      type: 'announcement',
      title: data.title,
      body: data.body,
      isRead: false,
      createdAt: new Date().toISOString(),
      targetRole: data.targetType === 'ALL' ? 'ALL' : (data.targetRole ?? undefined),
      targetUserId: data.targetUserId,
      targetCourseId: data.targetCourseId,
    }
    notifications.value.unshift(notif)
    return ann
  }

  function archiveAnnouncement(id: string): void {
    const a = announcements.value.find(x => x.id === id)
    if (a) a.status = 'archived'
  }

  function restoreAnnouncement(id: string): void {
    const a = announcements.value.find(x => x.id === id)
    if (a) a.status = 'active'
  }

  return {
    notifications,
    announcements,
    getNotificationsForUser,
    getUnreadCount,
    markAsRead,
    markAllAsRead,
    getAnnouncementsForUser,
    getAllAnnouncements,
    createAnnouncement,
    archiveAnnouncement,
    restoreAnnouncement,
  }
})
