<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\GenerateInvoiceRequest;
use App\Http\Resources\Billing\InvoiceResource;
use App\Services\Billing\InvoiceGenerationService;
use Illuminate\Http\JsonResponse;

class InvoiceGenerationController extends Controller
{
    public function __construct(private readonly InvoiceGenerationService $invoices) {}

    public function store(GenerateInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoices->generate([
            ...$request->validated(),
            'generated_by' => $request->user()->id,
            'source' => 'manual',
        ]);

        return response()->json([
            'data' => new InvoiceResource($invoice),
        ], 201);
    }
}
