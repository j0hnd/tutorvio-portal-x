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
    public function __construct(
        private readonly InvoiceGenerationService $invoices,
        private readonly AuditLogService $auditLogService,
    ) {}

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
                'payment_reference_present' => $invoice->payment_reference !== null,
            ],
        );

        return response()->json([
            'data' => new InvoiceResource($invoice),
        ], 201);
    }
}
