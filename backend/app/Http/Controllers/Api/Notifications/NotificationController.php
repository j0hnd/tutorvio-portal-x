<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\Notifications\NotificationResource;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    /**
     * Display a filtered list of notification records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $this->validateFilters($request);

        return response()->json(
            $this->applyFilters($this->visibleRecipientsFor($request->user()), $validated)
                ->tap(fn (Builder $query) => $this->orderNewestFirst($query))
                ->paginate($validated['per_page'] ?? 25)
                ->through(fn (NotificationRecipient $recipient) => new NotificationResource($recipient))
        );
    }

    /**
     * Display historical notification records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    public function history(Request $request): JsonResponse
    {
        $validated = $this->validateFilters($request);

        return response()->json(
            $this->applyFilters($this->historyRecipientsFor($request->user()), $validated)
                ->tap(fn (Builder $query) => $this->orderNewestFirst($query))
                ->paginate($validated['per_page'] ?? 25)
                ->through(fn (NotificationRecipient $recipient) => new NotificationResource($recipient))
        );
    }

    /**
     * Handle the unread count action for notification records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'unread_count' => $this->visibleRecipientsFor($request->user())
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    /**
     * Display the selected notification record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $notification.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    public function show(Request $request, Notification $notification): JsonResponse
    {
        return response()->json([
            'data' => new NotificationResource($this->recipientForNotification($request->user(), $notification)),
        ]);
    }

    /**
     * Handle the mark read action for notification records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $notification.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $recipient = $this->recipientForNotification($request->user(), $notification);

        if ($recipient->read_at === null) {
            $recipient->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'data' => new NotificationResource($recipient->refresh()->load('notification')),
        ]);
    }

    /**
     * Handle the mark all read action for notification records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $readAt = now();

        $updated = $this->visibleRecipientsFor($request->user())
            ->whereNull('read_at')
            ->update(['read_at' => $readAt]);

        return response()->json([
            'data' => [
                'marked_read_count' => $updated,
                'read_at' => $readAt,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        foreach (['read', 'unread'] as $key) {
            $value = $request->input($key);

            if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
                $request->merge([$key => strtolower($value) === 'true']);
            }
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['read', 'unread'])],
            'read' => ['sometimes', 'boolean'],
            'unread' => ['sometimes', 'boolean'],
            'type' => ['sometimes', 'string', Rule::in(Notification::TYPES)],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'created_from' => ['sometimes', 'date'],
            'created_to' => ['sometimes', 'date'],
            'published_from' => ['sometimes', 'date'],
            'published_to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if (($validated['read'] ?? false) && ($validated['unread'] ?? false)) {
            throw ValidationException::withMessages([
                'read' => 'The read and unread filters cannot both be true.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  Builder<NotificationRecipient>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<NotificationRecipient>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $readFilter = $filters['status'] ?? null;

        if ($readFilter === null && ($filters['read'] ?? false)) {
            $readFilter = 'read';
        }

        if ($readFilter === null && ($filters['unread'] ?? false)) {
            $readFilter = 'unread';
        }

        return $query
            ->when($readFilter === 'read', fn (Builder $query) => $query->whereNotNull('read_at'))
            ->when($readFilter === 'unread', fn (Builder $query) => $query->whereNull('read_at'))
            ->when($filters['type'] ?? null, function (Builder $query, string $type) {
                $query->whereHas('notification', fn (Builder $query) => $query->where('type', $type));
            })
            ->when($filters['date_from'] ?? $filters['created_from'] ?? null, function (Builder $query, string $from) {
                $query->whereHas('notification', fn (Builder $query) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()));
            })
            ->when($filters['date_to'] ?? $filters['created_to'] ?? null, function (Builder $query, string $to) {
                $query->whereHas('notification', fn (Builder $query) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()));
            })
            ->when($filters['published_from'] ?? null, function (Builder $query, string $from) {
                $query->whereHas('notification', fn (Builder $query) => $query->where('published_at', '>=', Carbon::parse($from)->startOfDay()));
            })
            ->when($filters['published_to'] ?? null, function (Builder $query, string $to) {
                $query->whereHas('notification', fn (Builder $query) => $query->where('published_at', '<=', Carbon::parse($to)->endOfDay()));
            });
    }

    /**
     * @return Builder<NotificationRecipient>
     */
    private function visibleRecipientsFor(User $user): Builder
    {
        return NotificationRecipient::query()
            ->with('notification')
            ->where('user_id', $user->id)
            ->where('channel', NotificationRecipient::CHANNEL_IN_PORTAL)
            ->whereNull('archived_at')
            ->whereHas('notification', function (Builder $query) {
                $query
                    ->where('is_archived', false)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
            });
    }

    /**
     * @return Builder<NotificationRecipient>
     */
    private function historyRecipientsFor(User $user): Builder
    {
        return NotificationRecipient::query()
            ->with('notification')
            ->when(
                ! $user->hasRole('admin'),
                fn (Builder $query) => $query->where('user_id', $user->id)
            )
            ->whereNull('archived_at')
            ->whereHas('notification', function (Builder $query) {
                $query->where('is_archived', false);
            });
    }

    /**
     * @param  Builder<NotificationRecipient>  $query
     */
    private function orderNewestFirst(Builder $query): void
    {
        $query
            ->orderByDesc(
                Notification::query()
                    ->select('created_at')
                    ->whereColumn('notifications.id', 'notification_recipients.notification_id')
                    ->limit(1)
            )
            ->orderByDesc('id');
    }

    /**
     * Handle the recipient for notification action for notification records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $user, $notification.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    private function recipientForNotification(User $user, Notification $notification): NotificationRecipient
    {
        return $this->visibleRecipientsFor($user)
            ->where('notification_id', $notification->id)
            ->firstOrFail();
    }
}
