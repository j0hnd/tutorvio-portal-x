<?php

namespace App\Http\Controllers\Api\CourseCatalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseCatalog\StoreCourseTypeRequest;
use App\Http\Requests\CourseCatalog\UpdateCourseTypeRequest;
use App\Http\Resources\CourseCatalog\CourseTypeResource;
use App\Models\CourseProgram;
use App\Models\CourseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseTypeController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'name',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CourseType::class);

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'include_archived' => ['sometimes', 'boolean'],
            'only_archived' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', Rule::in(self::SORTABLE_COLUMNS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $types = CourseType::query()
            ->when($request->boolean('include_archived') || $request->boolean('only_archived'), fn (Builder $query) => $query->withArchived())
            ->when($request->boolean('only_archived'), fn (Builder $query) => $query->where('is_archived', true))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search))
            ->withCount('programs')
            ->orderBy($validated['sort'] ?? 'sort_order', $validated['direction'] ?? 'asc')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($types->through(fn (CourseType $courseType) => new CourseTypeResource($courseType)));
    }

    public function store(StoreCourseTypeRequest $request): JsonResponse
    {
        Gate::authorize('create', CourseType::class);

        $validated = $request->validated();
        $courseType = CourseType::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name']),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new CourseTypeResource($courseType),
        ], 201);
    }

    public function show(CourseType $courseType): JsonResponse
    {
        Gate::authorize('view', $courseType);

        return response()->json([
            'data' => new CourseTypeResource($courseType->loadCount('programs')),
        ]);
    }

    public function update(UpdateCourseTypeRequest $request, CourseType $courseType): JsonResponse
    {
        Gate::authorize('update', $courseType);

        $validated = $request->validated();

        if (array_key_exists('name', $validated) && $validated['name'] !== $courseType->name) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $courseType);
        }

        $courseType->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new CourseTypeResource($courseType->refresh()->loadCount('programs')),
        ]);
    }

    public function archive(Request $request, CourseType $courseType): JsonResponse
    {
        Gate::authorize('delete', $courseType);

        DB::transaction(function () use ($request, $courseType) {
            $courseType->update([
                'is_archived' => true,
                'archived_at' => now(),
                'archived_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            CourseProgram::query()
                ->where('course_type_id', $courseType->id)
                ->update([
                    'is_archived' => true,
                    'archived_at' => now(),
                    'archived_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
        });

        return response()->json([
            'data' => new CourseTypeResource($courseType->refresh()->loadCount('programs')),
        ]);
    }

    public function destroy(Request $request, CourseType $courseType): JsonResponse
    {
        $this->archive($request, $courseType);

        return response()->json(status: 204);
    }

    private function search(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('name', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    private function uniqueSlug(string $name, ?CourseType $ignore = null): string
    {
        $base = Str::slug($name) ?: 'course-type';
        $slug = $base;
        $counter = 2;

        while (CourseType::withArchived()
            ->where('slug', $slug)
            ->when($ignore !== null, fn (Builder $query) => $query->whereKeyNot($ignore->id))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
