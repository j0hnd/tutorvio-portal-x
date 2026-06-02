<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SchoolReportRequest;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class SchoolReportController extends Controller
{
    public function index(SchoolReportRequest $request): JsonResponse
    {
        return ReportResponse::json([
            'filters' => $request->filters()->toArray(),
        ], $request->pagination());
    }
}
