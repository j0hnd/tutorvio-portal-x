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
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     */
    public function __construct(private readonly SubscriptionRenewalReminderService $renewalReminders) {}

    /**
     * Handle the student package summary endpoint.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     */
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

    /**
     * Determine whether the user can view a student's package summary.
     *
     * Admins can view any summary. Staff need `subscriptions.view`. Students
     * can view only their own summary. Teachers can view summaries only for
     * students assigned to them. Other users are denied.
     */
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
