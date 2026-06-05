<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Subscriptions\SubscriptionHistoryResource;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentPackageHistoryController extends Controller
{
    /**
     * Handle the student package history endpoint.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $student.
     * Inline validation rejects missing or invalid request data before processing. The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     */
    public function __invoke(Request $request, User $student): JsonResponse
    {
        abort_unless($student->hasRole('student'), 404);
        abort_unless($this->canViewPackageHistory($request->user(), $student), 403);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $history = SubscriptionHistory::query()
            ->where('student_id', $student->id)
            ->when($this->requiresSafeHistory($request->user()), function (Builder $query) {
                $query->whereNotIn('event_type', [
                    SubscriptionHistory::EVENT_PAYMENT_CHANGED,
                    SubscriptionHistory::EVENT_INVOICE_REFERENCE_CHANGED,
                ]);
            })
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json(
            $history->through(fn (SubscriptionHistory $item) => new SubscriptionHistoryResource($item))
        );
    }

    /**
     * Determine whether the user can view a student's package history.
     *
     * Students can view only their own history, and only when
     * `billing.package_history.student_visibility_enabled` is enabled. Admins
     * can view any history. Staff need `subscriptions.view`. Teachers are
     * denied.
     */
    private function canViewPackageHistory(User $user, User $student): bool
    {
        if ($user->hasRole('student')) {
            return (int) $user->id === (int) $student->id
                && (bool) config('billing.package_history.student_visibility_enabled', true);
        }

        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.view'));
    }

    /**
     * Determine whether billing-sensitive history fields must be hidden.
     *
     * Admins and staff with `subscriptions.view` can receive full package
     * history. Students, teachers, and staff without that permission receive
     * the safe history shape.
     */
    private function requiresSafeHistory(User $user): bool
    {
        return ! $user->hasRole('admin')
            && ! ($user->hasRole('staff') && $user->can('subscriptions.view'));
    }
}
