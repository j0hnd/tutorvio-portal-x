<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LearningResources\StoreFileResourceRequest;
use App\Http\Requests\LearningResources\StoreLinkResourceRequest;
use App\Http\Requests\LearningResources\UpdateLearningResourceRequest;
use App\Http\Resources\LearningResources\LearningResourceResource;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\User;
use App\Services\LearningResourceStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LearningResourceController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'created_at',
        'updated_at',
        'title',
        'resource_type',
        'course',
        'level',
        'visibility',
    ];

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
            'visibility' => ['sometimes', 'string', Rule::in(LearningResource::VISIBILITIES)],
            'assigned_student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'assigned_lesson_id' => ['sometimes', 'integer', 'exists:lessons,id'],
            'sort' => ['sometimes', 'string', Rule::in(self::SORTABLE_COLUMNS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $resources = LearningResource::query()
            ->with('createdBy')
            ->visibleTo($request->user())
            ->search($validated['search'] ?? null)
            ->when($validated['resource_type'] ?? null, fn ($query, $type) => $query->where('resource_type', $type))
            ->when($validated['course'] ?? null, fn ($query, $course) => $query->where('course', $course))
            ->when($validated['level'] ?? null, fn ($query, $level) => $query->where('level', $level))
            ->when($validated['visibility'] ?? null, fn ($query, $visibility) => $query->where('visibility', $visibility))
            ->when(
                $validated['assigned_student_id'] ?? null,
                fn (Builder $query, int $studentId) => $this->whereAssignedToStudentForUser($query, $studentId, $request->user())
            )
            ->when(
                $validated['assigned_lesson_id'] ?? null,
                fn (Builder $query, int $lessonId) => $this->whereAssignedToLessonForUser($query, $lessonId, $request->user())
            )
            ->orderBy($validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc')
            ->orderByDesc('id')
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

    public function assignStudent(Request $request, LearningResource $learningResource): JsonResponse
    {
        Gate::authorize('assign', $learningResource);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $student = User::findOrFail($validated['student_id']);
        $this->assertStudentUser($student);
        $this->assertStudentAssignmentDoesNotExist($learningResource, $student);

        $learningResource->assignedStudents()->attach($student->id, [
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        return response()->json([
            'data' => new LearningResourceResource(
                $student->assignedLearningResources()
                    ->whereKey($learningResource->id)
                    ->firstOrFail()
            ),
        ], 201);
    }

    public function assignLesson(Request $request, LearningResource $learningResource): JsonResponse
    {
        Gate::authorize('assign', $learningResource);

        $validated = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
        ]);

        $lesson = Lesson::findOrFail($validated['lesson_id']);
        $this->assertLessonAssignmentDoesNotExist($learningResource, $lesson);

        $learningResource->assignedLessons()->attach($lesson->id, [
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        return response()->json([
            'data' => new LearningResourceResource(
                $lesson->learningResources()
                    ->whereKey($learningResource->id)
                    ->firstOrFail()
            ),
        ], 201);
    }

    public function unassignStudent(LearningResource $learningResource, User $student): JsonResponse
    {
        Gate::authorize('assign', $learningResource);
        $this->assertStudentUser($student);

        $learningResource->assignedStudents()->detach($student->id);

        return response()->json(status: 204);
    }

    public function unassignLesson(LearningResource $learningResource, Lesson $lesson): JsonResponse
    {
        Gate::authorize('assign', $learningResource);

        $learningResource->assignedLessons()->detach($lesson->id);

        return response()->json(status: 204);
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

    private function assertStudentUser(User $student): void
    {
        if (! $student->hasRole('student')) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must be a student.',
            ]);
        }
    }

    private function assertStudentAssignmentDoesNotExist(LearningResource $learningResource, User $student): void
    {
        if ($learningResource->assignedStudents()->whereKey($student->id)->exists()) {
            throw ValidationException::withMessages([
                'student_id' => 'This resource is already assigned to the selected student.',
            ]);
        }
    }

    private function assertLessonAssignmentDoesNotExist(LearningResource $learningResource, Lesson $lesson): void
    {
        if ($learningResource->assignedLessons()->whereKey($lesson->id)->exists()) {
            throw ValidationException::withMessages([
                'lesson_id' => 'This resource is already assigned to the selected lesson.',
            ]);
        }
    }

    private function whereAssignedToStudentForUser(Builder $query, int $studentId, User $user): Builder
    {
        return $query->whereHas('assignedStudents', function (Builder $query) use ($studentId, $user) {
            $query->whereKey($studentId);

            if ($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff'])) {
                $query->whereKey($user->id);
            }

            if ($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff'])) {
                $query->whereHas('studentProfile', fn (Builder $query) => $query->where('assigned_teacher_id', $user->id));
            }
        });
    }

    private function whereAssignedToLessonForUser(Builder $query, int $lessonId, User $user): Builder
    {
        return $query->whereHas('assignedLessons', function (Builder $query) use ($lessonId, $user) {
            $query->whereKey($lessonId);

            if ($user->hasRole('student') && ! $user->hasAnyRole(['admin', 'staff'])) {
                $query->where('student_id', $user->id);
            }

            if ($user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'staff'])) {
                $query->where('teacher_id', $user->id);
            }
        });
    }
}
