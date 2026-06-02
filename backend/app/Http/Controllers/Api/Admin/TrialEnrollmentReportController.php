<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\TrialEnrollmentReportRequest;
use App\Reports\TrialEnrollmentReport;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class TrialEnrollmentReportController extends Controller
{
    /**
     * Handle the trial enrollment report endpoint.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $report.
     * The TrialEnrollmentReportRequest handles authorization and validation before the controller action runs.
     * Returns a JSON response containing the requested data.
     *
     * @param  TrialEnrollmentReportRequest  $request
     * @param  TrialEnrollmentReport  $report
     * @return JsonResponse
     */
    public function __invoke(TrialEnrollmentReportRequest $request, TrialEnrollmentReport $report): JsonResponse
    {
        return ReportResponse::json($report->generate($request->filters()), $request->pagination());
    }
}
