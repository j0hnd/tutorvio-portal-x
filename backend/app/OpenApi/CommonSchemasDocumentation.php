<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UserSummary',
    description: 'Reusable public user summary. The `id` value is the user public ID, not a database primary key.',
    required: ['id', 'name', 'email'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'name', type: 'string', example: 'Alex Student'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex.student@example.com'),
        new OA\Property(property: 'timezone', nullable: true, type: 'string', example: 'Asia/Manila'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'StudentProfile',
    description: 'Student profile fields safe for public API responses.',
    properties: [
        new OA\Property(property: 'english_level', nullable: true, type: 'string', example: 'B1'),
        new OA\Property(property: 'current_level', nullable: true, type: 'string', example: 'Intermediate'),
        new OA\Property(property: 'course', nullable: true, type: 'string', example: 'Business English Foundations'),
        new OA\Property(property: 'assigned_teacher_id', description: 'Teacher public ID.', nullable: true, type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'class_type', nullable: true, type: 'string', example: 'one_on_one'),
        new OA\Property(property: 'start_date', nullable: true, type: 'string', format: 'date', example: '2026-06-15'),
        new OA\Property(property: 'preferences', nullable: true, type: 'string', example: 'Conversation-first classes.'),
        new OA\Property(property: 'goals', nullable: true, type: 'string', example: 'Improve speaking confidence for work calls.'),
        new OA\Property(property: 'learning_concerns', nullable: true, type: 'string', example: 'Needs slower correction pacing.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'TeacherProfile',
    description: 'Teacher profile fields safe for public API responses.',
    properties: [
        new OA\Property(property: 'specialization', nullable: true, type: 'string', example: 'Business English'),
        new OA\Property(property: 'bio', nullable: true, type: 'string', example: 'Experienced business English coach.'),
        new OA\Property(property: 'expertise', nullable: true, type: 'string', example: 'IELTS, business presentations, interview preparation'),
        new OA\Property(property: 'class_load', nullable: true, type: 'integer', example: 18),
        new OA\Property(property: 'teaching_availability', nullable: true, type: 'object', example: ['monday' => ['09:00-12:00'], 'wednesday' => ['13:00-16:00']]),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'StaffProfile',
    description: 'Staff profile fields safe for public API responses.',
    properties: [
        new OA\Property(property: 'department', nullable: true, type: 'string', example: 'Operations'),
        new OA\Property(property: 'access_limitations', nullable: true, type: 'string', example: 'Scheduling and billing only'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AdminProfile',
    description: 'Admin profile fields safe for public API responses.',
    properties: [
        new OA\Property(property: 'department', nullable: true, type: 'string', example: 'Administration'),
        new OA\Property(property: 'role_scope', type: 'string', example: 'platform_admin'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'User',
    description: 'Reusable user response. Relationship IDs are public IDs and sensitive account fields are omitted.',
    required: ['id', 'name', 'email', 'status', 'roles'],
    properties: [
        new OA\Property(property: 'id', description: 'User public ID.', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'name', type: 'string', example: 'Alex Student'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex.student@example.com'),
        new OA\Property(property: 'phone', nullable: true, type: 'string', example: '+15551234567'),
        new OA\Property(property: 'timezone', nullable: true, type: 'string', example: 'Asia/Manila'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'invited', 'suspended'], example: 'active'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string', enum: ['student', 'teacher', 'admin', 'staff']), example: ['student']),
        new OA\Property(property: 'student_profile', ref: '#/components/schemas/StudentProfile', nullable: true),
        new OA\Property(property: 'teacher_profile', ref: '#/components/schemas/TeacherProfile', nullable: true),
        new OA\Property(property: 'staff_profile', ref: '#/components/schemas/StaffProfile', nullable: true),
        new OA\Property(property: 'admin_profile', ref: '#/components/schemas/AdminProfile', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-01T09:30:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Lesson',
    allOf: [new OA\Schema(ref: '#/components/schemas/LessonRecord')]
)]
#[OA\Schema(
    schema: 'Schedule',
    allOf: [new OA\Schema(ref: '#/components/schemas/ClassSchedule')]
)]
#[OA\Schema(
    schema: 'Homework',
    allOf: [new OA\Schema(ref: '#/components/schemas/LearningHomework')]
)]
#[OA\Schema(
    schema: 'Course',
    description: 'Reusable public course response. Admin-only audit fields are intentionally omitted.',
    required: ['id', 'title', 'slug', 'number_of_sessions'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'crs_01J0BUSINESS000000000001'),
        new OA\Property(property: 'course_type_id', nullable: true, type: 'string', example: 'ctp_01J0ENGLISH00000000001'),
        new OA\Property(property: 'title', type: 'string', example: 'Business English Foundations'),
        new OA\Property(property: 'name', description: 'Alias of `title` for frontend compatibility.', type: 'string', example: 'Business English Foundations'),
        new OA\Property(property: 'slug', type: 'string', example: 'business-english-foundations'),
        new OA\Property(property: 'description', nullable: true, type: 'string', example: 'A 12-session path for meetings, presentations, and workplace fluency.'),
        new OA\Property(property: 'placement_level', nullable: true, type: 'string', example: 'B1'),
        new OA\Property(property: 'number_of_sessions', type: 'integer', example: 12),
        new OA\Property(property: 'lesson_structure', type: 'array', items: new OA\Items(type: 'object'), example: [['session' => 1, 'topic' => 'Workplace introductions', 'objective' => 'Introduce role and responsibilities clearly.']]),
        new OA\Property(property: 'milestones', type: 'array', items: new OA\Items(type: 'object'), example: [['session' => 4, 'goal' => 'Complete first progress checkpoint']]),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ProgressSummary',
    description: 'Reusable public progress summary for student learning dashboards and reports.',
    required: ['student_id', 'lesson_completion_count', 'progress_status', 'skill_summaries'],
    properties: [
        new OA\Property(property: 'student_id', description: 'Student user public ID.', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'course_id', nullable: true, type: 'string', example: 'crs_01J0BUSINESS000000000001'),
        new OA\Property(property: 'lesson_completion_count', type: 'integer', example: 8),
        new OA\Property(property: 'progress_status', ref: '#/components/schemas/LearningProgressStatus'),
        new OA\Property(property: 'skill_summaries', type: 'object', example: ['speaking' => 'Uses longer answers with fewer pauses.', 'grammar' => 'Needs support with articles.']),
        new OA\Property(property: 'goals_completed', type: 'array', items: new OA\Items(type: 'string'), example: ['Introduce project status clearly']),
        new OA\Property(property: 'goals_in_progress', type: 'array', items: new OA\Items(type: 'string'), example: ['Ask concise follow-up questions']),
        new OA\Property(property: 'last_recorded_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Notification',
    description: 'Reusable public notification response for notification lists, details, and read-state updates.',
    required: ['id', 'title', 'body', 'message', 'type', 'is_read', 'read_status', 'read_at', 'created_at', 'metadata'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'ntf_01J0NOTIFICATION0000001'),
        new OA\Property(property: 'title', type: 'string', example: 'Holiday schedule update'),
        new OA\Property(property: 'body', type: 'string', example: 'Classes are paused on the public holiday.'),
        new OA\Property(property: 'message', description: 'Alias of `body` for client compatibility.', type: 'string', example: 'Classes are paused on the public holiday.'),
        new OA\Property(property: 'type', type: 'string', enum: ['system', 'class_reminder', 'reschedule_alert', 'homework_reminder', 'renewal_reminder', 'admin_announcement', 'student_teacher_message', 'email', 'in_portal'], example: 'admin_announcement'),
        new OA\Property(property: 'is_read', type: 'boolean', example: false),
        new OA\Property(property: 'read_status', type: 'string', enum: ['read', 'unread'], example: 'unread'),
        new OA\Property(property: 'read_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'published_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'metadata', type: 'object', example: ['announcement_id' => 'ann_01J0ANNOUNCEMENT000000001']),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Announcement',
    description: 'Reusable public announcement response for visible announcement lists and details.',
    required: ['id', 'title', 'content', 'body', 'status', 'scheduled_at', 'published_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'ann_01J0ANNOUNCEMENT000000001'),
        new OA\Property(property: 'title', type: 'string', example: 'Holiday schedule update'),
        new OA\Property(property: 'content', type: 'string', example: 'Classes are paused on the public holiday.'),
        new OA\Property(property: 'body', type: 'string', example: 'Classes are paused on the public holiday.'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'scheduled', 'published', 'archived'], example: 'published'),
        new OA\Property(property: 'scheduled_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'published_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'author', ref: '#/components/schemas/UserSummary', nullable: true),
        new OA\Property(property: 'is_read', type: 'boolean', example: false),
        new OA\Property(property: 'read_status', type: 'string', enum: ['read', 'unread'], example: 'unread'),
        new OA\Property(property: 'read_at', nullable: true, type: 'string', format: 'date-time', example: null),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Invoice',
    description: 'Reusable public invoice response. Gateway payloads, payment method identifiers, and internal payment references are omitted.',
    required: ['id', 'invoice_number', 'subtotal', 'amount', 'tax_amount', 'total_amount', 'currency', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'inv_01J0INVOICE000000000001'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'subscription_id', nullable: true, type: 'string', example: 'sub_01J0PACKAGE000000000001'),
        new OA\Property(property: 'course_program_id', nullable: true, type: 'string', example: 'crs_01J0BUSINESS000000000001'),
        new OA\Property(property: 'invoice_number', type: 'string', example: 'INV-2026-00042'),
        new OA\Property(property: 'reference', type: 'string', example: 'INV-2026-00042'),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 200.00),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 200.00),
        new OA\Property(property: 'tax_amount', type: 'number', format: 'float', example: 20.00),
        new OA\Property(property: 'total_amount', type: 'number', format: 'float', example: 220.00),
        new OA\Property(property: 'currency', type: 'string', minLength: 3, maxLength: 3, example: 'USD'),
        new OA\Property(property: 'issued_date', type: 'string', format: 'date', example: '2026-06-01'),
        new OA\Property(property: 'due_date', type: 'string', format: 'date', example: '2026-06-15'),
        new OA\Property(property: 'paid_date', nullable: true, type: 'string', format: 'date', example: null),
        new OA\Property(property: 'status', ref: '#/components/schemas/InvoiceStatus'),
        new OA\Property(property: 'student', ref: '#/components/schemas/UserSummary', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Package',
    allOf: [new OA\Schema(ref: '#/components/schemas/StudentPackageSummary')]
)]
#[OA\Schema(
    schema: 'Issue',
    description: 'Reusable public-safe issue response. Relationship fields use public IDs.',
    required: ['id', 'type', 'status', 'priority', 'title'],
    properties: [
        new OA\Property(property: 'id', description: 'Issue report public ID.', type: 'string', example: 'isr_01J0ISSUE000000000000001'),
        new OA\Property(property: 'type', ref: '#/components/schemas/IssueType'),
        new OA\Property(property: 'issue_type', ref: '#/components/schemas/IssueType'),
        new OA\Property(property: 'status', ref: '#/components/schemas/IssueStatus'),
        new OA\Property(property: 'priority', ref: '#/components/schemas/IssuePriority'),
        new OA\Property(property: 'reporter_id', nullable: true, type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'assigned_to_id', nullable: true, type: 'string', example: 'usr_01J0STAFF0000000000000001'),
        new OA\Property(property: 'related_student_id', nullable: true, type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'related_teacher_id', nullable: true, type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'lesson_id', nullable: true, type: 'string', example: 'lrn_01J0LESSONRECORD000000001'),
        new OA\Property(property: 'class_schedule_id', nullable: true, type: 'string', example: 'cls_01J0CLASS0000000000000001'),
        new OA\Property(property: 'title', type: 'string', example: 'Student could not join lesson'),
        new OA\Property(property: 'description', nullable: true, type: 'string', example: 'The meeting link showed an access denied error.'),
        new OA\Property(property: 'resolution_notes', nullable: true, type: 'string', example: 'Regenerated the meeting link and confirmed access.'),
        new OA\Property(property: 'resolved_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:15:00Z'),
        new OA\Property(property: 'reporter', ref: '#/components/schemas/UserSummary', nullable: true),
        new OA\Property(property: 'assigned_to', ref: '#/components/schemas/UserSummary', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-01T08:30:00Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-01T09:15:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ValidationError',
    allOf: [new OA\Schema(ref: '#/components/schemas/ValidationErrorResponse')]
)]
#[OA\Schema(
    schema: 'UnauthorizedError',
    allOf: [new OA\Schema(ref: '#/components/schemas/UnauthorizedResponse')]
)]
#[OA\Schema(
    schema: 'ForbiddenError',
    allOf: [new OA\Schema(ref: '#/components/schemas/ForbiddenResponse')]
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    allOf: [new OA\Schema(ref: '#/components/schemas/AdminPaginationMeta')]
)]
class CommonSchemasDocumentation {}
