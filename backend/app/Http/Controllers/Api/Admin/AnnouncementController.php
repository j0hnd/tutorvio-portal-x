<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\StoreAnnouncementRequest;
use App\Http\Requests\Announcements\UpdateAnnouncementRequest;
use App\Http\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use App\Services\Announcements\AnnouncementRecipientResolver;
use App\Services\Notifications\SystemNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AnnouncementController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  AnnouncementRecipientResolver  $recipientResolver
     * @param  SystemNotificationService  $notificationService
     */
    public function __construct(
        private readonly AnnouncementRecipientResolver $recipientResolver,
        private readonly SystemNotificationService $notificationService
    ) {}

    /**
     * Display a filtered list of announcement records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Notable request fields include include_archived, only_archived.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        foreach (['include_archived', 'only_archived'] as $key) {
            $value = $request->input($key);

            if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
                $request->merge([$key => strtolower($value) === 'true']);
            }
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(Announcement::STATUSES)],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'include_archived' => ['sometimes', 'boolean'],
            'only_archived' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $includeArchived = $request->boolean('include_archived')
            || $request->boolean('only_archived')
            || ($validated['status'] ?? null) === Announcement::STATUS_ARCHIVED;

        $announcements = Announcement::query()
            ->with('author')
            ->withCount('recipients')
            ->when(! $includeArchived, fn (Builder $query) => $query->where('is_archived', false))
            ->when($request->boolean('only_archived'), fn (Builder $query) => $query->where('is_archived', true))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($announcements->through(fn (Announcement $announcement) => new AnnouncementResource($announcement)));
    }

    /**
     * Create a new announcement record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreAnnouncementRequest handles authorization and validation before the controller action runs.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  StoreAnnouncementRequest  $request
     * @return JsonResponse
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $status = $validated['status'] ?? Announcement::STATUS_DRAFT;

        $announcement = DB::transaction(function () use ($request, $validated, $status) {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['content'] ?? $validated['body'],
                'status' => $status,
                'type' => Announcement::TYPE_ADMIN_ANNOUNCEMENT,
                'author_id' => $request->user()->id,
                'scheduled_at' => $status === Announcement::STATUS_SCHEDULED
                    ? Carbon::parse($validated['scheduled_at'])
                    : null,
                'published_at' => $status === Announcement::STATUS_PUBLISHED ? now() : null,
            ]);

            $this->syncTargets($announcement, $validated['targets'] ?? []);
            $this->recipientResolver->syncRecipients($announcement);

            return $announcement;
        });

        if ($announcement->status === Announcement::STATUS_PUBLISHED) {
            $this->notifyPublishedAnnouncement($announcement->refresh());
        }

        return response()->json([
            'data' => new AnnouncementResource($announcement->load(['author', 'targets'])->loadCount('recipients')),
        ], 201);
    }

    /**
     * Display the selected announcement record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $announcement.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Announcement  $announcement
     * @return JsonResponse
     */
    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json([
            'data' => new AnnouncementResource($announcement->load(['author', 'targets'])->loadCount('recipients')),
        ]);
    }

    /**
     * Update the selected announcement record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $announcement.
     * The UpdateAnnouncementRequest handles authorization and validation before the controller action runs.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  UpdateAnnouncementRequest  $request
     * @param  Announcement  $announcement
     * @return JsonResponse
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $this->ensureNotArchived($announcement);

        $validated = $request->validated();
        $attributes = [];

        foreach (['title', 'status'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('content', $validated) || array_key_exists('body', $validated)) {
            $attributes['body'] = $validated['content'] ?? $validated['body'];
        }

        if (($attributes['status'] ?? null) === Announcement::STATUS_SCHEDULED) {
            $attributes['scheduled_at'] = Carbon::parse($validated['scheduled_at']);
            $attributes['published_at'] = null;
        } elseif (($attributes['status'] ?? null) === Announcement::STATUS_PUBLISHED) {
            $attributes['published_at'] = $announcement->published_at ?? now();
            $attributes['scheduled_at'] = null;
        } elseif (($attributes['status'] ?? null) === Announcement::STATUS_DRAFT) {
            $attributes['published_at'] = null;
            $attributes['scheduled_at'] = null;
        } elseif (array_key_exists('scheduled_at', $validated)) {
            $attributes['scheduled_at'] = $validated['scheduled_at'] === null ? null : Carbon::parse($validated['scheduled_at']);
        }

        DB::transaction(function () use ($announcement, $attributes, $validated) {
            $announcement->update($attributes);

            if (array_key_exists('targets', $validated)) {
                $this->syncTargets($announcement, $validated['targets']);
            }

            $this->recipientResolver->syncRecipients($announcement);
        });

        if (($attributes['status'] ?? null) === Announcement::STATUS_PUBLISHED) {
            $this->notifyPublishedAnnouncement($announcement->refresh());
        }

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load(['author', 'targets'])->loadCount('recipients')),
        ]);
    }

    /**
     * Handle the publish action for announcement records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $announcement.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Announcement  $announcement
     * @return JsonResponse
     */
    public function publish(Announcement $announcement): JsonResponse
    {
        $this->ensureNotArchived($announcement);

        DB::transaction(function () use ($announcement) {
            $announcement->update([
                'status' => Announcement::STATUS_PUBLISHED,
                'published_at' => now(),
                'scheduled_at' => null,
            ]);

            $this->recipientResolver->syncRecipients($announcement);
        });

        $this->notifyPublishedAnnouncement($announcement->refresh());

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load(['author', 'targets'])->loadCount('recipients')),
        ]);
    }

    /**
     * Handle the unpublish action for announcement records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $announcement.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Announcement  $announcement
     * @return JsonResponse
     */
    public function unpublish(Announcement $announcement): JsonResponse
    {
        $this->ensureNotArchived($announcement);

        $announcement->update([
            'status' => Announcement::STATUS_DRAFT,
            'published_at' => null,
            'scheduled_at' => null,
        ]);

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load(['author', 'targets'])->loadCount('recipients')),
        ]);
    }

    /**
     * Handle the schedule action for announcement records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $announcement.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  Announcement  $announcement
     * @return JsonResponse
     */
    public function schedule(Request $request, Announcement $announcement): JsonResponse
    {
        $this->ensureNotArchived($announcement);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        DB::transaction(function () use ($announcement, $validated) {
            $announcement->update([
                'status' => Announcement::STATUS_SCHEDULED,
                'scheduled_at' => Carbon::parse($validated['scheduled_at']),
                'published_at' => null,
            ]);

            $this->recipientResolver->syncRecipients($announcement);
        });

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load(['author', 'targets'])->loadCount('recipients')),
        ]);
    }

    /**
     * Archive the selected announcement record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $announcement.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  Request  $request
     * @param  Announcement  $announcement
     * @return JsonResponse
     */
    public function archive(Request $request, Announcement $announcement): JsonResponse
    {
        $announcement->update([
            'status' => Announcement::STATUS_ARCHIVED,
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load(['author', 'targets'])->loadCount('recipients')),
        ]);
    }

    /**
     * Delete the selected announcement record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $announcement.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON confirmation after deletion.
     *
     * @param  Request  $request
     * @param  Announcement  $announcement
     * @return JsonResponse
     */
    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        return $this->archive($request, $announcement);
    }

    /**
     * Handle the recipient count action for announcement records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $announcement.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Announcement  $announcement
     * @return JsonResponse
     */
    public function recipientCount(Announcement $announcement): JsonResponse
    {
        return response()->json([
            'data' => [
                'announcement_id' => $announcement->id,
                'recipient_count' => $this->recipientResolver->syncRecipients($announcement),
            ],
        ]);
    }

    /**
     * Handle the ensure not archived action for announcement records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $announcement.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Announcement  $announcement
     * @return void
     */
    private function ensureNotArchived(Announcement $announcement): void
    {
        if ($announcement->is_archived || $announcement->status === Announcement::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'announcement' => 'Archived announcements cannot be modified.',
            ]);
        }
    }

    /**
     * Handle the notify published announcement action for announcement records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $announcement.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Announcement  $announcement
     * @return void
     */
    private function notifyPublishedAnnouncement(Announcement $announcement): void
    {
        try {
            $recipients = $announcement->recipients()
                ->orderBy('id')
                ->get(['user_id', 'matched_targets']);

            $this->notificationService->adminAnnouncement(
                $recipients->pluck('user_id')->all(),
                $announcement->title,
                $announcement->body,
                [
                    'announcement_id' => $announcement->id,
                ],
                [
                    'email' => true,
                    'sender_id' => $announcement->author_id,
                    'published_at' => $announcement->published_at ?? now(),
                    'source_type' => 'announcement',
                    'source_id' => $announcement->id,
                    'recipient_metadata' => $recipients
                        ->mapWithKeys(fn ($recipient) => [
                            $recipient->user_id => [
                                'matched_targets' => $recipient->matched_targets ?? [],
                            ],
                        ])
                        ->all(),
                ]
            );
        } catch (Throwable $exception) {
            Log::warning('Announcement notification delivery failed.', [
                'announcement_id' => $announcement->id,
                'author_id' => $announcement->author_id,
                'failure_type' => $exception::class,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $targets
     *
     * @param  Announcement  $announcement
     * @param  array  $targets
     * @return void
     */
    private function syncTargets(Announcement $announcement, array $targets): void
    {
        if ($targets === []) {
            $targets = [
                ['type' => AnnouncementTarget::TARGET_ALL],
            ];
        }

        $announcement->targets()->delete();

        $announcement->targets()->createMany(array_map(function (array $target) {
            $type = $target['type'] ?? $target['target_type'];
            $metadata = $target['metadata'] ?? [];

            if (array_key_exists('group', $target) && $target['group'] !== null) {
                $metadata['group'] = $target['group'];
            }

            return [
                'target_type' => $type,
                'target_id' => $target['target_id'] ?? null,
                'user_id' => $type === AnnouncementTarget::TARGET_USER
                    ? ($target['user_id'] ?? $target['target_id'] ?? null)
                    : ($target['user_id'] ?? null),
                'role' => $target['role'] ?? null,
                'metadata' => $metadata,
            ];
        }, $targets));
    }
}
