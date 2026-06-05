<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Communication',
    description: 'Notifications, announcements, announcement targeting, scheduled publication, archive behavior, legacy student-teacher message threads, and internal chat conversations. All operations require Sanctum bearer authentication. Notification and announcement list/read endpoints are scoped to records visible to the authenticated user. Announcement management requires admin/staff access with `announcements.manage`; notification history requires `notifications.history.view` and is full-history for admins only. Legacy message thread access and internal chat access require `messages.view` or `messages.manage`; internal chat uses public IDs for conversations, messages, attachments, pins, templates, and escalations. Chat reminder automation is represented through reminder templates and reminder/system messages; no separate chat reminder trigger/list HTTP endpoint is currently exposed.'
)]
#[OA\Schema(
    schema: 'CommunicationTargetType',
    description: 'Announcement targeting rule. Empty `targets` defaults to `all`. Target resolution stores concrete announcement recipients when the announcement is created, updated, scheduled, or published. Staff users are included only when they have `dashboard.operational_notices.view`.',
    type: 'string',
    enum: ['all', 'role', 'user', 'course', 'course_type', 'course_program', 'teacher_group', 'student_group', 'class_schedule'],
    example: 'role'
)]
#[OA\Schema(
    schema: 'CommunicationAnnouncementTarget',
    description: 'Announcement target request/response object. `role` targets use a role name. `user` targets use `user_id` or `target_id`. Course/course_type/course_program targets use an internal numeric `target_id`. Teacher and student group targets use `group` or `metadata.group`.',
    properties: [
        new OA\Property(property: 'id', description: 'Returned on admin responses only.', type: 'integer', example: 41),
        new OA\Property(property: 'type', ref: '#/components/schemas/CommunicationTargetType'),
        new OA\Property(property: 'target_type', ref: '#/components/schemas/CommunicationTargetType'),
        new OA\Property(property: 'target_id', nullable: true, type: 'integer', example: 3),
        new OA\Property(property: 'user_id', nullable: true, type: 'integer', example: 23),
        new OA\Property(property: 'role', nullable: true, type: 'string', example: 'student'),
        new OA\Property(property: 'group', nullable: true, type: 'string', example: 'business_english'),
        new OA\Property(property: 'metadata', type: 'object', example: ['group' => 'business_english']),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationAnnouncement',
    description: 'Announcement response. Public announcement endpoints return only published, unarchived announcements whose `published_at` is not in the future and whose recipient set includes the authenticated user. Staff users without `dashboard.operational_notices.view` receive no visible announcements. Admin fields, targets, archive fields, and recipient counts are returned only to admins or staff with `announcements.manage`.',
    required: ['id', 'title', 'content', 'body', 'status', 'scheduled_at', 'published_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'ann_01J0ANNOUNCEMENT000000001'),
        new OA\Property(property: 'title', type: 'string', example: 'Holiday schedule update'),
        new OA\Property(property: 'content', type: 'string', example: 'Classes are paused on the public holiday.'),
        new OA\Property(property: 'body', type: 'string', example: 'Classes are paused on the public holiday.'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'scheduled', 'published', 'archived'], example: 'published'),
        new OA\Property(property: 'scheduled_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'published_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'author', ref: '#/components/schemas/LearningUserSummary'),
        new OA\Property(property: 'is_read', description: 'Present on user-facing announcement responses when read state is loaded.', type: 'boolean', example: false),
        new OA\Property(property: 'read_status', description: 'Read/unread response format for announcement read state.', type: 'string', enum: ['read', 'unread'], example: 'unread'),
        new OA\Property(property: 'read_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'archived_at', description: 'Admin field. Set when an announcement is archived.', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'created_by', description: 'Admin field. Author public ID.', nullable: true, type: 'string', example: 'usr_01J0ADMIN000000000000001'),
        new OA\Property(property: 'archived_by', description: 'Admin field. Archiving actor public ID.', nullable: true, type: 'string', example: null),
        new OA\Property(property: 'recipient_count', description: 'Admin field. Count of users resolved from the current targeting rules.', type: 'integer', example: 42),
        new OA\Property(property: 'targets', type: 'array', items: new OA\Items(ref: '#/components/schemas/CommunicationAnnouncementTarget')),
        new OA\Property(property: 'created_at', description: 'Admin field.', type: 'string', format: 'date-time', example: '2026-06-01T08:30:00Z'),
        new OA\Property(property: 'updated_at', description: 'Admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationAnnouncementRequest',
    description: 'Create or update an announcement. Scheduling fields: set `status` to `scheduled` and provide future `scheduled_at`; set `status` to `published` to publish immediately; set `status` to `draft` to clear scheduled and published timestamps. `archived` is not accepted here; use the archive endpoint.',
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Holiday schedule update'),
        new OA\Property(property: 'content', description: 'Required when `body` is omitted.', type: 'string', example: 'Classes are paused on the public holiday.'),
        new OA\Property(property: 'body', description: 'Alias accepted when `content` is omitted.', type: 'string', example: 'Classes are paused on the public holiday.'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'scheduled', 'published'], example: 'scheduled'),
        new OA\Property(property: 'scheduled_at', description: 'Required and must be in the future when `status` is `scheduled`.', nullable: true, type: 'string', format: 'date-time', example: '2026-06-05T09:00:00Z'),
        new OA\Property(property: 'targets', type: 'array', items: new OA\Items(ref: '#/components/schemas/CommunicationAnnouncementTarget'), example: [['type' => 'role', 'role' => 'student']]),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationNotification',
    description: 'Notification recipient response. User-facing notification endpoints return in-portal recipients for the authenticated user only, excluding archived notifications and future unpublished notifications. Admin history includes all non-archived recipients. Staff history is scoped to the authenticated staff user. History responses add recipient/channel/delivery fields when the actor has `notifications.history.view`.',
    required: ['id', 'title', 'body', 'message', 'type', 'is_read', 'read_status', 'read_at', 'created_at', 'published_at', 'metadata'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'ntf_01J0NOTIFICATION0000001'),
        new OA\Property(property: 'title', type: 'string', example: 'Holiday schedule update'),
        new OA\Property(property: 'body', type: 'string', example: 'Classes are paused on the public holiday.'),
        new OA\Property(property: 'message', description: 'Alias of `body` for client compatibility.', type: 'string', example: 'Classes are paused on the public holiday.'),
        new OA\Property(property: 'type', type: 'string', enum: ['system', 'class_reminder', 'reschedule_alert', 'homework_reminder', 'renewal_reminder', 'admin_announcement', 'student_teacher_message', 'email', 'in_portal'], example: 'admin_announcement'),
        new OA\Property(property: 'is_read', type: 'boolean', example: false),
        new OA\Property(property: 'read_status', description: 'Read/unread response format for notification state.', type: 'string', enum: ['read', 'unread'], example: 'unread'),
        new OA\Property(property: 'read_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'published_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'metadata', type: 'object', example: ['announcement_id' => 'ann_01J0ANNOUNCEMENT000000001']),
        new OA\Property(property: 'recipient_user_id', description: 'Admin history field. Recipient user public ID.', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'channel', description: 'Admin history field. Email notifications are represented as recipients with `channel=email`; no standalone email notification settings API is currently implemented.', type: 'string', enum: ['in_portal', 'email'], example: 'in_portal'),
        new OA\Property(property: 'delivery_status', description: 'Admin history field.', type: 'string', enum: ['pending', 'sending', 'sent', 'delivered', 'failed'], example: 'delivered'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationMessageThread',
    description: 'Student-teacher message thread response. Students and teachers see only their own participant threads. Admins and staff with `messages.view` or `messages.manage` can view all threads. Sending requires an active thread; non-admin/non-managing users must be participants.',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'mth_01J0THREAD000000000001'),
        new OA\Property(property: 'title', nullable: true, type: 'string', example: 'Lesson follow-up'),
        new OA\Property(property: 'thread_type', type: 'string', enum: ['student_teacher'], example: 'student_teacher'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'closed'], example: 'active'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'last_message_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:10:00Z'),
        new OA\Property(property: 'is_archived', type: 'boolean', example: false),
        new OA\Property(property: 'archived_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'unread_count', description: 'Count of messages in the thread sent by other users after the actor participant `last_read_at`.', type: 'integer', example: 2),
        new OA\Property(property: 'participants', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'latest_message', ref: '#/components/schemas/CommunicationMessage'),
        new OA\Property(property: 'created_by', description: 'Admin field. Internal numeric creator ID.', type: 'integer', example: 23),
        new OA\Property(property: 'metadata', description: 'Admin field.', type: 'object', example: []),
        new OA\Property(property: 'created_at', description: 'Admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'updated_at', description: 'Admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:10:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationMessage',
    properties: [
        new OA\Property(property: 'thread_id', type: 'string', example: 'mth_01J0THREAD000000000001'),
        new OA\Property(property: 'sender_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'sender', ref: '#/components/schemas/LearningUserSummary'),
        new OA\Property(property: 'body', type: 'string', example: 'Can you review my homework before class?'),
        new OA\Property(property: 'message_type', type: 'string', enum: ['student_teacher_message'], example: 'student_teacher_message'),
        new OA\Property(property: 'sent_at', type: 'string', format: 'date-time', example: '2026-06-01T09:10:00Z'),
        new OA\Property(property: 'edited_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'archived_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'metadata', description: 'Admin field.', type: 'object', example: []),
        new OA\Property(property: 'created_at', description: 'Admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:10:00Z'),
        new OA\Property(property: 'updated_at', description: 'Admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:10:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationConversation',
    description: 'Internal chat conversation response. All relationship IDs are public IDs. Students and teachers see active participant conversations only; admins and staff with `messages.view` or `messages.manage` can view all conversations and receive admin audit fields.',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'cnv_01J0CHAT000000000000001'),
        new OA\Property(property: 'type', type: 'string', enum: ['student_teacher', 'teacher_admin', 'admin_student', 'group_course', 'announcement_thread'], example: 'student_teacher'),
        new OA\Property(property: 'title', nullable: true, type: 'string', example: 'Lesson follow-up'),
        new OA\Property(property: 'display_title', nullable: true, type: 'string', example: 'Taylor Teacher'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'archived', 'closed'], example: 'active'),
        new OA\Property(property: 'is_archived', type: 'boolean', example: false),
        new OA\Property(property: 'is_closed', type: 'boolean', example: false),
        new OA\Property(property: 'student_id', nullable: true, type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', nullable: true, type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'course_program_id', nullable: true, type: 'string', example: 'crs_01J0BUSINESS000000000001'),
        new OA\Property(property: 'last_message_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:10:00Z'),
        new OA\Property(property: 'last_message_by', nullable: true, type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'last_message_preview', nullable: true, type: 'string', example: 'Can you review my homework before class?'),
        new OA\Property(property: 'last_message_metadata', type: 'object', example: ['message_id' => 'msg_01J0CHATMSG000000000001', 'message_type' => 'text', 'has_attachments' => false, 'has_links' => false]),
        new OA\Property(property: 'metadata', description: 'Returned to conversation viewers; clients should avoid sending sensitive internal data.', type: 'object', example: []),
        new OA\Property(property: 'unread_count', type: 'integer', example: 2),
        new OA\Property(property: 'unread_message_count', type: 'integer', example: 2),
        new OA\Property(property: 'is_pinned', description: 'Actor participant-level conversation pin state from participant metadata.', type: 'boolean', example: false),
        new OA\Property(property: 'pinned_messages_summary', type: 'object', example: ['count' => 1, 'latest' => ['id' => 'pin_01J0CHATPIN000000000001', 'message_id' => 'msg_01J0CHATMSG000000000001', 'pinned_by' => 'usr_01J0TEACHER000000000000001', 'pinned_at' => '2026-06-01T09:12:00Z', 'body_preview' => 'Can you review my homework before class?']]),
        new OA\Property(property: 'participant_summary', type: 'object', example: ['total' => 2, 'preview' => [['user_id' => 'usr_01J0TEACHER000000000000001', 'name' => 'Taylor Teacher', 'participant_role' => 'teacher']]]),
        new OA\Property(property: 'participants', type: 'array', items: new OA\Items(type: 'object'), example: [['user_id' => 'usr_01J0STUDENT000000000000001', 'participant_role' => 'student', 'participant_role_snapshot' => 'student', 'participant_roles_snapshot' => ['student'], 'joined_at' => '2026-06-01T09:00:00Z', 'last_read_at' => '2026-06-01T09:10:00Z', 'last_read_message_id' => 'msg_01J0CHATMSG000000000001', 'muted_at' => null, 'archived_at' => null, 'user' => ['id' => 'usr_01J0STUDENT000000000000001', 'name' => 'Alex Student', 'email' => 'alex.student@example.com']]]),
        new OA\Property(property: 'permission_metadata', type: 'object', example: ['current_user_id' => 'usr_01J0STUDENT000000000000001', 'is_participant' => true, 'participant_role' => 'student', 'can_send_messages' => true, 'can_upload_files' => true, 'can_pin_messages' => false, 'can_close_conversation' => false, 'can_archive_conversation' => false]),
        new OA\Property(property: 'created_by', description: 'Admin/staff message permission field. Creator public ID.', nullable: true, type: 'string', example: 'usr_01J0ADMIN000000000000001'),
        new OA\Property(property: 'created_at', description: 'Admin/staff message permission field.', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'updated_at', description: 'Admin/staff message permission field.', type: 'string', format: 'date-time', example: '2026-06-01T09:10:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationConversationMessage',
    description: 'Internal chat message response. File attachments are represented by public attachment IDs plus download/preview endpoints; storage disk and file path are never exposed.',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'msg_01J0CHATMSG000000000001'),
        new OA\Property(property: 'conversation_id', type: 'string', example: 'cnv_01J0CHAT000000000000001'),
        new OA\Property(property: 'sender_id', nullable: true, type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'sender', ref: '#/components/schemas/UserSummary', nullable: true),
        new OA\Property(property: 'body', nullable: true, type: 'string', example: 'Can you review my homework before class?'),
        new OA\Property(property: 'links', type: 'array', items: new OA\Items(type: 'string', format: 'uri'), example: ['https://example.com/homework-notes']),
        new OA\Property(property: 'attachments', type: 'array', items: new OA\Items(ref: '#/components/schemas/CommunicationConversationAttachment')),
        new OA\Property(property: 'message_type', type: 'string', enum: ['text', 'system', 'reminder'], example: 'text'),
        new OA\Property(property: 'is_system_message', type: 'boolean', example: false),
        new OA\Property(property: 'is_reminder_message', type: 'boolean', example: false),
        new OA\Property(property: 'reminder_type', nullable: true, type: 'string', example: 'lesson_reminder'),
        new OA\Property(property: 'status', type: 'string', enum: ['sent', 'delivered', 'read', 'failed'], example: 'sent'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-01T09:10:00Z'),
        new OA\Property(property: 'edited_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'deleted_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'metadata', description: 'Admin/staff with `messages.manage` only.', type: 'object', example: []),
        new OA\Property(property: 'updated_at', description: 'Admin/staff with `messages.manage` only.', type: 'string', format: 'date-time', example: '2026-06-01T09:10:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationConversationAttachment',
    description: 'Internal chat attachment. Uploads are accepted on `POST /conversations/{conversation}/messages` as multipart `files[]`; allowed files are PDF, JPG/JPEG, PNG, WebP, or GIF, up to 10 MB each by default, with at most 5 files and 20 links per message unless deployment config overrides those limits.',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'att_01J0CHATATTACH000000001'),
        new OA\Property(property: 'type', type: 'string', enum: ['file', 'link'], example: 'file'),
        new OA\Property(property: 'title', nullable: true, type: 'string', example: 'homework.pdf'),
        new OA\Property(property: 'url', nullable: true, type: 'string', format: 'uri', example: null),
        new OA\Property(property: 'original_filename', nullable: true, type: 'string', example: 'homework.pdf'),
        new OA\Property(property: 'mime_type', nullable: true, type: 'string', example: 'application/pdf'),
        new OA\Property(property: 'file_size', nullable: true, type: 'integer', example: 245760),
        new OA\Property(property: 'download', nullable: true, type: 'object', example: ['endpoint' => 'http://localhost:8001/api/v1/conversations/cnv_01J0CHAT000000000000001/messages/msg_01J0CHATMSG000000000001/attachments/att_01J0CHATATTACH000000001/download']),
        new OA\Property(property: 'preview', nullable: true, type: 'object', example: ['endpoint' => 'http://localhost:8001/api/v1/conversations/cnv_01J0CHAT000000000000001/messages/msg_01J0CHATMSG000000000001/attachments/att_01J0CHATATTACH000000001/preview']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-01T09:10:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationConversationMessagePin',
    description: 'Pinned internal chat message. Pin IDs, conversation IDs, message IDs, and user IDs are public IDs.',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'pin_01J0CHATPIN000000000001'),
        new OA\Property(property: 'conversation_id', type: 'string', example: 'cnv_01J0CHAT000000000000001'),
        new OA\Property(property: 'message_id', type: 'string', example: 'msg_01J0CHATMSG000000000001'),
        new OA\Property(property: 'pinned_by', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'pinned_by_user', ref: '#/components/schemas/UserSummary'),
        new OA\Property(property: 'pinned_at', type: 'string', format: 'date-time', example: '2026-06-01T09:12:00Z'),
        new OA\Property(property: 'message', ref: '#/components/schemas/CommunicationConversationMessage'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationMessageTemplate',
    description: 'Quick message template response. User-facing template endpoints return active templates visible to at least one actor role and either global templates or templates owned by the actor teacher. Admin fields require `message_templates.view`.',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'mtp_01J0TEMPLATE000000000001'),
        new OA\Property(property: 'title', type: 'string', example: 'Lesson reminder'),
        new OA\Property(property: 'body', type: 'string', example: 'Hi! This is a quick reminder about your upcoming lesson.'),
        new OA\Property(property: 'category', type: 'string', enum: ['lesson_reminder', 'homework_reminder', 'reschedule_notice', 'payment_reminder', 'attendance_follow_up', 'progress_check_in'], example: 'lesson_reminder'),
        new OA\Property(property: 'role_visibility', type: 'array', items: new OA\Items(type: 'string', enum: ['admin', 'staff', 'teacher', 'student']), example: ['admin', 'staff', 'teacher']),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], example: 'active'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'teacher_id', nullable: true, type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'teacher', ref: '#/components/schemas/UserSummary', nullable: true),
        new OA\Property(property: 'created_by', description: 'Admin field.', nullable: true, type: 'string', example: 'usr_01J0ADMIN000000000000001'),
        new OA\Property(property: 'updated_by', description: 'Admin field.', nullable: true, type: 'string', example: 'usr_01J0ADMIN000000000000001'),
        new OA\Property(property: 'created_at', description: 'Admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
        new OA\Property(property: 'updated_at', description: 'Admin field.', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CommunicationConversationEscalation',
    description: 'Internal chat escalation. Students may escalate their own student conversation. Teachers may escalate assigned-student conversations. Admins and permitted staff review the queue through `/admin/chat-escalations`.',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'ces_01J0CHATESCL00000000001'),
        new OA\Property(property: 'conversation_id', type: 'string', example: 'cnv_01J0CHAT000000000000001'),
        new OA\Property(property: 'message_id', nullable: true, type: 'string', example: 'msg_01J0CHATMSG000000000001'),
        new OA\Property(property: 'issue_report_id', nullable: true, type: 'string', example: 'isr_01J0ISSUE000000000000001'),
        new OA\Property(property: 'status', type: 'string', enum: ['open', 'in_review', 'resolved', 'dismissed'], example: 'open'),
        new OA\Property(property: 'reason', type: 'string', example: 'Student reported repeated access problems in this lesson chat.'),
        new OA\Property(property: 'notes', nullable: true, type: 'string', example: 'Please review before the next scheduled class.'),
        new OA\Property(property: 'escalated_by', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-01T09:15:00Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-01T09:15:00Z'),
        new OA\Property(property: 'review_notes', description: 'Review queue field.', nullable: true, type: 'string', example: 'Linked to the existing issue report.'),
        new OA\Property(property: 'reviewed_by', description: 'Review queue field.', nullable: true, type: 'string', example: 'usr_01J0STAFF0000000000000001'),
        new OA\Property(property: 'reviewed_at', description: 'Review queue field.', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T10:00:00Z'),
        new OA\Property(property: 'resolved_at', description: 'Review queue field.', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'dismissed_at', description: 'Review queue field.', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'metadata', description: 'Review queue field.', type: 'object', example: []),
        new OA\Property(property: 'conversation', ref: '#/components/schemas/CommunicationConversation'),
        new OA\Property(property: 'message', ref: '#/components/schemas/CommunicationConversationMessage'),
    ],
    type: 'object'
)]
#[OA\Get(
    path: '/notifications',
    operationId: 'communicationNotificationsList',
    summary: 'List visible notifications',
    description: 'Access: any authenticated user. Visibility: only the authenticated user\'s in-portal notification recipients are returned, and only when the notification is published, not archived, and not scheduled for the future. Read/unread filters accept `status=read|unread` or boolean `read`/`unread`; `read` and `unread` cannot both be true.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['read', 'unread']), example: 'unread'),
        new OA\Parameter(name: 'read', in: 'query', schema: new OA\Schema(type: 'boolean'), example: false),
        new OA\Parameter(name: 'unread', in: 'query', schema: new OA\Schema(type: 'boolean'), example: true),
        new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string'), example: 'admin_announcement'),
        new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'published_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'published_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated notifications.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'ntf_01J0NOTIFICATION0000001', 'title' => 'Holiday schedule update', 'body' => 'Classes are paused on the public holiday.', 'message' => 'Classes are paused on the public holiday.', 'type' => 'admin_announcement', 'is_read' => false, 'read_status' => 'unread', 'read_at' => null, 'created_at' => '2026-06-01T09:00:00Z', 'published_at' => '2026-06-01T09:00:00Z', 'metadata' => ['announcement_id' => 'ann_01J0ANNOUNCEMENT000000001']]], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/notifications/history',
    operationId: 'communicationNotificationHistory',
    summary: 'Notification delivery history',
    description: 'Access: admin, or staff with `notifications.history.view`. Visibility: admins see all non-archived notification recipients; staff see only notification recipients addressed to themselves. Includes `in_portal` and `email` channels. Email notification settings endpoints are not implemented; email delivery appears here as channel and delivery status history.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Authorization'],
    parameters: [
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['read', 'unread']), example: 'read'),
        new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string'), example: 'email'),
        new OA\Parameter(name: 'created_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'created_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated notification recipient history with recipient, channel, and delivery status fields.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'ntf_01J0EMAIL0000000000001', 'title' => 'Invoice sent', 'body' => 'Your invoice is ready.', 'message' => 'Your invoice is ready.', 'type' => 'email', 'is_read' => true, 'read_status' => 'read', 'read_at' => '2026-06-01T10:00:00Z', 'recipient_user_id' => 'usr_01J0STUDENT000000000000001', 'channel' => 'email', 'delivery_status' => 'sent', 'metadata' => []]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/notifications/unread-count',
    operationId: 'communicationNotificationsUnreadCount',
    summary: 'Notification unread count',
    description: 'Access: any authenticated user. Counts visible, in-portal notification recipients for the authenticated user where `read_at` is null.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    responses: [
        new OA\Response(response: 200, description: 'Unread notification count.', content: new OA\JsonContent(type: 'object', example: ['data' => ['unread_count' => 3]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
    ]
)]
#[OA\Post(
    path: '/notifications/mark-all-read',
    operationId: 'communicationNotificationsMarkAllRead',
    summary: 'Mark all visible notifications read',
    description: 'Access: any authenticated user. Updates only the authenticated user\'s visible in-portal notification recipients. Response read/unread format includes `marked_read_count` and the shared `read_at` timestamp.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    responses: [
        new OA\Response(response: 200, description: 'Notifications were marked read.', content: new OA\JsonContent(type: 'object', example: ['data' => ['marked_read_count' => 3, 'read_at' => '2026-06-01T10:00:00Z']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
    ]
)]
#[OA\Get(
    path: '/notifications/{notification}',
    operationId: 'communicationNotificationShow',
    summary: 'Show visible notification',
    description: 'Access: any authenticated user. The notification must have an in-portal recipient for the authenticated user, must be published, and must not be archived.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'notification', in: 'path', required: true, description: 'Notification public ID.', schema: new OA\Schema(type: 'string'), example: 'ntf_01J0NOTIFICATION0000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Notification.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ntf_01J0NOTIFICATION0000001', 'title' => 'Holiday schedule update', 'body' => 'Classes are paused on the public holiday.', 'message' => 'Classes are paused on the public holiday.', 'type' => 'admin_announcement', 'is_read' => false, 'read_status' => 'unread', 'read_at' => null, 'metadata' => []]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Notification is not visible to the authenticated user.'),
    ]
)]
#[OA\Post(
    path: '/notifications/{notification}/read',
    operationId: 'communicationNotificationMarkRead',
    summary: 'Mark notification read',
    description: 'Access: any authenticated user. Marks the authenticated user\'s visible notification recipient read and returns the notification with `is_read=true`, `read_status=read`, and `read_at` populated.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'notification', in: 'path', required: true, description: 'Notification public ID.', schema: new OA\Schema(type: 'string'), example: 'ntf_01J0NOTIFICATION0000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Notification marked read.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ntf_01J0NOTIFICATION0000001', 'is_read' => true, 'read_status' => 'read', 'read_at' => '2026-06-01T10:00:00Z']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Notification is not visible to the authenticated user.'),
    ]
)]
#[OA\Get(
    path: '/announcements',
    operationId: 'communicationAnnouncementsList',
    summary: 'List visible announcements',
    description: 'Access: any authenticated user. Visibility: only published, unarchived announcements with `published_at <= now()` and a resolved recipient row for the authenticated user. Staff users also need `dashboard.operational_notices.view`; without it they receive an empty list. Read/unread filters use `status=read|unread` or boolean `read`/`unread`.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), example: 'holiday'),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['read', 'unread']), example: 'unread'),
        new OA\Parameter(name: 'read', in: 'query', schema: new OA\Schema(type: 'boolean'), example: false),
        new OA\Parameter(name: 'unread', in: 'query', schema: new OA\Schema(type: 'boolean'), example: true),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated visible announcements.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'ann_01J0ANNOUNCEMENT000000001', 'title' => 'Holiday schedule update', 'content' => 'Classes are paused on the public holiday.', 'body' => 'Classes are paused on the public holiday.', 'status' => 'published', 'scheduled_at' => null, 'published_at' => '2026-06-01T09:00:00Z', 'is_read' => false, 'read_status' => 'unread', 'read_at' => null]], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/announcements/unread-count',
    operationId: 'communicationAnnouncementsUnreadCount',
    summary: 'Announcement unread count',
    description: 'Access: any authenticated user. Counts visible announcements without a non-null read state for the actor. Staff users without `dashboard.operational_notices.view` receive zero.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    responses: [
        new OA\Response(response: 200, description: 'Unread announcement count.', content: new OA\JsonContent(type: 'object', example: ['data' => ['unread_count' => 2]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
    ]
)]
#[OA\Post(
    path: '/announcements/mark-all-read',
    operationId: 'communicationAnnouncementsMarkAllRead',
    summary: 'Mark all visible announcements read',
    description: 'Access: any authenticated user. Creates or updates read-state rows for all visible unread announcements. Staff users without `dashboard.operational_notices.view` update zero rows.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    responses: [
        new OA\Response(response: 200, description: 'Announcements marked read.', content: new OA\JsonContent(type: 'object', example: ['data' => ['marked_read_count' => 2, 'read_at' => '2026-06-01T10:00:00Z']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
    ]
)]
#[OA\Get(
    path: '/announcements/{announcement}',
    operationId: 'communicationAnnouncementShow',
    summary: 'Show visible announcement',
    description: 'Access: any authenticated user. The announcement must be published, unarchived, already published by timestamp, and targeted to the authenticated user. Otherwise the API returns 404.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Visible announcement.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationAnnouncement')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Announcement is not visible to the authenticated user.'),
    ]
)]
#[OA\Post(
    path: '/announcements/{announcement}/read',
    operationId: 'communicationAnnouncementMarkRead',
    summary: 'Mark announcement read',
    description: 'Access: any authenticated user. Creates or updates the actor read state and returns the announcement with `is_read=true`, `read_status=read`, and `read_at` populated.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Announcement marked read.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ann_01J0ANNOUNCEMENT000000001', 'is_read' => true, 'read_status' => 'read', 'read_at' => '2026-06-01T10:00:00Z']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Announcement is not visible to the authenticated user.'),
    ]
)]
#[OA\Post(
    path: '/announcements/{announcement}/unread',
    operationId: 'communicationAnnouncementMarkUnread',
    summary: 'Mark announcement unread',
    description: 'Access: any authenticated user. Creates or updates the actor read state with `read_at=null` and returns the announcement with `is_read=false` and `read_status=unread`.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Announcement marked unread.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ann_01J0ANNOUNCEMENT000000001', 'is_read' => false, 'read_status' => 'unread', 'read_at' => null]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Announcement is not visible to the authenticated user.'),
    ]
)]
#[OA\Get(
    path: '/admin/announcements',
    operationId: 'communicationAdminAnnouncementsList',
    summary: 'Admin list announcements',
    description: 'Access: admin or staff with `announcements.manage`. Defaults exclude archived announcements. Use `include_archived=true`, `only_archived=true`, or `status=archived` to inspect archived records.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['draft', 'scheduled', 'published', 'archived']), example: 'scheduled'),
        new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), example: 'holiday'),
        new OA\Parameter(name: 'include_archived', in: 'query', schema: new OA\Schema(type: 'boolean'), example: false),
        new OA\Parameter(name: 'only_archived', in: 'query', schema: new OA\Schema(type: 'boolean'), example: false),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated admin announcements.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'ann_01J0ANNOUNCEMENT000000001', 'title' => 'Holiday schedule update', 'status' => 'scheduled', 'scheduled_at' => '2026-06-05T09:00:00Z', 'published_at' => null, 'recipient_count' => 42]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/admin/announcements',
    operationId: 'communicationAdminAnnouncementCreate',
    summary: 'Create announcement',
    description: 'Access: admin or staff with `announcements.manage`. Targeting rules: empty `targets` means all active users; role/user/course/course_type/course_program/group targets are unioned and de-duplicated into concrete recipients. Staff recipients are excluded unless they have `dashboard.operational_notices.view`. If created as `published`, notifications are sent to resolved recipients. If created as `scheduled`, `scheduled_at` must be in the future.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CommunicationAnnouncementRequest')),
    responses: [
        new OA\Response(response: 201, description: 'Announcement created.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ann_01J0ANNOUNCEMENT000000001', 'title' => 'Holiday schedule update', 'status' => 'scheduled', 'scheduled_at' => '2026-06-05T09:00:00Z', 'published_at' => null, 'recipient_count' => 42, 'targets' => [['id' => 41, 'type' => 'role', 'role' => 'student']]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/admin/announcements/{announcement}',
    operationId: 'communicationAdminAnnouncementShow',
    summary: 'Admin show announcement',
    description: 'Access: admin or staff with `announcements.manage`. Includes targets, recipient count, archive fields, author, and audit timestamps.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Announcement.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ann_01J0ANNOUNCEMENT000000001', 'title' => 'Holiday schedule update', 'status' => 'published', 'targets' => [['type' => 'role', 'role' => 'student']], 'recipient_count' => 42]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/admin/announcements/{announcement}',
    operationId: 'communicationAdminAnnouncementUpdate',
    summary: 'Update announcement',
    description: 'Access: admin or staff with `announcements.manage`. Archived announcements cannot be modified. Updating `targets` replaces existing targets and resyncs recipients. Setting `status=scheduled` requires a future `scheduled_at`; setting `status=published` publishes immediately if not already published.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CommunicationAnnouncementRequest')),
    responses: [
        new OA\Response(response: 200, description: 'Announcement updated.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationAnnouncement')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Put(
    path: '/admin/announcements/{announcement}',
    operationId: 'communicationAdminAnnouncementReplace',
    summary: 'Update announcement with PUT',
    description: 'Access and behavior match `PATCH /admin/announcements/{announcement}`.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CommunicationAnnouncementRequest')),
    responses: [
        new OA\Response(response: 200, description: 'Announcement updated.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationAnnouncement')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/admin/announcements/{announcement}/schedule',
    operationId: 'communicationAdminAnnouncementSchedule',
    summary: 'Schedule announcement publication',
    description: 'Access: admin or staff with `announcements.manage`. Archived announcements cannot be scheduled. Sets `status=scheduled`, stores future `scheduled_at`, clears `published_at`, and resyncs recipients for targeting visibility.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['scheduled_at'], properties: [new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time', example: '2026-06-05T09:00:00Z')], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Announcement scheduled.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationAnnouncement')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/admin/announcements/{announcement}/publish',
    operationId: 'communicationAdminAnnouncementPublish',
    summary: 'Publish announcement now',
    description: 'Access: admin or staff with `announcements.manage`. Archived announcements cannot be published. Sets `status=published`, sets `published_at=now`, clears `scheduled_at`, resyncs recipients, and sends announcement notifications.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Announcement published.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationAnnouncement')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/admin/announcements/{announcement}/unpublish',
    operationId: 'communicationAdminAnnouncementUnpublish',
    summary: 'Unpublish announcement',
    description: 'Access: admin or staff with `announcements.manage`. Archived announcements cannot be unpublished. Sets `status=draft`, clears `published_at` and `scheduled_at`, and removes it from user-facing announcement visibility.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Announcement unpublished.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationAnnouncement')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/admin/announcements/{announcement}/archive',
    operationId: 'communicationAdminAnnouncementArchive',
    summary: 'Archive announcement',
    description: 'Access: admin or staff with `announcements.manage`. Archive behavior: sets `status=archived`, `is_archived=true`, `archived_at=now`, and `archived_by` to the authenticated user. Archived announcements are hidden from user-facing announcement APIs and excluded from admin lists unless archived records are requested.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Announcement archived.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ann_01J0ANNOUNCEMENT000000001', 'status' => 'archived', 'archived_at' => '2026-06-01T10:00:00Z', 'archived_by' => 'usr_01J0ADMIN000000000000001']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Delete(
    path: '/admin/announcements/{announcement}',
    operationId: 'communicationAdminAnnouncementDestroy',
    summary: 'Archive announcement with DELETE',
    description: 'Access and archive behavior match `POST /admin/announcements/{announcement}/archive`; this endpoint archives rather than permanently deleting.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Announcement archived.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationAnnouncement')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/admin/announcements/{announcement}/recipient-count',
    operationId: 'communicationAdminAnnouncementRecipientCount',
    summary: 'Resolve announcement recipient count',
    description: 'Access: admin or staff with `announcements.manage`. Re-syncs announcement recipients from current targeting rules and returns the resolved count.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'announcement', in: 'path', required: true, description: 'Announcement public ID.', schema: new OA\Schema(type: 'string'), example: 'ann_01J0ANNOUNCEMENT000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Resolved recipient count.', content: new OA\JsonContent(type: 'object', example: ['data' => ['announcement_id' => 'ann_01J0ANNOUNCEMENT000000001', 'recipient_count' => 42]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/message-templates',
    operationId: 'communicationMessageTemplatesList',
    summary: 'List visible quick message templates',
    description: 'Access: any authenticated user whose role has visible active templates. Role visibility: templates must include at least one of the actor roles in `role_visibility`; teacher-specific templates are returned only to that teacher, while global templates have `teacher_id=null`. Categories include reminder templates (`lesson_reminder`, `homework_reminder`, `payment_reminder`) but there is no separate chat reminder trigger/list HTTP endpoint.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string', enum: ['lesson_reminder', 'homework_reminder', 'reschedule_notice', 'payment_reminder', 'attendance_follow_up', 'progress_check_in']), example: 'lesson_reminder'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated templates visible to the actor role.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'mtp_01J0TEMPLATE000000000001', 'title' => 'Lesson reminder', 'body' => 'Hi! This is a quick reminder about your upcoming lesson.', 'category' => 'lesson_reminder', 'role_visibility' => ['admin', 'staff', 'teacher'], 'status' => 'active', 'is_active' => true, 'teacher_id' => null]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/message-templates/{messageTemplate}',
    operationId: 'communicationMessageTemplateShow',
    summary: 'Show visible quick message template',
    description: 'Access: any authenticated user. The template must be active, match one of the actor roles, and be either global or owned by the actor teacher; otherwise it returns 404.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'messageTemplate', in: 'path', required: true, description: 'Message template public ID.', schema: new OA\Schema(type: 'string'), example: 'mtp_01J0TEMPLATE000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Visible template.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'mtp_01J0TEMPLATE000000000001', 'title' => 'Lesson reminder', 'body' => 'Hi! This is a quick reminder about your upcoming lesson.', 'category' => 'lesson_reminder', 'role_visibility' => ['admin', 'staff', 'teacher'], 'status' => 'active', 'is_active' => true, 'teacher_id' => null]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Template is not visible to the authenticated user.'),
    ]
)]
#[OA\Get(
    path: '/conversations/unread-count',
    operationId: 'communicationConversationsUnreadCount',
    summary: 'Internal chat unread count',
    description: 'Access: students, teachers, admins, and staff with `messages.view` or `messages.manage`. Counts unread messages across the actor participant rows, excluding messages sent by the actor.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    responses: [
        new OA\Response(response: 200, description: 'Unread internal chat count.', content: new OA\JsonContent(type: 'object', example: ['data' => ['unread_count' => 4]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/conversations',
    operationId: 'communicationConversationsList',
    summary: 'List internal chat conversations',
    description: 'Access: students, teachers, admins, and staff with `messages.view` or `messages.manage`. Visibility: admins and permitted staff can view all conversations; students and teachers see only conversations where they are active participants. Filters accept public IDs for `student_id`, `teacher_id`, and `course_program_id` and are resolved server-side.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['student_teacher', 'teacher_admin', 'admin_student', 'group_course', 'announcement_thread']), example: 'student_teacher'),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'archived', 'closed']), example: 'active'),
        new OA\Parameter(name: 'student_id', in: 'query', description: 'Student user public ID.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'teacher_id', in: 'query', description: 'Teacher user public ID.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0TEACHER000000000000001'),
        new OA\Parameter(name: 'course_program_id', in: 'query', description: 'Course program public ID.', schema: new OA\Schema(type: 'string'), example: 'crs_01J0BUSINESS000000000001'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated conversations.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'cnv_01J0CHAT000000000000001', 'type' => 'student_teacher', 'display_title' => 'Taylor Teacher', 'status' => 'active', 'student_id' => 'usr_01J0STUDENT000000000000001', 'teacher_id' => 'usr_01J0TEACHER000000000000001', 'unread_count' => 2, 'permission_metadata' => ['is_participant' => true, 'can_send_messages' => true, 'can_pin_messages' => false]]], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/conversations',
    operationId: 'communicationConversationCreate',
    summary: 'Create internal chat conversation',
    description: 'Access: students, teachers, admins, and staff with `messages.view` or `messages.manage`; staff creation beyond self-service requires `messages.manage`. Public ID usage: submit user and course program public IDs. One-to-one conversations return an existing active conversation for the same two participants instead of creating a duplicate. Students can start assigned-teacher chats or their own admin chats. Teachers can start chats with assigned students or their own admin chats. Admins and staff with `messages.manage` may create direct and course group conversations.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['student_teacher', 'teacher_admin', 'admin_student', 'group_course'], example: 'student_teacher'),
        new OA\Property(property: 'recipient_id', description: 'Recipient user public ID for actor-led one-to-one creation.', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'student_id', description: 'Student user public ID for admin/staff managed creation.', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', description: 'Teacher user public ID for student-teacher or teacher-admin creation.', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'admin_id', description: 'Admin or staff contact public ID. Staff contacts must be allowed to manage messages.', type: 'string', example: 'usr_01J0ADMIN000000000000001'),
        new OA\Property(property: 'course_program_id', description: 'Required for `group_course` conversations.', type: 'string', example: 'crs_01J0BUSINESS000000000001'),
        new OA\Property(property: 'participant_ids', description: 'Additional participant user public IDs for group course conversations. Maximum 100.', type: 'array', items: new OA\Items(type: 'string'), example: ['usr_01J0STUDENT000000000000001']),
        new OA\Property(property: 'title', nullable: true, type: 'string', maxLength: 255, example: 'Business English group chat'),
        new OA\Property(property: 'metadata', type: 'object', example: []),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 201, description: 'Conversation created.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'cnv_01J0CHAT000000000000001', 'type' => 'student_teacher', 'display_title' => 'Taylor Teacher', 'status' => 'active', 'student_id' => 'usr_01J0STUDENT000000000000001', 'teacher_id' => 'usr_01J0TEACHER000000000000001']])),
        new OA\Response(response: 200, description: 'Existing active one-to-one conversation returned.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'cnv_01J0CHAT000000000000001', 'type' => 'student_teacher', 'status' => 'active']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Get(
    path: '/conversations/{conversation}',
    operationId: 'communicationConversationShow',
    summary: 'Show internal chat conversation',
    description: 'Access follows conversation list visibility. Non-global users only receive active participant conversations; invisible conversations return 404. Path parameter is the conversation public ID.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Conversation detail.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'cnv_01J0CHAT000000000000001', 'type' => 'student_teacher', 'display_title' => 'Taylor Teacher', 'status' => 'active', 'participants' => [['user_id' => 'usr_01J0STUDENT000000000000001', 'participant_role' => 'student']]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'Conversation is not visible to the authenticated user.'),
    ]
)]
#[OA\Get(
    path: '/conversations/{conversation}/messages',
    operationId: 'communicationConversationMessagesList',
    summary: 'List internal chat message history',
    description: 'Access follows conversation visibility. Messages are paginated oldest-first by default; `order=newest` returns newest first. Response includes attachment records with public attachment IDs and download/preview endpoint hints.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
        new OA\Parameter(name: 'order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['oldest', 'newest']), example: 'oldest'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 50),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated conversation messages.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'msg_01J0CHATMSG000000000001', 'conversation_id' => 'cnv_01J0CHAT000000000000001', 'sender_id' => 'usr_01J0STUDENT000000000000001', 'body' => 'Can you review my homework before class?', 'links' => [], 'attachments' => [], 'message_type' => 'text', 'status' => 'sent', 'created_at' => '2026-06-01T09:10:00Z']], 'meta' => ['current_page' => 1, 'per_page' => 50, 'total' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Conversation is not visible to the authenticated user.'),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/conversations/{conversation}/messages',
    operationId: 'communicationConversationMessageSend',
    summary: 'Send internal chat message or upload attachments',
    description: 'Access: active participants with `messages.view` or `messages.manage` on active conversations. A message requires `body`, uploaded `files[]`, or `attachment_links[]`. File upload limits: default maximum 5 files per message, 10 MB per file, PDF/JPG/JPEG/PNG/WebP/GIF only; default maximum 20 attachment links per message. These limits are deployment-configurable through `chat_attachments`. Successful sends update conversation last-message fields and mark the sender participant read through the sent message.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: [
        new OA\JsonContent(properties: [
            new OA\Property(property: 'body', nullable: true, type: 'string', maxLength: 10000, example: 'Can you review my homework before class?'),
            new OA\Property(property: 'links', description: 'Legacy bare URL list. Maximum 20.', type: 'array', items: new OA\Items(type: 'string', format: 'uri'), example: ['https://example.com/homework-notes']),
            new OA\Property(property: 'attachment_links', type: 'array', items: new OA\Items(type: 'object'), example: [['url' => 'https://example.com/homework-notes', 'title' => 'Homework notes']]),
            new OA\Property(property: 'metadata', type: 'object', example: []),
        ], type: 'object'),
        new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(properties: [
            new OA\Property(property: 'body', nullable: true, type: 'string', maxLength: 10000, example: 'Attached my homework.'),
            new OA\Property(property: 'files[]', description: 'PDF, JPG/JPEG, PNG, WebP, or GIF files. Maximum 5 files and 10 MB each by default.', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
            new OA\Property(property: 'attachment_links[0][url]', type: 'string', format: 'uri', example: 'https://example.com/homework-notes'),
            new OA\Property(property: 'attachment_links[0][title]', nullable: true, type: 'string', maxLength: 255, example: 'Homework notes'),
        ], type: 'object')),
    ]),
    responses: [
        new OA\Response(response: 201, description: 'Message sent.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'msg_01J0CHATMSG000000000001', 'conversation_id' => 'cnv_01J0CHAT000000000000001', 'sender_id' => 'usr_01J0STUDENT000000000000001', 'body' => 'Attached my homework.', 'attachments' => [['id' => 'att_01J0CHATATTACH000000001', 'type' => 'file', 'original_filename' => 'homework.pdf', 'mime_type' => 'application/pdf', 'file_size' => 245760, 'download' => ['endpoint' => 'http://localhost:8001/api/v1/conversations/cnv_01J0CHAT000000000000001/messages/msg_01J0CHATMSG000000000001/attachments/att_01J0CHATATTACH000000001/download']]], 'status' => 'sent', 'created_at' => '2026-06-01T09:10:00Z']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'Conversation is not visible to the authenticated user.'),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Get(
    path: '/conversations/{conversation}/messages/{message}/attachments/{conversationAttachment}/download',
    operationId: 'communicationConversationAttachmentDownload',
    summary: 'Download internal chat attachment',
    description: 'Access: users who can view the conversation and whose message/attachment public IDs belong to that conversation. Link attachments and missing stored files return 404. Successful responses stream the stored file with private no-store cache headers.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
        new OA\Parameter(name: 'message', in: 'path', required: true, description: 'Conversation message public ID.', schema: new OA\Schema(type: 'string'), example: 'msg_01J0CHATMSG000000000001'),
        new OA\Parameter(name: 'conversationAttachment', in: 'path', required: true, description: 'Conversation attachment public ID.', schema: new OA\Schema(type: 'string'), example: 'att_01J0CHATATTACH000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Attachment file stream.', content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Attachment is not visible, is not a stored file, or the file is missing.'),
        new OA\Response(response: 503, description: 'Stored file could not be downloaded.'),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Get(
    path: '/conversations/{conversation}/messages/{message}/attachments/{conversationAttachment}/preview',
    operationId: 'communicationConversationAttachmentPreview',
    summary: 'Preview internal chat attachment',
    description: 'Access matches attachment download. Only PDF and image uploads (`image/jpeg`, `image/png`, `image/webp`, `image/gif`) are previewable; non-previewable attachments return 404.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
        new OA\Parameter(name: 'message', in: 'path', required: true, description: 'Conversation message public ID.', schema: new OA\Schema(type: 'string'), example: 'msg_01J0CHATMSG000000000001'),
        new OA\Parameter(name: 'conversationAttachment', in: 'path', required: true, description: 'Conversation attachment public ID.', schema: new OA\Schema(type: 'string'), example: 'att_01J0CHATATTACH000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Inline preview file response.', content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Attachment is not visible, not previewable, or the file is missing.'),
        new OA\Response(response: 503, description: 'Stored file could not be previewed.'),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Post(
    path: '/conversations/{conversation}/read',
    operationId: 'communicationConversationMarkRead',
    summary: 'Mark internal chat conversation read',
    description: 'Access: active participant in a visible conversation. Marks the actor participant read through the latest message and returns the latest read message public ID.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Conversation marked read.', content: new OA\JsonContent(type: 'object', example: ['data' => ['conversation_id' => 'cnv_01J0CHAT000000000000001', 'read_at' => '2026-06-01T10:00:00Z', 'last_read_message_id' => 'msg_01J0CHATMSG000000000001', 'unread_count' => 0]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Conversation or actor participant row is not visible.'),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Post(
    path: '/conversations/{conversation}/messages/read',
    operationId: 'communicationConversationMessagesMarkRead',
    summary: 'Mark internal chat messages read',
    description: 'Access: active participant in a visible conversation. Accepts one `message_id` or up to 100 `message_ids`; all message IDs must be public IDs that belong to the conversation. The participant is marked read through the newest selected message.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message_id', type: 'string', example: 'msg_01J0CHATMSG000000000001'),
        new OA\Property(property: 'message_ids', type: 'array', maxItems: 100, items: new OA\Items(type: 'string'), example: ['msg_01J0CHATMSG000000000001', 'msg_01J0CHATMSG000000000002']),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Selected messages marked read.', content: new OA\JsonContent(type: 'object', example: ['data' => ['conversation_id' => 'cnv_01J0CHAT000000000000001', 'read_at' => '2026-06-01T10:00:00Z', 'last_read_message_id' => 'msg_01J0CHATMSG000000000002', 'unread_count' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Conversation or actor participant row is not visible.'),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Get(
    path: '/conversations/{conversation}/pinned-messages',
    operationId: 'communicationConversationPinnedMessagesList',
    summary: 'List pinned internal chat messages',
    description: 'Access follows conversation visibility. Returns non-deleted pinned messages newest-pin first.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated pinned messages.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'pin_01J0CHATPIN000000000001', 'conversation_id' => 'cnv_01J0CHAT000000000000001', 'message_id' => 'msg_01J0CHATMSG000000000001', 'pinned_by' => 'usr_01J0TEACHER000000000000001', 'pinned_at' => '2026-06-01T09:12:00Z', 'message' => ['id' => 'msg_01J0CHATMSG000000000001', 'body' => 'Can you review my homework before class?']]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(response: 404, description: 'Conversation is not visible to the authenticated user.'),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/conversations/{conversation}/messages/{message}/pin',
    operationId: 'communicationConversationMessagePin',
    summary: 'Pin internal chat message',
    description: 'Access: admins and staff with `messages.manage`; teachers can pin in their active teacher conversation when `chat.allow_teacher_message_pins` is enabled; students can pin only when `chat.allow_student_message_pins` is enabled. Message ID must belong to the conversation. Re-pinning an already pinned message returns the existing pin with 200.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
        new OA\Parameter(name: 'message', in: 'path', required: true, description: 'Conversation message public ID.', schema: new OA\Schema(type: 'string'), example: 'msg_01J0CHATMSG000000000001'),
    ],
    responses: [
        new OA\Response(response: 201, description: 'Message pinned.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'pin_01J0CHATPIN000000000001', 'conversation_id' => 'cnv_01J0CHAT000000000000001', 'message_id' => 'msg_01J0CHATMSG000000000001', 'pinned_by' => 'usr_01J0TEACHER000000000000001', 'pinned_at' => '2026-06-01T09:12:00Z']])),
        new OA\Response(response: 200, description: 'Existing pin returned.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationConversationMessagePin')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'Conversation or message is not visible.'),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Delete(
    path: '/conversations/{conversation}/messages/{message}/pin',
    operationId: 'communicationConversationMessageUnpin',
    summary: 'Unpin internal chat message',
    description: 'Access rules match message pinning. Deleting an absent pin is idempotent and still returns `is_pinned=false` for the message.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
        new OA\Parameter(name: 'message', in: 'path', required: true, description: 'Conversation message public ID.', schema: new OA\Schema(type: 'string'), example: 'msg_01J0CHATMSG000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Message unpinned.', content: new OA\JsonContent(type: 'object', example: ['data' => ['conversation_id' => 'cnv_01J0CHAT000000000000001', 'message_id' => 'msg_01J0CHATMSG000000000001', 'is_pinned' => false]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'Conversation or message is not visible.'),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Post(
    path: '/conversations/{conversation}/typing/start',
    operationId: 'communicationConversationTypingStart',
    summary: 'Start internal chat typing indicator',
    description: 'HTTP-based typing indicator. Access: active participant with `messages.view` or `messages.manage` in an active conversation. The event is cached for `chat.typing_indicator_ttl_seconds` seconds, default 10, and broadcasts `ConversationTypingStateChanged`.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Typing started.', content: new OA\JsonContent(type: 'object', example: ['data' => ['conversation_id' => 'cnv_01J0CHAT000000000000001', 'user_id' => 'usr_01J0STUDENT000000000000001', 'is_typing' => true, 'expires_at' => '2026-06-01T09:10:10Z']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'Conversation is not visible to the authenticated user.'),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Post(
    path: '/conversations/{conversation}/typing/stop',
    operationId: 'communicationConversationTypingStop',
    summary: 'Stop internal chat typing indicator',
    description: 'HTTP-based typing indicator. Access rules match typing start. Clears the actor typing cache key and broadcasts a stopped state.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Typing stopped.', content: new OA\JsonContent(type: 'object', example: ['data' => ['conversation_id' => 'cnv_01J0CHAT000000000000001', 'user_id' => 'usr_01J0STUDENT000000000000001', 'is_typing' => false, 'expires_at' => null]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'Conversation is not visible to the authenticated user.'),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Post(
    path: '/conversations/{conversation}/escalations',
    operationId: 'communicationConversationEscalate',
    summary: 'Escalate internal chat conversation',
    description: 'Access: active student participants may escalate their own student conversation; active teachers may escalate conversations for students assigned to them. Admins and staff do not create user-side escalations here; they review the queue under `/admin/chat-escalations`. `issue_report_id` uses an issue report public ID when supplied.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [
        new OA\Property(property: 'reason', type: 'string', maxLength: 5000, example: 'Student reported repeated access problems in this lesson chat.'),
        new OA\Property(property: 'notes', nullable: true, type: 'string', maxLength: 5000, example: 'Please review before the next scheduled class.'),
        new OA\Property(property: 'issue_report_id', nullable: true, type: 'string', example: 'isr_01J0ISSUE000000000000001'),
        new OA\Property(property: 'metadata', type: 'object', example: []),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 201, description: 'Conversation escalation created.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ces_01J0CHATESCL00000000001', 'conversation_id' => 'cnv_01J0CHAT000000000000001', 'message_id' => null, 'issue_report_id' => 'isr_01J0ISSUE000000000000001', 'status' => 'open', 'reason' => 'Student reported repeated access problems in this lesson chat.', 'notes' => 'Please review before the next scheduled class.', 'escalated_by' => 'usr_01J0TEACHER000000000000001']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'Conversation is not visible to the authenticated user.'),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Post(
    path: '/conversations/{conversation}/messages/{message}/escalations',
    operationId: 'communicationConversationMessageEscalate',
    summary: 'Escalate internal chat message',
    description: 'Access rules match conversation escalation. Message ID must be a public ID belonging to the conversation.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'conversation', in: 'path', required: true, description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
        new OA\Parameter(name: 'message', in: 'path', required: true, description: 'Conversation message public ID.', schema: new OA\Schema(type: 'string'), example: 'msg_01J0CHATMSG000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [
        new OA\Property(property: 'reason', type: 'string', maxLength: 5000, example: 'This message needs staff review before the next lesson.'),
        new OA\Property(property: 'notes', nullable: true, type: 'string', maxLength: 5000, example: 'Escalated from the conversation detail view.'),
        new OA\Property(property: 'issue_report_id', nullable: true, type: 'string', example: 'isr_01J0ISSUE000000000000001'),
        new OA\Property(property: 'metadata', type: 'object', example: []),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 201, description: 'Message escalation created.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ces_01J0CHATESCL00000000001', 'conversation_id' => 'cnv_01J0CHAT000000000000001', 'message_id' => 'msg_01J0CHATMSG000000000001', 'status' => 'open', 'reason' => 'This message needs staff review before the next lesson.']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'Conversation or message is not visible.'),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Get(
    path: '/admin/message-templates',
    operationId: 'communicationAdminMessageTemplatesList',
    summary: 'Admin list quick message templates',
    description: 'Access: admin or staff with `message_templates.view`. Includes active and inactive templates, global templates, and teacher-specific templates. `teacher_id` is a user public ID; `teacher_id=null` filters global templates.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string', enum: ['lesson_reminder', 'homework_reminder', 'reschedule_notice', 'payment_reminder', 'attendance_follow_up', 'progress_check_in']), example: 'lesson_reminder'),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'inactive']), example: 'active'),
        new OA\Parameter(name: 'teacher_id', in: 'query', description: 'Teacher user public ID; null means global templates.', schema: new OA\Schema(type: 'string', nullable: true), example: 'usr_01J0TEACHER000000000000001'),
        new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string', maxLength: 255), example: 'reminder'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated admin template list.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'mtp_01J0TEMPLATE000000000001', 'title' => 'Lesson reminder', 'category' => 'lesson_reminder', 'role_visibility' => ['admin', 'staff', 'teacher'], 'status' => 'active', 'teacher_id' => null, 'created_by' => 'usr_01J0ADMIN000000000000001']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/admin/message-templates',
    operationId: 'communicationAdminMessageTemplateCreate',
    summary: 'Create quick message template',
    description: 'Access: admin or staff with `message_templates.manage`. `category` and role values are normalized from spaces/hyphens to underscores. `teacher_id` must be a teacher public ID when present; omit or set null for a global template.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['title', 'body', 'category', 'role_visibility'], properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Lesson reminder'),
        new OA\Property(property: 'body', type: 'string', maxLength: 20000, example: 'Hi! This is a quick reminder about your upcoming lesson.'),
        new OA\Property(property: 'category', type: 'string', enum: ['lesson_reminder', 'homework_reminder', 'reschedule_notice', 'payment_reminder', 'attendance_follow_up', 'progress_check_in'], example: 'lesson_reminder'),
        new OA\Property(property: 'role_visibility', type: 'array', minItems: 1, items: new OA\Items(type: 'string', enum: ['admin', 'staff', 'teacher', 'student']), example: ['admin', 'staff', 'teacher']),
        new OA\Property(property: 'roles', description: 'Alias accepted for `role_visibility`.', type: 'array', items: new OA\Items(type: 'string'), example: ['teacher']),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], example: 'active'),
        new OA\Property(property: 'teacher_id', nullable: true, type: 'string', example: 'usr_01J0TEACHER000000000000001'),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 201, description: 'Template created.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'mtp_01J0TEMPLATE000000000001', 'title' => 'Lesson reminder', 'body' => 'Hi! This is a quick reminder about your upcoming lesson.', 'category' => 'lesson_reminder', 'role_visibility' => ['admin', 'staff', 'teacher'], 'status' => 'active', 'is_active' => true, 'teacher_id' => null]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/admin/message-templates/{messageTemplate}',
    operationId: 'communicationAdminMessageTemplateShow',
    summary: 'Admin show quick message template',
    description: 'Access: admin or staff with `message_templates.view`. Path parameter is the template public ID.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'messageTemplate', in: 'path', required: true, description: 'Message template public ID.', schema: new OA\Schema(type: 'string'), example: 'mtp_01J0TEMPLATE000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Template detail.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationMessageTemplate')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/admin/message-templates/{messageTemplate}',
    operationId: 'communicationAdminMessageTemplateUpdate',
    summary: 'Update quick message template',
    description: 'Access: admin or staff with `message_templates.manage`. Validation matches create, but fields are optional. `teacher_id=null` clears teacher-specific ownership.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'messageTemplate', in: 'path', required: true, description: 'Message template public ID.', schema: new OA\Schema(type: 'string'), example: 'mtp_01J0TEMPLATE000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Updated lesson reminder'),
        new OA\Property(property: 'body', type: 'string', maxLength: 20000, example: 'Reminder: your lesson starts soon.'),
        new OA\Property(property: 'category', type: 'string', enum: ['lesson_reminder', 'homework_reminder', 'reschedule_notice', 'payment_reminder', 'attendance_follow_up', 'progress_check_in'], example: 'lesson_reminder'),
        new OA\Property(property: 'role_visibility', type: 'array', minItems: 1, items: new OA\Items(type: 'string', enum: ['admin', 'staff', 'teacher', 'student']), example: ['teacher']),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], example: 'inactive'),
        new OA\Property(property: 'teacher_id', nullable: true, type: 'string', example: null),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Template updated.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationMessageTemplate')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Put(
    path: '/admin/message-templates/{messageTemplate}',
    operationId: 'communicationAdminMessageTemplateReplace',
    summary: 'Update quick message template with PUT',
    description: 'Access and validation match `PATCH /admin/message-templates/{messageTemplate}`.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'messageTemplate', in: 'path', required: true, description: 'Message template public ID.', schema: new OA\Schema(type: 'string'), example: 'mtp_01J0TEMPLATE000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CommunicationMessageTemplate')),
    responses: [
        new OA\Response(response: 200, description: 'Template updated.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationMessageTemplate')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Delete(
    path: '/admin/message-templates/{messageTemplate}',
    operationId: 'communicationAdminMessageTemplateDestroy',
    summary: 'Deactivate quick message template',
    description: 'Access: admin or staff with `message_templates.manage`. This endpoint marks the template inactive; it does not hard-delete the record.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'messageTemplate', in: 'path', required: true, description: 'Message template public ID.', schema: new OA\Schema(type: 'string'), example: 'mtp_01J0TEMPLATE000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Template deactivated.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'mtp_01J0TEMPLATE000000000001', 'status' => 'inactive', 'is_active' => false]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/admin/chat-escalations',
    operationId: 'communicationAdminChatEscalationsList',
    summary: 'List internal chat escalations',
    description: 'Access: admin or staff with `chat_escalations.view`. Filter IDs use public IDs. Admin queue responses include conversation, message, escalated-by, and reviewed-by context when loaded.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['open', 'in_review', 'resolved', 'dismissed']), example: 'open'),
        new OA\Parameter(name: 'conversation_id', in: 'query', description: 'Conversation public ID.', schema: new OA\Schema(type: 'string'), example: 'cnv_01J0CHAT000000000000001'),
        new OA\Parameter(name: 'issue_report_id', in: 'query', description: 'Issue report public ID.', schema: new OA\Schema(type: 'string'), example: 'isr_01J0ISSUE000000000000001'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated chat escalation queue.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'ces_01J0CHATESCL00000000001', 'conversation_id' => 'cnv_01J0CHAT000000000000001', 'message_id' => 'msg_01J0CHATMSG000000000001', 'issue_report_id' => 'isr_01J0ISSUE000000000000001', 'status' => 'open', 'reason' => 'This message needs staff review before the next lesson.', 'escalated_by' => 'usr_01J0TEACHER000000000000001']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/admin/chat-escalations/{conversationEscalation}',
    operationId: 'communicationAdminChatEscalationShow',
    summary: 'Show internal chat escalation',
    description: 'Access: admin or staff with `chat_escalations.view`. Path parameter is the escalation public ID.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'conversationEscalation', in: 'path', required: true, description: 'Conversation escalation public ID.', schema: new OA\Schema(type: 'string'), example: 'ces_01J0CHATESCL00000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Escalation detail.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationConversationEscalation')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/admin/chat-escalations/{conversationEscalation}/status',
    operationId: 'communicationAdminChatEscalationUpdateStatus',
    summary: 'Update internal chat escalation status',
    description: 'Access: admin or staff with `chat_escalations.manage`. Sets reviewer fields and marks `resolved_at` or `dismissed_at` based on the selected status. Optional `issue_report_id` uses an issue report public ID or null to clear the link.',
    security: [['sanctum' => []]],
    tags: ['Communication', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'conversationEscalation', in: 'path', required: true, description: 'Conversation escalation public ID.', schema: new OA\Schema(type: 'string'), example: 'ces_01J0CHATESCL00000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['status'], properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['open', 'in_review', 'resolved', 'dismissed'], example: 'resolved'),
        new OA\Property(property: 'review_notes', nullable: true, type: 'string', maxLength: 5000, example: 'Linked to the existing issue report and notified operations.'),
        new OA\Property(property: 'issue_report_id', nullable: true, type: 'string', example: 'isr_01J0ISSUE000000000000001'),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Escalation status updated.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ces_01J0CHATESCL00000000001', 'status' => 'resolved', 'reviewed_by' => 'usr_01J0STAFF0000000000000001', 'reviewed_at' => '2026-06-01T10:00:00Z', 'resolved_at' => '2026-06-01T10:00:00Z', 'review_notes' => 'Linked to the existing issue report and notified operations.']])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/message-threads',
    operationId: 'communicationMessageThreadsList',
    summary: 'List student-teacher message threads',
    description: 'Access: admin, or users with `messages.view` or `messages.manage`. Visibility: admins and staff with message permission can view all non-archived student-teacher threads; students and teachers see only threads where they are active participants.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'closed']), example: 'active'),
        new OA\Parameter(name: 'student_id', in: 'query', description: 'Student user public ID.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'teacher_id', in: 'query', description: 'Teacher user public ID.', schema: new OA\Schema(type: 'string'), example: 'usr_01J0TEACHER000000000000001'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated message threads.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'mth_01J0THREAD000000000001', 'thread_type' => 'student_teacher', 'status' => 'active', 'student_id' => 'usr_01J0STUDENT000000000000001', 'teacher_id' => 'usr_01J0TEACHER000000000000001', 'unread_count' => 2]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/message-threads',
    operationId: 'communicationMessageThreadCreate',
    summary: 'Create student-teacher message thread',
    description: 'Access: admin, or users with `messages.view` or `messages.manage`. Students may only create a thread with their assigned teacher. Teachers may only create a thread with assigned students. Admin/staff must have `messages.manage` and provide both `student_id` and `teacher_id`. Optional `body` creates the first message.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'recipient_id', description: 'Recipient user public ID for student/teacher self-service creation.', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'student_id', description: 'Student user public ID. Required with `teacher_id` for admin/staff.', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', description: 'Teacher user public ID. Required with `student_id` for admin/staff.', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'title', nullable: true, type: 'string', maxLength: 255, example: 'Lesson follow-up'),
        new OA\Property(property: 'body', nullable: true, type: 'string', maxLength: 10000, example: 'Can you review my homework before class?'),
        new OA\Property(property: 'metadata', type: 'object', example: []),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 201, description: 'Message thread created.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationMessageThread')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/message-threads/unread-count',
    operationId: 'communicationMessageThreadsUnreadCount',
    summary: 'Message unread count',
    description: 'Access: admin, or users with `messages.view` or `messages.manage`. Counts unread messages across the actor participant rows, excluding messages sent by the actor and archived messages.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    responses: [
        new OA\Response(response: 200, description: 'Unread message count.', content: new OA\JsonContent(type: 'object', example: ['data' => ['unread_count' => 4]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/message-threads/{messageThread}/messages',
    operationId: 'communicationMessagesList',
    summary: 'List messages in a thread',
    description: 'Access: admin, or users with `messages.view` or `messages.manage`. Thread visibility follows message-thread visibility rules. Archived messages are excluded.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'messageThread', in: 'path', required: true, description: 'Message thread public ID.', schema: new OA\Schema(type: 'string'), example: 'mth_01J0THREAD000000000001'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 50),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated messages.', content: new OA\JsonContent(type: 'object', example: ['data' => [['thread_id' => 'mth_01J0THREAD000000000001', 'sender_id' => 'usr_01J0STUDENT000000000000001', 'body' => 'Can you review my homework before class?', 'message_type' => 'student_teacher_message', 'sent_at' => '2026-06-01T09:10:00Z']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'Thread is not visible to the authenticated user.'),
    ]
)]
#[OA\Post(
    path: '/message-threads/{messageThread}/messages',
    operationId: 'communicationMessageSend',
    summary: 'Send message in a thread',
    description: 'Access: admin, or users with `messages.view` or `messages.manage`. The thread must be active. Users without `messages.manage` must be participants. Sending updates the sender participant `last_read_at` and the thread `last_message_at`.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'messageThread', in: 'path', required: true, description: 'Message thread public ID.', schema: new OA\Schema(type: 'string'), example: 'mth_01J0THREAD000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['body'], properties: [
        new OA\Property(property: 'body', type: 'string', maxLength: 10000, example: 'Can you review my homework before class?'),
        new OA\Property(property: 'metadata', type: 'object', example: []),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 201, description: 'Message sent.', content: new OA\JsonContent(ref: '#/components/schemas/CommunicationMessage')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(response: 404, description: 'Thread is not visible to the authenticated user.'),
    ]
)]
#[OA\Post(
    path: '/message-threads/{messageThread}/read',
    operationId: 'communicationMessageThreadMarkRead',
    summary: 'Mark message thread read',
    description: 'Access: admin, or users with `messages.view` or `messages.manage`. Updates the actor participant `last_read_at`. Response read/unread format is `{ thread_id, read_at, unread_count }` with `unread_count` reset to zero for that thread.',
    security: [['sanctum' => []]],
    tags: ['Communication'],
    parameters: [
        new OA\Parameter(name: 'messageThread', in: 'path', required: true, description: 'Message thread public ID.', schema: new OA\Schema(type: 'string'), example: 'mth_01J0THREAD000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Thread marked read.', content: new OA\JsonContent(type: 'object', example: ['data' => ['thread_id' => 'mth_01J0THREAD000000000001', 'read_at' => '2026-06-01T10:00:00Z', 'unread_count' => 0]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'Thread is not visible to the authenticated user.'),
    ]
)]
class CommunicationDocumentation {}
