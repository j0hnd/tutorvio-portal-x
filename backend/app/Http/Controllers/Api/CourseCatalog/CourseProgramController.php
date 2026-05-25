<?php

namespace App\Http\Controllers\Api\CourseCatalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseCatalog\StoreCourseProgramRequest;
use App\Http\Requests\CourseCatalog\UpdateCourseProgramRequest;
use App\Http\Resources\CourseCatalog\CourseProgramResource;
use App\Models\CourseProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseProgramController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'title',
        'placement_level',
        'number_of_sessions',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CourseProgram::class);

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'course_type_id' => ['sometimes', 'integer', 'exists:course_types,id'],
            'placement_level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'include_archived' => ['sometimes', 'boolean'],
            'only_archived' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', Rule::in(self::SORTABLE_COLUMNS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $programs = CourseProgram::query()
            ->when($request->boolean('include_archived') || $request->boolean('only_archived'), fn (Builder $query) => $query->withArchived())
            ->when($request->boolean('only_archived'), fn (Builder $query) => $query->where('is_archived', true))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search))
            ->when($validated['course_type_id'] ?? null, fn (Builder $query, int $courseTypeId) => $query->where('course_type_id', $courseTypeId))
            ->when($validated['placement_level'] ?? null, fn (Builder $query, string $level) => $query->where('placement_level', $level))
            ->with(['courseType', 'learningResources.createdBy'])
            ->orderBy($validated['sort'] ?? 'title', $validated['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($programs->through(fn (CourseProgram $program) => new CourseProgramResource($program)));
    }

    public function store(StoreCourseProgramRequest $request): JsonResponse
    {
        Gate::authorize('create', CourseProgram::class);

        $validated = $request->validated();
        $resourceIds = $validated['learning_resource_ids'] ?? [];

        $program = CourseProgram::create([
            ...Arr::except($validated, ['learning_resource_ids', 'name']),
            'slug' => $this->uniqueSlug($validated['title']),
            'created_by' => $request->user()->id,
        ]);

        $this->syncLearningResources($program, $resourceIds, $request->user()->id);

        return response()->json([
            'data' => new CourseProgramResource($program->load(['courseType', 'learningResources.createdBy'])),
        ], 201);
    }

    public function show(CourseProgram $courseProgram): JsonResponse
    {
        Gate::authorize('view', $courseProgram);

        return response()->json([
            'data' => new CourseProgramResource($courseProgram->load(['courseType', 'learningResources.createdBy'])),
        ]);
    }

    public function update(UpdateCourseProgramRequest $request, CourseProgram $courseProgram): JsonResponse
    {
        Gate::authorize('update', $courseProgram);

        $validated = $request->validated();

        if (array_key_exists('title', $validated) && $validated['title'] !== $courseProgram->title) {
            $validated['slug'] = $this->uniqueSlug($validated['title'], $courseProgram);
        }

        $courseProgram->update([
            ...Arr::except($validated, ['learning_resource_ids', 'name']),
            'updated_by' => $request->user()->id,
        ]);

        if (array_key_exists('learning_resource_ids', $validated)) {
            $this->syncLearningResources($courseProgram, $validated['learning_resource_ids'], $request->user()->id);
        }

        return response()->json([
            'data' => new CourseProgramResource($courseProgram->refresh()->load(['courseType', 'learningResources.createdBy'])),
        ]);
    }

    public function archive(Request $request, CourseProgram $courseProgram): JsonResponse
    {
        Gate::authorize('delete', $courseProgram);

        $courseProgram->update([
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new CourseProgramResource($courseProgram->refresh()->load(['courseType', 'learningResources.createdBy'])),
        ]);
    }

    public function destroy(Request $request, CourseProgram $courseProgram): JsonResponse
    {
        $this->archive($request, $courseProgram);

        return response()->json(status: 204);
    }

    /**
     * @param  array<int, int>  $resourceIds
     */
    private function syncLearningResources(CourseProgram $program, array $resourceIds, int $userId): void
    {
        $program->learningResources()->syncWithPivotValues($resourceIds, [
            'attached_by' => $userId,
            'attached_at' => now(),
        ]);
    }

    private function search(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('placement_level', 'like', $like);
        });
    }

    private function uniqueSlug(string $title, ?CourseProgram $ignore = null): string
    {
        $base = Str::slug($title) ?: 'course-program';
        $slug = $base;
        $counter = 2;

        while (CourseProgram::withArchived()
            ->where('slug', $slug)
            ->when($ignore !== null, fn (Builder $query) => $query->whereKeyNot($ignore->id))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
