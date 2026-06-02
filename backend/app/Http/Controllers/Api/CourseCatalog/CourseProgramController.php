<?php

namespace App\Http\Controllers\Api\CourseCatalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseCatalog\StoreCourseProgramRequest;
use App\Http\Requests\CourseCatalog\UpdateCourseProgramRequest;
use App\Http\Resources\CourseCatalog\CourseProgramResource;
use App\Models\CourseProgram;
use App\Models\LearningResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseProgramController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'title',
        'placement_level',
        'number_of_sessions',
        'created_at',
        'updated_at',
    ];

    /**
     * Display a filtered list of course program records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Notable request fields include include_archived, only_archived.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
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

        $canViewArchived = $this->canViewArchived($request);

        $programs = CourseProgram::query()
            ->when($canViewArchived && ($request->boolean('include_archived') || $request->boolean('only_archived')), fn (Builder $query) => $query->withArchived())
            ->when($canViewArchived && $request->boolean('only_archived'), fn (Builder $query) => $query->where('is_archived', true))
            ->visibleTo($request->user())
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search))
            ->when($validated['course_type_id'] ?? null, fn (Builder $query, int $courseTypeId) => $query->where('course_type_id', $courseTypeId))
            ->when($validated['placement_level'] ?? null, fn (Builder $query, string $level) => $query->where('placement_level', $level))
            ->with($this->relations($request))
            ->orderBy($validated['sort'] ?? 'title', $validated['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($programs->through(fn (CourseProgram $program) => new CourseProgramResource($program)));
    }

    /**
     * Create a new course program record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreCourseProgramRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  StoreCourseProgramRequest  $request
     * @return JsonResponse
     */
    public function store(StoreCourseProgramRequest $request): JsonResponse
    {
        Gate::authorize('create', CourseProgram::class);

        $validated = $request->validated();
        $resourceIds = $this->accessibleLearningResourceIds($validated['learning_resource_ids'] ?? [], $request);

        $program = CourseProgram::create([
            ...Arr::except($validated, ['learning_resource_ids', 'name']),
            'slug' => $this->uniqueSlug($validated['title']),
            'created_by' => $request->user()->id,
        ]);

        $this->syncLearningResources($program, $resourceIds, $request->user()->id);

        return response()->json([
            'data' => new CourseProgramResource($program->load($this->relations($request))),
        ], 201);
    }

    /**
     * Display the selected course program record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $courseProgram.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  CourseProgram  $courseProgram
     * @return JsonResponse
     */
    public function show(Request $request, CourseProgram $courseProgram): JsonResponse
    {
        Gate::authorize('view', $courseProgram);

        return response()->json([
            'data' => new CourseProgramResource($courseProgram->load($this->relations($request))),
        ]);
    }

    /**
     * Update the selected course program record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $courseProgram.
     * The UpdateCourseProgramRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  UpdateCourseProgramRequest  $request
     * @param  CourseProgram  $courseProgram
     * @return JsonResponse
     */
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
            $this->syncLearningResources(
                $courseProgram,
                $this->accessibleLearningResourceIds($validated['learning_resource_ids'], $request),
                $request->user()->id
            );
        }

        return response()->json([
            'data' => new CourseProgramResource($courseProgram->refresh()->load($this->relations($request))),
        ]);
    }

    /**
     * Handle the attach learning resources action for course program records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $courseProgram.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  CourseProgram  $courseProgram
     * @return JsonResponse
     */
    public function attachLearningResources(Request $request, CourseProgram $courseProgram): JsonResponse
    {
        Gate::authorize('update', $courseProgram);

        $validated = $request->validate([
            'learning_resource_ids' => ['required', 'array', 'min:1'],
            'learning_resource_ids.*' => ['integer', 'distinct', 'exists:learning_resources,id'],
        ]);

        $courseProgram->learningResources()->syncWithoutDetaching(
            collect($this->accessibleLearningResourceIds($validated['learning_resource_ids'], $request))
                ->mapWithKeys(fn (int $resourceId) => [$resourceId => [
                    'attached_by' => $request->user()->id,
                    'attached_at' => now(),
                ]])
                ->all()
        );

        return response()->json([
            'data' => new CourseProgramResource($courseProgram->refresh()->load($this->relations($request))),
        ]);
    }

    /**
     * Handle the detach learning resource action for course program records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $courseProgram, $learningResource.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  CourseProgram  $courseProgram
     * @param  LearningResource  $learningResource
     * @return JsonResponse
     */
    public function detachLearningResource(Request $request, CourseProgram $courseProgram, LearningResource $learningResource): JsonResponse
    {
        Gate::authorize('update', $courseProgram);

        $this->accessibleLearningResourceIds([$learningResource->id], $request);

        $courseProgram->learningResources()->detach($learningResource->id);

        return response()->json([
            'data' => new CourseProgramResource($courseProgram->refresh()->load($this->relations($request))),
        ]);
    }

    /**
     * Archive the selected course program record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $courseProgram.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  Request  $request
     * @param  CourseProgram  $courseProgram
     * @return JsonResponse
     */
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
            'data' => new CourseProgramResource($courseProgram->refresh()->load($this->relations($request))),
        ]);
    }

    /**
     * Delete the selected course program record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $courseProgram.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON confirmation after deletion.
     *
     * @param  Request  $request
     * @param  CourseProgram  $courseProgram
     * @return JsonResponse
     */
    public function destroy(Request $request, CourseProgram $courseProgram): JsonResponse
    {
        $this->archive($request, $courseProgram);

        return response()->json(status: 204);
    }

    /**
     * @param  array<int, int>  $resourceIds
     *
     * @param  CourseProgram  $program
     * @param  array  $resourceIds
     * @param  int  $userId
     * @return void
     */
    private function syncLearningResources(CourseProgram $program, array $resourceIds, int $userId): void
    {
        $program->learningResources()->syncWithPivotValues($resourceIds, [
            'attached_by' => $userId,
            'attached_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @param  Request  $request
     */
    private function relations(Request $request): array
    {
        return [
            'courseType',
            'learningResources' => fn ($query) => $query
                ->visibleTo($request->user())
                ->orderBy('learning_resources.id')
                ->with('createdBy'),
        ];
    }

    /**
     * @param  array<int, int>  $resourceIds
     * @return array<int, int>
     *
     * @param  array  $resourceIds
     * @param  Request  $request
     */
    private function accessibleLearningResourceIds(array $resourceIds, Request $request): array
    {
        $resourceIds = array_values(array_unique(array_map('intval', $resourceIds)));

        if ($resourceIds === []) {
            return [];
        }

        $accessibleIds = LearningResource::query()
            ->visibleTo($request->user())
            ->whereKey($resourceIds)
            ->pluck('id')
            ->all();

        if (count($accessibleIds) !== count($resourceIds)) {
            throw ValidationException::withMessages([
                'learning_resource_ids' => 'One or more selected learning resources are not available to attach.',
            ]);
        }

        return $accessibleIds;
    }

    /**
     * Handle the search action for course program records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $query, $term.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Builder  $query
     * @param  string  $term
     * @return Builder
     */
    private function search(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $query) use ($like) {
            $query->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('placement_level', 'like', $like);
        });
    }

    /**
     * Handle the can view archived action for course program records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return bool
     */
    private function canViewArchived(Request $request): bool
    {
        $user = $request->user();

        return $user?->hasRole('admin') === true
            || ($user?->hasRole('staff') === true && $user->can('course_programs.view'));
    }

    /**
     * Handle the unique slug action for course program records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $title, $ignore.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  string  $title
     * @param  ?CourseProgram  $ignore
     * @return string
     */
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
