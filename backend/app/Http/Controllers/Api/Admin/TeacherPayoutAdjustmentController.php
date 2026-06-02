<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreTeacherPayoutAdjustmentRequest;
use App\Http\Resources\Payroll\TeacherPayoutAdjustmentResource;
use App\Models\TeacherPayoutAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeacherPayoutAdjustmentController extends Controller
{
    /**
     * Display a filtered list of teacher payout adjustment records.
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
        Gate::authorize('viewAny', TeacherPayoutAdjustment::class);

        $validated = $request->validate([
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'payout_period_id' => ['sometimes', 'integer', 'exists:payout_periods,id'],
            'type' => ['sometimes', 'string', Rule::in(TeacherPayoutAdjustment::TYPES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $adjustments = TeacherPayoutAdjustment::query()
            ->with(['teacher', 'payoutPeriod', 'createdBy'])
            ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($validated['payout_period_id'] ?? null, fn (Builder $query, int $periodId) => $query->where('payout_period_id', $periodId))
            ->when($validated['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($adjustments->through(fn (TeacherPayoutAdjustment $adjustment) => new TeacherPayoutAdjustmentResource($adjustment)));
    }

    /**
     * Create a new teacher payout adjustment record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreTeacherPayoutAdjustmentRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record. The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  StoreTeacherPayoutAdjustmentRequest  $request
     * @return JsonResponse
     */
    public function store(StoreTeacherPayoutAdjustmentRequest $request): JsonResponse
    {
        Gate::authorize('create', TeacherPayoutAdjustment::class);

        $validated = $request->validated();
        $teacher = User::query()->findOrFail($validated['teacher_id']);

        abort_unless($teacher->hasRole('teacher'), 422, 'Adjustments must be tied to a teacher.');

        $adjustment = TeacherPayoutAdjustment::create([
            ...$validated,
            'amount' => $this->normalizedAmount($validated['type'], (float) $validated['amount']),
            'currency' => strtoupper($validated['currency'] ?? 'USD'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new TeacherPayoutAdjustmentResource($adjustment->load(['teacher', 'payoutPeriod', 'createdBy'])),
        ], 201);
    }

    /**
     * Handle the normalized amount action for teacher payout adjustment records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $type, $amount.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  string  $type
     * @param  float  $amount
     * @return float
     */
    private function normalizedAmount(string $type, float $amount): float
    {
        if ($type === TeacherPayoutAdjustment::TYPE_DEDUCTION) {
            return -abs($amount);
        }

        return $amount;
    }
}
