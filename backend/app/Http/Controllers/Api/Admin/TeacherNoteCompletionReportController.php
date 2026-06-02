<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\TeacherNoteCompletionReportRequest;
use App\Reports\TeacherNoteCompletionReport;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class TeacherNoteCompletionReportController extends Controller
{
    /**
     * Handle the teacher note completion report endpoint.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $report.
     * The TeacherNoteCompletionReportRequest handles authorization and validation before the controller action runs.
     * Returns a JSON response containing the requested data.
     *
     * @param  TeacherNoteCompletionReportRequest  $request
     * @param  TeacherNoteCompletionReport  $report
     * @return JsonResponse
     */
    public function __invoke(TeacherNoteCompletionReportRequest $request, TeacherNoteCompletionReport $report): JsonResponse
    {
        return ReportResponse::json($report->generate($request->filters(), $request->user()), $request->pagination());
    }
}
