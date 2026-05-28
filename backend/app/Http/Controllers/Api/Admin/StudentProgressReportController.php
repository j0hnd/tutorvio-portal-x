<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StudentProgressReportRequest;
use App\Reports\StudentProgressReport;
use Illuminate\Http\JsonResponse;

class StudentProgressReportController extends Controller
{
    public function __invoke(StudentProgressReportRequest $request, StudentProgressReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->generate($request->filters(), $request->user()),
        ]);
    }
}
