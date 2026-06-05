<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IssueReports\StoreIssueReportRequest;
use App\Http\Resources\IssueReports\IssueReportResource;
use App\Models\IssueReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssueReportController extends Controller
{
    /**
     * Create a new issue report record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreIssueReportRequest handles authorization and validation before the controller action runs.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  StoreIssueReportRequest  $request
     * @return JsonResponse
     */
    public function store(StoreIssueReportRequest $request): JsonResponse
    {
        $issueReport = IssueReport::create($request->issuePayload());

        return response()->json([
            'data' => new IssueReportResource($issueReport->refresh()),
        ], 201);
    }

    /**
     * Display the selected issue report record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $issueReport.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  IssueReport  $issueReport
     * @return JsonResponse
     */
    public function show(Request $request, IssueReport $issueReport): JsonResponse
    {
        $user = $request->user();

        if (! $user || (int) $issueReport->reporter_id !== (int) $user->id) {
            abort(404);
        }

        return response()->json([
            'data' => new IssueReportResource($issueReport),
        ]);
    }
}
