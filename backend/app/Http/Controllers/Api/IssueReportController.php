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
            abort(403);
        }

        return response()->json([
            'data' => [
                'id' => $issueReport->id,
                'type' => $issueReport->issue_type,
                'issue_type' => $issueReport->issue_type,
                'status' => $issueReport->status,
                'priority' => $issueReport->priority,
                'title' => $issueReport->title,
                'assigned_to_id' => $issueReport->assigned_to_id,
                'resolved_at' => $issueReport->resolved_at,
                'created_at' => $issueReport->created_at,
                'updated_at' => $issueReport->updated_at,
            ],
        ]);
    }
}
