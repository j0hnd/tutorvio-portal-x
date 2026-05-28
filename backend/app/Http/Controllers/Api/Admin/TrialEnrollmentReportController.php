<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\TrialEnrollmentReportRequest;
use App\Reports\TrialEnrollmentReport;
use Illuminate\Http\JsonResponse;

class TrialEnrollmentReportController extends Controller
{
    public function __invoke(TrialEnrollmentReportRequest $request, TrialEnrollmentReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->generate($request->filters()),
        ]);
    }
}
