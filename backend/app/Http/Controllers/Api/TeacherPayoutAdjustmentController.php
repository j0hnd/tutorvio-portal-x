<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Payroll\TeacherPayoutAdjustmentResource;
use App\Models\TeacherPayoutAdjustment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeacherPayoutAdjustmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewOwnSummary', TeacherPayoutAdjustment::class);

        $validated = $request->validate([
            'payout_period_id' => ['sometimes', 'integer', 'exists:payout_periods,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $adjustments = TeacherPayoutAdjustment::query()
            ->with('payoutPeriod')
            ->where('teacher_id', $request->user()->id)
            ->when($validated['payout_period_id'] ?? null, fn (Builder $query, int $periodId) => $query->where('payout_period_id', $periodId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($adjustments->through(fn (TeacherPayoutAdjustment $adjustment) => new TeacherPayoutAdjustmentResource($adjustment)));
    }
}
