<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\TrialEnrollmentReportRequest;
use App\Reports\TrialEnrollmentReport;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class TrialEnrollmentReportController extends Controller
{
    public function __invoke(TrialEnrollmentReportRequest $request, TrialEnrollmentReport $report): JsonResponse
    {
        return ReportResponse::json($report->generate($request->filters()), $request->pagination());
    }
}
