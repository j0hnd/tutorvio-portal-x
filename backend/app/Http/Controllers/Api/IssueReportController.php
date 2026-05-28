<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IssueReports\StoreIssueReportRequest;
use App\Http\Resources\IssueReports\IssueReportResource;
use App\Models\IssueReport;
use Illuminate\Http\JsonResponse;

class IssueReportController extends Controller
{
    public function store(StoreIssueReportRequest $request): JsonResponse
    {
        $issueReport = IssueReport::create($request->issuePayload());

        return response()->json([
            'data' => new IssueReportResource($issueReport->refresh()),
        ], 201);
    }
}
