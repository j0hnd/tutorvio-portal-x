<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\RetentionContinuationReportRequest;
use App\Reports\RetentionContinuationReport;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class RetentionContinuationReportController extends Controller
{
    public function __invoke(RetentionContinuationReportRequest $request, RetentionContinuationReport $report): JsonResponse
    {
        return ReportResponse::json($report->generate($request->filters()), $request->pagination());
    }
}
