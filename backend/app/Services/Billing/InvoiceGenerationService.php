<?php

namespace App\Services\Billing;

use App\Models\CourseProgram;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceGenerationService
{
    public function __construct(
        private readonly InvoiceEmailService $invoiceEmails,
        private readonly InvoicePricingService $pricing,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function generate(array $payload): Invoice
    {
        $invoice = DB::transaction(function () use ($payload) {
            $student = User::findOrFail((int) $payload['student_id']);
            $subscription = $this->subscription($payload);
            $courseProgram = $this->courseProgram($payload);

            $this->assertStudent($student);
            $this->assertSingleInvoiceSource($subscription, $courseProgram);
            $this->assertSourceBelongsToStudent($student, $subscription);

            if (! ($payload['allow_duplicate'] ?? false)) {
                $this->assertNoDuplicateInvoice($student, $subscription, $courseProgram);
            }

            $subtotal = round((float) $payload['subtotal'], 2);
            $taxProfile = $this->pricing->taxProfile($payload['tax_country'] ?? null);
            $taxRate = $taxProfile['rate'];
            $taxAmount = $this->pricing->taxAmount($subtotal, $taxRate);
            $totalAmount = round($subtotal + $taxAmount, 2);
            $issuedDate = Carbon::parse($payload['issued_date'] ?? now())->startOfDay();
            $dueDate = isset($payload['due_date'])
                ? Carbon::parse($payload['due_date'])->startOfDay()
                : $issuedDate->copy()->addDays($this->dueDays());
            $currency = $this->pricing->currency($payload['currency'] ?? null);

            $invoice = Invoice::create([
                'student_id' => $student->id,
                'subscription_id' => $subscription?->id,
                'course_program_id' => $courseProgram?->id,
                'invoice_number' => $this->invoiceNumber(),
                'amount' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'issued_date' => $issuedDate,
                'due_date' => $dueDate,
                'status' => Invoice::STATUS_UNPAID,
                'payment_reference' => $payload['payment_reference'] ?? null,
                'metadata' => [
                    'source' => $payload['source'] ?? 'manual',
                    'tax_label' => $taxProfile['label'],
                    'tax_rate' => $taxRate,
                    'tax_country' => $taxProfile['country'],
                    'generated_by' => $payload['generated_by'] ?? null,
                    ...($payload['metadata'] ?? []),
                ],
            ]);

            return $invoice->load(['student:id,public_id,name,email,status', 'subscription', 'courseProgram']);
        });

        $this->invoiceEmails->sendAutomatically($invoice);

        return $invoice->refresh()->load(['student:id,public_id,name,email,status', 'subscription', 'courseProgram']);
    }

    public function generateForSubscriptionPurchase(
        Subscription $subscription,
        float $subtotal,
        ?string $currency = null,
        bool $allowDuplicate = false,
    ): Invoice {
        return $this->generate([
            'student_id' => $subscription->user_id,
            'subscription_id' => $subscription->public_id,
            'subtotal' => $subtotal,
            'currency' => $currency ?? $this->pricing->currency(),
            'allow_duplicate' => $allowDuplicate,
            'source' => 'purchase',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function subscription(array $payload): ?Subscription
    {
        return isset($payload['subscription_id'])
            ? Subscription::query()->where('public_id', $payload['subscription_id'])->firstOrFail()
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function courseProgram(array $payload): ?CourseProgram
    {
        return isset($payload['course_program_id'])
            ? CourseProgram::withArchived()->findOrFail((int) $payload['course_program_id'])
            : null;
    }

    private function assertStudent(User $student): void
    {
        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must be a student.',
            ]);
        }
    }

    private function assertSingleInvoiceSource(?Subscription $subscription, ?CourseProgram $courseProgram): void
    {
        if (($subscription === null && $courseProgram === null) || ($subscription !== null && $courseProgram !== null)) {
            throw ValidationException::withMessages([
                'source' => 'Provide exactly one of subscription_id or course_program_id.',
            ]);
        }
    }

    private function assertSourceBelongsToStudent(User $student, ?Subscription $subscription): void
    {
        if ($subscription !== null && $subscription->user_id !== $student->id) {
            throw ValidationException::withMessages([
                'subscription_id' => 'The selected subscription does not belong to the selected student.',
            ]);
        }
    }

    private function assertNoDuplicateInvoice(User $student, ?Subscription $subscription, ?CourseProgram $courseProgram): void
    {
        $exists = Invoice::query()
            ->when(
                $subscription,
                fn ($query) => $query->where('subscription_id', $subscription->id),
                fn ($query) => $query
                    ->where('student_id', $student->id)
                    ->where('course_program_id', $courseProgram?->id)
                    ->whereNull('subscription_id')
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'invoice' => 'An invoice already exists for the selected package or subscription.',
            ]);
        }
    }

    private function invoiceNumber(): string
    {
        do {
            $invoiceNumber = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Invoice::where('invoice_number', $invoiceNumber)->exists());

        return $invoiceNumber;
    }

    private function dueDays(): int
    {
        return max(0, (int) config('billing.invoice.due_days', 14));
    }
}
