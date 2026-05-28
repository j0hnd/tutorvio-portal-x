<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SchoolReportRequest;
use App\Reports\MissedClassReport;
use Illuminate\Http\JsonResponse;

class MissedClassReportController extends Controller
{
    public function __invoke(SchoolReportRequest $request, MissedClassReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->generate($request->filters()),
        ]);
    }
}
