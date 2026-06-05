<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(security: [['sanctum' => []]])]
#[OA\Info(
    version: L5_SWAGGER_API_VERSION,
    description: L5_SWAGGER_API_DESCRIPTION."\n\n".
        'Protected endpoints require `Authorization: Bearer {token}` with a valid Sanctum token. '.
        'Role and permission requirements are documented on protected operations and summarized in the Authorization tag. '.
        'Public endpoints explicitly disable security in this specification.',
    title: L5_SWAGGER_API_TITLE
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST.'/api/v1',
    description: 'Tutorvio Portal API v1'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    description: 'Use a Laravel Sanctum bearer token. Protected endpoints require `Authorization: Bearer {token}`. Enter only the token value in Swagger UI.',
    bearerFormat: 'Token',
    scheme: 'bearer'
)]
#[OA\Tag(
    name: 'Authorization',
    description: 'Access model for protected endpoints: all protected routes require a bearer token. Admin-only endpoints require the `admin` role. Student-only endpoints require the `student` role. Teacher-only endpoints require the `teacher` role and are limited to teacher-owned resources. Staff-access endpoints require the `staff` role plus the named permission shown on the operation; admins have the full permission set.'
)]
#[OA\Tag(
    name: 'Admin',
    description: 'Administrative endpoints require bearer authentication. `/admin/access` is admin-only with `admin.access`. Other admin endpoint groups are available to admins and to staff users only when the operation permission is granted.'
)]
#[OA\Tag(
    name: 'Student',
    description: 'Student-only operations require bearer authentication and the `student` role. Resource-specific endpoints may additionally require ownership of the student record or request.'
)]
#[OA\Tag(
    name: 'Teacher',
    description: 'Teacher-only operations require bearer authentication and the `teacher` role. Teacher-owned resources are scoped to the authenticated teacher unless an admin or permitted staff operation is documented.'
)]
#[OA\Schema(
    schema: 'ForbiddenResponse',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Forbidden.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ServerErrorResponse',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Server error.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'TooManyRequestsResponse',
    description: 'Generic Laravel throttle response. Redis-backed rate limiters use the same safe body and include a `Retry-After` response header.',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Too many requests.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'DashboardResponse',
    description: 'Authenticated dashboard payload. The `summary` object is role-scoped and cached briefly; its shape varies for admin, staff, teacher, and student dashboards.',
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            required: ['role', 'user', 'permissions', 'summary', 'sections'],
            properties: [
                new OA\Property(property: 'role', nullable: true, type: 'string', enum: ['admin', 'staff', 'teacher', 'student'], example: 'student'),
                new OA\Property(property: 'user', type: 'object', example: ['id' => 'usr_01J0STUDENT000000000000001', 'name' => 'Alex Student', 'email' => 'alex.student@example.com']),
                new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), example: ['classes.view', 'homeworks.view']),
                new OA\Property(property: 'summary', type: 'object', example: ['classes' => ['total' => 2, 'upcoming' => 1, 'completed' => 1], 'learning_progress' => ['completed_lessons' => 1, 'scheduled_lessons' => 1], 'next_lesson' => null]),
                new OA\Property(property: 'sections', type: 'array', items: new OA\Items(type: 'string'), example: ['classes', 'materials', 'subscription']),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PortalMetadataResponse',
    description: 'Authenticated portal metadata payload. Stable metadata and public-safe settings are cached; access metadata is included only for admins or users allowed to view user management data.',
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            required: ['public_settings', 'course_types', 'lesson_types', 'attendance_status_options'],
            properties: [
                new OA\Property(property: 'public_settings', type: 'array', items: new OA\Items(ref: '#/components/schemas/PortalSetting')),
                new OA\Property(property: 'course_types', type: 'array', items: new OA\Items(type: 'object'), example: [['id' => 'ctp_01J0ENGLISH00000000001', 'name' => 'Business English', 'slug' => 'business-english', 'description' => null, 'sort_order' => 2]]),
                new OA\Property(property: 'lesson_types', type: 'array', items: new OA\Items(type: 'object'), example: [['key' => 'trial_class', 'label' => 'Trial Class'], ['key' => 'first_official_lesson', 'label' => 'First Official Lesson']]),
                new OA\Property(property: 'attendance_status_options', type: 'object', example: ['default_status' => 'scheduled', 'statuses' => [['key' => 'present', 'label' => 'Present', 'counts_as_attended' => true]]]),
                new OA\Property(property: 'access', type: 'object', example: ['roles' => [['name' => 'admin', 'label' => 'Admin', 'permissions' => ['users.view']]], 'permissions' => [['name' => 'users.view', 'label' => 'Users View']]]),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Components(
    responses: [
        new OA\Response(
            response: 'UnauthorizedError',
            description: 'Unauthorized. The bearer token is missing, expired, or invalid.',
            content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedError')
        ),
        new OA\Response(
            response: 'ForbiddenError',
            description: 'Forbidden. The authenticated user does not have the required role, permission, or resource ownership.',
            content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenError')
        ),
        new OA\Response(
            response: 'ValidationError',
            description: 'Validation error. One or more submitted fields failed validation.',
            content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
        ),
        new OA\Response(
            response: 'ServerError',
            description: 'Server error. The request could not be completed because of an unexpected server-side failure.',
            content: new OA\JsonContent(ref: '#/components/schemas/ServerErrorResponse')
        ),
        new OA\Response(
            response: 'TooManyRequests',
            description: 'Too many requests. The endpoint rate limit was exceeded; retry after the response `Retry-After` header.',
            headers: [
                new OA\Header(header: 'Retry-After', description: 'Seconds until the client may retry.', schema: new OA\Schema(type: 'integer', minimum: 1, example: 60)),
            ],
            content: new OA\JsonContent(ref: '#/components/schemas/TooManyRequestsResponse')
        ),
    ]
)]
#[OA\Get(
    path: '/health',
    operationId: 'healthCheck',
    summary: 'API health check',
    security: [],
    tags: ['System'],
    responses: [
        new OA\Response(response: 200, description: 'API is healthy'),
    ]
)]
#[OA\Get(
    path: '/dashboard',
    operationId: 'dashboard',
    summary: 'Authenticated dashboard',
    description: 'Protected endpoint. Returns role-scoped dashboard data for the authenticated user. The `summary` block is cached briefly and invalidated when dashboard source models change; cache behavior does not change the response envelope.',
    security: [['sanctum' => []]],
    tags: ['System'],
    responses: [
        new OA\Response(response: 200, description: 'Role-scoped dashboard payload.', content: new OA\JsonContent(ref: '#/components/schemas/DashboardResponse')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
    ]
)]
#[OA\Get(
    path: '/metadata',
    operationId: 'portalMetadata',
    summary: 'Portal metadata',
    description: 'Protected endpoint. Returns cached public-safe portal settings, course types, lesson types, and attendance status options. Role and permission metadata appears only for admins or users with access to user-management metadata.',
    security: [['sanctum' => []]],
    tags: ['System'],
    responses: [
        new OA\Response(response: 200, description: 'Cached portal metadata.', content: new OA\JsonContent(ref: '#/components/schemas/PortalMetadataResponse')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
    ]
)]
class Documentation {}
