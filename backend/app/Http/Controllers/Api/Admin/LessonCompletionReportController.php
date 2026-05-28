<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\LessonCompletionReportRequest;
use App\Reports\LessonCompletionReport;
use Illuminate\Http\JsonResponse;

class LessonCompletionReportController extends Controller
{
    public function __invoke(LessonCompletionReportRequest $request, LessonCompletionReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->generate($request->filters()),
        ]);
    }
}
