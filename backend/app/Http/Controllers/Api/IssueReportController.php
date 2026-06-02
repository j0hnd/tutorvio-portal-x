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
    public function store(StoreIssueReportRequest $request): JsonResponse
    {
        $issueReport = IssueReport::create($request->issuePayload());

        return response()->json([
            'data' => new IssueReportResource($issueReport->refresh()),
        ], 201);
    }

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
