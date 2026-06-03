<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Billing',
    description: 'Invoice and payment-status APIs. All endpoints require Sanctum bearer authentication. Admins have full access. Staff users need the documented billing permission (`invoices.view`, `invoices.create`, or `invoices.update`). Students may view and download only their own invoices when student invoice visibility is enabled. Teachers do not have invoice access.'
)]
#[OA\Tag(
    name: 'Packages',
    description: 'Student package, subscription status, lesson balance, package history, and package assignment APIs. Admins have full access. Staff users need the documented subscription permission. Students can see only their own student-facing package records when enabled. Teachers may see only limited package summaries for assigned students and do not receive billing references.'
)]
#[OA\Tag(
    name: 'Payroll',
    description: 'Teacher pay rate, earning, payout report, and manual payroll adjustment APIs. Payroll data is restricted to admins and staff with payroll or compensation permissions. Teacher self-service payroll views are limited to the authenticated teacher and are available only when the related teacher visibility configuration or own-view permission allows it. Teacher responses omit internal notes and creator/audit fields.'
)]
#[OA\Schema(
    schema: 'InvoiceStatus',
    description: 'Invoice payment status. `overdue` may be stored directly or derived for unpaid invoices past due.',
    type: 'string',
    enum: ['paid', 'unpaid', 'overdue'],
    example: 'unpaid'
)]
#[OA\Schema(
    schema: 'PackageStatus',
    description: 'Stored package/subscription status. Student summaries may additionally present `frozen` when `is_frozen` is true.',
    type: 'string',
    enum: ['active', 'inactive', 'expired', 'cancelled', 'frozen'],
    example: 'active'
)]
#[OA\Schema(
    schema: 'PackagePaymentStatus',
    type: 'string',
    enum: ['paid', 'unpaid', 'partial', 'overdue'],
    example: 'paid'
)]
#[OA\Schema(
    schema: 'PackageType',
    type: 'string',
    enum: ['package', 'subscription'],
    example: 'package'
)]
#[OA\Schema(
    schema: 'TeacherPayModel',
    type: 'string',
    enum: ['per_lesson', 'per_hour', 'per_student', 'per_course'],
    example: 'per_lesson'
)]
#[OA\Schema(
    schema: 'TeacherEarningStatus',
    type: 'string',
    enum: ['pending', 'approved', 'included_in_payout', 'paid', 'voided'],
    example: 'approved'
)]
#[OA\Schema(
    schema: 'PayrollAdjustmentType',
    type: 'string',
    enum: ['bonus', 'deduction', 'correction', 'reimbursement', 'other'],
    example: 'bonus'
)]
#[OA\Schema(
    schema: 'BillingInvoice',
    description: 'Invoice response. Public IDs are used in client-facing relationship fields. `payment_reference`, `metadata`, and timestamps are returned only to admins and staff with invoice permissions. Gateway payloads and customer/payment method identifiers are never exposed by this resource.',
    required: ['id', 'invoice_number', 'subtotal', 'amount', 'tax_amount', 'total_amount', 'currency', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'inv_01J0INVOICE000000000001'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'subscription_id', nullable: true, type: 'string', example: 'sub_01J0PACKAGE000000000001'),
        new OA\Property(property: 'course_program_id', nullable: true, type: 'string', example: 'crs_01J0BUSINESS000000000001'),
        new OA\Property(property: 'invoice_number', type: 'string', example: 'INV-2026-00042'),
        new OA\Property(property: 'reference', type: 'string', example: 'INV-2026-00042'),
        new OA\Property(property: 'subtotal', description: 'Pre-tax invoice amount in `currency`.', type: 'number', format: 'float', example: 200.00),
        new OA\Property(property: 'amount', description: 'Alias of subtotal in `currency`.', type: 'number', format: 'float', example: 200.00),
        new OA\Property(property: 'tax_amount', description: 'Tax amount in `currency`.', type: 'number', format: 'float', example: 20.00),
        new OA\Property(property: 'total_amount', description: 'Subtotal plus tax in `currency`.', type: 'number', format: 'float', example: 220.00),
        new OA\Property(property: 'currency', description: 'ISO 4217 currency code.', type: 'string', minLength: 3, maxLength: 3, example: 'USD'),
        new OA\Property(property: 'issued_date', type: 'string', format: 'date', example: '2026-06-01'),
        new OA\Property(property: 'due_date', type: 'string', format: 'date', example: '2026-06-15'),
        new OA\Property(property: 'paid_date', nullable: true, type: 'string', format: 'date', example: null),
        new OA\Property(property: 'status', ref: '#/components/schemas/InvoiceStatus'),
        new OA\Property(property: 'student', type: 'object', example: ['id' => 'usr_01J0STUDENT000000000000001', 'name' => 'Alex Student', 'email' => 'alex.student@example.com']),
        new OA\Property(property: 'payment_reference', description: 'Admin/staff billing field. Do not expose to students or teachers.', nullable: true, type: 'string', example: 'BANK-REF-2026-00042'),
        new OA\Property(property: 'metadata', description: 'Admin/staff billing metadata sanitized for API output.', type: 'object', example: ['payment_status_history' => []]),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PackageSubscription',
    description: 'Admin/staff package subscription response. Internal notes, creator IDs, renewal window keys, and renewal notes are admin/staff-only fields.',
    required: ['id', 'plan_name', 'package_type', 'total_lesson_count', 'consumed_lesson_count', 'remaining_lesson_count', 'status', 'payment_status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'sub_01J0PACKAGE000000000001'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'plan_name', type: 'string', example: 'Business English 20 Lessons'),
        new OA\Property(property: 'package_type', ref: '#/components/schemas/PackageType'),
        new OA\Property(property: 'total_lesson_count', type: 'integer', example: 20),
        new OA\Property(property: 'consumed_lesson_count', type: 'integer', example: 6),
        new OA\Property(property: 'remaining_lesson_count', type: 'integer', example: 14),
        new OA\Property(property: 'status', ref: '#/components/schemas/PackageStatus'),
        new OA\Property(property: 'is_frozen', type: 'boolean', example: false),
        new OA\Property(property: 'frozen_at', nullable: true, type: 'string', format: 'date-time', example: null),
        new OA\Property(property: 'payment_status', ref: '#/components/schemas/PackagePaymentStatus'),
        new OA\Property(property: 'invoice_id', nullable: true, type: 'string', example: 'inv_01J0INVOICE000000000001'),
        new OA\Property(property: 'invoice_reference', description: 'Billing reference visible only to admins and staff with invoice access.', nullable: true, type: 'string', example: 'INV-2026-00042'),
        new OA\Property(property: 'starts_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T00:00:00Z'),
        new OA\Property(property: 'ends_at', nullable: true, type: 'string', format: 'date-time', example: '2026-08-31T23:59:59Z'),
        new OA\Property(property: 'internal_notes', description: 'Admin/staff-only field.', nullable: true, type: 'string', example: 'Approved package extension after billing review.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'StudentPackageSummary',
    description: 'Student-facing package summary. Billing references appear only for students when invoice visibility is enabled and for admins/staff with invoice access; assigned teachers receive lesson/status fields without billing references.',
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Business English 20 Lessons'),
        new OA\Property(property: 'plan_name', type: 'string', example: 'Business English 20 Lessons'),
        new OA\Property(property: 'status', ref: '#/components/schemas/PackageStatus'),
        new OA\Property(property: 'starts_at', nullable: true, type: 'string', format: 'date-time', example: '2026-06-01T00:00:00Z'),
        new OA\Property(property: 'ends_at', nullable: true, type: 'string', format: 'date-time', example: '2026-08-31T23:59:59Z'),
        new OA\Property(property: 'total_lessons', type: 'integer', example: 20),
        new OA\Property(property: 'consumed_lessons', type: 'integer', example: 6),
        new OA\Property(property: 'remaining_lessons', type: 'integer', example: 14),
        new OA\Property(property: 'payment_status', ref: '#/components/schemas/PackagePaymentStatus'),
        new OA\Property(property: 'invoice_reference', nullable: true, type: 'string', example: 'INV-2026-00042'),
        new OA\Property(property: 'renewal_reminder', type: 'object', example: ['reminder_date' => '2026-08-24', 'status' => 'pending', 'renewal_eligible' => true, 'days_until_end' => 7, 'should_renew_soon' => true]),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PackageHistoryEntry',
    description: 'Package history entry. Students receive safe history only; payment changes, invoice-reference changes, previous/new values, notes, and creator IDs are visible only to admins and staff with `subscriptions.view`.',
    properties: [
        new OA\Property(property: 'subscription_id', type: 'string', example: 'sub_01J0PACKAGE000000000001'),
        new OA\Property(property: 'student_id', type: 'string', example: 'usr_01J0STUDENT000000000000001'),
        new OA\Property(property: 'event_type', type: 'string', enum: ['assigned', 'updated', 'status_changed', 'payment_changed', 'invoice_reference_changed', 'frozen', 'unfrozen', 'renewed', 'cancelled', 'archived', 'lesson_balance_adjusted'], example: 'assigned'),
        new OA\Property(property: 'plan_name', type: 'string', example: 'Business English 20 Lessons'),
        new OA\Property(property: 'package_type', ref: '#/components/schemas/PackageType'),
        new OA\Property(property: 'total_lesson_count', type: 'integer', example: 20),
        new OA\Property(property: 'consumed_lesson_count', type: 'integer', example: 6),
        new OA\Property(property: 'remaining_lesson_count', type: 'integer', example: 14),
        new OA\Property(property: 'status', ref: '#/components/schemas/PackageStatus'),
        new OA\Property(property: 'payment_status', ref: '#/components/schemas/PackagePaymentStatus'),
        new OA\Property(property: 'effective_at', type: 'string', format: 'date-time', example: '2026-06-01T09:00:00Z'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PackageAssignmentRequest',
    required: ['student_id', 'plan_name', 'package_type', 'total_lesson_count', 'consumed_lesson_count', 'remaining_lesson_count', 'status', 'payment_status'],
    properties: [
        new OA\Property(property: 'student_id', description: 'Internal numeric user ID for a student.', type: 'integer', example: 23),
        new OA\Property(property: 'plan_name', type: 'string', example: 'Business English 20 Lessons'),
        new OA\Property(property: 'package_type', ref: '#/components/schemas/PackageType'),
        new OA\Property(property: 'total_lesson_count', type: 'integer', minimum: 0, example: 20),
        new OA\Property(property: 'consumed_lesson_count', type: 'integer', minimum: 0, example: 0),
        new OA\Property(property: 'remaining_lesson_count', type: 'integer', minimum: 0, example: 20),
        new OA\Property(property: 'status', ref: '#/components/schemas/PackageStatus'),
        new OA\Property(property: 'payment_status', ref: '#/components/schemas/PackagePaymentStatus'),
        new OA\Property(property: 'invoice_id', nullable: true, type: 'string', example: 'inv_01J0INVOICE000000000001'),
        new OA\Property(property: 'invoice_reference', nullable: true, type: 'string', example: 'INV-2026-00042'),
        new OA\Property(property: 'starts_at', nullable: true, type: 'string', format: 'date', example: '2026-06-01'),
        new OA\Property(property: 'ends_at', nullable: true, type: 'string', format: 'date', example: '2026-08-31'),
        new OA\Property(property: 'internal_notes', description: 'Admin/staff-only note; never returned to students or teachers.', nullable: true, type: 'string', example: 'Assigned after payment confirmation.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'TeacherCompensation',
    description: 'Teacher pay rate configuration. Admin/staff-only payroll object; teachers do not receive compensation configuration through these admin routes.',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'tcp_01J0COMP000000000001'),
        new OA\Property(property: 'teacher_id', description: 'Internal numeric teacher user ID.', type: 'integer', example: 17),
        new OA\Property(property: 'pay_model', ref: '#/components/schemas/TeacherPayModel'),
        new OA\Property(property: 'base_rate', type: 'number', format: 'float', example: 18.00),
        new OA\Property(property: 'default_pay_rate', type: 'number', format: 'float', example: 18.00),
        new OA\Property(property: 'currency', description: 'ISO 4217 currency code.', type: 'string', example: 'USD'),
        new OA\Property(property: 'effective_start_date', type: 'string', format: 'date', example: '2026-06-01'),
        new OA\Property(property: 'effective_end_date', nullable: true, type: 'string', format: 'date', example: null),
        new OA\Property(property: 'internal_admin_notes', nullable: true, type: 'string', example: 'Default rate for regular lessons.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'TeacherCompensationRateRule',
    description: 'Teacher pay-rate override rule. Admin/staff-only payroll configuration. A rule can target lesson type, experience level, contract agreement, course type, or course program; higher priority rules are evaluated first by payroll calculation.',
    properties: [
        new OA\Property(property: 'id', type: 'string', example: 'tcr_01J0RATE000000000001'),
        new OA\Property(property: 'teacher_compensation_id', type: 'string', example: 'tcp_01J0COMP000000000001'),
        new OA\Property(property: 'lesson_type', nullable: true, type: 'string', example: 'business_english'),
        new OA\Property(property: 'experience_level', nullable: true, type: 'string', example: 'senior'),
        new OA\Property(property: 'contract_agreement', nullable: true, type: 'string', example: 'standard_contract'),
        new OA\Property(property: 'course_type_id', nullable: true, type: 'integer', example: 3),
        new OA\Property(property: 'course_program_id', nullable: true, type: 'integer', example: 11),
        new OA\Property(property: 'pay_model', ref: '#/components/schemas/TeacherPayModel'),
        new OA\Property(property: 'pay_rate', type: 'number', format: 'float', example: 22.50),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'priority', type: 'integer', example: 100),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'internal_admin_notes', nullable: true, type: 'string', example: 'Course-specific override approved for June cohort.'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'TeacherEarning',
    description: 'Teacher earning row. Admin/staff routes can return all teachers according to permission; teacher self-service route is scoped to the authenticated teacher.',
    properties: [
        new OA\Property(property: 'teacher_id', description: 'Teacher public ID.', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'teacher_name', nullable: true, type: 'string', example: 'Taylor Teacher'),
        new OA\Property(property: 'earning_source', type: 'object', example: ['type' => 'lesson_record', 'id' => 'lrc_01J0LESSONRECORD00000001']),
        new OA\Property(property: 'lesson_reference', type: 'object', example: ['id' => 'lrc_01J0LESSONRECORD00000001', 'scheduled_date' => '2026-06-03', 'lesson_type' => 'business_english', 'lesson_status' => 'completed']),
        new OA\Property(property: 'course_reference', type: 'object', example: ['id' => 'crs_01J0BUSINESS000000000001', 'course_type_id' => 'ctp_01J0COURSETYPE000000001']),
        new OA\Property(property: 'pay_model', ref: '#/components/schemas/TeacherPayModel'),
        new OA\Property(property: 'rate_used', type: 'number', format: 'float', example: 18.00),
        new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 1.00),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 18.00),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'status', ref: '#/components/schemas/TeacherEarningStatus'),
        new OA\Property(property: 'earning_date', type: 'string', format: 'date', example: '2026-06-03'),
        new OA\Property(property: 'payout_period', nullable: true, type: 'string', example: '2026-06'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PayoutReport',
    description: 'Payroll report summary. Available only to admins and staff with `payroll.view`.',
    properties: [
        new OA\Property(property: 'scope', type: 'string', enum: ['period', 'teacher'], example: 'teacher'),
        new OA\Property(property: 'breakdown', type: 'object', example: ['base_earnings' => 360.00, 'variable_rate_earnings' => 40.00, 'manual_additions' => 25.00, 'manual_deductions' => 10.00, 'total_payout_amount' => 415.00]),
        new OA\Property(property: 'teachers', type: 'array', items: new OA\Items(type: 'object'), example: [['teacher_id' => 'usr_01J0TEACHER000000000000001', 'teacher_name' => 'Taylor Teacher', 'breakdown' => ['base_earnings' => 360.00, 'variable_rate_earnings' => 40.00, 'manual_additions' => 25.00, 'manual_deductions' => 10.00, 'total_payout_amount' => 415.00]]]),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PayrollAdjustment',
    description: 'Manual payroll adjustment. Teacher self-service responses omit `internal_notes`, `created_by`, `created_by_name`, and other teachers names.',
    properties: [
        new OA\Property(property: 'teacher_id', description: 'Teacher public ID.', type: 'string', example: 'usr_01J0TEACHER000000000000001'),
        new OA\Property(property: 'teacher_name', nullable: true, type: 'string', example: 'Taylor Teacher'),
        new OA\Property(property: 'payout_period_id', nullable: true, type: 'string', example: 'ppo_01J0PAYOUT000000000001'),
        new OA\Property(property: 'payout_period_name', nullable: true, type: 'string', example: 'June 2026 payout'),
        new OA\Property(property: 'type', ref: '#/components/schemas/PayrollAdjustmentType'),
        new OA\Property(property: 'amount', description: 'Deduction values are stored and returned as negative amounts.', type: 'number', format: 'float', example: 25.00),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'reason', type: 'string', example: 'Approved demo lesson bonus.'),
        new OA\Property(property: 'internal_notes', nullable: true, type: 'string', example: 'Reviewed by payroll admin.'),
    ],
    type: 'object'
)]
#[OA\Get(
    path: '/invoices',
    operationId: 'billingInvoiceList',
    summary: 'List student invoices',
    description: 'Access: admin; staff with `invoices.view`; or a student listing only their own invoices when student invoice visibility is enabled. Teachers have no invoice access. Admin/staff may filter by student, status, subscription, package, reference, and date fields. Student results are always scoped to the authenticated student. Currency fields use ISO 4217 codes; `subtotal`/`amount`, `tax_amount`, and `total_amount` are monetary values in that currency.',
    security: [['sanctum' => []]],
    tags: ['Billing'],
    parameters: [
        new OA\Parameter(name: 'student_id', in: 'query', description: 'Internal numeric student ID. Honored only for admin/staff with invoice view access.', schema: new OA\Schema(type: 'integer'), example: 23),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/InvoiceStatus')),
        new OA\Parameter(name: 'subscription_id', in: 'query', description: 'Subscription public ID.', schema: new OA\Schema(type: 'string'), example: 'sub_01J0PACKAGE000000000001'),
        new OA\Parameter(name: 'package', in: 'query', schema: new OA\Schema(type: 'string'), example: 'Business English'),
        new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'date_field', in: 'query', schema: new OA\Schema(type: 'string', enum: ['issued_date', 'due_date', 'paid_date']), example: 'issued_date'),
        new OA\Parameter(name: 'overdue', in: 'query', schema: new OA\Schema(type: 'boolean'), example: false),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated invoice list.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'inv_01J0INVOICE000000000001', 'invoice_number' => 'INV-2026-00042', 'subtotal' => '200.00', 'tax_amount' => '20.00', 'total_amount' => '220.00', 'currency' => 'USD', 'status' => 'unpaid']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/students/{student}/invoices',
    operationId: 'billingStudentInvoiceHistory',
    summary: 'List invoices for a student',
    description: 'Access: admin; staff with `invoices.view`; or the matching student when student invoice visibility is enabled. The `{student}` path parameter is a user public ID. Students cannot request another student record. Teachers cannot access this endpoint.',
    security: [['sanctum' => []]],
    tags: ['Billing', 'Student'],
    parameters: [
        new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/InvoiceStatus')),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated student invoice history.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'inv_01J0INVOICE000000000001', 'invoice_number' => 'INV-2026-00042', 'total_amount' => '220.00', 'currency' => 'USD', 'status' => 'unpaid']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/invoices/{invoice}',
    operationId: 'billingInvoiceShow',
    summary: 'Show invoice',
    description: 'Access: admin; staff with `invoices.view`; or the owning student when student invoice visibility is enabled. Response examples avoid gateway customer IDs, payment method IDs, and raw gateway payloads.',
    security: [['sanctum' => []]],
    tags: ['Billing'],
    parameters: [
        new OA\Parameter(name: 'invoice', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'inv_01J0INVOICE000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Invoice detail.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/BillingInvoice')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/invoices/{invoice}/download',
    operationId: 'billingInvoicePdfDownload',
    summary: 'Download invoice PDF',
    description: 'Access: admin; staff with `invoices.view`; or the owning student when student invoice visibility is enabled. Returns a private, non-cacheable PDF attachment. Teachers cannot download invoices.',
    security: [['sanctum' => []]],
    tags: ['Billing'],
    parameters: [
        new OA\Parameter(name: 'invoice', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'inv_01J0INVOICE000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'PDF attachment.', content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary'))),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Post(
    path: '/invoices/{invoice}/send-email',
    operationId: 'billingInvoiceEmailQueue',
    summary: 'Queue invoice email',
    description: 'Access: admin or staff with `invoices.create`. Queues a background job to email the invoice PDF to the student and returns the current invoice resource. Delivery history and invoice email metadata are updated by the queued job.',
    security: [['sanctum' => []]],
    tags: ['Billing'],
    parameters: [
        new OA\Parameter(name: 'invoice', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'inv_01J0INVOICE000000000001'),
    ],
    responses: [
        new OA\Response(response: 202, description: 'Invoice email queued.', content: new OA\JsonContent(type: 'object', properties: [
            new OA\Property(property: 'data', ref: '#/components/schemas/BillingInvoice'),
            new OA\Property(property: 'email_queued', type: 'boolean', example: true),
        ])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/invoices/{invoice}/payment-status',
    operationId: 'billingInvoicePaymentStatusUpdate',
    summary: 'Manually update invoice status',
    description: 'Access: admin or staff with `invoices.update`. Students and teachers cannot update invoice status. Only `paid` and `unpaid` are accepted for manual updates; `overdue` is tracked by overdue processing. The change is audit logged.',
    security: [['sanctum' => []]],
    tags: ['Billing'],
    parameters: [
        new OA\Parameter(name: 'invoice', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'inv_01J0INVOICE000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['status'], properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['paid', 'unpaid'], example: 'paid'),
        new OA\Property(property: 'paid_date', nullable: true, type: 'string', format: 'date', example: '2026-06-10'),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Invoice payment status updated.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/BillingInvoice')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Post(
    path: '/admin/subscriptions',
    operationId: 'packageAssignmentCreate',
    summary: 'Assign package or subscription',
    description: 'Access: admin or staff with `subscriptions.create`. This is the package assignment endpoint. Request uses an internal numeric `student_id`; response uses public IDs. Lesson counts must be internally consistent and cannot exceed total lessons. Billing references are admin/staff-only.',
    security: [['sanctum' => []]],
    tags: ['Packages', 'Admin'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/PackageAssignmentRequest')),
    responses: [
        new OA\Response(response: 201, description: 'Package assigned.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PackageSubscription')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/admin/subscriptions',
    operationId: 'packageSubscriptionList',
    summary: 'List package subscriptions',
    description: 'Access: admin or staff with `subscriptions.view`. Use this endpoint to inspect package/subscription status, payment status, lesson balance, and renewal status across students. Students and teachers cannot access this admin listing.',
    security: [['sanctum' => []]],
    tags: ['Packages', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'student_id', in: 'query', description: 'Internal numeric student ID.', schema: new OA\Schema(type: 'integer'), example: 23),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'expired', 'cancelled']), example: 'active'),
        new OA\Parameter(name: 'payment_status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/PackagePaymentStatus')),
        new OA\Parameter(name: 'package_type', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/PackageType')),
        new OA\Parameter(name: 'is_frozen', in: 'query', schema: new OA\Schema(type: 'boolean'), example: false),
        new OA\Parameter(name: 'search', in: 'query', description: 'Searches package name, invoice reference, student name, or student email for admin/staff users.', schema: new OA\Schema(type: 'string'), example: 'Business English'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated package subscriptions.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'sub_01J0PACKAGE000000000001', 'plan_name' => 'Business English 20 Lessons', 'package_type' => 'package', 'status' => 'active', 'payment_status' => 'paid', 'remaining_lesson_count' => 14]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/admin/subscriptions/{subscription}',
    operationId: 'packageSubscriptionShow',
    summary: 'Show package subscription status',
    description: 'Access: admin or staff with `subscriptions.view`. Returns the current stored package status, lesson balance, payment status, renewal reminder state, and admin/staff-only notes. Students should use `/students/{student}/package-summary`; teachers receive only limited assigned-student summaries.',
    security: [['sanctum' => []]],
    tags: ['Packages', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'subscription', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'sub_01J0PACKAGE000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Package subscription detail.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PackageSubscription')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/students/{student}/package-summary',
    operationId: 'packageStudentSummary',
    summary: 'Show student package summary',
    description: 'Access: admin; staff with `subscriptions.view`; the matching student; or an assigned teacher. Teachers receive limited lesson-balance/status information for assigned students only and do not receive payment status or invoice references. Students may receive invoice references only when student invoice visibility is enabled.',
    security: [['sanctum' => []]],
    tags: ['Packages', 'Student', 'Teacher'],
    parameters: [
        new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Current or most recent package summary, or null.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/StudentPackageSummary')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Patch(
    path: '/admin/subscriptions/{subscription}/lesson-balance',
    operationId: 'packageLessonBalanceAdjust',
    summary: 'Adjust lesson balance',
    description: 'Access: admin or staff with `subscriptions.update`. This endpoint manually updates total, consumed, and/or remaining lesson counts. At least one count is required, `consumed_lesson_count` cannot exceed total, and `remaining_lesson_count` must equal total minus consumed. Change notes are required and are admin/staff-only.',
    security: [['sanctum' => []]],
    tags: ['Packages', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'subscription', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'sub_01J0PACKAGE000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['notes'], properties: [
        new OA\Property(property: 'total_lesson_count', type: 'integer', minimum: 0, example: 20),
        new OA\Property(property: 'consumed_lesson_count', type: 'integer', minimum: 0, example: 7),
        new OA\Property(property: 'remaining_lesson_count', type: 'integer', minimum: 0, example: 13),
        new OA\Property(property: 'notes', type: 'string', example: 'Corrected balance after confirmed makeup lesson.'),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Lesson balance updated.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PackageSubscription')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Patch(
    path: '/admin/subscriptions/{subscription}/status',
    operationId: 'packageSubscriptionStatusUpdate',
    summary: 'Update subscription status',
    description: 'Access: admin or staff with `subscriptions.update`. Status values are `active`, `inactive`, `expired`, and `cancelled`; freeze/unfreeze endpoints can cause student summaries to show `frozen`.',
    security: [['sanctum' => []]],
    tags: ['Packages', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'subscription', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'sub_01J0PACKAGE000000000001'),
    ],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['status'], properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive', 'expired', 'cancelled'], example: 'active'),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 200, description: 'Subscription status updated.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PackageSubscription')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
#[OA\Get(
    path: '/students/{student}/package-history',
    operationId: 'packageStudentHistory',
    summary: 'Show student package history',
    description: 'Access: admin; staff with `subscriptions.view`; or the matching student when package history visibility is enabled. Public student history omits payment-change and invoice-reference-change events and hides previous/new values, notes, billing fields, and creator IDs. Teachers do not have package-history access.',
    security: [['sanctum' => []]],
    tags: ['Packages', 'Student'],
    parameters: [
        new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated package history.', content: new OA\JsonContent(type: 'object', example: ['data' => [['subscription_id' => 'sub_01J0PACKAGE000000000001', 'event_type' => 'assigned', 'plan_name' => 'Business English 20 Lessons', 'status' => 'active', 'remaining_lesson_count' => 20, 'effective_at' => '2026-06-01T09:00:00Z']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/admin/students/{student}/subscriptions/history',
    operationId: 'packageAdminStudentHistory',
    summary: 'Show full student package history',
    description: 'Access: admin or staff with `subscriptions.view`. Unlike the student-facing package history route, this admin route can include billing events, previous/new values, notes, and creator IDs. Keep these fields out of student and teacher UI.',
    security: [['sanctum' => []]],
    tags: ['Packages', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'student', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01J0STUDENT000000000000001'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated full package history.', content: new OA\JsonContent(type: 'object', example: ['data' => [['subscription_id' => 'sub_01J0PACKAGE000000000001', 'event_type' => 'payment_changed', 'payment_status' => 'paid', 'previous_values' => ['payment_status' => 'unpaid'], 'new_values' => ['payment_status' => 'paid'], 'effective_at' => '2026-06-01T09:00:00Z']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/admin/teacher-compensations',
    operationId: 'payrollTeacherCompensationList',
    summary: 'List teacher pay rates',
    description: 'Access: admin or staff with `teacher_compensations.view`. These payroll configuration records are not visible to students or teachers. Internal notes are payroll-admin data and must not be shown in student/teacher contexts.',
    security: [['sanctum' => []]],
    tags: ['Payroll', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'teacher_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 17),
        new OA\Parameter(name: 'pay_model', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/TeacherPayModel')),
        new OA\Parameter(name: 'currency', in: 'query', schema: new OA\Schema(type: 'string', minLength: 3, maxLength: 3), example: 'USD'),
        new OA\Parameter(name: 'effective_on', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated teacher compensation records.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'tcp_01J0COMP000000000001', 'teacher_id' => 'usr_01J0TEACHER000000000000001', 'pay_model' => 'per_lesson', 'default_pay_rate' => '18.00', 'currency' => 'USD']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/admin/teacher-compensations/{teacherCompensation}/rate-rules',
    operationId: 'payrollTeacherCompensationRateRules',
    summary: 'List teacher pay-rate override rules',
    description: 'Access: admin or staff with `teacher_compensations.view`. Rate rules are payroll configuration data and are hidden from students and teacher self-service payroll views.',
    security: [['sanctum' => []]],
    tags: ['Payroll', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'teacherCompensation', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'tcp_01J0COMP000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Teacher compensation rate rules.', content: new OA\JsonContent(type: 'object', example: ['data' => [['id' => 'tcr_01J0RATE000000000001', 'lesson_type' => 'business_english', 'pay_model' => 'per_lesson', 'pay_rate' => '22.50', 'currency' => 'USD', 'priority' => 100, 'is_active' => true]]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/teacher-earnings',
    operationId: 'payrollOwnTeacherEarnings',
    summary: 'List own teacher earnings',
    description: 'Access: authenticated teacher only when `teacher_earnings.teacher_self_access_enabled` is enabled or the teacher has `teacher_earnings.view_own`. Results are scoped to the authenticated teacher. Students cannot access payroll data.',
    security: [['sanctum' => []]],
    tags: ['Payroll', 'Teacher'],
    parameters: [
        new OA\Parameter(name: 'payout_period', in: 'query', schema: new OA\Schema(type: 'string'), example: '2026-06'),
        new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/TeacherEarningStatus')),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated earnings for the authenticated teacher.', content: new OA\JsonContent(type: 'object', example: ['data' => [['teacher_id' => 'usr_01J0TEACHER000000000000001', 'pay_model' => 'per_lesson', 'rate_used' => '18.00', 'quantity' => '1.00', 'amount' => '18.00', 'currency' => 'USD', 'status' => 'approved']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/admin/teacher-earnings',
    operationId: 'payrollAdminTeacherEarnings',
    summary: 'List teacher earnings',
    description: 'Access: admin or staff with `teacher_earnings.view`. This endpoint can expose earnings across teachers and must not be used in teacher or student contexts.',
    security: [['sanctum' => []]],
    tags: ['Payroll', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'teacher_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 17),
        new OA\Parameter(name: 'payout_period', in: 'query', schema: new OA\Schema(type: 'string'), example: '2026-06'),
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/TeacherEarningStatus')),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated teacher earnings.', content: new OA\JsonContent(type: 'object', example: ['data' => [['teacher_id' => 'usr_01J0TEACHER000000000000001', 'teacher_name' => 'Taylor Teacher', 'amount' => '18.00', 'currency' => 'USD', 'status' => 'approved']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/admin/teachers/{teacher}/payout-report',
    operationId: 'payrollTeacherPayoutReport',
    summary: 'Show teacher payout report',
    description: 'Access: admin or staff with `payroll.view`. Returns payroll summary for one teacher. Teachers do not access this admin report route; self-service payroll visibility is controlled separately.',
    security: [['sanctum' => []]],
    tags: ['Payroll', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'teacher', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'usr_01J0TEACHER000000000000001'),
        new OA\Parameter(name: 'payout_period_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 7),
        new OA\Parameter(name: 'date_from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-01'),
        new OA\Parameter(name: 'date_to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-06-30'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Teacher payout report.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PayoutReport')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/admin/payout-periods/{payoutPeriod}/report',
    operationId: 'payrollPeriodPayoutReport',
    summary: 'Show payout period report',
    description: 'Access: admin or staff with `payroll.view`. Returns period-wide payroll totals and per-teacher breakdowns. Students and teachers cannot access period-wide payroll reports.',
    security: [['sanctum' => []]],
    tags: ['Payroll', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'payoutPeriod', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'ppo_01J0PAYOUT000000000001'),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Payout period report.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PayoutReport')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/payroll-adjustments',
    operationId: 'payrollOwnAdjustments',
    summary: 'List own payroll adjustments',
    description: 'Access: authenticated teacher only when `teacher_earnings.teacher_payroll_visibility_enabled` is enabled. Results are scoped to the authenticated teacher. Internal notes, creator IDs, and creator names are omitted from teacher responses.',
    security: [['sanctum' => []]],
    tags: ['Payroll', 'Teacher'],
    parameters: [
        new OA\Parameter(name: 'payout_period_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 7),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated teacher-visible adjustments.', content: new OA\JsonContent(type: 'object', example: ['data' => [['teacher_id' => 'usr_01J0TEACHER000000000000001', 'payout_period_id' => 'ppo_01J0PAYOUT000000000001', 'type' => 'bonus', 'amount' => '25.00', 'currency' => 'USD', 'reason' => 'Approved demo lesson bonus.']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Get(
    path: '/admin/payout-adjustments',
    operationId: 'payrollAdminAdjustmentList',
    summary: 'List manual payroll adjustments',
    description: 'Access: admin or staff with `payroll.view`. Returns manual payroll adjustments across teachers. This route is not visible to students and must not be used for teacher self-service views.',
    security: [['sanctum' => []]],
    tags: ['Payroll', 'Admin'],
    parameters: [
        new OA\Parameter(name: 'teacher_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 17),
        new OA\Parameter(name: 'payout_period_id', in: 'query', schema: new OA\Schema(type: 'integer'), example: 7),
        new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(ref: '#/components/schemas/PayrollAdjustmentType')),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated manual payroll adjustments.', content: new OA\JsonContent(type: 'object', example: ['data' => [['teacher_id' => 'usr_01J0TEACHER000000000000001', 'teacher_name' => 'Taylor Teacher', 'type' => 'bonus', 'amount' => '25.00', 'currency' => 'USD', 'reason' => 'Approved demo lesson bonus.']]])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
    ]
)]
#[OA\Post(
    path: '/admin/payout-adjustments',
    operationId: 'payrollAdminAdjustmentCreate',
    summary: 'Create manual payroll adjustment',
    description: 'Access: admin or staff with `payroll.manage`. Creates a bonus, deduction, correction, reimbursement, or other manual adjustment for a teacher. Deductions are normalized to negative amounts. Internal notes are visible only to admin/staff payroll views.',
    security: [['sanctum' => []]],
    tags: ['Payroll', 'Admin'],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['teacher_id', 'type', 'amount', 'reason'], properties: [
        new OA\Property(property: 'teacher_id', description: 'Internal numeric teacher user ID.', type: 'integer', example: 17),
        new OA\Property(property: 'payout_period_id', nullable: true, type: 'integer', example: 7),
        new OA\Property(property: 'type', ref: '#/components/schemas/PayrollAdjustmentType'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 25.00),
        new OA\Property(property: 'currency', type: 'string', minLength: 3, maxLength: 3, example: 'USD'),
        new OA\Property(property: 'reason', type: 'string', example: 'Approved demo lesson bonus.'),
        new OA\Property(property: 'internal_notes', nullable: true, type: 'string', example: 'Reviewed by payroll admin.'),
    ], type: 'object')),
    responses: [
        new OA\Response(response: 201, description: 'Manual payroll adjustment created.', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PayrollAdjustment')])),
        new OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401),
        new OA\Response(ref: '#/components/responses/ForbiddenError', response: 403),
        new OA\Response(ref: '#/components/responses/ValidationError', response: 422),
    ]
)]
class FinancialPackagePayrollDocumentation {}
