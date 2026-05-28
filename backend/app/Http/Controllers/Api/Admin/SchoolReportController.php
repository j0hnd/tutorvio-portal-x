<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SchoolReportRequest;
use Illuminate\Http\JsonResponse;

class SchoolReportController extends Controller
{
    public function index(SchoolReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'filters' => $request->filters()->toArray(),
            ],
        ]);
    }
}
