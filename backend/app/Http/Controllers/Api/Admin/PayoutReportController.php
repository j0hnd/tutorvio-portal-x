<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutPeriod;
use App\Models\PayoutReport;
use App\Models\User;
use App\Services\Payroll\PayoutReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PayoutReportController extends Controller
{
    /**
     * Handle the period action for payout report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $payoutPeriod, $reports.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  PayoutPeriod  $payoutPeriod
     * @param  PayoutReportService  $reports
     * @return JsonResponse
     */
    public function period(PayoutPeriod $payoutPeriod, PayoutReportService $reports): JsonResponse
    {
        Gate::authorize('viewAny', PayoutReport::class);

        return response()->json([
            'data' => $reports->forPeriod($payoutPeriod),
        ]);
    }

    /**
     * Handle the teacher action for payout report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacher, $reports.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record. The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  User  $teacher
     * @param  PayoutReportService  $reports
     * @return JsonResponse
     */
    public function teacher(Request $request, User $teacher, PayoutReportService $reports): JsonResponse
    {
        Gate::authorize('viewAny', PayoutReport::class);

        abort_unless($teacher->hasRole('teacher'), 404);

        $validated = $request->validate([
            'payout_period_id' => ['sometimes', 'integer', 'exists:payout_periods,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

        return response()->json([
            'data' => $reports->forTeacher($teacher, $validated),
        ]);
    }
}
