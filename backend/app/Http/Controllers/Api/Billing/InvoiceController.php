<?php

namespace App\Http\Controllers\Api\Billing;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Billing\InvoiceEmailService;
use App\Services\Billing\InvoicePdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    private const SORTABLE_COLUMNS = [
        'created_at',
        'updated_at',
        'issued_date',
        'due_date',
        'paid_date',
        'invoice_number',
        'status',
        'total_amount',
    ];

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $validated = $this->validatedFilters($request);

        $invoices = $this->filteredInvoices($request->user(), $validated)
            ->orderBy($validated['sort'] ?? 'issued_date', $validated['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json(
            $invoices->through(fn (Invoice $invoice) => new InvoiceResource($invoice))
        );
    }

    public function show(Invoice $invoice): JsonResponse
    {
        Gate::authorize('view', $invoice);

        return response()->json([
            'data' => new InvoiceResource($invoice->load(['student', 'subscription', 'courseProgram'])),
        ]);
    }

    public function download(Invoice $invoice, InvoicePdfService $pdfs): Response
    {
        Gate::authorize('download', $invoice);

        $pdf = $pdfs->render($invoice);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => strlen($pdf),
            'Content-Disposition' => 'attachment; filename="'.$pdfs->filename($invoice).'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function sendEmail(Request $request, Invoice $invoice, InvoiceEmailService $emails): JsonResponse
    {
        Gate::authorize('sendEmail', $invoice);

        $sent = $emails->resendManually($invoice, $request->user());

        return response()->json([
            'data' => new InvoiceResource($invoice->refresh()->load(['student', 'subscription', 'courseProgram'])),
            'email_sent' => $sent,
        ], $sent ? 200 : 422);
    }

    public function updatePaymentStatus(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('updatePaymentStatus', $invoice);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([Invoice::STATUS_PAID, Invoice::STATUS_UNPAID])],
            'paid_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $newStatus = $validated['status'];
        $this->assertAllowedPaymentStatusTransition($invoice, $newStatus);

        $oldStatus = $invoice->status;
        $oldPaidDate = $invoice->paid_date?->toDateString();
        $newPaidDate = $newStatus === Invoice::STATUS_PAID
            ? ($validated['paid_date'] ?? now()->toDateString())
            : null;

        $metadata = $invoice->metadata ?? [];
        $metadata['payment_status_history'] = [
            ...($metadata['payment_status_history'] ?? []),
            [
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'from_paid_date' => $oldPaidDate,
                'to_paid_date' => $newPaidDate,
                'changed_by' => $request->user()->id,
                'changed_at' => now()->toISOString(),
            ],
        ];

        $invoice->forceFill([
            'status' => $newStatus,
            'paid_date' => $newPaidDate,
            'metadata' => $metadata,
        ])->save();
        $changedFields = ['status'];
        if ($oldPaidDate !== $newPaidDate) {
            $changedFields[] = 'paid_date';
        }

        $this->auditLogService->record(
            actorUserId: $request->user()->id,
            actionType: AuditActionType::PAYMENT_ADJUSTED,
            module: AuditModule::BILLING,
            targetEntityType: 'invoice',
            targetEntityId: $invoice->id,
            metadata: [
                'invoice_id' => $invoice->id,
                'subscription_id' => $invoice->subscription_id,
                'affected_user_id' => $invoice->student_id,
                'changed_fields' => array_fill_keys($changedFields, true),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'old_paid_date' => $oldPaidDate,
                'new_paid_date' => $newPaidDate,
            ],
        );

        return response()->json([
            'data' => new InvoiceResource($invoice->refresh()->load(['student', 'subscription', 'courseProgram'])),
        ]);
    }

    public function history(Request $request, User $student): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        abort_unless($student->hasRole('student'), 404);
        abort_unless($this->canViewStudentHistory($request->user(), $student), 403);

        $validated = $this->validatedFilters($request);
        $validated['student_id'] = $student->id;

        $invoices = $this->filteredInvoices($request->user(), $validated)
            ->orderBy($validated['sort'] ?? 'issued_date', $validated['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json(
            $invoices->through(fn (Invoice $invoice) => new InvoiceResource($invoice))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'student' => ['sometimes', 'integer', 'exists:users,id'],
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(Invoice::STATUSES)],
            'subscription' => ['sometimes', 'integer', 'exists:subscriptions,id'],
            'subscription_id' => ['sometimes', 'integer', 'exists:subscriptions,id'],
            'package' => ['sometimes', 'nullable', 'string', 'max:255'],
            'package_id' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'course_program_id' => ['sometimes', 'integer', 'exists:course_programs,id'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'date_field' => ['sometimes', 'string', Rule::in(['issued_date', 'due_date', 'paid_date'])],
            'overdue' => ['sometimes', Rule::in(['1', '0', 'true', 'false', 1, 0, true, false])],
            'sort' => ['sometimes', 'string', Rule::in(self::SORTABLE_COLUMNS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $validated['student_id'] ??= $validated['student'] ?? null;
        $validated['subscription_id'] ??= $validated['subscription'] ?? null;
        $validated['course_program_id'] ??= $validated['package_id'] ?? null;
        $validated['reference'] ??= $validated['invoice_number'] ?? null;
        $validated['overdue'] = array_key_exists('overdue', $validated)
            ? filter_var($validated['overdue'], FILTER_VALIDATE_BOOLEAN)
            : null;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    private function filteredInvoices(User $user, array $filters): Builder
    {
        return Invoice::query()
            ->with(['student', 'subscription', 'courseProgram'])
            ->when(! $this->canViewAllInvoices($user), fn (Builder $query) => $query->where('student_id', $user->id))
            ->when($filters['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('student_id', $studentId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['subscription_id'] ?? null, fn (Builder $query, int $subscriptionId) => $query->where('subscription_id', $subscriptionId))
            ->when($filters['course_program_id'] ?? null, fn (Builder $query, int $courseProgramId) => $query->where('course_program_id', $courseProgramId))
            ->when($filters['package'] ?? null, fn (Builder $query, string $package) => $this->wherePackageMatches($query, $package))
            ->when($filters['reference'] ?? null, fn (Builder $query, string $reference) => $query->where('invoice_number', 'like', '%'.$reference.'%'))
            ->when($filters['date_from'] ?? null, function (Builder $query, string $dateFrom) use ($filters) {
                $query->where($filters['date_field'] ?? 'issued_date', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $dateTo) use ($filters) {
                $query->where($filters['date_field'] ?? 'issued_date', '<=', $dateTo);
            })
            ->when(
                array_key_exists('overdue', $filters) && (bool) $filters['overdue'],
                fn (Builder $query) => $query->overdueAsOf()
            );
    }

    private function wherePackageMatches(Builder $query, string $package): void
    {
        $query->where(function (Builder $query) use ($package) {
            $query
                ->whereHas('subscription', fn (Builder $query) => $query->where('plan_name', 'like', '%'.$package.'%'))
                ->orWhereHas('courseProgram', fn (Builder $query) => $query->where('title', 'like', '%'.$package.'%'));
        });
    }

    private function canViewStudentHistory(User $user, User $student): bool
    {
        return $this->canViewAllInvoices($user)
            || ($user->hasRole('student') && (int) $user->id === (int) $student->id);
    }

    private function canViewAllInvoices(User $user): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('invoices.view'));
    }

    private function assertAllowedPaymentStatusTransition(Invoice $invoice, string $newStatus): void
    {
        $allowed = match ($invoice->status) {
            Invoice::STATUS_UNPAID, Invoice::STATUS_OVERDUE => $newStatus === Invoice::STATUS_PAID,
            Invoice::STATUS_PAID => $newStatus === Invoice::STATUS_UNPAID,
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'status' => 'The requested invoice payment status transition is not allowed.',
            ]);
        }
    }
}
