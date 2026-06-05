<?php

namespace App\Http\Controllers\Api\Billing;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\GenerateInvoiceRequest;
use App\Http\Resources\Billing\InvoiceResource;
use App\Services\AuditLogService;
use App\Services\Billing\InvoiceGenerationService;
use Illuminate\Http\JsonResponse;

class InvoiceGenerationController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  InvoiceGenerationService  $invoices
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(
        private readonly InvoiceGenerationService $invoices,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Create a new invoice generation record.
     *
     * Admin or staff users with invoices.create permission; route middleware also throttles the action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The GenerateInvoiceRequest handles authorization and validation before the controller action runs.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  GenerateInvoiceRequest  $request
     * @return JsonResponse
     */
    public function store(GenerateInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->generate([
            ...$request->validated(),
            'generated_by' => $request->user()->id,
            'source' => 'manual',
        ]);
        $this->auditLogService->record(
            actorUserId: $request->user()->id,
            actionType: AuditActionType::PAYMENT_CREATED,
            module: AuditModule::BILLING,
            targetEntityType: 'invoice',
            targetEntityId: $invoice->id,
            metadata: [
                'invoice_id' => $invoice->id,
                'subscription_id' => $invoice->subscription_id,
                'package_id' => $invoice->subscription_id ?? $invoice->course_program_id,
                'affected_user_id' => $invoice->student_id,
                'changed_fields' => [
                    'amount' => true,
                    'tax_amount' => true,
                    'total_amount' => true,
                    'status' => true,
                    'due_date' => true,
                ],
                'new_status' => $invoice->status,
                'external_reference_supplied' => $invoice->payment_reference !== null,
            ],
        );

        return response()->json([
            'data' => new InvoiceResource($invoice),
        ], 201);
    }
}
