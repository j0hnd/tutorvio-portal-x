<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\LessonCompletionReportRequest;
use App\Reports\LessonCompletionReport;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class LessonCompletionReportController extends Controller
{
    /**
     * Handle the lesson completion report endpoint.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $report.
     * The LessonCompletionReportRequest handles authorization and validation before the controller action runs.
     * Returns a JSON response containing the requested data.
     */
    public function __invoke(LessonCompletionReportRequest $request, LessonCompletionReport $report): JsonResponse
    {
        return ReportResponse::json($report->generate($request->filters(), $request->pagination()), $request->pagination());
    }
}
