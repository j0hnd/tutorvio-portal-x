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
        new OA\Property(property: 'message', type: 'string', example: 'This action is unauthorized.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ServerErrorResponse',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Server Error'),
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
class Documentation {}
