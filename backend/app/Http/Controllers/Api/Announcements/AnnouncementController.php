<?php

namespace App\Http\Controllers\Api\Announcements;

use App\Http\Controllers\Controller;
use App\Http\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AnnouncementReadState;
use App\Services\Announcements\AnnouncementRecipientResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     */
    public function __construct(private readonly AnnouncementRecipientResolver $recipientResolver) {}

    /**
     * Display a filtered list of announcement records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     */
    public function index(Request $request): JsonResponse
    {
        foreach (['read', 'unread'] as $key) {
            $value = $request->input($key);

            if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
                $request->merge([$key => strtolower($value) === 'true']);
            }
        }

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['read', 'unread'])],
            'read' => ['sometimes', 'boolean'],
            'unread' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if (($validated['read'] ?? false) && ($validated['unread'] ?? false)) {
            throw ValidationException::withMessages([
                'read' => 'The read and unread filters cannot both be true.',
            ]);
        }

        if ($request->user()->hasRole('staff') && ! $request->user()->can('dashboard.operational_notices.view')) {
            $announcements = Announcement::query()
                ->whereRaw('1 = 0')
                ->paginate($validated['per_page'] ?? 25);

            return response()->json($announcements->through(fn (Announcement $announcement) => new AnnouncementResource($announcement)));
        }

        $announcements = Announcement::query()
            ->with(['readStates' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->active()
            ->visibleTo($request->user())
            ->tap(fn (Builder $query) => $this->applyReadFilter($query, $validated, $request->user()->id))
            ->search($validated['search'] ?? null)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($announcements->through(fn (Announcement $announcement) => new AnnouncementResource($announcement)));
    }

    /**
     * Display the selected announcement record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $announcement.
     * The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     */
    public function show(Announcement $announcement): JsonResponse
    {
        abort_unless(
            $announcement->status === Announcement::STATUS_PUBLISHED
            && ! $announcement->is_archived
            && $announcement->published_at !== null
            && $announcement->published_at->lessThanOrEqualTo(now())
            && $this->recipientResolver->canView($announcement, request()->user()),
            404
        );

        return response()->json([
            'data' => new AnnouncementResource($announcement->load([
                'readStates' => fn ($query) => $query->where('user_id', request()->user()->id),
            ])),
        ]);
    }

    /**
     * Handle the unread count action for announcement records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        if ($request->user()->hasRole('staff') && ! $request->user()->can('dashboard.operational_notices.view')) {
            return response()->json([
                'data' => [
                    'unread_count' => 0,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'unread_count' => Announcement::query()
                    ->active()
                    ->visibleTo($request->user())
                    ->whereDoesntHave('readStates', fn (Builder $query) => $query
                        ->where('user_id', $request->user()->id)
                        ->whereNotNull('read_at'))
                    ->count(),
            ],
        ]);
    }

    /**
     * Handle the mark read action for announcement records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $announcement.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function markRead(Request $request, Announcement $announcement): JsonResponse
    {
        $this->abortUnlessVisible($announcement, $request);

        AnnouncementReadState::updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $request->user()->id,
            ],
            [
                'read_at' => now(),
            ]
        );

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load([
                'readStates' => fn ($query) => $query->where('user_id', $request->user()->id),
            ])),
        ]);
    }

    /**
     * Handle the mark unread action for announcement records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $announcement.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function markUnread(Request $request, Announcement $announcement): JsonResponse
    {
        $this->abortUnlessVisible($announcement, $request);

        AnnouncementReadState::updateOrCreate(
            [
                'announcement_id' => $announcement->id,
                'user_id' => $request->user()->id,
            ],
            [
                'read_at' => null,
            ]
        );

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load([
                'readStates' => fn ($query) => $query->where('user_id', $request->user()->id),
            ])),
        ]);
    }

    /**
     * Handle the mark all read action for announcement records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $readAt = now();

        if ($request->user()->hasRole('staff') && ! $request->user()->can('dashboard.operational_notices.view')) {
            return response()->json([
                'data' => [
                    'marked_read_count' => 0,
                    'read_at' => $readAt,
                ],
            ]);
        }

        $announcementIds = Announcement::query()
            ->active()
            ->visibleTo($request->user())
            ->whereDoesntHave('readStates', fn (Builder $query) => $query
                ->where('user_id', $request->user()->id)
                ->whereNotNull('read_at'))
            ->pluck('id');

        $markedReadCount = 0;

        foreach ($announcementIds as $announcementId) {
            $readState = AnnouncementReadState::updateOrCreate(
                [
                    'announcement_id' => $announcementId,
                    'user_id' => $request->user()->id,
                ],
                [
                    'read_at' => $readAt,
                ]
            );

            if ($readState->wasRecentlyCreated || $readState->wasChanged('read_at')) {
                $markedReadCount++;
            }
        }

        return response()->json([
            'data' => [
                'marked_read_count' => $markedReadCount,
                'read_at' => $readAt,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyReadFilter(Builder $query, array $filters, int $userId): void
    {
        $readFilter = $filters['status'] ?? null;

        if ($readFilter === null && ($filters['read'] ?? false)) {
            $readFilter = 'read';
        }

        if ($readFilter === null && ($filters['unread'] ?? false)) {
            $readFilter = 'unread';
        }

        if ($readFilter === 'read') {
            $query->whereHas('readStates', fn (Builder $query) => $query
                ->where('user_id', $userId)
                ->whereNotNull('read_at'));
        }

        if ($readFilter === 'unread') {
            $query->whereDoesntHave('readStates', fn (Builder $query) => $query
                ->where('user_id', $userId)
                ->whereNotNull('read_at'));
        }
    }

    /**
     * Handle the abort unless visible action for announcement records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $announcement.
     * The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     */
    private function abortUnlessVisible(Announcement $announcement, Request $request): void
    {
        abort_unless(
            $announcement->status === Announcement::STATUS_PUBLISHED
            && ! $announcement->is_archived
            && $announcement->published_at !== null
            && $announcement->published_at->lessThanOrEqualTo(now())
            && $this->recipientResolver->canView($announcement, $request->user()),
            404
        );
    }
}
