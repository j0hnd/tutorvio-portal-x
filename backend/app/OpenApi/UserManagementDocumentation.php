<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'User Management',
    description: 'Admin and staff user management endpoints. All operations require `Authorization: Bearer {access_token}`, the `admin` or `staff` role, and the named permission shown on each operation. Use user `public_id` values in URLs and client-facing examples; do not expose database primary keys.'
)]
#[OA\Schema(
    schema: 'UserManagementStudentProfile',
    properties: [
        new OA\Property(property: 'english_level', nullable: true, type: 'string', example: 'B1'),
        new OA\Property(property: 'current_level', nullable: true, type: 'string', example: 'Intermediate'),
        new OA\Property(property: 'course', nullable: true, type: 'string', example: 'General English'),
        new OA\Property(
            property: 'assigned_teacher_id',
            description: 'Teacher public ID when assigning or returning the assigned teacher. Use public IDs, not database primary keys.',
            nullable: true,
            type: 'string',
            example: 'usr_01HZYVTEACHER000000000000'
        ),
        new OA\Property(property: 'class_type', nullable: true, type: 'string', example: 'one_on_one'),
        new OA\Property(property: 'start_date', nullable: true, type: 'string', format: 'date', example: '2026-06-15'),
        new OA\Property(property: 'notes', nullable: true, type: 'string', example: 'Prefers weekday evening lessons.'),
        new OA\Property(property: 'preferences', nullable: true, type: 'string', example: 'Conversation-first classes.'),
        new OA\Property(property: 'goals', nullable: true, type: 'string', example: 'Improve speaking confidence for work calls.'),
        new OA\Property(property: 'learning_concerns', nullable: true, type: 'string', example: 'Needs slower correction pacing.'),
        new OA\Property(property: 'teacher_notes', nullable: true, type: 'string', example: 'Focus on fluency before grammar correction.'),
        new OA\Property(property: 'internal_notes', nullable: true, type: 'string', example: 'Billing team confirmed corporate account.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UserManagementTeacherProfile',
    properties: [
        new OA\Property(property: 'specialization', nullable: true, type: 'string', example: 'Business English'),
        new OA\Property(property: 'bio', nullable: true, type: 'string', example: 'Experienced business English coach.'),
        new OA\Property(property: 'expertise', nullable: true, type: 'string', example: 'IELTS, business presentations, interview preparation'),
        new OA\Property(property: 'class_load', nullable: true, type: 'integer', example: 18),
        new OA\Property(
            property: 'teaching_availability',
            nullable: true,
            type: 'object',
            example: ['monday' => ['09:00-12:00'], 'wednesday' => ['13:00-16:00']]
        ),
        new OA\Property(property: 'performance_summary', nullable: true, type: 'string', example: 'Strong retention and punctuality.'),
        new OA\Property(property: 'internal_status', nullable: true, type: 'string', example: 'available'),
        new OA\Property(property: 'teaching_notes', nullable: true, type: 'string', example: 'Best assigned to adult professionals.'),
        new OA\Property(property: 'internal_remarks', nullable: true, type: 'string', example: 'Reviewed by operations lead.'),
        new OA\Property(property: 'document_contract_status', nullable: true, type: 'string', example: 'signed'),
        new OA\Property(
            property: 'assigned_student_ids',
            description: 'Student public IDs to assign to this teacher. Use public IDs, not database primary keys.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['usr_01HZYVSTUDENT00000000000']
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UserManagementStaffProfile',
    properties: [
        new OA\Property(property: 'department', nullable: true, type: 'string', example: 'Operations'),
        new OA\Property(property: 'access_limitations', nullable: true, type: 'string', example: 'Scheduling and billing only'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UserManagementUser',
    required: ['id', 'name', 'email', 'status', 'roles', 'permissions'],
    properties: [
        new OA\Property(
            property: 'id',
            description: 'User public ID. Clients should store and send this value in path parameters instead of a database primary key.',
            type: 'string',
            example: 'usr_01HZYV5W8K6J7Q9P2N3M4R5T6V'
        ),
        new OA\Property(property: 'name', type: 'string', example: 'Alex Student'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alex.student@example.com'),
        new OA\Property(property: 'phone', nullable: true, type: 'string', example: '+15551234567'),
        new OA\Property(property: 'timezone', nullable: true, type: 'string', example: 'Asia/Manila'),
        new OA\Property(
            property: 'profile_photo_path',
            description: 'Optional path or key for an already-uploaded profile photo. Update through the create/update user payload when implemented by storage flow.',
            nullable: true,
            type: 'string',
            example: 'users/profile-photos/usr_01HZYV5W8K6J7Q9P2N3M4R5T6V.jpg'
        ),
        new OA\Property(property: 'signed_document_path', nullable: true, type: 'string', example: 'contracts/usr_01HZYV5W8K6J7Q9P2N3M4R5T6V.pdf'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'invited', 'suspended'], example: 'active'),
        new OA\Property(
            property: 'roles',
            type: 'array',
            items: new OA\Items(type: 'string', enum: ['student', 'teacher', 'admin', 'staff']),
            example: ['student']
        ),
        new OA\Property(
            property: 'permissions',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: []
        ),
        new OA\Property(property: 'student_profile', ref: '#/components/schemas/UserManagementStudentProfile', nullable: true),
        new OA\Property(property: 'teacher_profile', ref: '#/components/schemas/UserManagementTeacherProfile', nullable: true),
        new OA\Property(property: 'staff_profile', ref: '#/components/schemas/UserManagementStaffProfile', nullable: true),
        new OA\Property(property: 'invited_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:00:00+00:00'),
        new OA\Property(property: 'activated_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T09:30:00+00:00'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-01T09:30:00+00:00'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UserManagementUserEnvelope',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/UserManagementUser'),
    ],
    type: 'object',
    example: [
        'data' => [
            'id' => 'usr_01HZYV5W8K6J7Q9P2N3M4R5T6V',
            'name' => 'Alex Student',
            'email' => 'alex.student@example.com',
            'phone' => '+15551234567',
            'timezone' => 'Asia/Manila',
            'profile_photo_path' => 'users/profile-photos/usr_01HZYV5W8K6J7Q9P2N3M4R5T6V.jpg',
            'signed_document_path' => null,
            'status' => 'active',
            'roles' => ['student'],
            'permissions' => [],
            'student_profile' => [
                'english_level' => 'B1',
                'current_level' => 'Intermediate',
                'course' => 'General English',
                'assigned_teacher_id' => 'usr_01HZYVTEACHER000000000000',
                'class_type' => 'one_on_one',
                'start_date' => '2026-06-15',
                'goals' => 'Improve speaking confidence for work calls.',
            ],
            'teacher_profile' => null,
            'staff_profile' => null,
            'invited_at' => '2026-06-01T09:00:00+00:00',
            'activated_at' => '2026-06-01T09:30:00+00:00',
            'created_at' => '2026-06-01T09:00:00+00:00',
            'updated_at' => '2026-06-01T09:30:00+00:00',
        ],
    ]
)]
#[OA\Schema(
    schema: 'UserManagementCreateUserRequest',
    required: ['name', 'email', 'role'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Alex Student'),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'alex.student@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'TempPass123'),
        new OA\Property(property: 'phone', nullable: true, type: 'string', maxLength: 50, example: '+15551234567'),
        new OA\Property(property: 'timezone', nullable: true, type: 'string', example: 'Asia/Manila'),
        new OA\Property(property: 'profile_photo_path', nullable: true, type: 'string', maxLength: 2048, example: 'users/profile-photos/usr_01HZYV5W8K6J7Q9P2N3M4R5T6V.jpg'),
        new OA\Property(property: 'signed_document_path', nullable: true, type: 'string', maxLength: 2048, example: 'contracts/usr_01HZYV5W8K6J7Q9P2N3M4R5T6V.pdf'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'invited', 'suspended'], example: 'invited'),
        new OA\Property(property: 'status_reason', nullable: true, type: 'string', maxLength: 2000, example: 'Initial invitation created by operations.'),
        new OA\Property(property: 'role', type: 'string', enum: ['student', 'teacher', 'admin', 'staff'], example: 'student'),
        new OA\Property(
            property: 'permissions',
            description: 'Only allowed for staff users. Values must be existing permission names.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['users.view', 'users.update']
        ),
        new OA\Property(property: 'student_profile', ref: '#/components/schemas/UserManagementStudentProfile'),
        new OA\Property(property: 'teacher_profile', ref: '#/components/schemas/UserManagementTeacherProfile'),
        new OA\Property(property: 'staff_profile', ref: '#/components/schemas/UserManagementStaffProfile'),
    ],
    type: 'object',
    example: [
        'name' => 'Alex Student',
        'email' => 'alex.student@example.com',
        'role' => 'student',
        'phone' => '+15551234567',
        'timezone' => 'Asia/Manila',
        'status' => 'invited',
        'student_profile' => [
            'english_level' => 'B1',
            'course' => 'General English',
            'assigned_teacher_id' => 'usr_01HZYVTEACHER000000000000',
            'class_type' => 'one_on_one',
            'start_date' => '2026-06-15',
            'goals' => 'Improve speaking confidence for work calls.',
        ],
    ]
)]
#[OA\Schema(
    schema: 'UserManagementUpdateUserRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Alex Student Updated'),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'alex.updated@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'NewTempPass123'),
        new OA\Property(property: 'phone', nullable: true, type: 'string', maxLength: 50, example: '+15551239999'),
        new OA\Property(property: 'timezone', nullable: true, type: 'string', example: 'Asia/Manila'),
        new OA\Property(property: 'profile_photo_path', nullable: true, type: 'string', maxLength: 2048, example: 'users/profile-photos/usr_01HZYV5W8K6J7Q9P2N3M4R5T6V-updated.jpg'),
        new OA\Property(property: 'signed_document_path', nullable: true, type: 'string', maxLength: 2048, example: 'contracts/usr_01HZYV5W8K6J7Q9P2N3M4R5T6V.pdf'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'invited', 'suspended'], example: 'active'),
        new OA\Property(property: 'status_reason', nullable: true, type: 'string', maxLength: 2000, example: 'Profile verified by operations.'),
        new OA\Property(property: 'role', type: 'string', enum: ['student', 'teacher', 'admin', 'staff'], example: 'student'),
        new OA\Property(
            property: 'permissions',
            description: 'Only allowed for staff users.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['users.view', 'users.update']
        ),
        new OA\Property(property: 'student_profile', ref: '#/components/schemas/UserManagementStudentProfile'),
        new OA\Property(property: 'teacher_profile', ref: '#/components/schemas/UserManagementTeacherProfile'),
        new OA\Property(property: 'staff_profile', ref: '#/components/schemas/UserManagementStaffProfile'),
    ],
    type: 'object',
    example: [
        'phone' => '+15551239999',
        'timezone' => 'Asia/Manila',
        'profile_photo_path' => 'users/profile-photos/usr_01HZYV5W8K6J7Q9P2N3M4R5T6V-updated.jpg',
        'student_profile' => [
            'english_level' => 'B2',
            'goals' => 'Improve fluency in meetings.',
        ],
    ]
)]
#[OA\Schema(
    schema: 'UserManagementStatusRequest',
    properties: [
        new OA\Property(property: 'reason', nullable: true, type: 'string', maxLength: 2000, example: 'Ready for lessons.'),
    ],
    type: 'object',
    example: ['reason' => 'Ready for lessons.']
)]
#[OA\Schema(
    schema: 'UserManagementAssignRoleRequest',
    required: ['role'],
    properties: [
        new OA\Property(property: 'role', type: 'string', enum: ['student', 'teacher', 'admin', 'staff'], example: 'staff'),
        new OA\Property(
            property: 'permissions',
            description: 'Only allowed when `role` is `staff`.',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['users.view', 'users.update']
        ),
    ],
    type: 'object',
    example: [
        'role' => 'staff',
        'permissions' => ['users.view', 'users.update'],
    ]
)]
#[OA\Get(
    path: '/users',
    operationId: 'adminListUsers',
    summary: 'List users',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: admin users, or staff users with `users.view` permission. Query filters are optional. Returned examples use public IDs instead of database primary keys.',
    security: [['sanctum' => []]],
    tags: ['User Management', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'role', description: 'Filter by primary managed role.', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['student', 'teacher', 'admin', 'staff'])),
        new OA\Parameter(name: 'status', description: 'Filter by account status.', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'invited', 'suspended'])),
        new OA\Parameter(name: 'search', description: 'Search by name or email.', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 255), example: 'alex'),
        new OA\Parameter(name: 'per_page', description: 'Pagination size from 1 to 100.', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 25),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Paginated user list.',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/UserManagementUser')),
                    new OA\Property(property: 'links', type: 'object'),
                    new OA\Property(property: 'meta', type: 'object'),
                ],
                type: 'object',
                example: [
                    'data' => [
                        [
                            'id' => 'usr_01HZYV5W8K6J7Q9P2N3M4R5T6V',
                            'name' => 'Alex Student',
                            'email' => 'alex.student@example.com',
                            'phone' => '+15551234567',
                            'timezone' => 'Asia/Manila',
                            'status' => 'active',
                            'roles' => ['student'],
                            'permissions' => [],
                            'student_profile' => [
                                'english_level' => 'B1',
                                'course' => 'General English',
                                'assigned_teacher_id' => 'usr_01HZYVTEACHER000000000000',
                                'class_type' => 'one_on_one',
                            ],
                            'teacher_profile' => null,
                            'staff_profile' => null,
                            'created_at' => '2026-06-01T09:00:00+00:00',
                            'updated_at' => '2026-06-01T09:30:00+00:00',
                        ],
                    ],
                    'links' => ['first' => 'http://localhost:8001/api/v1/users?page=1', 'last' => 'http://localhost:8001/api/v1/users?page=1', 'prev' => null, 'next' => null],
                    'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1],
                ]
            )
        ),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
