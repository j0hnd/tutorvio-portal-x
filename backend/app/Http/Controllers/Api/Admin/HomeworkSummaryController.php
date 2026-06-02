<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homeworks\HomeworkSummaryRequest;
use App\Services\HomeworkSummaryService;
use Illuminate\Http\JsonResponse;

class HomeworkSummaryController extends Controller
{
    public function __invoke(HomeworkSummaryRequest $request, HomeworkSummaryService $homeworks): JsonResponse
    {
        return response()->json([
            'data' => $homeworks->summary($request->validated()),
        ]);
    }
}
