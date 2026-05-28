<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\TeacherNoteCompletionReportRequest;
use App\Reports\TeacherNoteCompletionReport;
use Illuminate\Http\JsonResponse;

class TeacherNoteCompletionReportController extends Controller
{
    public function __invoke(TeacherNoteCompletionReportRequest $request, TeacherNoteCompletionReport $report): JsonResponse
    {
        return response()->json([
            'data' => $report->generate($request->filters(), $request->user()),
        ]);
    }
}
