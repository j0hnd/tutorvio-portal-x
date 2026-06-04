<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Admin Operations',
    description: 'Operational admin APIs for audit logs, issue tracking, reports, class oversight, and portal settings. Endpoints require Sanctum bearer authentication. Admin users have full access; staff users require the permission named on each operation.'
)]
#[OA\Schema(
    schema: 'AdminPaginationMeta',
    required: ['current_page', 'per_page', 'total', 'last_page'],
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'per_page', type: 'integer', example: 50),
        new OA\Property(property: 'total', type: 'integer', example: 132),
        new OA\Property(property: 'last_page', type: 'integer', example: 3),
        new OA\Property(property: 'from', nullable: true, type: 'integer', example: 1),
        new OA\Property(property: 'to', nullable: true, type: 'integer', example: 50),
        new OA\Property(property: 'has_more_pages', type: 'boolean', example: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AuditLogMetadata',
    description: 'Sanitized audit metadata. Sensitive keys are removed before response serialization. Issue actions include issue type, related actor/entity IDs, status transitions, changed fields, and note presence.',
    type: 'object',
    example: [
        'issue_type' => 'technical_issue',
        'previous_status' => 'open',
        'new_status' => 'in_progress',
        'assigned_to_id' => 'usr_01J0STAFF0000000000000001',
        'changed_fields' => ['assigned_to_id' => true, 'status' => true],
        'note_present' => true,
    ]
)]
#[OA\Schema(
    schema: 'AuditLog',
    description: 'Reusable public-safe audit log response. The audit log `id` is a public ID; `target_entity_id` is a stored polymorphic target reference used only on this admin endpoint.',
    required: ['id', 'action_type', 'module', 'timestamp', 'metadata'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'aud_01J0AUDIT000000000000001'),
        new OA\Property(property: 'actor_user', ref: '#/components/schemas/UserSummary', nullable: true),
        new OA\Property(property: 'action_type', type: 'string', example: 'issue_status_changed'),
        new OA\Property(property: 'module', type: 'string', example: 'issue_reports'),
        new OA\Property(property: 'target_entity_type', nullable: true, type: 'string', example: 'issue_report'),
        new OA\Property(property: 'target_entity_id', nullable: true, type: 'integer', example: 42),
        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2026-06-01T08:30:00Z'),
        new OA\Property(property: 'metadata', ref: '#/components/schemas/AuditLogMetadata'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'IssueStatus',
    type: 'string',
    enum: ['open', 'in_progress', 'resolved', 'closed', 'cancelled'],
    example: 'open'
)]
#[OA\Schema(
    schema: 'IssueType',
    type: 'string',
    enum: ['technical_issue', 'student_concern', 'teacher_concern', 'class_incident', 'student_absent_issue_form', 'teacher_absent_issue_form', 'material_request', 'change_request'],
    example: 'technical_issue'
)]
#[OA\Schema(
    schema: 'IssuePriority',
    type: 'string',
    enum: ['low', 'normal', 'high', 'urgent'],
    example: 'normal'
)]
#[OA\Schema(
    schema: 'IssueReport',
    description: 'Admin issue report response. Admin/staff issue permissions expose description, assignment, resolution notes, comments, and timestamps.',
    required: ['id', 'type', 'issue_type', 'status', 'priority', 'title'],
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
        new OA\Property(property: 'lesson_id', nullable: true, type: 'string', example: 'les_01J0LESSON000000000000001'),
        new OA\Property(property: 'class_schedule_id', nullable: true, type: 'string', example: 'cls_01J0CLASS0000000000000001'),
        new OA\Property(property: 'title', type: 'string', example: 'Student could not join lesson'),
        new OA\Property(property: 'description', nullable: true, type: 'string', example: 'The meeting link showed an access denied error.'),
        new OA\Property(property: 'resolution_notes', nullable: true, type: 'string', example: 'Regenerated the meeting link and confirmed access.'),
        new OA\Property(property: 'resolved_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:15:00Z'),
        new OA\Property(property: 'resolved_by', nullable: true, type: 'string', example: 'usr_01J0ADMIN000000000000001'),
        new OA\Property(property: 'reporter', nullable: true, type: 'object', example: ['id' => 'usr_01J0STUDENT000000000000001', 'name' => 'Ada Student', 'email' => 'ada@example.com']),
        new OA\Property(property: 'assigned_to', nullable: true, type: 'object', example: ['id' => 'usr_01J0ADMIN000000000000001', 'name' => 'Admin User', 'email' => 'admin@example.com']),
        new OA\Property(property: 'comments', type: 'array', items: new OA\Items(ref: '#/components/schemas/IssueComment')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-01T08:30:00Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-01T09:15:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'IssueComment',
    required: ['id', 'comment_type', 'body', 'is_internal'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 701),
        new OA\Property(property: 'issue_report_id', type: 'integer', example: 42),
        new OA\Property(property: 'author_id', nullable: true, type: 'integer', example: 7),
        new OA\Property(property: 'comment_type', type: 'string', enum: ['comment', 'resolution_note', 'status_change'], example: 'status_change'),
        new OA\Property(property: 'body', type: 'string', example: 'Status changed from open to in_progress.'),
        new OA\Property(property: 'is_internal', type: 'boolean', example: true),
        new OA\Property(property: 'author', nullable: true, type: 'object', example: ['id' => 7, 'name' => 'Admin User', 'email' => 'admin@example.com']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-01T08:35:00Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-01T08:35:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AdminReportResponse',
    description: 'Standard report envelope. The top-level payload is duplicated under `data` for compatibility with existing clients. `summary` contains aggregate metrics and `rows` contains the paginated detailed records for the selected report.',
    required: ['success', 'filters', 'summary', 'rows', 'pagination', 'export'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'filters', type: 'object', example: ['date_from' => '2026-06-01', 'date_to' => '2026-06-30', 'teacher_id' => 17]),
        new OA\Property(property: 'summary', type: 'object', example: ['total_lessons' => 40, 'completed_count' => 34, 'completion_rate' => 85.0]),
        new OA\Property(property: 'rows', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminReportRow')),
        new OA\Property(property: 'pagination', ref: '#/components/schemas/AdminPaginationMeta'),
        new OA\Property(property: 'export', type: 'object', example: ['supported' => true, 'formats' => ['csv', 'xlsx', 'pdf']]),
        new OA\Property(property: 'data', type: 'object', example: ['success' => true, 'summary' => ['total_lessons' => 40], 'rows' => []]),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AdminReportRow',
    description: 'Detailed report row format varies by report. Common row fields include student/teacher/course summaries, date fields, status fields, and numeric counts. Financial or billing fields are returned only when the actor has the related permission.',
    type: 'object',
    example: [
        'lesson_record_id' => 'lrc_01J0LESSONRECORD00000001',
        'student' => ['id' => 'usr_01J0STUDENT000000000000001', 'name' => 'Ada Student'],
        'teacher' => ['id' => 'usr_01J0TEACHER000000000000001', 'name' => 'Taylor Teacher'],
        'course' => ['id' => 'crs_01J0BUSINESS000000000001', 'title' => 'Business English', 'placement_level' => 'B1'],
        'lesson_date' => '2026-06-10',
        'lesson_status' => 'completed',
        'attendance_status' => 'present',
    ]
)]
#[OA\Schema(
    schema: 'PortalSetting',
    required: ['key', 'category', 'value', 'value_type', 'description', 'is_public'],
    properties: [
        new OA\Property(property: 'key', type: 'string', example: 'school.branding'),
        new OA\Property(property: 'category', type: 'string', example: 'school'),
        new OA\Property(property: 'value', description: 'Type depends on `value_type`: string, boolean, or JSON object/array.', nullable: true),
        new OA\Property(property: 'value_type', type: 'string', enum: ['string', 'boolean', 'json'], example: 'json'),
        new OA\Property(property: 'description', type: 'string', example: 'Public-safe brand assets and theme tokens used by the portal.'),
        new OA\Property(property: 'is_public', type: 'boolean', example: true),
        new OA\Property(property: 'updated_by', nullable: true, type: 'integer', example: 7),
        new OA\Property(property: 'updated_by_user', nullable: true, type: 'object', example: ['id' => 7, 'name' => 'Admin User', 'email' => 'admin@example.com']),
        new OA\Property(property: 'updated_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T08:30:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PublicPortalSettings',
    required: ['school', 'branding', 'locale', 'timezone', 'calendar_colors'],
    properties: [
        new OA\Property(property: 'school', type: 'object', example: ['name' => 'Tutorvio']),
        new OA\Property(property: 'branding', type: 'object', example: ['primary_color' => '#1d4ed8', 'secondary_color' => '#0f766e', 'accent_color' => '#f59e0b', 'logo_url' => '/assets/logo.svg', 'favicon_url' => '/assets/favicon.ico']),
        new OA\Property(property: 'locale', type: 'object', example: ['default' => 'en', 'supported' => ['en']]),
        new OA\Property(property: 'timezone', type: 'object', example: ['value' => 'Asia/Manila', 'label' => 'Asia/Manila']),
        new OA\Property(property: 'calendar_colors', type: 'object', example: ['scheduled' => ['label' => 'Scheduled', 'color' => '#2563eb'], 'completed' => ['label' => 'Completed', 'color' => '#16a34a']]),
    ],
    type: 'object'
)]
#[OA\Get(
    path: '/admin/audit-logs',
    operationId: 'adminAuditLogList',
    summary: 'List audit logs',
    description: 'Access: admin or staff with `audit_logs.view`. Supports actor, action, module, target entity, date range, free-text search, and pagination filters. `user_id` is accepted as an alias for `actor_user_id`.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'actor_user_id', in: 'query', description: 'Internal numeric actor user ID.', schema: new OA\Schema(type: 'integer'), example: 7),
        new OA\Parameter(name: 'user_id', in: 'query', description: 'Alias of `actor_user_id`.', schema: new OA\Schema(type: 'integer'), example: 7),
        new OA\Parameter(name: 'action_type', in: 'query', schema: new OA\Schema(type: 'string'), example: 'issue_status_changed'),
        new OA\Parameter(name: 'module', in: 'query', schema: new OA\Schema(type: 'string'), example: 'issue_reports'),
        new OA\Parameter(name: 'target_entity_type', in: 'query', schema: new OA\Schema(type: 'string'), example: 'issue_report'),
        new OA\Parameter(name: 'target_entity_id', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1), example: 42),
        new OA\Parameter(name: 'date_from', in: 'query', description: 'Inclusive audit creation date in `YYYY-MM-DD` format.', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', description: 'Inclusive audit creation date in `YYYY-MM-DD` format. Must be on or after `date_from`.', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'search', in: 'query', description: 'Searches action, module, target type, IP, user agent, sanitized metadata, actor name/email, and numeric target ID.', schema: new OA\Schema(type: 'string', maxLength: 255), example: 'issue_status_changed'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated audit logs.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditLog'))])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/admin/issue-reports',
    operationId: 'adminIssueReportList',
    summary: 'List issue reports',
    description: 'Access: admin or staff with `issue_reports.view`. Supports status/type/priority, reporter, assignment, related student/teacher, lesson/class schedule, date range, and pagination filters.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/IssueStatus')),
        new OA\Parameter(name: 'issue_type', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/IssueType')),
        new OA\Parameter(name: 'type', in: 'query', description: 'Alias of `issue_type`.', schema: new OA\Schema(ref: '#/components/schemas/IssueType')),
        new OA\Parameter(name: 'priority', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/IssuePriority')),
        new OA\Parameter(name: 'reporter_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 23),
        new OA\Parameter(name: 'assigned_to_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 7),
        new OA\Parameter(name: 'related_student_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 23),
        new OA\Parameter(name: 'related_teacher_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 17),
        new OA\Parameter(name: 'lesson_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 501),
        new OA\Parameter(name: 'class_schedule_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 88),
        new OA\Parameter(name: 'date_from', in: 'query', description: 'Inclusive issue creation date in `YYYY-MM-DD` format.', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', description: 'Inclusive issue creation date in `YYYY-MM-DD` format. Must be on or after `date_from`.', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated issue reports.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/IssueReport'))])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/admin/issue-reports/{issueReport}',
    operationId: 'adminIssueReportShow',
    summary: 'Show issue report',
    description: 'Access: admin or staff with `issue_reports.view`. Includes sensitive issue details, assignment, resolution metadata, and comment history.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'issueReport', in: 'path', required: true, description: 'Issue report public ID.', schema: new OA\Schema(type: 'string'), example: 'isr_01J0ISSUE000000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Issue report.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/IssueReport')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/admin/issue-reports/{issueReport}/status',
    operationId: 'adminIssueReportUpdateStatus',
    summary: 'Update issue status',
    description: 'Access: admin or staff with `issue_reports.manage`. Valid statuses are `open`, `in_progress`, `resolved`, `closed`, and `cancelled`. Setting `resolved` stores resolver metadata; moving away from `resolved` clears resolver metadata. Writes issue history and audit metadata.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [new OA\Parameter(name: 'issueReport', in: 'path', required: true, description: 'Issue report public ID.', schema: new OA\Schema(type: 'string'), example: 'isr_01J0ISSUE000000000000001')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['status'], properties: [new OA\Property(property: 'status', ref: '#/components/schemas/IssueStatus'), new OA\Property(property: 'note', nullable: true, type: 'string', maxLength: 10000, example: 'Investigating with the scheduling team.')], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Issue status updated.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/IssueReport')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Patch(
    path: '/admin/issue-reports/{issueReport}/assignment',
    operationId: 'adminIssueReportAssign',
    summary: 'Assign issue report',
    description: 'Access: admin or staff with `issue_reports.assign`. Assignee must be an active admin or staff user. Assigning an open issue automatically moves it to `in_progress`. Writes issue history and audit metadata.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [new OA\Parameter(name: 'issueReport', in: 'path', required: true, description: 'Issue report public ID.', schema: new OA\Schema(type: 'string'), example: 'isr_01J0ISSUE000000000000001')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['assigned_to_id'], properties: [new OA\Property(property: 'assigned_to_id', description: 'Internal numeric active admin/staff user ID.', type: 'integer', example: 7), new OA\Property(property: 'note', nullable: true, type: 'string', maxLength: 10000, example: 'Assigning to operations lead.')], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Issue assigned.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/IssueReport')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/admin/issue-reports/{issueReport}/resolution-notes',
    operationId: 'adminIssueReportResolutionNotes',
    summary: 'Add issue resolution notes',
    description: 'Access: admin or staff with `issue_reports.resolve`. Accepts `resolution_notes` or `note`; `is_internal` defaults to true. Writes issue history and audit metadata.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [new OA\Parameter(name: 'issueReport', in: 'path', required: true, description: 'Issue report public ID.', schema: new OA\Schema(type: 'string'), example: 'isr_01J0ISSUE000000000000001')],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'resolution_notes', type: 'string', maxLength: 20000, example: 'Meeting link was regenerated and verified.'), new OA\Property(property: 'note', type: 'string', maxLength: 20000, example: 'Meeting link was regenerated and verified.'), new OA\Property(property: 'is_internal', type: 'boolean', example: true)], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Resolution notes updated.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/IssueReport')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/admin/issue-reports/{issueReport}/close',
    operationId: 'adminIssueReportClose',
    summary: 'Close issue report',
    description: 'Access: admin or staff with `issue_reports.manage`. Sets status to `closed`, stores resolver metadata if missing, and can persist final resolution notes. Writes issue history and audit metadata.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [new OA\Parameter(name: 'issueReport', in: 'path', required: true, description: 'Issue report public ID.', schema: new OA\Schema(type: 'string'), example: 'isr_01J0ISSUE000000000000001')],
    requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'note', nullable: true, type: 'string', maxLength: 10000), new OA\Property(property: 'resolution_notes', nullable: true, type: 'string', maxLength: 20000)], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Issue closed.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/IssueReport')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/admin/issue-reports/{issueReport}/cancel',
    operationId: 'adminIssueReportCancel',
    summary: 'Cancel issue report',
    description: 'Access: admin or staff with `issue_reports.manage`. Sets status to `cancelled`, stores resolver metadata if missing, and can persist final resolution notes. Writes issue history and audit metadata.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [new OA\Parameter(name: 'issueReport', in: 'path', required: true, description: 'Issue report public ID.', schema: new OA\Schema(type: 'string'), example: 'isr_01J0ISSUE000000000000001')],
    requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'note', nullable: true, type: 'string', maxLength: 10000), new OA\Property(property: 'resolution_notes', nullable: true, type: 'string', maxLength: 20000)], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Issue cancelled.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/IssueReport')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/admin/reports/school',
    operationId: 'adminSchoolReport',
    summary: 'School report filter echo',
    description: 'Access: admin or staff with `school_reports.view`. Returns normalized filters, pagination, empty summary/rows, and export metadata. Use this endpoint to validate report filters before requesting specific analytics reports.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'date_from', in: 'query', description: 'Inclusive date in `YYYY-MM-DD` format. Flexible date input is normalized to this format.', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', description: 'Inclusive date in `YYYY-MM-DD` format. Must be on or after `date_from`.', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'teacher_id', in: 'query', description: 'Internal numeric teacher user ID.', schema: new OA\Schema(type: 'integer'), example: 17),
        new OA\Parameter(name: 'student_id', in: 'query', description: 'Internal numeric student user ID.', schema: new OA\Schema(type: 'integer'), example: 23),
        new OA\Parameter(name: 'course_id', in: 'query', description: 'Internal numeric course program ID.', schema: new OA\Schema(type: 'integer'), example: 3),
        new OA\Parameter(name: 'status', in: 'query', description: 'Report-specific status filter.', schema: new OA\Schema(type: 'string'), example: 'completed'),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 50),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Report response.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(path: '/admin/reports/teacher-load', operationId: 'adminTeacherLoadReport', summary: 'Teacher load report', description: 'Access: admin or staff with `school_reports.view`. Supports `date_from`, `date_to`, `teacher_id`, `student_id`, `course_id`, `status`, `page`, and `per_page`. Returns summary metrics, detailed teacher load rows, pagination, and export metadata (`csv`, `xlsx`, `pdf` supported as metadata only by the JSON endpoint).', security: [['sanctum' => []]], tags: ['Admin Operations', 'Admin'], responses: [new OA\Response(response: 200, description: 'Teacher load report.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')), new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401), new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403), new OA\Response(ref: '#/components/responses/ValidationError', response: 422)])]
