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
    public function period(PayoutPeriod $payoutPeriod, PayoutReportService $reports): JsonResponse
    {
        Gate::authorize('viewAny', PayoutReport::class);

        return response()->json([
            'data' => $reports->forPeriod($payoutPeriod),
        ]);
    }

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
