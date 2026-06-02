<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SchoolReportRequest;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;

class SchoolReportController extends Controller
{
    /**
     * Display a filtered list of school report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The SchoolReportRequest handles authorization and validation before the controller action runs.
     * Returns a JSON response containing the requested data.
     *
     * @param  SchoolReportRequest  $request
     * @return JsonResponse
     */
    public function index(SchoolReportRequest $request): JsonResponse
    {
        return ReportResponse::json([
            'filters' => $request->filters()->toArray(),
        ], $request->pagination());
    }
}
