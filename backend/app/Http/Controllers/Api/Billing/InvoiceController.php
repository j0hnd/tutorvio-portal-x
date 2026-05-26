<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\InvoicePdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
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
                fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->where('status', Invoice::STATUS_OVERDUE)
                        ->orWhere(fn (Builder $query) => $query
                            ->where('status', Invoice::STATUS_UNPAID)
                            ->whereDate('due_date', '<', now()->toDateString())))
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
        return $user->hasRole('admin') || $user->can('invoices.view');
    }
}
