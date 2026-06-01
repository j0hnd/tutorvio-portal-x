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
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: teacher users with `availability.manage` permission only. Created availability is scoped to the authenticated teacher.',
    security: [['sanctum' => []]],
    tags: ['Teacher', 'Authorization'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(type: 'object')
    ),
    responses: [
        new OA\Response(response: 201, description: 'Teacher availability was created.'),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
class AccessControlDocumentation {}
