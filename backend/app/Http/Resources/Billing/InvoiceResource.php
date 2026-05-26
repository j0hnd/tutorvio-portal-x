<?php

namespace App\Http\Resources\Billing;

use App\Http\Resources\CourseCatalog\CourseProgramResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'subscription_id' => $this->resource->subscription_id,
            'course_program_id' => $this->resource->course_program_id,
            'invoice_number' => $this->resource->invoice_number,
            'reference' => $this->resource->invoice_number,
            'subtotal' => $this->resource->amount,
            'amount' => $this->resource->amount,
            'tax_amount' => $this->resource->tax_amount,
            'total_amount' => $this->resource->total_amount,
            'currency' => $this->resource->currency,
            'issued_date' => $this->resource->issued_date,
            'due_date' => $this->resource->due_date,
            'paid_date' => $this->resource->paid_date,
            'status' => $this->resource->status,
            'payment_reference' => $this->when($this->canViewPaymentDetails($request), $this->resource->payment_reference),
            'metadata' => $this->when($this->canViewPaymentDetails($request), $this->resource->metadata ?? []),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->resource->student->id,
                'name' => $this->resource->student->name,
                'email' => $this->resource->student->email,
                'status' => $this->resource->student->status,
            ]),
            'subscription' => $this->whenLoaded('subscription', fn () => [
                'id' => $this->resource->subscription->id,
                'plan_name' => $this->resource->subscription->plan_name,
                'status' => $this->resource->subscription->status,
                'starts_at' => $this->resource->subscription->starts_at,
                'ends_at' => $this->resource->subscription->ends_at,
            ]),
            'course_program' => $this->whenLoaded('courseProgram', fn () => new CourseProgramResource($this->resource->courseProgram)),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }

    private function canViewPaymentDetails(Request $request): bool
    {
        $user = $request->user();

        return $user?->hasRole('admin') === true
            || (
                $user?->hasRole('staff') === true
                && ($user?->can('invoices.view') === true || $user?->can('invoices.update') === true)
            );
    }
}
