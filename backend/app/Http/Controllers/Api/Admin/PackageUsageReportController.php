<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\PackageUsageReportRequest;
use App\Reports\PackageUsageReport;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class PackageUsageReportController extends Controller
{
    /**
     * Handle the package usage report endpoint.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $report.
     * The PackageUsageReportRequest handles authorization and validation before the controller action runs.
     * Returns a JSON response containing the requested data.
     */
    public function __invoke(PackageUsageReportRequest $request, PackageUsageReport $report): JsonResponse
    {
        return ReportResponse::json($report->generate($request->filters(), $request->user(), $request->pagination()), $request->pagination());
    }
}
