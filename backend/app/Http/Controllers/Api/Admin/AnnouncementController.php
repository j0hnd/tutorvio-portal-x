<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\StoreAnnouncementRequest;
use App\Http\Requests\Announcements\UpdateAnnouncementRequest;
use App\Http\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
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

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $status = $validated['status'] ?? Announcement::STATUS_DRAFT;

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

        return response()->json([
            'data' => new AnnouncementResource($announcement->load('author')),
        ], 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json([
            'data' => new AnnouncementResource($announcement->load('author')),
        ]);
    }

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

        $announcement->update($attributes);

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load('author')),
        ]);
    }

    public function publish(Announcement $announcement): JsonResponse
    {
        $this->ensureNotArchived($announcement);

        $announcement->update([
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => now(),
            'scheduled_at' => null,
        ]);

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load('author')),
        ]);
    }

    public function schedule(Request $request, Announcement $announcement): JsonResponse
    {
        $this->ensureNotArchived($announcement);

        $validated = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $announcement->update([
            'status' => Announcement::STATUS_SCHEDULED,
            'scheduled_at' => Carbon::parse($validated['scheduled_at']),
            'published_at' => null,
        ]);

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load('author')),
        ]);
    }

    public function archive(Request $request, Announcement $announcement): JsonResponse
    {
        $announcement->update([
            'status' => Announcement::STATUS_ARCHIVED,
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new AnnouncementResource($announcement->refresh()->load('author')),
        ]);
    }

    private function ensureNotArchived(Announcement $announcement): void
    {
        if ($announcement->is_archived || $announcement->status === Announcement::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'announcement' => 'Archived announcements cannot be modified.',
            ]);
        }
    }
}
