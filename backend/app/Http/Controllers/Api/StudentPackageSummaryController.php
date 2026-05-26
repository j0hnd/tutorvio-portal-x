<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Subscriptions\StudentPackageSummaryResource;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentPackageSummaryController extends Controller
{
    public function __invoke(Request $request, User $student): JsonResponse
    {
        abort_unless($student->hasRole('student'), 404);
        abort_unless($request->user()->hasRole('student') && (int) $request->user()->id === (int) $student->id, 403);

        $subscription = Subscription::query()
            ->with('invoice:id,invoice_number')
            ->where('user_id', $student->id)
            ->orderByRaw("case when status = 'active' or is_frozen = 1 then 0 else 1 end")
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'data' => $subscription ? new StudentPackageSummaryResource($subscription) : null,
        ]);
    }
}
