<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayoutPeriods\StorePayoutPeriodRequest;
use App\Http\Requests\PayoutPeriods\UpdatePayoutPeriodRequest;
use App\Http\Resources\PayoutPeriods\PayoutPeriodResource;
use App\Models\PayoutPeriod;
use App\Models\TeacherEarning;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PayoutPeriodController extends Controller
{
    /**
     * Display a filtered list of payout period records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PayoutPeriod::class);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(PayoutPeriod::STATUSES)],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $periods = PayoutPeriod::query()
            ->withCount('earnings')
            ->withSum('earnings', 'amount')
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('end_date', '>=', $date))
            ->when($validated['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('start_date', '<=', $date))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($periods->through(fn (PayoutPeriod $period) => new PayoutPeriodResource($period)));
    }

    /**
     * Create a new payout period record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StorePayoutPeriodRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  StorePayoutPeriodRequest  $request
     * @return JsonResponse
     */
    public function store(StorePayoutPeriodRequest $request): JsonResponse
    {
        Gate::authorize('create', PayoutPeriod::class);

        $period = DB::transaction(function () use ($request): PayoutPeriod {
            $period = PayoutPeriod::create([
                ...$request->validated(),
                'status' => $request->validated('status', PayoutPeriod::STATUS_DRAFT),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->refreshEligibleEarnings($period);

            return $period;
        });

        return response()->json([
            'data' => new PayoutPeriodResource($period->refresh()->load(['earnings.teacher', 'earnings.lessonRecord'])->loadCount('earnings')->loadSum('earnings', 'amount')),
        ], 201);
    }

    /**
     * Display the selected payout period record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $payoutPeriod.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  PayoutPeriod  $payoutPeriod
     * @return JsonResponse
     */
    public function show(PayoutPeriod $payoutPeriod): JsonResponse
    {
        Gate::authorize('view', $payoutPeriod);

        return response()->json([
            'data' => new PayoutPeriodResource($payoutPeriod->load(['earnings.teacher', 'earnings.lessonRecord'])->loadCount('earnings')->loadSum('earnings', 'amount')),
        ]);
    }

    /**
     * Update the selected payout period record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $payoutPeriod.
     * The UpdatePayoutPeriodRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  UpdatePayoutPeriodRequest  $request
     * @param  PayoutPeriod  $payoutPeriod
     * @return JsonResponse
     */
    public function update(UpdatePayoutPeriodRequest $request, PayoutPeriod $payoutPeriod): JsonResponse
    {
        Gate::authorize('update', $payoutPeriod);

        DB::transaction(function () use ($request, $payoutPeriod): void {
            $beforeStatus = $payoutPeriod->status;
            $canRefreshBeforeUpdate = $payoutPeriod->canRefreshEarnings();

            $payoutPeriod->update([
                ...$request->validated(),
                'updated_by' => $request->user()->id,
            ]);

            $payoutPeriod->refresh();

            if ($payoutPeriod->isCancelled()) {
                $this->releaseEarnings($payoutPeriod);

                return;
            }

            if ($canRefreshBeforeUpdate && in_array($payoutPeriod->status, [
                PayoutPeriod::STATUS_DRAFT,
                PayoutPeriod::STATUS_OPEN,
                PayoutPeriod::STATUS_LOCKED,
            ], true)) {
                $this->refreshEligibleEarnings($payoutPeriod);

                return;
            }

            if ($beforeStatus === PayoutPeriod::STATUS_LOCKED && $payoutPeriod->status === PayoutPeriod::STATUS_PAID) {
                $payoutPeriod->earnings()->update(['status' => TeacherEarning::STATUS_PAID]);
            }
        });

        return response()->json([
            'data' => new PayoutPeriodResource($payoutPeriod->refresh()->load(['earnings.teacher', 'earnings.lessonRecord'])->loadCount('earnings')->loadSum('earnings', 'amount')),
        ]);
    }

    /**
     * Handle the refresh eligible earnings action for payout period records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $period.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  PayoutPeriod  $period
     * @return void
     */
    private function refreshEligibleEarnings(PayoutPeriod $period): void
    {
        $eligibleIds = $this->eligibleEarningsQuery($period)->pluck('teacher_earnings.id')->all();

        $currentIds = $period->earnings()->pluck('teacher_earnings.id')->all();
        $removedIds = array_values(array_diff($currentIds, $eligibleIds));

        $period->earnings()->sync($eligibleIds);

        if ($removedIds !== []) {
            TeacherEarning::query()
                ->whereKey($removedIds)
                ->where('status', TeacherEarning::STATUS_INCLUDED_IN_PAYOUT)
                ->update(['status' => TeacherEarning::STATUS_APPROVED]);
        }

        TeacherEarning::query()
            ->whereKey($eligibleIds)
            ->where('status', TeacherEarning::STATUS_APPROVED)
            ->update(['status' => TeacherEarning::STATUS_INCLUDED_IN_PAYOUT]);
    }

    /**
     * Handle the release earnings action for payout period records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $period.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  PayoutPeriod  $period
     * @return void
     */
    private function releaseEarnings(PayoutPeriod $period): void
    {
        $earningIds = $period->earnings()->pluck('teacher_earnings.id')->all();

        $period->earnings()->detach();

        TeacherEarning::query()
            ->whereKey($earningIds)
            ->where('status', TeacherEarning::STATUS_INCLUDED_IN_PAYOUT)
            ->update(['status' => TeacherEarning::STATUS_APPROVED]);
    }

    /**
     * @return Builder<TeacherEarning>
     *
     * @param  PayoutPeriod  $period
     */
    private function eligibleEarningsQuery(PayoutPeriod $period): Builder
    {
        return TeacherEarning::query()
            ->whereIn('status', [TeacherEarning::STATUS_APPROVED, TeacherEarning::STATUS_INCLUDED_IN_PAYOUT])
            ->where(function (Builder $query) use ($period): void {
                $query
                    ->whereDoesntHave('payoutPeriods', fn (Builder $query) => $query->active())
                    ->orWhereHas('payoutPeriods', fn (Builder $query) => $query->whereKey($period->id));
            })
            ->where(function (Builder $query) use ($period): void {
                $query
                    ->whereHas('lessonRecord', function (Builder $query) use ($period): void {
                        $query
                            ->whereDate('scheduled_date', '>=', $period->start_date)
                            ->whereDate('scheduled_date', '<=', $period->end_date)
                            ->whereDate('scheduled_date', '<=', $period->cutoff_date);
                    })
                    ->orWhere(function (Builder $query) use ($period): void {
                        $query
                            ->whereNull('lesson_record_id')
                            ->whereDate('created_at', '>=', $period->start_date)
                            ->whereDate('created_at', '<=', $period->end_date)
                            ->whereDate('created_at', '<=', $period->cutoff_date);
                    });
            });
    }
}