#[OA\Get(path: '/admin/reports/active-students', operationId: 'adminActiveStudentsReport', summary: 'Active students report', description: 'Access: admin or staff with `school_reports.view`. Filters: date range, teacher, student, course, status, page, per_page. Summary includes `active_students_count`; rows include student, course, assigned teacher, enrollment/package status, enrolled date, and current status.', security: [['sanctum' => []]], tags: ['Admin Operations', 'Admin'], responses: [new OA\Response(response: 200, description: 'Active students report.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')), new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401), new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403), new OA\Response(ref: '#/components/responses/ValidationError', response: 422)])]
#[OA\Get(path: '/admin/reports/attendance', operationId: 'adminAttendanceReport', summary: 'Attendance report', description: 'Access: admin or staff with `school_reports.view`. Filters: date range, teacher, student, course, lesson/attendance status, page, per_page. Summary includes lesson totals and attendance rate; rows include lesson record, student, teacher, course, lesson date/time, lesson status, attendance status/category, and note/reason.', security: [['sanctum' => []]], tags: ['Admin Operations', 'Admin'], responses: [new OA\Response(response: 200, description: 'Attendance report.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')), new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401), new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403), new OA\Response(ref: '#/components/responses/ValidationError', response: 422)])]
#[OA\Get(path: '/admin/reports/lesson-completions', operationId: 'adminLessonCompletionReport', summary: 'Lesson completion report', description: 'Access: admin or staff with `school_reports.view`. Filters: date range, teacher, student, course, lesson status, page, per_page. Summary includes total lessons, completed count, pending/upcoming count, cancelled/rescheduled/missed count, and completion rate; rows include lesson identifiers, datetime, student, teacher, course, lesson type/status, completion status, and completed timestamp.', security: [['sanctum' => []]], tags: ['Admin Operations', 'Admin'], responses: [new OA\Response(response: 200, description: 'Lesson completion report.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')), new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401), new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403), new OA\Response(ref: '#/components/responses/ValidationError', response: 422)])]
#[OA\Get(path: '/admin/reports/teacher-note-completions', operationId: 'adminTeacherNoteCompletionReport', summary: 'Teacher note completion report', description: 'Access: admin or staff with `school_reports.view`. Filters: date range, teacher, student, course, completion status, page, per_page. Summary reports completed lessons requiring notes, submitted notes, missing notes, and completion rate; rows include lesson, teacher, student, course, note status, due date, and submitted timestamp.', security: [['sanctum' => []]], tags: ['Admin Operations', 'Admin'], responses: [new OA\Response(response: 200, description: 'Teacher note completion report.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')), new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401), new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403), new OA\Response(ref: '#/components/responses/ValidationError', response: 422)])]
#[OA\Get(path: '/admin/reports/student-progress', operationId: 'adminStudentProgressReport', summary: 'Student progress report', description: 'Access: admin or staff with `school_reports.view`. Filters: date range, teacher, student, course, student progress status, page, per_page. Summary and rows cover skill areas, ratings, progress status, level movement, goals/milestones, teacher comments, and timestamps allowed by the actor permissions.', security: [['sanctum' => []]], tags: ['Admin Operations', 'Admin'], responses: [new OA\Response(response: 200, description: 'Student progress report.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')), new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401), new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403), new OA\Response(ref: '#/components/responses/ValidationError', response: 422)])]
#[OA\Get(path: '/admin/reports/missed-classes', operationId: 'adminMissedClassesReport', summary: 'Missed classes report', description: 'Access: admin or staff with `school_reports.view`. Filters: date range, teacher, student, course, missed status, page, per_page. Summary includes missed class count; rows include lesson record, student, teacher, course, lesson date, attendance status, missed status, and reason/note.', security: [['sanctum' => []]], tags: ['Admin Operations', 'Admin'], responses: [new OA\Response(response: 200, description: 'Missed classes report.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')), new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401), new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403), new OA\Response(ref: '#/components/responses/ValidationError', response: 422)])]
#[OA\Get(path: '/admin/reports/package-usage', operationId: 'adminPackageUsageReport', summary: 'Package usage report', description: 'Access: admin or staff with `school_reports.view`. Filters: date range, teacher, student, course, subscription status, page, per_page. Summary includes active package count, consumed/remaining lesson totals, expiring package count, and frozen package count. Rows include package balance, period, status, course, and billing fields only when permitted.', security: [['sanctum' => []]], tags: ['Admin Operations', 'Admin'], responses: [new OA\Response(response: 200, description: 'Package usage report.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')), new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401), new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403), new OA\Response(ref: '#/components/responses/ValidationError', response: 422)])]
#[OA\Get(path: '/admin/reports/retention-continuation', operationId: 'adminRetentionContinuationReport', summary: 'Retention and continuation report', description: 'Access: admin or staff with `school_reports.view`. Filters: date range, teacher, student, course, status, page, per_page. Returns summary retention metrics, detailed student/package continuation rows, pagination, and export metadata.', security: [['sanctum' => []]], tags: ['Admin Operations', 'Admin'], responses: [new OA\Response(response: 200, description: 'Retention and continuation report.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')), new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401), new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403), new OA\Response(ref: '#/components/responses/ValidationError', response: 422)])]
#[OA\Get(path: '/admin/reports/trial-enrollments', operationId: 'adminTrialEnrollmentReport', summary: 'Trial enrollment conversion report', description: 'Access: admin or staff with `school_reports.view`. Filters: date range, teacher, student, course, trial/enrollment status (`enrolled` or `not_converted`), page, per_page. Summary includes trial student/class counts, converted and not-converted counts, pending follow-up count, and conversion rate; rows include trial lesson date, teacher, course, trial status, enrollment status/date, and follow-up status.', security: [['sanctum' => []]], tags: ['Admin Operations', 'Admin'], responses: [new OA\Response(response: 200, description: 'Trial enrollment report.', content: new OA\JsonContent(ref: '#/components/schemas/AdminReportResponse')), new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401), new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403), new OA\Response(ref: '#/components/responses/ValidationError', response: 422)])]
#[OA\Get(
    path: '/admin/classes',
    operationId: 'adminClassOversightList',
    summary: 'List classes for oversight',
    description: 'Operational control endpoint. Access: admin or staff with `classes.view`. Used by operations teams to inspect scheduled/completed classes, issue counts, and teacher-note review state. Supports implementation filters exposed by the class oversight controller.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'teacher_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 17),
        new OA\Parameter(name: 'student_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 23),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'), example: 'completed'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated class oversight rows.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'cls_01J0CLASS000000000000001', 'student' => ['id' => 23, 'name' => 'Ada Student'], 'teacher' => ['id' => 17, 'name' => 'Taylor Teacher'], 'status' => 'completed', 'issue_count' => 1]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Patch(
    path: '/admin/teacher-notes/{lessonNote}/review',
    operationId: 'adminTeacherNoteReview',
    summary: 'Review teacher note',
    description: 'Operational control endpoint. Access: admin or staff with `lesson_notes.update`. Marks teacher notes reviewed for class oversight workflows.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    parameters: [new OA\Parameter(name: 'lessonNote', in: 'path', required: true, description: 'Lesson note public ID.', schema: new OA\Schema(type: 'string'), example: 'lnt_01J0NOTE000000000000001')],
    requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'review_notes', nullable: true, type: 'string', example: 'Reviewed for completeness.')], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Teacher note review updated.'),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/admin/portal-settings',
    operationId: 'adminPortalSettingsList',
    summary: 'List global portal settings',
    description: 'Access: admin or staff with `portal_settings.view`. Returns all supported setting definitions, stored/default values, public visibility, update metadata, and `allowed_keys`. Public settings are also available through the public-safe endpoint.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    responses: [
        new OA\Response(response: 200, description: 'Portal settings.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PortalSetting')), new OA\Property(property: 'allowed_keys', type: 'array', items: new OA\Items(type: 'string'), example: ['school.profile', 'portal.default_timezone', 'school.branding', 'lessons.defaults', 'issues.tracking_configuration'])])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/admin/portal-settings',
    operationId: 'adminPortalSettingsUpdate',
    summary: 'Update global portal settings',
    description: 'Access: admin or staff with `portal_settings.manage`. Body may be either `{ "settings": { "key": value } }` or a direct object of setting keys. Supported keys include school profile/branding, timezone, lesson defaults, cancellation rules, notification rules, attendance statuses, user role defaults, course settings, issue tracking configuration, and academic record settings. Updates are validated per key and audit logged with changed setting/category metadata.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', example: ['settings' => ['portal.default_timezone' => 'Asia/Manila', 'issues.tracking_configuration' => ['enabled' => true, 'default_priority' => 'normal', 'categories' => ['technical_issue', 'class_incident'], 'internal_comments_enabled' => true]]])),
    responses: [
        new OA\Response(response: 200, description: 'Updated settings.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PortalSetting'))])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Put(
    path: '/admin/portal-settings',
    operationId: 'adminPortalSettingsReplace',
    summary: 'Update global portal settings with PUT',
    description: 'Access and behavior match `PATCH /admin/portal-settings`.',
    security: [['sanctum' => []]],
    tags: ['Admin Operations', 'Admin'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', example: ['settings' => ['portal.default_timezone' => 'Asia/Manila']])),
    responses: [
        new OA\Response(response: 200, description: 'Updated settings.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PortalSetting'))])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/settings/public',
    operationId: 'publicPortalSettings',
    summary: 'Public-safe portal settings',
    description: 'Public root endpoint `/settings/public` (outside `/api/v1`). Returns only public-safe school name, branding tokens/assets, locale, timezone, and calendar colors. It excludes internal notification rules, email templates, user defaults, issue tracking configuration, and audit/update metadata.',
    security: [],
    tags: ['Admin Operations'],
    responses: [
        new OA\Response(response: 200, description: 'Public-safe portal settings.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PublicPortalSettings')])),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
class AdminOperationsDocumentation {}
