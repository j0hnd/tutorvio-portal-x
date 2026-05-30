<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\AdjustSubscriptionLessonBalanceRequest;
use App\Http\Requests\Subscriptions\StoreSubscriptionRequest;
use App\Http\Requests\Subscriptions\UpdateSubscriptionInvoiceReferenceRequest;
use App\Http\Requests\Subscriptions\UpdateSubscriptionNotesRequest;
use App\Http\Requests\Subscriptions\UpdateSubscriptionPaymentStatusRequest;
use App\Http\Requests\Subscriptions\UpdateSubscriptionRequest;
use App\Http\Requests\Subscriptions\UpdateSubscriptionStatusRequest;
use App\Http\Resources\Subscriptions\SubscriptionHistoryResource;
use App\Http\Resources\Subscriptions\SubscriptionResource;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\SubscriptionLessonBalanceService;
use App\Services\SubscriptionRenewalReminderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SubscriptionManagementController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'created_at',
        'updated_at',
        'starts_at',
        'ends_at',
        'plan_name',
        'status',
        'payment_status',
    ];

    public function __construct(
        private readonly SubscriptionLessonBalanceService $lessonBalances,
        private readonly SubscriptionRenewalReminderService $renewalReminders,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Subscription::class);

        $validated = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::in(Subscription::STATUSES)],
            'payment_status' => ['sometimes', 'string', Rule::in(Subscription::PAYMENT_STATUSES)],
            'package_type' => ['sometimes', 'string', Rule::in(Subscription::TYPES)],
            'is_frozen' => ['sometimes', Rule::in(['1', '0', 'true', 'false', 1, 0, true, false])],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', Rule::in(self::SORTABLE_COLUMNS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $subscriptions = Subscription::query()
            ->with('student')
            ->when($validated['student_id'] ?? null, fn (Builder $query, int $studentId) => $query->where('user_id', $studentId))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when($validated['package_type'] ?? null, fn (Builder $query, string $type) => $query->where('package_type', $type))
            ->when(array_key_exists('is_frozen', $validated), function (Builder $query) use ($validated) {
                $query->where('is_frozen', filter_var($validated['is_frozen'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('plan_name', 'like', '%'.$search.'%')
                        ->orWhere('invoice_reference', 'like', '%'.$search.'%')
                        ->orWhereHas('student', fn (Builder $query) => $query
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy($validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json(
            $subscriptions->through(fn (Subscription $subscription) => new SubscriptionResource($subscription))
        );
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        Gate::authorize('create', Subscription::class);

        $actorId = $request->user()->id;
        $subscription = DB::transaction(function () use ($request, $actorId) {
            $subscription = Subscription::create($this->subscriptionPayload($request->validated()) + [
                'user_id' => $request->integer('student_id'),
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'frozen_at' => $request->input('status') === Subscription::STATUS_INACTIVE && $request->boolean('is_frozen')
                    ? now()
                    : null,
            ]);

            $this->recordHistory($subscription, SubscriptionHistory::EVENT_ASSIGNED, [], $subscription->only($this->trackedFields()), $actorId);

            return $this->renewalReminders->refreshReminderState($subscription);
        });
        $this->auditSubscriptionMutation(
            actorId: $actorId,
            actionType: AuditActionType::PACKAGE_ASSIGNED,
            subscription: $subscription,
            previous: [],
            current: $subscription->only($this->trackedFields()),
            additionalMetadata: [
                'affected_user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'package_id' => $subscription->id,
            ],
        );

        return response()->json([
            'data' => new SubscriptionResource($subscription->load('student')),
        ], 201);
    }

    public function show(Subscription $subscription): JsonResponse
    {
        Gate::authorize('view', $subscription);

        return response()->json([
            'data' => new SubscriptionResource($this->renewalReminders->refreshReminderState($subscription)->load('student')),
        ]);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);

        $actorId = $request->user()->id;
        $previous = [];
        $current = [];
        $subscription = DB::transaction(function () use ($request, $subscription, $actorId, &$previous, &$current) {
            $previous = $subscription->only($this->trackedFields());
            $payload = $this->subscriptionPayload($request->validated());

            if ($request->has('student_id')) {
                $payload['user_id'] = $request->integer('student_id');
            }

            $subscription->fill($payload + ['updated_by' => $actorId])->save();
            $subscription->refresh();
            $current = $subscription->only($this->trackedFields());

            $this->recordHistory(
                $subscription,
                SubscriptionHistory::EVENT_UPDATED,
                $previous,
                $current,
                $actorId,
                $subscription->internal_notes
            );

            return $this->renewalReminders->refreshReminderState($subscription);
        });
        $this->auditSubscriptionMutation(
            actorId: $actorId,
            actionType: AuditActionType::PACKAGE_UPDATED,
            subscription: $subscription,
            previous: $previous,
            current: $current,
            additionalMetadata: [
                'affected_user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'package_id' => $subscription->id,
            ],
        );

        return response()->json([
            'data' => new SubscriptionResource($subscription->load('student')),
        ]);
    }

    public function updatePaymentStatus(UpdateSubscriptionPaymentStatusRequest $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('updatePaymentStatus', $subscription);

        $paymentStatus = $request->input('payment_status', $request->input('status'));

        return $this->applyUpdate(
            $request,
            $subscription,
            ['payment_status' => $paymentStatus],
            SubscriptionHistory::EVENT_PAYMENT_CHANGED,
            AuditActionType::PAYMENT_UPDATED
        );
    }

    public function updateStatus(UpdateSubscriptionStatusRequest $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);

        return $this->applyUpdate(
            $request,
            $subscription,
            ['status' => $request->input('status')],
            SubscriptionHistory::EVENT_STATUS_CHANGED,
            AuditActionType::PACKAGE_STATUS_CHANGED
        );
    }

    public function freeze(Request $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);

        return $this->applyUpdate($request, $subscription, [
            'is_frozen' => true,
            'frozen_at' => now(),
            'status' => Subscription::STATUS_INACTIVE,
        ], SubscriptionHistory::EVENT_FROZEN, AuditActionType::PACKAGE_STATUS_CHANGED);
    }

    public function unfreeze(Request $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);

        return $this->applyUpdate($request, $subscription, [
            'is_frozen' => false,
            'frozen_at' => null,
            'status' => Subscription::STATUS_ACTIVE,
        ], SubscriptionHistory::EVENT_UNFROZEN, AuditActionType::PACKAGE_STATUS_CHANGED);
    }

    public function updateNotes(UpdateSubscriptionNotesRequest $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);

        return $this->applyUpdate(
            $request,
            $subscription,
            ['internal_notes' => $request->input('internal_notes', $request->input('notes'))],
            SubscriptionHistory::EVENT_UPDATED,
            AuditActionType::PACKAGE_UPDATED
        );
    }

    public function updateInvoiceReference(UpdateSubscriptionInvoiceReferenceRequest $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);

        $payload = [
            'invoice_reference' => $request->input('invoice_reference', $request->input('reference')),
        ];

        if ($request->has('invoice_id')) {
            $payload['invoice_id'] = $this->invoiceKey($request->input('invoice_id'));
        }

        return $this->applyUpdate(
            $request,
            $subscription,
            $payload,
            SubscriptionHistory::EVENT_INVOICE_REFERENCE_CHANGED,
            AuditActionType::PAYMENT_UPDATED
        );
    }

    public function adjustLessonBalance(AdjustSubscriptionLessonBalanceRequest $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);

        return response()->json([
            'data' => new SubscriptionResource(
                $this->renewalReminders
                    ->refreshReminderState($this->lessonBalances->manuallyAdjust($subscription, $request->validated(), $request->user()))
                    ->load('student')
            ),
        ]);
    }

    public function renew(StoreSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('renew', $subscription);

        $actorId = $request->user()->id;
        $renewal = DB::transaction(function () use ($request, $subscription, $actorId) {
            $renewal = Subscription::create($this->subscriptionPayload($request->validated()) + [
                'user_id' => $request->integer('student_id'),
                'renewed_from_subscription_id' => $subscription->id,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->recordHistory($renewal, SubscriptionHistory::EVENT_RENEWED, $subscription->only($this->trackedFields()), $renewal->only($this->trackedFields()), $actorId);

            $this->renewalReminders->refreshReminderState($subscription);

            return $this->renewalReminders->refreshReminderState($renewal);
        });
        $this->auditSubscriptionMutation(
            actorId: $actorId,
            actionType: AuditActionType::PACKAGE_ASSIGNED,
            subscription: $renewal,
            previous: ['renewed_from_subscription_id' => $subscription->id],
            current: $renewal->only($this->trackedFields()),
            additionalMetadata: [
                'affected_user_id' => $renewal->user_id,
                'subscription_id' => $renewal->id,
                'package_id' => $renewal->id,
                'renewed_from_subscription_id' => $subscription->id,
            ],
        );

        return response()->json([
            'data' => new SubscriptionResource($renewal->load('student')),
        ], 201);
    }

    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('cancel', $subscription);

        return $this->applyUpdate(
            $request,
            $subscription,
            ['status' => Subscription::STATUS_CANCELLED],
            SubscriptionHistory::EVENT_CANCELLED,
            AuditActionType::PACKAGE_STATUS_CHANGED
        );
    }

    public function archive(Request $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('cancel', $subscription);

        return $this->applyUpdate(
            $request,
            $subscription,
            ['status' => Subscription::STATUS_CANCELLED],
            SubscriptionHistory::EVENT_ARCHIVED,
            AuditActionType::PACKAGE_STATUS_CHANGED
        );
    }

    public function studentHistory(Request $request, User $student): JsonResponse
    {
        Gate::authorize('viewAny', Subscription::class);
        abort_unless($student->hasRole('student'), 404);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $history = SubscriptionHistory::query()
            ->with(['subscription', 'student'])
            ->where('student_id', $student->id)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json(
            $history->through(fn (SubscriptionHistory $item) => new SubscriptionHistoryResource($item))
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function subscriptionPayload(array $validated): array
    {
        return collect($validated)
            ->except('student_id')
            ->only($this->trackedFields())
            ->map(fn (mixed $value, string $field) => $field === 'invoice_id' ? $this->invoiceKey($value) : $value)
            ->all();
    }

    private function invoiceKey(mixed $publicId): ?int
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        return Invoice::query()->where('public_id', $publicId)->firstOrFail()->getKey();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyUpdate(
        Request $request,
        Subscription $subscription,
        array $payload,
        string $eventType,
        AuditActionType|string $auditActionType = AuditActionType::PACKAGE_UPDATED
    ): JsonResponse {
        $actorId = $request->user()->id;
        $previous = [];
        $current = [];
        $subscription = DB::transaction(function () use ($subscription, $payload, $eventType, $actorId, &$previous, &$current) {
            $previous = $subscription->only($this->trackedFields());

            $subscription->fill($payload + ['updated_by' => $actorId])->save();
            $subscription->refresh();
            $current = $subscription->only($this->trackedFields());

            $this->recordHistory(
                $subscription,
                $eventType,
                $previous,
                $current,
                $actorId,
                $subscription->internal_notes
            );

            return $this->renewalReminders->refreshReminderState($subscription);
        });
        $this->auditSubscriptionMutation(
            actorId: $actorId,
            actionType: $auditActionType,
            subscription: $subscription,
            previous: $previous,
            current: $current,
            additionalMetadata: [
                'affected_user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'package_id' => $subscription->id,
            ],
        );

        return response()->json([
            'data' => new SubscriptionResource($subscription->load('student')),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function trackedFields(): array
    {
        return [
            'plan_name',
            'package_type',
            'total_lesson_count',
            'consumed_lesson_count',
            'remaining_lesson_count',
            'status',
            'is_frozen',
            'payment_status',
            'invoice_id',
            'invoice_reference',
            'internal_notes',
            'renewed_from_subscription_id',
            'renewal_reminder_due_at',
            'renewal_reminder_last_sent_at',
            'renewal_reminder_status',
            'renewal_reminder_window_key',
            'renewal_eligible',
            'renewal_reminder_notes',
            'starts_at',
            'ends_at',
        ];
    }

    /**
     * @param  array<string, mixed>  $previousValues
     * @param  array<string, mixed>  $newValues
     */
    private function recordHistory(Subscription $subscription, string $eventType, array $previousValues, array $newValues, int $createdBy, ?string $notes = null): void
    {
        SubscriptionHistory::create([
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->user_id,
            'event_type' => $eventType,
            'plan_name' => $subscription->plan_name,
            'package_type' => $subscription->package_type,
            'total_lesson_count' => $subscription->total_lesson_count,
            'consumed_lesson_count' => $subscription->consumed_lesson_count,
            'remaining_lesson_count' => $subscription->remaining_lesson_count,
            'status' => $subscription->status,
            'is_frozen' => $subscription->is_frozen,
            'payment_status' => $subscription->payment_status,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'previous_values' => $previousValues,
            'new_values' => $newValues,
            'notes' => $notes,
            'effective_at' => now(),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $additionalMetadata
     */
    private function auditSubscriptionMutation(
        int $actorId,
        AuditActionType|string $actionType,
        Subscription $subscription,
        array $previous,
        array $current,
        array $additionalMetadata = [],
    ): void {
        $changedFields = $this->changedFields($previous, $current);

        if ($changedFields === [] && $actionType === AuditActionType::PACKAGE_UPDATED) {
            return;
        }

        $metadata = [
            ...$additionalMetadata,
            'changed_fields' => array_fill_keys($changedFields, true),
            'old_status' => $previous['status'] ?? null,
            'new_status' => $current['status'] ?? $subscription->status,
            'old_payment_status' => $previous['payment_status'] ?? null,
            'new_payment_status' => $current['payment_status'] ?? $subscription->payment_status,
            'invoice_reference_updated' => in_array('invoice_reference', $changedFields, true),
        ];
        $actionTypeValue = $actionType instanceof AuditActionType ? $actionType->value : $actionType;

        $this->auditLogService->record(
            actorUserId: $actorId,
            actionType: $actionType,
            module: in_array($actionTypeValue, [
                AuditActionType::PAYMENT_UPDATED->value,
                AuditActionType::PAYMENT_ADJUSTED->value,
                AuditActionType::PAYMENT_CREATED->value,
            ], true) ? AuditModule::BILLING : AuditModule::PACKAGES,
            targetEntityType: 'subscription',
            targetEntityId: $subscription->id,
            metadata: Arr::whereNotNull($metadata),
        );
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $current
     * @return array<int, string>
     */
    private function changedFields(array $previous, array $current): array
    {
        $changed = [];

        foreach ($current as $field => $value) {
            if (! array_key_exists($field, $previous)) {
                $changed[] = $field;

                continue;
            }

            if ($previous[$field] != $value) {
                $changed[] = $field;
            }
        }

        return array_values(array_unique($changed));
    }
}