#[OA\Post(
    path: '/users',
    operationId: 'adminCreateUser',
    summary: 'Create user',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: admin users, or staff users with `users.create` permission. Role-specific profile fields must match the selected role. Custom `permissions` are allowed only for staff users. `profile_photo_path` may be supplied when photo storage already has an uploaded path.',
    security: [['sanctum' => []]],
    tags: ['User Management', 'Admin'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/UserManagementCreateUserRequest')
    ),
    responses: [
        new OA\Response(response: 201, description: 'User created.', content: new OA\JsonContent(ref: '#/components/schemas/UserManagementUserEnvelope')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
#[OA\Get(
    path: '/users/{user}',
    operationId: 'adminViewUser',
    summary: 'View user',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: admin users, or staff users with `users.view` permission. The `{user}` path parameter is the user public ID, not the database primary key.',
    security: [['sanctum' => []]],
    tags: ['User Management', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'user', description: 'User public ID.', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01HZYV5W8K6J7Q9P2N3M4R5T6V'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'User details.', content: new OA\JsonContent(ref: '#/components/schemas/UserManagementUserEnvelope')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'No user was found for the supplied public ID.'),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
#[OA\Patch(
    path: '/users/{user}',
    operationId: 'adminUpdateUser',
    summary: 'Update user',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: admin users, or staff users with `users.update` permission. The `{user}` path parameter is the user public ID. Use this endpoint to update profile fields and `profile_photo_path` when a photo path is already available.',
    security: [['sanctum' => []]],
    tags: ['User Management', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'user', description: 'User public ID.', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01HZYV5W8K6J7Q9P2N3M4R5T6V'),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/UserManagementUpdateUserRequest')
    ),
    responses: [
        new OA\Response(response: 200, description: 'User updated.', content: new OA\JsonContent(ref: '#/components/schemas/UserManagementUserEnvelope')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'No user was found for the supplied public ID.'),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
#[OA\Post(
    path: '/users/{user}/activate',
    operationId: 'adminActivateUser',
    summary: 'Activate user',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: admin users, or staff users with `users.activate` permission. The `{user}` path parameter is the user public ID.',
    security: [['sanctum' => []]],
    tags: ['User Management', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'user', description: 'User public ID.', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01HZYV5W8K6J7Q9P2N3M4R5T6V'),
    ],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(ref: '#/components/schemas/UserManagementStatusRequest')
    ),
    responses: [
        new OA\Response(response: 200, description: 'User activated.', content: new OA\JsonContent(ref: '#/components/schemas/UserManagementUserEnvelope')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'No user was found for the supplied public ID.'),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
#[OA\Post(
    path: '/users/{user}/deactivate',
    operationId: 'adminDeactivateUser',
    summary: 'Deactivate user',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: admin users, or staff users with `users.deactivate` permission. The `{user}` path parameter is the user public ID.',
    security: [['sanctum' => []]],
    tags: ['User Management', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'user', description: 'User public ID.', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01HZYV5W8K6J7Q9P2N3M4R5T6V'),
    ],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(ref: '#/components/schemas/UserManagementStatusRequest')
    ),
    responses: [
        new OA\Response(response: 200, description: 'User deactivated.', content: new OA\JsonContent(ref: '#/components/schemas/UserManagementUserEnvelope')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'No user was found for the supplied public ID.'),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
#[OA\Post(
    path: '/users/{user}/roles',
    operationId: 'adminAssignUserRole',
    summary: 'Assign role',
    description: 'Protected endpoint. Required header: `Authorization: Bearer {access_token}`. Access: admin users, or staff users with `users.assign_roles` permission. The `{user}` path parameter is the user public ID. Custom permissions are valid only when assigning the staff role.',
    security: [['sanctum' => []]],
    tags: ['User Management', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'user', description: 'User public ID.', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01HZYV5W8K6J7Q9P2N3M4R5T6V'),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/UserManagementAssignRoleRequest')
    ),
    responses: [
        new OA\Response(response: 200, description: 'User role and staff permissions updated.', content: new OA\JsonContent(ref: '#/components/schemas/UserManagementUserEnvelope')),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(response: 404, description: 'No user was found for the supplied public ID.'),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
        new OA\Response(ref: '#/components/responses/ServerError', response: 500),
    ]
)]
class UserManagementDocumentation {}
