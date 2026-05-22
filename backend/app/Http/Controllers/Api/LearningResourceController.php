<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LearningResources\StoreFileResourceRequest;
use App\Http\Requests\LearningResources\StoreLinkResourceRequest;
use App\Http\Requests\LearningResources\UpdateLearningResourceRequest;
use App\Http\Resources\LearningResources\LearningResourceResource;
use App\Models\LearningResource;
use App\Services\LearningResourceStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LearningResourceController extends Controller
{
    public function __construct(
        private readonly LearningResourceStorage $storage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LearningResource::class);

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'resource_type' => ['sometimes', 'string', Rule::in(LearningResource::RESOURCE_TYPES)],
            'course' => ['sometimes', 'nullable', 'string', 'max:255'],
            'level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $resources = LearningResource::query()
            ->with('createdBy')
            ->visibleTo($request->user())
            ->search($validated['search'] ?? null)
            ->when($validated['resource_type'] ?? null, fn ($query, $type) => $query->where('resource_type', $type))
            ->when($validated['course'] ?? null, fn ($query, $course) => $query->where('course', $course))
            ->when($validated['level'] ?? null, fn ($query, $level) => $query->where('level', $level))
            ->latest()
            ->paginate($validated['per_page'] ?? 15);

        return response()->json(
            $resources->through(fn (LearningResource $resource) => new LearningResourceResource($resource))
        );
    }

    public function storeFile(StoreFileResourceRequest $request): JsonResponse
    {
        Gate::authorize('create', LearningResource::class);

        $validated = $request->validated();
        $fileMetadata = $this->storage->store($validated['file']);

        unset($validated['file']);

        $resource = LearningResource::create([
            ...$validated,
            ...$fileMetadata,
            'url' => null,
            'preview_metadata' => $this->previewMetadata($fileMetadata),
            'visibility' => $validated['visibility'] ?? LearningResource::VISIBILITY_TEACHER_ONLY,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new LearningResourceResource($resource->load('createdBy')),
        ], 201);
    }

    public function storeLink(StoreLinkResourceRequest $request): JsonResponse
    {
        Gate::authorize('create', LearningResource::class);

        $validated = $request->validated();

        $resource = LearningResource::create([
            ...$validated,
            'storage_disk' => null,
            'file_path' => null,
            'original_filename' => null,
            'mime_type' => null,
            'file_size' => null,
            'preview_metadata' => null,
            'visibility' => $validated['visibility'] ?? LearningResource::VISIBILITY_TEACHER_ONLY,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new LearningResourceResource($resource->load('createdBy')),
        ], 201);
    }

    public function show(LearningResource $learningResource): JsonResponse
    {
        Gate::authorize('view', $learningResource);

        return response()->json([
            'data' => new LearningResourceResource($learningResource->load('createdBy')),
        ]);
    }

    public function update(UpdateLearningResourceRequest $request, LearningResource $learningResource): JsonResponse
    {
        Gate::authorize('update', $learningResource);

        $learningResource->update($request->validated());

        return response()->json([
            'data' => new LearningResourceResource($learningResource->refresh()->load('createdBy')),
        ]);
    }

    public function destroy(LearningResource $learningResource): JsonResponse
    {
        Gate::authorize('delete', $learningResource);

        $this->storage->delete($learningResource);
        $learningResource->delete();

        return response()->json(status: 204);
    }

    /**
     * @param  array{original_filename: string, mime_type: string|null, file_size: int}  $fileMetadata
     * @return array<string, mixed>
     */
    private function previewMetadata(array $fileMetadata): array
    {
        return [
            'filename' => $fileMetadata['original_filename'],
            'mime_type' => $fileMetadata['mime_type'],
            'size' => $fileMetadata['file_size'],
            'extension' => pathinfo($fileMetadata['original_filename'], PATHINFO_EXTENSION) ?: null,
        ];
    }
}
