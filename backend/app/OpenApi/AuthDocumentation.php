<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Authentication',
    description: 'Authentication, password reset, invitation, and current session endpoints.'
)]
#[OA\Schema(
    schema: 'AuthTokenResponse',
    required: ['access_token', 'token_type', 'expires_at'],
    properties: [
        new OA\Property(
            property: 'access_token',
            description: 'Laravel Sanctum plain text token. Use this value as a bearer token on authenticated requests.',
            type: 'string',
            example: '1|n7S5m3mExampleSanctumTokenValue'
        ),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        new OA\Property(
            property: 'expires_at',
            description: 'ISO 8601 token expiration timestamp.',
            type: 'string',
            format: 'date-time',
            example: '2026-06-01T12:00:00+00:00'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'MessageResponse',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Logged out'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'The email field is required. (and 1 more error)'
        ),
        new OA\Property(
            property: 'errors',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string')
            ),
            type: 'object',
            example: [
                'email' => ['The email field is required.'],
                'password' => ['The password field is required.'],
            ]
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UnauthorizedResponse',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CurrentUserResponse',
    required: ['data'],
    properties: [
        new OA\Property(
            property: 'data',
            required: ['id', 'name', 'phone', 'timezone', 'email'],
            properties: [
                new OA\Property(property: 'id', type: 'string', example: 'usr_01HZYV5W8K6J7Q9P2N3M4R5T6V'),
                new OA\Property(property: 'name', type: 'string', example: 'Alex Student'),
                new OA\Property(property: 'phone', nullable: true, type: 'string', example: '+15551234567'),
                new OA\Property(property: 'timezone', nullable: true, type: 'string', example: 'Asia/Manila'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex.student@example.com'),
                new OA\Property(
                    property: 'roles',
                    description: 'Returned for admin or staff viewers.',
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                    example: ['student']
                ),
                new OA\Property(
                    property: 'status',
                    description: 'Returned for admin or staff viewers.',
                    type: 'string',
                    example: 'active'
                ),
                new OA\Property(
                    property: 'student_profile',
                    description: 'Included when the authenticated user has a loaded student profile.',
                    nullable: true,
                    type: 'object'
                ),
                new OA\Property(
                    property: 'teacher_profile',
                    description: 'Included when the authenticated user has a loaded teacher profile.',
                    nullable: true,
                    type: 'object'
                ),
                new OA\Property(
                    property: 'staff_profile',
                    description: 'Included when the authenticated user has a loaded staff profile.',
                    nullable: true,
                    type: 'object'
                ),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Post(
    path: '/auth/login',
    operationId: 'authLogin',
    summary: 'Login',
    description: 'Authenticates an active user and returns a Laravel Sanctum bearer token.',
    security: [],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex.student@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
            ],
            type: 'object'
        )
    ),
    tags: ['Authentication'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Login succeeded. Send `Authorization: Bearer {access_token}` on protected endpoints.',
            content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')
        ),
        new OA\Response(
            response: 401,
            description: 'Invalid credentials or inactive account.',
            content: new OA\JsonContent(
                allOf: [new OA\Schema(ref: '#/components/schemas/UnauthorizedResponse')],
                example: ['message' => 'Invalid credentials.']
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Validation error.',
            content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
        ),
    ]
)]
#[OA\Post(
    path: '/auth/register',
    operationId: 'authRegister',
    summary: 'Register',
    description: 'Creates a self-registered active student account.',
    security: [],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Alex Student'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex.student@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
            ],
            type: 'object'
        )
    ),
    tags: ['Authentication'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Registration succeeded.',
            content: new OA\JsonContent(
                allOf: [new OA\Schema(ref: '#/components/schemas/MessageResponse')],
                example: ['message' => 'User registered successfully']
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Validation error.',
            content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
        ),
    ]
)]
#[OA\Post(
    path: '/auth/forgot-password',
    operationId: 'authForgotPassword',
    summary: 'Forgot password',
    description: 'Requests a password reset link. The response is intentionally generic whether or not the account exists.',
    security: [],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex.student@example.com'),
            ],
            type: 'object'
        )
    ),
    tags: ['Authentication'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Password reset link request accepted.',
            content: new OA\JsonContent(
                allOf: [new OA\Schema(ref: '#/components/schemas/MessageResponse')],
                example: ['message' => 'If the account exists, a reset link has been sent.']
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Validation error.',
            content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
        ),
    ]
)]
#[OA\Post(
    path: '/auth/reset-password',
    operationId: 'authResetPassword',
    summary: 'Reset password',
    description: 'Resets a password with a valid reset token.',
    security: [],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['token', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'token', type: 'string', example: '8a2f1f3b9e4c5d6a7b8c9d0e1f2a3b4c5d6e7f8a'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex.student@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'new-password123'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'new-password123'),
            ],
            type: 'object'
        )
    ),
    tags: ['Authentication'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Password reset succeeded.',
            content: new OA\JsonContent(
                allOf: [new OA\Schema(ref: '#/components/schemas/MessageResponse')],
                example: ['message' => 'Password has been reset.']
            )
        ),
        new OA\Response(
            response: 400,
            description: 'Reset token was invalid or expired.',
            content: new OA\JsonContent(
                allOf: [new OA\Schema(ref: '#/components/schemas/MessageResponse')],
                example: ['message' => 'Failed to reset password.']
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Validation error.',
            content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
        ),
    ]
)]
#[OA\Post(
    path: '/auth/logout',
    operationId: 'authLogout',
    summary: 'Logout',
    description: 'Revokes the current Sanctum access token. Required header: `Authorization: Bearer {access_token}`.',
    security: [['sanctum' => []]],
    tags: ['Authentication'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Logout succeeded.',
            content: new OA\JsonContent(
                allOf: [new OA\Schema(ref: '#/components/schemas/MessageResponse')],
                example: ['message' => 'Logged out']
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Missing, expired, or invalid bearer token.',
            content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedResponse')
        ),
    ]
)]
#[OA\Post(
    path: '/auth/invite',
    operationId: 'authInvite',
    summary: 'Invite user',
    description: 'Creates a user invitation token. Required header: `Authorization: Bearer {access_token}`. The authenticated user must be an admin or staff user with `users.create` permission.',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'new.user@example.com'),
            ],
            type: 'object'
        )
    ),
    tags: ['Authentication'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Invitation was created.',
            content: new OA\JsonContent(
                allOf: [new OA\Schema(ref: '#/components/schemas/MessageResponse')],
                example: ['message' => 'Invitation sent.']
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Missing, expired, or invalid bearer token.',
            content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedResponse')
        ),
        new OA\Response(response: 403, description: 'Authenticated user does not have permission to invite users.'),
        new OA\Response(
            response: 422,
            description: 'Validation error.',
            content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
        ),
    ]
)]
#[OA\Get(
    path: '/auth/accept-invitation/{token}',
    operationId: 'authAcceptInvitation',
    summary: 'Accept invitation',
    description: 'Accepts an invitation token. The current implementation validates and consumes the invitation token.',
    security: [],
    tags: ['Authentication'],
    parameters: [
        new OA\Parameter(
            name: 'token',
            description: 'Invitation token from the invitation link.',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string'),
            example: 'W3vP2eD9sT7aK4mQ8xY1bC6nR0hL5zFu'
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Invitation accepted.',
            content: new OA\JsonContent(
                allOf: [new OA\Schema(ref: '#/components/schemas/MessageResponse')],
                example: ['message' => 'Invitation accepted.']
            )
        ),
        new OA\Response(
            response: 400,
            description: 'Invitation token is expired.',
            content: new OA\JsonContent(
                allOf: [new OA\Schema(ref: '#/components/schemas/MessageResponse')],
                example: ['message' => 'Invitation has expired.']
            )
        ),
        new OA\Response(response: 404, description: 'Invitation token was not found.'),
    ]
)]
#[OA\Get(
    path: '/user',
    operationId: 'authCurrentUser',
    summary: 'Current authenticated user',
    description: 'Returns the user associated with the current Sanctum token. Required header: `Authorization: Bearer {access_token}`.',
    security: [['sanctum' => []]],
    tags: ['Authentication'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Authenticated user profile.',
            content: new OA\JsonContent(ref: '#/components/schemas/CurrentUserResponse')
        ),
        new OA\Response(
            response: 401,
            description: 'Missing, expired, or invalid bearer token.',
            content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedResponse')
        ),
    ]
)]
class AuthDocumentation {}
