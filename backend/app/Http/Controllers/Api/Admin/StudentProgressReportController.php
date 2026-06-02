<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StudentProgressReportRequest;
use App\Reports\StudentProgressReport;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class StudentProgressReportController extends Controller
{
    /**
     * Handle the student progress report endpoint.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $report.
     * The StudentProgressReportRequest handles authorization and validation before the controller action runs.
     * Returns a JSON response containing the requested data.
     *
     * @param  StudentProgressReportRequest  $request
     * @param  StudentProgressReport  $report
     * @return JsonResponse
     */
    public function __invoke(StudentProgressReportRequest $request, StudentProgressReport $report): JsonResponse
    {
        return ReportResponse::json($report->generate($request->filters(), $request->user()), $request->pagination());
    }
}
