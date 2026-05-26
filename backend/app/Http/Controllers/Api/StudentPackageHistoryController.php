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

    private function canViewPackageHistory(User $user, User $student): bool
    {
        if ($user->hasRole('student')) {
            return (int) $user->id === (int) $student->id
                && (bool) config('billing.package_history.student_visibility_enabled', true);
        }

        return $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('subscriptions.view'));
    }

    private function requiresSafeHistory(User $user): bool
    {
        return ! $user->hasRole('admin')
            && ! ($user->hasRole('staff') && $user->can('subscriptions.view'));
    }
}
