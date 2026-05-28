<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\PackageUsageReportRequest;
use App\Models\Subscription;
use App\Reports\PackageUsageReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PackageUsageReportController extends Controller
{
    public function __invoke(PackageUsageReportRequest $request, PackageUsageReport $report): JsonResponse
    {
        Gate::authorize('viewAny', Subscription::class);

        return response()->json([
            'data' => $report->generate($request->filters(), $request->user()),
        ]);
    }
}
