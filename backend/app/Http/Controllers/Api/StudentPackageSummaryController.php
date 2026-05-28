<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Subscriptions\StudentPackageSummaryResource;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionRenewalReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentPackageSummaryController extends Controller
{
    public function __construct(private readonly SubscriptionRenewalReminderService $renewalReminders) {}

    public function __invoke(Request $request, User $student): JsonResponse
    {
        abort_unless($student->hasRole('student'), 404);
        abort_unless($this->canViewPackageSummary($request->user(), $student), 403);

        $subscription = Subscription::query()
            ->with('invoice:id,invoice_number')
            ->where('user_id', $student->id)
            ->orderByRaw("case when status = 'active' or is_frozen = 1 then 0 else 1 end")
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->first();

        if ($subscription !== null) {
            $subscription = $this->renewalReminders->refreshReminderState($subscription);
        }

        return response()->json([
            'data' => $subscription ? new StudentPackageSummaryResource($subscription) : null,
        ]);
    }

    private function canViewPackageSummary(User $user, User $student): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('staff')) {
            return $user->can('subscriptions.view');
        }

        if ($user->hasRole('student')) {
            return (int) $user->id === (int) $student->id;
        }

        if ($user->hasRole('teacher')) {
            $student->loadMissing('studentProfile');

            return (int) $student->studentProfile?->assigned_teacher_id === (int) $user->id;
        }

        return false;
    }
}
