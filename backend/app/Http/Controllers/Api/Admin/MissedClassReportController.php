<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SchoolReportRequest;
use App\Reports\MissedClassReport;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class MissedClassReportController extends Controller
{
    public function __invoke(SchoolReportRequest $request, MissedClassReport $report): JsonResponse
    {
        return ReportResponse::json($report->generate($request->filters()), $request->pagination());
    }
}
