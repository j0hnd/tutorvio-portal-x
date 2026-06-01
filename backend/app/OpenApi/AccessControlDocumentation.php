<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/admin/access',
    operationId: 'adminAccessCheck',
    summary: 'Admin access check',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: admin users with `admin.access` permission only.',
    security: [['sanctum' => []]],
    tags: ['Admin', 'Authorization'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Admin access confirmed.',
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'ok'),
                ],
                type: 'object',
                example: ['status' => 'ok']
            )
        ),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
#[OA\Get(
    path: '/notifications/history',
    operationId: 'notificationHistory',
    summary: 'Notification history',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: admin users, or staff users with `notifications.history.view` permission.',
    security: [['sanctum' => []]],
    tags: ['Authorization'],
    responses: [
        new OA\Response(response: 200, description: 'Notification history for permitted admin or staff users.'),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
#[OA\Get(
    path: '/teacher-change-requests',
    operationId: 'studentTeacherChangeRequests',
    summary: 'List teacher change requests',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: student users only. Returned records are scoped to the authenticated student.',
    security: [['sanctum' => []]],
    tags: ['Student', 'Authorization'],
    responses: [
        new OA\Response(response: 200, description: 'Teacher change requests for the authenticated student.'),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
#[OA\Post(
    path: '/scheduling/teacher-availabilities',
    operationId: 'teacherAvailabilityCreate',
    summary: 'Create teacher availability',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: teacher users with `availability.manage` permission only. Created availability is scoped to the authenticated teacher. `day_of_week` uses 0 Sunday through 6 Saturday; `start_time` and `end_time` use 24-hour `HH:mm`; `timezone` must be an IANA timezone.',
    security: [['sanctum' => []]],
    tags: ['Teacher', 'Authorization', 'Scheduling'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['teacher_id', 'day_of_week', 'start_time', 'end_time', 'timezone'],
            properties: [
                new OA\Property(property: 'teacher_id', description: 'Internal numeric user ID. Teachers may only submit their own ID.', type: 'integer', example: 17),
                new OA\Property(property: 'day_of_week', type: 'integer', minimum: 0, maximum: 6, example: 3),
                new OA\Property(property: 'start_time', type: 'string', example: '09:00'),
                new OA\Property(property: 'end_time', type: 'string', example: '17:00'),
                new OA\Property(property: 'timezone', type: 'string', example: 'Asia/Manila'),
                new OA\Property(property: 'effective_from', nullable: true, type: 'string', format: 'date', example: '2026-06-01'),
                new OA\Property(property: 'effective_until', nullable: true, type: 'string', format: 'date', example: null),
                new OA\Property(property: 'capacity', type: 'integer', minimum: 1, maximum: 100, example: 1),
                new OA\Property(property: 'is_active', type: 'boolean', example: true),
                new OA\Property(property: 'notes', nullable: true, type: 'string', example: 'Available for regular weekday lessons.'),
            ],
            type: 'object'
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Teacher availability was created.',
            content: new OA\JsonContent(
                type: 'object',
                example: ['data' => ['id' => 'tav_01J0AVAILABILITY000000001', 'teacher_id' => 17, 'day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '17:00', 'timezone' => 'Asia/Manila', 'effective_from' => '2026-06-01', 'effective_until' => null, 'capacity' => 1, 'is_active' => true]]
            )
        ),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
class AccessControlDocumentation {}
