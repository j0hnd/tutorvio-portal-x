<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SchoolReportRequest;
use App\Reports\ActiveStudentsReport;
use Illuminate\Http\JsonResponse;

class ActiveStudentsReportController extends Controller
{
    public function __invoke(SchoolReportRequest $request, ActiveStudentsReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->generate($request->filters()),
        ]);
    }
}
