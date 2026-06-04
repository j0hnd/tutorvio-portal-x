<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Scheduling',
    description: 'Calendar, class schedule, teacher availability, unavailable-date, and holiday endpoints. Unless a timestamp field explicitly says otherwise, date-time values are ISO 8601 strings and are interpreted in the request `timezone`, the authenticated user timezone, or `config(app.timezone)` fallback. Date-only values use `YYYY-MM-DD`; time-only values use 24-hour `HH:mm`.'
)]
#[OA\Tag(
    name: 'Lessons',
    description: 'Lesson records, lesson joining, lesson statuses, and teacher lesson notes. Lesson records use `scheduled_date` as `YYYY-MM-DD`, `start_time` and `end_time` as `HH:mm`, and optional join-window timestamps as ISO 8601 date-times. Meeting links are returned only when the authenticated user can join.'
)]
#[OA\Schema(
    schema: 'SchedulingUserSummary',
    allOf: [new OA\Schema(ref: '#/components/schemas/UserSummary')]
)]
#[OA\Schema(
    schema: 'ClassStatus',
    description: 'Class and lesson status values: `scheduled` means upcoming and joinable only during the configured window; `pending_confirmation` means booked but still awaiting confirmation; `completed` means delivered; `cancelled` means no longer active; `rescheduled` means replaced by another class or lesson; `missed_by_student` and `missed_by_teacher` represent no-show outcomes. Legacy `lessons` may also expose `expired` after the join window closes.',
    type: 'string',
    enum: ['scheduled', 'pending_confirmation', 'completed', 'cancelled', 'rescheduled', 'missed_by_student', 'missed_by_teacher', 'expired'],
    example: 'scheduled'
)]
#[OA\Schema(
    schema: 'LessonType',
    description: 'Lesson type values accepted by lesson records.',
    type: 'string',
    enum: ['trial_class', 'first_official_lesson', 'practical_conversational_english', 'business_english', 'exam_preparation', 'special_advanced_courses'],
    example: 'business_english'
)]
#[OA\Schema(
    schema: 'ClassSchedule',
    required: ['id', 'status', 'class_type', 'timezone', 'starts_at', 'ends_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'cls_01J0CLASS0000000000000001'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'title', nullable: true, type: 'string', example: 'Business English coaching'),
        new OA\Property(property: 'description', nullable: true, type: 'string', example: 'Presentation practice and feedback'),
        new OA\Property(property: 'status', ref: '#/components/schemas/ClassStatus'),
        new OA\Property(property: 'class_type', type: 'string', enum: ['regular', 'trial'], example: 'regular'),
        new OA\Property(property: 'timezone', type: 'string', example: 'Asia/Manila'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', example: '2026-06-03T10:00:00+08:00'),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', example: '2026-06-03T10:50:00+08:00'),
        new OA\Property(property: 'teacher_blocked_until', nullable: true, type: 'string', format: 'date-time', example: '2026-06-03T11:05:00+08:00'),
        new OA\Property(property: 'meeting_url', nullable: true, type: 'string', format: 'uri', example: 'https://meet.google.com/abc-defg-hij'),
        new OA\Property(property: 'notes', nullable: true, type: 'string', example: 'Focus on interview answers.'),
        new OA\Property(property: 'cancelled_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-02T12:00:00+08:00'),
        new OA\Property(property: 'cancellation_reason', nullable: true, type: 'string', example: 'Student requested cancellation.'),
        new OA\Property(property: 'student', ref: '#/components/schemas/SchedulingUserSummary'),
        new OA\Property(property: 'teacher', ref: '#/components/schemas/SchedulingUserSummary'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ClassScheduleResponse',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/ClassSchedule'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ClassScheduleRequest',
    required: ['student_id', 'teacher_id', 'timezone', 'starts_at', 'ends_at'],
    properties: [
        new OA\Property(property: 'student_id', description: 'Internal numeric user ID for the student.', type: 'integer', example: 23),
        new OA\Property(property: 'teacher_id', description: 'Internal numeric user ID for the teacher.', type: 'integer', example: 17),
        new OA\Property(property: 'title', nullable: true, type: 'string', example: 'Business English coaching'),
        new OA\Property(property: 'description', nullable: true, type: 'string', example: 'Presentation practice and feedback'),
        new OA\Property(property: 'status', ref: '#/components/schemas/ClassStatus'),
        new OA\Property(property: 'class_type', type: 'string', enum: ['regular', 'trial'], example: 'regular'),
        new OA\Property(property: 'timezone', description: 'IANA timezone used to interpret local schedule intent.', type: 'string', example: 'Asia/Manila'),
        new OA\Property(property: 'starts_at', description: 'ISO 8601 date-time. Include an offset when possible.', type: 'string', format: 'date-time', example: '2026-06-03T10:00:00+08:00'),
        new OA\Property(property: 'ends_at', description: 'ISO 8601 date-time after `starts_at`.', type: 'string', format: 'date-time', example: '2026-06-03T10:50:00+08:00'),
        new OA\Property(property: 'meeting_url', nullable: true, type: 'string', format: 'uri', example: 'https://meet.google.com/abc-defg-hij'),
        new OA\Property(property: 'notes', nullable: true, type: 'string', example: 'Focus on interview answers.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LessonBookingRequest',
    required: ['teacher_id', 'timezone', 'starts_at', 'ends_at'],
    properties: [
        new OA\Property(property: 'teacher_id', description: 'Internal numeric user ID for the student assigned teacher.', type: 'integer', example: 17),
        new OA\Property(property: 'title', nullable: true, type: 'string', maxLength: 255, example: 'Business English coaching'),
        new OA\Property(property: 'description', nullable: true, type: 'string', example: 'Presentation practice and feedback'),
        new OA\Property(property: 'status', type: 'string', enum: ['scheduled', 'pending_confirmation'], example: 'pending_confirmation'),
        new OA\Property(property: 'class_type', type: 'string', enum: ['regular', 'trial'], example: 'regular'),
        new OA\Property(property: 'timezone', description: 'IANA timezone used to interpret the requested lesson time.', type: 'string', example: 'Asia/Manila'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', example: '2026-06-03T10:00:00+08:00'),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', example: '2026-06-03T10:50:00+08:00'),
        new OA\Property(property: 'meeting_url', nullable: true, type: 'string', format: 'uri', maxLength: 2048, example: 'https://meet.google.com/abc-defg-hij'),
        new OA\Property(property: 'notes', nullable: true, type: 'string', example: 'Focus on interview answers.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LessonRecord',
    required: ['id', 'scheduled_date', 'start_time', 'end_time', 'lesson_type', 'lesson_status', 'is_completed', 'is_join_available'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'lrn_01J0LESSONRECORD000000001'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'teacher_id', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'scheduled_date', type: 'string', format: 'date', example: '2026-06-03'),
        new OA\Property(property: 'start_time', description: '24-hour local time in `HH:mm`.', type: 'string', example: '10:00'),
        new OA\Property(property: 'end_time', description: '24-hour local time in `HH:mm`; must be after `start_time`.', type: 'string', example: '10:50'),
        new OA\Property(property: 'meeting_provider', nullable: true, type: 'string', enum: ['google_meet', 'custom', 'other'], example: 'google_meet'),
        new OA\Property(property: 'meeting_link', description: 'Only returned when the current user can join the lesson meeting.', type: 'string', format: 'uri', example: 'https://meet.google.com/abc-defg-hij'),
        new OA\Property(property: 'join_available_from', nullable: true, type: 'string', format: 'date-time', example: '2026-06-03T09:45:00+08:00'),
        new OA\Property(property: 'join_available_until', nullable: true, type: 'string', format: 'date-time', example: '2026-06-03T11:05:00+08:00'),
        new OA\Property(property: 'is_join_available', type: 'boolean', example: false),
        new OA\Property(property: 'lesson_type', ref: '#/components/schemas/LessonType'),
        new OA\Property(property: 'lesson_status', ref: '#/components/schemas/ClassStatus'),
        new OA\Property(property: 'lesson_notes', nullable: true, type: 'string', example: 'Student completed speaking drill.'),
        new OA\Property(property: 'homework_details', nullable: true, type: 'string', example: 'Prepare five interview answers.'),
        new OA\Property(property: 'homework_due_date', nullable: true, type: 'string', format: 'date', example: '2026-06-07'),
        new OA\Property(property: 'attendance_status', nullable: true, type: 'string', enum: ['present', 'absent', 'late', 'excused', 'no_show'], example: 'present'),
        new OA\Property(property: 'is_completed', type: 'boolean', example: true),
        new OA\Property(property: 'completed_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-03T11:00:00+08:00'),
        new OA\Property(property: 'student', ref: '#/components/schemas/SchedulingUserSummary'),
        new OA\Property(property: 'teacher', ref: '#/components/schemas/SchedulingUserSummary'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LessonRecordResponse',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/LessonRecord'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LessonRecordRequest',
    required: ['student_id', 'teacher_id', 'scheduled_date', 'start_time', 'end_time', 'lesson_type', 'lesson_status'],
    properties: [
        new OA\Property(property: 'student_id', type: 'integer', example: 23),
        new OA\Property(property: 'teacher_id', type: 'integer', example: 17),
        new OA\Property(property: 'scheduled_date', description: 'Date-only local lesson date in `YYYY-MM-DD`.', type: 'string', format: 'date', example: '2026-06-03'),
        new OA\Property(property: 'start_time', description: '24-hour local time in `HH:mm`.', type: 'string', example: '10:00'),
        new OA\Property(property: 'end_time', description: '24-hour local time in `HH:mm`; must be after `start_time`.', type: 'string', example: '10:50'),
        new OA\Property(property: 'meeting_link', nullable: true, type: 'string', format: 'uri', example: 'https://meet.google.com/abc-defg-hij'),
        new OA\Property(property: 'meeting_provider', nullable: true, type: 'string', enum: ['google_meet', 'custom', 'other'], example: 'google_meet'),
        new OA\Property(property: 'join_available_from', description: 'Optional ISO 8601 override. Defaults to lesson start minus the configured lead window.', nullable: true, type: 'string', format: 'date-time', example: '2026-06-03T09:45:00+08:00'),
        new OA\Property(property: 'join_available_until', description: 'Optional ISO 8601 override. Defaults to lesson end plus the configured grace window.', nullable: true, type: 'string', format: 'date-time', example: '2026-06-03T11:05:00+08:00'),
        new OA\Property(property: 'lesson_type', ref: '#/components/schemas/LessonType'),
        new OA\Property(property: 'lesson_status', ref: '#/components/schemas/ClassStatus'),
        new OA\Property(property: 'lesson_notes', nullable: true, type: 'string', example: 'Student completed speaking drill.'),
        new OA\Property(property: 'homework_details', nullable: true, type: 'string', example: 'Prepare five interview answers.'),
        new OA\Property(property: 'homework_due_date', nullable: true, type: 'string', format: 'date', example: '2026-06-07'),
        new OA\Property(property: 'attendance_status', nullable: true, type: 'string', enum: ['present', 'absent', 'late', 'excused', 'no_show'], example: 'present'),
        new OA\Property(property: 'is_completed', type: 'boolean', example: false),
        new OA\Property(property: 'internal_remarks', nullable: true, type: 'string', example: 'Admin-visible scheduling note.'),
        new OA\Property(property: 'material_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [11, 12]),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'LessonJoinResponse',
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            required: ['lesson_id', 'status', 'can_join', 'meeting_link', 'is_join_available'],
            properties: [
                new OA\Property(property: 'lesson_id', type: 'string', example: 'les_01J0LESSON000000000000001'),
                new OA\Property(property: 'status', ref: '#/components/schemas/ClassStatus'),
                new OA\Property(property: 'can_join', type: 'boolean', example: true),
                new OA\Property(property: 'available_from', nullable: true, type: 'string', format: 'date-time', example: '2026-06-03T01:45:00Z'),
                new OA\Property(property: 'available_until', nullable: true, type: 'string', format: 'date-time', example: '2026-06-03T03:05:00Z'),
                new OA\Property(property: 'starts_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-03T02:00:00Z'),
                new OA\Property(property: 'ends_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-03T02:50:00Z'),
                new OA\Property(property: 'seconds_until_available', nullable: true, type: 'integer', example: 0),
                new OA\Property(property: 'meeting_provider', nullable: true, type: 'string', example: 'google_meet'),
                new OA\Property(property: 'meeting_link', nullable: true, type: 'string', format: 'uri', example: 'https://meet.google.com/abc-defg-hij'),
                new OA\Property(property: 'is_join_available', type: 'boolean', example: true),
                new OA\Property(property: 'reason', nullable: true, type: 'string', enum: ['unauthorized', 'not_yet_available', 'lesson_expired', 'lesson_cancelled', 'lesson_rescheduled', 'lesson_not_joinable', 'no_meeting_link'], example: null),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Post(
    path: '/scheduling/lesson-bookings',
    operationId: 'lessonBookingCreate',
    summary: 'Book one-time lesson',
    description: 'Student-only endpoint. Creates a pending or scheduled one-time class with the authenticated student assigned to their teacher. Booking uses short-lived cache locks around the student/time slot and teacher availability slot; lock conflicts return the normal 422 validation error format on `starts_at`.',
    security: [['sanctum' => []]],
    tags: ['Scheduling', 'Student'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LessonBookingRequest')),
    responses: [
        new OA\Response(response: 201, description: 'Lesson booking created.', content: new OA\JsonContent(ref: '#/components/schemas/ClassScheduleResponse')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(
            response: 422,
            description: 'Validation error. Includes booking lock conflicts such as another in-progress booking for the same student/time slot or teacher availability slot.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse',
                example: [
                    'message' => 'This lesson slot is already being booked. Please try another time or retry shortly.',
                    'errors' => [
                        'starts_at' => ['This lesson slot is already being booked. Please try another time or retry shortly.'],
                    ],
                ]
            )
        ),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Get(
    path: '/scheduling/calendar',
    operationId: 'schedulingCalendar',
    summary: 'Calendar listing',
    description: 'Protected endpoint. Access: users with `classes.view` or `schedules.view`. Admin and staff can view all schedules; teacher and student results are scoped to their own schedules by the policy and service layer. `timezone` controls the day, week, or month boundary used for the listing. Date format: `YYYY-MM-DD`.',
    security: [['sanctum' => []]],
    tags: ['Scheduling'],
    parameters: [
        new OA\Parameter(name: 'view', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['day', 'week', 'month']), example: 'week'),
        new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-03'),
        new OA\Parameter(name: 'timezone', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'Asia/Manila'),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Calendar entries for the requested view. Example includes upcoming, completed, cancelled, missed, and rescheduled lessons.',
            content: new OA\JsonContent(
                type: 'object',
                example: [
                    'data' => [
                        ['id' => 'cls_upcoming', 'status' => 'scheduled', 'class_type' => 'regular', 'timezone' => 'Asia/Manila', 'starts_at' => '2026-06-03T10:00:00+08:00', 'ends_at' => '2026-06-03T10:50:00+08:00'],
                        ['id' => 'cls_completed', 'status' => 'completed', 'class_type' => 'regular', 'timezone' => 'Asia/Manila', 'starts_at' => '2026-05-29T10:00:00+08:00', 'ends_at' => '2026-05-29T10:50:00+08:00'],
                        ['id' => 'cls_cancelled', 'status' => 'cancelled', 'class_type' => 'trial', 'timezone' => 'Asia/Manila', 'starts_at' => '2026-05-30T10:00:00+08:00', 'ends_at' => '2026-05-30T10:50:00+08:00', 'cancellation_reason' => 'Student unavailable'],
                        ['id' => 'cls_missed', 'status' => 'missed_by_student', 'class_type' => 'regular', 'timezone' => 'Asia/Manila', 'starts_at' => '2026-05-31T10:00:00+08:00', 'ends_at' => '2026-05-31T10:50:00+08:00'],
                        ['id' => 'cls_rescheduled', 'status' => 'rescheduled', 'class_type' => 'regular', 'timezone' => 'Asia/Manila', 'starts_at' => '2026-06-01T10:00:00+08:00', 'ends_at' => '2026-06-01T10:50:00+08:00'],
                    ],
                ]
            )
        ),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/scheduling/class-schedules',
    operationId: 'classScheduleCreate',
    summary: 'Create schedule',
    description: 'Protected endpoint. Access: users with `classes.create` or `schedules.create`. `starts_at` and `ends_at` are ISO 8601 date-times; include the offset and the IANA `timezone` to make daylight-saving and local-calendar behavior explicit.',
    security: [['sanctum' => []]],
    tags: ['Scheduling'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClassScheduleRequest')),
    responses: [
        new OA\Response(response: 201, description: 'Schedule created.', content: new OA\JsonContent(ref: '#/components/schemas/ClassScheduleResponse')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Patch(
    path: '/scheduling/class-schedules/{classSchedule}',
    operationId: 'classScheduleUpdate',
    summary: 'Update schedule',
    description: 'Protected endpoint. Access: users with `classes.update` or `schedules.update`; admin and staff may update managed schedules, and teachers may update schedules assigned to them. Send only fields that should change.',
    security: [['sanctum' => []]],
    tags: ['Scheduling'],
    parameters: [
        new OA\Parameter(name: 'classSchedule', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'cls_01J0CLASS0000000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClassScheduleRequest')),
    responses: [
        new OA\Response(response: 200, description: 'Schedule updated.', content: new OA\JsonContent(ref: '#/components/schemas/ClassScheduleResponse')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/scheduling/class-schedules/{classSchedule}/cancel',
    operationId: 'classScheduleCancel',
    summary: 'Cancel schedule',
    description: 'Protected endpoint. Access follows schedule update rules. Cancelling sets status to `cancelled`, records `cancelled_at`, and stores an optional cancellation reason.',
    security: [['sanctum' => []]],
    tags: ['Scheduling'],
    parameters: [
        new OA\Parameter(name: 'classSchedule', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'cls_01J0CLASS0000000000000001'),
    ],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'reason', nullable: true, type: 'string', example: 'Student requested cancellation.'),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Schedule cancelled.', content: new OA\JsonContent(ref: '#/components/schemas/ClassScheduleResponse')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Patch(
    path: '/scheduling/class-schedules/{classSchedule}/status',
    operationId: 'classScheduleStatusUpdate',
    summary: 'Lesson status updates',
    description: 'Protected endpoint. Access follows schedule update rules. Valid status values are documented by `ClassStatus`; `expired` is only used by legacy lesson join records and is not accepted for class schedules.',
    security: [['sanctum' => []]],
    tags: ['Scheduling', 'Lessons'],
    parameters: [
        new OA\Parameter(name: 'classSchedule', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'cls_01J0CLASS0000000000000001'),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['scheduled', 'completed', 'cancelled', 'rescheduled', 'missed_by_student', 'missed_by_teacher', 'pending_confirmation'], example: 'completed'),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Schedule status updated.', content: new OA\JsonContent(ref: '#/components/schemas/ClassScheduleResponse')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/scheduling/teacher-availabilities',
    operationId: 'teacherAvailabilityList',
    summary: 'Teacher availability',
    description: 'Protected endpoint. Access: admin, staff, teacher, or student users with `classes.view` or `availability.view`. Teachers see their own availability; students see their assigned teacher availability; admin and staff can filter by `teacher_id`. `day_of_week` uses 0 Sunday through 6 Saturday. Times are `HH:mm` in the row timezone.',
    security: [['sanctum' => []]],
    tags: ['Scheduling', 'Teacher'],
    parameters: [
        new OA\Parameter(name: 'teacher_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 17),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Teacher availability rows.',
            content: new OA\JsonContent(
                type: 'object',
                example: ['data' => [['id' => 'tav_01J0AVAILABILITY000000001', 'teacher_id' => 'usr_01J0TEACHER000000000000001', 'day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'timezone' => 'Asia/Manila', 'effective_from' => '2026-06-01', 'effective_until' => null, 'capacity' => 1, 'is_active' => true]]]
            )
        ),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/scheduling/teacher-unavailable-dates',
    operationId: 'teacherUnavailableDateList',
    summary: 'Unavailable dates',
    description: 'Protected endpoint. Access follows teacher availability viewing rules. `starts_at` and `ends_at` are ISO 8601 date-times in the row timezone unless `is_all_day` is true, in which case clients should treat the local calendar date as blocked.',
    security: [['sanctum' => []]],
    tags: ['Scheduling', 'Teacher'],
    parameters: [
        new OA\Parameter(name: 'teacher_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 17),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Unavailable dates for the visible teacher scope.',
            content: new OA\JsonContent(
                type: 'object',
                example: ['data' => [['id' => 'tud_01J0UNAVAILABLE0000001', 'teacher_id' => 'usr_01J0TEACHER000000000000001', 'starts_at' => '2026-06-12T00:00:00+08:00', 'ends_at' => '2026-06-12T23:59:59+08:00', 'timezone' => 'Asia/Manila', 'is_all_day' => true, 'reason' => 'Personal leave']]]
            )
        ),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/scheduling/holidays',
    operationId: 'holidayList',
    summary: 'Holidays',
    description: 'Protected endpoint. Access: users with `classes.view` or `holidays.view`. Dates use `YYYY-MM-DD` in the holiday timezone. `repeats_annually` means the month and day recur in later years.',
    security: [['sanctum' => []]],
    tags: ['Scheduling'],
    parameters: [
        new OA\Parameter(name: 'timezone', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'Asia/Manila'),
        new OA\Parameter(name: 'country_code', in: 'query', required: false, schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 2), example: 'PH'),
        new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: true),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Configured holidays.',
            content: new OA\JsonContent(
                type: 'object',
                example: ['data' => [['id' => 'hol_01J0HOLIDAY00000000001', 'name' => 'Independence Day', 'date' => '2026-06-12', 'timezone' => 'Asia/Manila', 'country_code' => 'PH', 'repeats_annually' => true, 'is_active' => true, 'notes' => null]]]
            )
        ),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Post(
    path: '/lesson-records',
    operationId: 'lessonRecordCreate',
    summary: 'Create lesson record',
    description: 'Protected endpoint. Access: admin users or staff with `lesson_records.create`. Date and time fields are local lesson values: `scheduled_date` is `YYYY-MM-DD`; `start_time` and `end_time` are `HH:mm`. Join-window overrides are ISO 8601 date-times.',
    security: [['sanctum' => []]],
    tags: ['Lessons'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LessonRecordRequest')),
    responses: [
        new OA\Response(response: 201, description: 'Lesson record created.', content: new OA\JsonContent(ref: '#/components/schemas/LessonRecordResponse')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/lesson-records/{lessonRecord}',
    operationId: 'lessonRecordView',
    summary: 'View lesson record',
    description: 'Protected endpoint. Access: admin, staff with `lesson_records.view`, the assigned teacher, or the assigned student. Admin/staff receive internal fields; meeting links are returned only when the authenticated user can join.',
    security: [['sanctum' => []]],
    tags: ['Lessons'],
    parameters: [
        new OA\Parameter(name: 'lessonRecord', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'lrn_01J0LESSONRECORD000000001'),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Lesson record. Examples cover upcoming, completed, cancelled, missed, and rescheduled records.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/LessonRecordResponse',
                examples: [
                    'upcoming' => new OA\Examples(example: 'upcoming', summary: 'Upcoming lesson', value: ['data' => ['id' => 'lrn_upcoming', 'scheduled_date' => '2026-06-03', 'start_time' => '10:00', 'end_time' => '10:50', 'lesson_type' => 'business_english', 'lesson_status' => 'scheduled', 'is_completed' => false, 'is_join_available' => false]]),
                    'completed' => new OA\Examples(example: 'completed', summary: 'Completed lesson', value: ['data' => ['id' => 'lrn_completed', 'scheduled_date' => '2026-05-29', 'start_time' => '10:00', 'end_time' => '10:50', 'lesson_type' => 'business_english', 'lesson_status' => 'completed', 'attendance_status' => 'present', 'is_completed' => true, 'completed_at' => '2026-05-29T10:55:00+08:00', 'is_join_available' => false]]),
                    'cancelled' => new OA\Examples(example: 'cancelled', summary: 'Cancelled lesson', value: ['data' => ['id' => 'lrn_cancelled', 'scheduled_date' => '2026-05-30', 'start_time' => '10:00', 'end_time' => '10:50', 'lesson_type' => 'trial_class', 'lesson_status' => 'cancelled', 'is_completed' => false, 'is_join_available' => false]]),
                    'missed' => new OA\Examples(example: 'missed', summary: 'Missed lesson', value: ['data' => ['id' => 'lrn_missed', 'scheduled_date' => '2026-05-31', 'start_time' => '10:00', 'end_time' => '10:50', 'lesson_type' => 'exam_preparation', 'lesson_status' => 'missed_by_student', 'attendance_status' => 'no_show', 'is_completed' => false, 'is_join_available' => false]]),
                    'rescheduled' => new OA\Examples(example: 'rescheduled', summary: 'Rescheduled lesson', value: ['data' => ['id' => 'lrn_rescheduled', 'scheduled_date' => '2026-06-01', 'start_time' => '10:00', 'end_time' => '10:50', 'lesson_type' => 'business_english', 'lesson_status' => 'rescheduled', 'is_completed' => false, 'is_join_available' => false]]),
                ]
            )
        ),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/lesson-records/{lessonRecord}',
    operationId: 'lessonRecordUpdate',
    summary: 'Update lesson record',
    description: 'Protected endpoint. Access: admin users or staff with `lesson_records.update`. Send `lesson_status` to update lesson state; valid values are the class status values except legacy `expired`.',
    security: [['sanctum' => []]],
    tags: ['Lessons'],
    parameters: [
        new OA\Parameter(name: 'lessonRecord', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'lrn_01J0LESSONRECORD000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LessonRecordRequest')),
    responses: [
        new OA\Response(response: 200, description: 'Lesson record updated.', content: new OA\JsonContent(ref: '#/components/schemas/LessonRecordResponse')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/lessons/{lesson}/join',
    operationId: 'lessonJoin',
    summary: 'Lesson join endpoint',
    description: 'Protected endpoint. Access: admin, assigned student, or assigned teacher for the lesson. Valid join scenario: status is `scheduled` or `pending_confirmation`, a meeting link exists, and current UTC time is between `available_from` and `available_until` inclusive. Invalid scenarios return `can_join: false` with reasons such as `not_yet_available`, `lesson_expired`, `lesson_cancelled`, `lesson_rescheduled`, `lesson_not_joinable`, or `no_meeting_link`; unauthorized users receive 403 with reason `unauthorized`.',
    security: [['sanctum' => []]],
    tags: ['Lessons'],
    parameters: [
        new OA\Parameter(name: 'lesson', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'les_01J0LESSON000000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Join availability. Meeting link is null unless `can_join` is true.', content: new OA\JsonContent(ref: '#/components/schemas/LessonJoinResponse')),
        new OA\Response(
            response: 403,
            description: 'The authenticated user is not the lesson student, lesson teacher, or admin.',
            content: new OA\JsonContent(
                required: ['message', 'reason'],
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Unauthorized.'),
                    new OA\Property(property: 'reason', type: 'string', example: 'unauthorized'),
                ],
                type: 'object'
            )
        ),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
    ]
)]
#[OA\Get(
    path: '/lessons/{lesson}/lesson-notes',
    operationId: 'lessonNotesByLesson',
    summary: 'Teacher lesson notes for a lesson',
    description: 'Protected endpoint. Access: admin, assigned teacher, assigned student, or staff with `lesson_notes.view`. Teachers can submit notes only for their assigned completed or missed lessons; students can view their own notes but cannot create or update them.',
    security: [['sanctum' => []]],
    tags: ['Lessons', 'Teacher'],
    parameters: [
        new OA\Parameter(name: 'lesson', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'les_01J0LESSON000000000000001'),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated lesson notes for the lesson. Internal notes are visible to admins, permitted staff, and the assigned teacher.'),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Post(
    path: '/lesson-notes',
    operationId: 'lessonNoteCreate',
    summary: 'Create teacher lesson note',
    description: 'Protected endpoint. Access: admin, teacher, or staff with `lesson_notes.create`. Teachers are restricted to their own assigned lessons. Notes can only be submitted for `completed`, `missed_by_student`, or `missed_by_teacher` lessons and only once per lesson or matching lesson record.',
    security: [['sanctum' => []]],
    tags: ['Lessons', 'Teacher'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['lesson_id'],
            properties: [
                new OA\Property(property: 'lesson_id', description: 'Internal numeric lesson ID.', type: 'integer', example: 101),
                new OA\Property(property: 'lesson_record_id', description: 'Optional internal numeric lesson record ID that must match the lesson.', nullable: true, type: 'integer', example: 501),
                new OA\Property(property: 'lesson_objective', nullable: true, type: 'string', example: 'Practice interview answers.'),
                new OA\Property(property: 'topics_covered', nullable: true, type: 'string', example: 'Introductions, STAR answers, follow-up questions.'),
                new OA\Property(property: 'homework_assignment', nullable: true, type: 'string', example: 'Write five STAR answers.'),
                new OA\Property(property: 'recommendation_for_next_lesson', nullable: true, type: 'string', example: 'Continue pronunciation feedback.'),
                new OA\Property(property: 'internal_note', nullable: true, type: 'string', example: 'Teacher-visible/admin-visible coaching note.'),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Lesson note created.'),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
class SchedulingLessonDocumentation {}
