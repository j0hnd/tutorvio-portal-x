<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SchoolReportRequest;
use App\Reports\AttendanceReport;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class AttendanceReportController extends Controller
{
    public function __invoke(SchoolReportRequest $request, AttendanceReport $report): JsonResponse
    {
        return ReportResponse::json($report->generate($request->filters()), $request->pagination());
    }
}
