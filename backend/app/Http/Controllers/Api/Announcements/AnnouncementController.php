<?php

namespace App\Http\Controllers\Api\Announcements;

use App\Http\Controllers\Controller;
use App\Http\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Services\Announcements\AnnouncementRecipientResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementRecipientResolver $recipientResolver) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if ($request->user()->hasRole('staff') && ! $request->user()->can('dashboard.operational_notices.view')) {
            $announcements = Announcement::query()
                ->whereRaw('1 = 0')
                ->paginate($validated['per_page'] ?? 25);

            return response()->json($announcements->through(fn (Announcement $announcement) => new AnnouncementResource($announcement)));
        }

        $announcements = Announcement::query()
            ->active()
            ->visibleTo($request->user())
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($announcements->through(fn (Announcement $announcement) => new AnnouncementResource($announcement)));
    }

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
            'data' => new AnnouncementResource($announcement),
        ]);
    }
}
