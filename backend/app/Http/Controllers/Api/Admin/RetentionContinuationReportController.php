<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\RetentionContinuationReportRequest;
use App\Reports\RetentionContinuationReport;
use Illuminate\Http\JsonResponse;

class RetentionContinuationReportController extends Controller
{
    public function __invoke(RetentionContinuationReportRequest $request, RetentionContinuationReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->generate($request->filters()),
        ]);
    }
}
