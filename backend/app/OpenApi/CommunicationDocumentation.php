<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Communication',
    description: 'Notifications, announcements, announcement targeting, scheduled publication, archive behavior, and student-teacher messaging. All operations require Sanctum bearer authentication. Notification and announcement list/read endpoints are scoped to records visible to the authenticated user. Announcement management requires admin/staff access with `announcements.manage`; notification history requires `notifications.history.view`; message thread access requires `messages.view` or `messages.manage`.'
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
        new OA\Property(property: 'created_by', description: 'Admin field. Internal numeric author ID.', nullable: true, type: 'integer', example: 5),
        new OA\Property(property: 'archived_by', description: 'Admin field. Internal numeric archiving actor ID.', nullable: true, type: 'integer', example: null),
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
    description: 'Notification recipient response. User-facing notification endpoints return in-portal recipients for the authenticated user only, excluding archived notifications and future unpublished notifications. Admin history includes all non-archived recipients and adds recipient/channel/delivery fields when the actor has `notifications.history.view`.',
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
        new OA\Property(property: 'metadata', type: 'object', example: ['announcement_id' => 12]),
        new OA\Property(property: 'recipient_user_id', description: 'Admin history field. Internal numeric user ID.', type: 'integer', example: 23),
        new OA\Property(property: 'recipient_id', description: 'Admin history field. Internal numeric notification recipient ID.', type: 'integer', example: 501),
        new OA\Property(property: 'channel', description: 'Admin history field. Email notifications are represented as recipients with `channel=email`; no standalone email notification settings API is currently implemented.', type: 'string', enum: ['in_portal', 'email'], example: 'in_portal'),
        new OA\Property(property: 'delivery_status', description: 'Admin history field.', type: 'string', enum: ['pending', 'sent', 'delivered', 'failed'], example: 'delivered'),
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
        new OA\Response(response: 200, description: 'Paginated notifications.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'ntf_01J0NOTIFICATION0000001', 'title' => 'Holiday schedule update', 'body' => 'Classes are paused on the public holiday.', 'message' => 'Classes are paused on the public holiday.', 'type' => 'admin_announcement', 'is_read' => false, 'read_status' => 'unread', 'read_at' => null, 'created_at' => '2026-06-01T09:00:00Z', 'published_at' => '2026-06-01T09:00:00Z', 'metadata' => ['announcement_id' => 12]]], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/notifications/history',
    operationId: 'communicationNotificationHistory',
    summary: 'Notification delivery history',
    description: 'Access: admin, or staff with `notifications.history.view`. Visibility: all non-archived notification recipients, including `in_portal` and `email` channels. Email notification settings endpoints are not implemented; email delivery appears here as channel and delivery status history.',
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
        new OA\Response(response: 200, description: 'Paginated notification recipient history with recipient, channel, and delivery status fields.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'ntf_01J0EMAIL0000000000001', 'title' => 'Invoice sent', 'body' => 'Your invoice is ready.', 'message' => 'Your invoice is ready.', 'type' => 'email', 'is_read' => true, 'read_status' => 'read', 'read_at' => '2026-06-01T10:00:00Z', 'recipient_user_id' => 23, 'recipient_id' => 501, 'channel' => 'email', 'delivery_status' => 'sent', 'metadata' => []]]])),
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
        new OA\Response(response: 200, description: 'Announcement archived.', content: new OA\JsonContent(type: 'object', example: ['data' => ['id' => 'ann_01J0ANNOUNCEMENT000000001', 'status' => 'archived', 'archived_at' => '2026-06-01T10:00:00Z', 'archived_by' => 5]])),
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
        new OA\Response(response: 200, description: 'Resolved recipient count.', content: new OA\JsonContent(type: 'object', example: ['data' => ['announcement_id' => 12, 'recipient_count' => 42]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
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
        new OA\Parameter(name: 'student_id', in: 'query', description: 'Internal numeric user ID.', schema: new OA\Schema(type: 'integer'), example: 23),
        new OA\Parameter(name: 'teacher_id', in: 'query', description: 'Internal numeric user ID.', schema: new OA\Schema(type: 'integer'), example: 17),
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
        new OA\Property(property: 'recipient_id', description: 'Internal numeric recipient ID for student/teacher self-service creation.', type: 'integer', example: 17),
        new OA\Property(property: 'student_id', description: 'Internal numeric student user ID. Required with `teacher_id` for admin/staff.', type: 'integer', example: 23),
        new OA\Property(property: 'teacher_id', description: 'Internal numeric teacher user ID. Required with `student_id` for admin/staff.', type: 'integer', example: 17),
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
