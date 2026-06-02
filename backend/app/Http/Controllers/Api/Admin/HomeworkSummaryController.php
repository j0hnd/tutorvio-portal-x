<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homeworks\HomeworkSummaryRequest;
use App\Services\HomeworkSummaryService;
use Illuminate\Http\JsonResponse;

class HomeworkSummaryController extends Controller
{
    /**
     * Handle the homework summary endpoint.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $homeworks.
     * The HomeworkSummaryRequest handles authorization and validation before the controller action runs.
     * Returns a JSON response containing the requested data.
     *
     * @param  HomeworkSummaryRequest  $request
     * @param  HomeworkSummaryService  $homeworks
     * @return JsonResponse
     */
    public function __invoke(HomeworkSummaryRequest $request, HomeworkSummaryService $homeworks): JsonResponse
    {
        return response()->json([
            'data' => $homeworks->summary($request->validated()),
        ]);
    }
}
