<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\LearningResources\StoreFileResourceRequest;
use App\Http\Requests\LearningResources\StoreLinkResourceRequest;
use App\Http\Requests\LearningResources\UpdateLearningResourceRequest;
use App\Http\Resources\LearningResources\LearningResourceResource;
use App\Models\LearningResource;
use App\Models\LearningResourceVersion;
use App\Models\Lesson;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\LearningResourceStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        private readonly AuditLogService $auditLogService,
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
            'current_version_number' => 1,
        ]);
        $this->createVersionSnapshot(
            $resource,
            $fileMetadata,
            uploadedBy: $request->user()->id,
            previousFilePath: null,
            changeNotes: null
        );
        $this->logFileUploaded($resource, $request->user()->id, isReplacement: false);

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
            'current_version_number' => null,
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

    public function download(LearningResource $learningResource): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $learningResource);

        if ($learningResource->isExternalLink()) {
            $this->logFileDownloaded($learningResource, request()->user()?->id, 'external_link');

            return response()->json([
                'data' => [
                    'type' => LearningResource::TYPE_LINK,
                    'url' => $learningResource->url,
                    'preview_metadata' => $learningResource->preview_metadata,
                ],
            ]);
        }

        if (! $learningResource->hasStoredFile()) {
            return response()->json([
                'message' => 'This resource does not have a downloadable file.',
            ], 404);
        }

        if (! $learningResource->storedFileExists()) {
            return response()->json([
                'message' => 'The resource file could not be found.',
            ], 404);
        }

        if ($this->shouldReturnTemporaryUrl($learningResource)) {
            $expiresAt = now()->addMinutes($this->temporaryUrlTtlMinutes());
            $temporaryUrl = Storage::disk($learningResource->storageDisk())->temporaryUrl(
                $learningResource->file_path,
                $expiresAt,
                $this->temporaryDownloadOptions($learningResource->original_filename)
            );
            $this->logFileDownloaded($learningResource, request()->user()?->id, 'temporary_url');

            return response()->json([
                'data' => [
                    'type' => LearningResource::TYPE_FILE,
                    'download_url' => $temporaryUrl,
                    'expires_at' => $expiresAt->toIso8601String(),
                    'preview_metadata' => $learningResource->preview_metadata,
                ],
            ])->header('Cache-Control', 'private, no-store, max-age=0');
        }

        $this->logFileDownloaded($learningResource, request()->user()?->id, 'stream');

        return Storage::disk($learningResource->storageDisk())->download(
            $learningResource->file_path,
            $learningResource->original_filename,
            [
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }

    public function update(UpdateLearningResourceRequest $request, LearningResource $learningResource): JsonResponse
    {
        Gate::authorize('update', $learningResource);

        $validated = $request->validated();
        $uploadedFile = $request->file('file');
        $changeNotes = $validated['change_notes'] ?? null;

        unset($validated['file'], $validated['change_notes']);

        if ($uploadedFile instanceof UploadedFile) {
            DB::transaction(function () use ($learningResource, $uploadedFile, $validated, $request, $changeNotes) {
                $this->ensureInitialVersionSnapshotExists($learningResource);

                $previousFilePath = $learningResource->file_path;
                $fileMetadata = $this->storage->store($uploadedFile);
                $latestVersionNumber = (int) $learningResource->versions()->max('version_number');
                $nextVersionNumber = max($learningResource->currentVersionNumber(), $latestVersionNumber) + 1;

                $learningResource->update([
                    ...$validated,
                    ...$fileMetadata,
                    'preview_metadata' => $this->previewMetadata($fileMetadata),
                    'current_version_number' => $nextVersionNumber,
                ]);

                $this->createVersionSnapshot(
                    $learningResource,
                    $fileMetadata,
                    uploadedBy: $request->user()->id,
                    previousFilePath: $previousFilePath,
                    changeNotes: $changeNotes
                );
                $this->logFileUploaded($learningResource->refresh(), $request->user()->id, isReplacement: true);
            });
        } else {
            $learningResource->update($validated);
        }

        return response()->json([
            'data' => new LearningResourceResource($learningResource->refresh()->load('createdBy')),
        ]);
    }

    public function versions(LearningResource $learningResource): JsonResponse
    {
        Gate::authorize('viewVersionHistory', $learningResource);

        $versions = $learningResource->versions()
            ->with('uploadedBy:id,public_id,name,email')
            ->get()
            ->map(fn (LearningResourceVersion $version) => [
                'version_number' => $version->version_number,
                'previous_file_reference' => $version->previous_file_path === null
                    ? null
                    : basename($version->previous_file_path),
                'original_filename' => $version->original_filename,
                'mime_type' => $version->mime_type,
                'file_size' => $version->file_size,
                'preview_metadata' => $version->preview_metadata,
                'change_notes' => $version->change_notes,
                'uploaded_at' => $version->uploaded_at,
                'uploaded_by' => $version->uploadedBy?->public_id,
                'uploaded_by_user' => $version->uploadedBy === null ? null : [
                    'id' => $version->uploadedBy->public_id,
                    'name' => $version->uploadedBy->name,
                    'email' => $version->uploadedBy->email,
                ],
            ])
            ->values();

        return response()->json([
            'data' => $versions,
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

        $this->deleteAllStoredFiles($learningResource);
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

    /**
     * @param  array{storage_disk: string, file_path: string, original_filename: string, mime_type: string|null, file_size: int}  $fileMetadata
     */
    private function createVersionSnapshot(
        LearningResource $learningResource,
        array $fileMetadata,
        int $uploadedBy,
        ?string $previousFilePath,
        ?string $changeNotes
    ): void {
        $learningResource->versions()->create([
            'version_number' => $learningResource->currentVersionNumber(),
            'storage_disk' => $fileMetadata['storage_disk'],
            'file_path' => $fileMetadata['file_path'],
            'previous_file_path' => $previousFilePath,
            'original_filename' => $fileMetadata['original_filename'],
            'mime_type' => $fileMetadata['mime_type'],
            'file_size' => $fileMetadata['file_size'],
            'preview_metadata' => $this->previewMetadata($fileMetadata),
            'change_notes' => $changeNotes,
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => now(),
        ]);
    }

    private function ensureInitialVersionSnapshotExists(LearningResource $learningResource): void
    {
        if (! $learningResource->hasStoredFile()) {
            return;
        }

        if ($learningResource->versions()->exists()) {
            return;
        }

        $learningResource->versions()->create([
            'version_number' => max(1, $learningResource->currentVersionNumber()),
            'storage_disk' => $learningResource->storage_disk,
            'file_path' => $learningResource->file_path,
            'previous_file_path' => null,
            'original_filename' => $learningResource->original_filename,
            'mime_type' => $learningResource->mime_type,
            'file_size' => $learningResource->file_size,
            'preview_metadata' => $learningResource->preview_metadata,
            'change_notes' => null,
            'uploaded_by' => $learningResource->created_by,
            'uploaded_at' => $learningResource->created_at ?? now(),
        ]);
    }

    private function deleteAllStoredFiles(LearningResource $learningResource): void
    {
        $paths = $learningResource->versions()
            ->get(['storage_disk', 'file_path'])
            ->map(fn (LearningResourceVersion $version) => [
                'storage_disk' => $version->storage_disk,
                'file_path' => $version->file_path,
            ])
            ->push([
                'storage_disk' => $learningResource->storage_disk,
                'file_path' => $learningResource->file_path,
            ])
            ->filter(fn (array $entry) => filled($entry['storage_disk']) && filled($entry['file_path']))
            ->unique(fn (array $entry) => $entry['storage_disk'].'|'.$entry['file_path']);

        foreach ($paths as $entry) {
            Storage::disk($entry['storage_disk'])->delete($entry['file_path']);
        }
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

    private function shouldReturnTemporaryUrl(LearningResource $learningResource): bool
    {
        $disk = $learningResource->storageDisk();
        $strategy = (string) config('learning_resources.download.strategy', 'auto');
        $driver = (string) config("filesystems.disks.{$disk}.driver", '');

        if (! Storage::disk($disk)->providesTemporaryUrls()) {
            return false;
        }

        return match ($strategy) {
            'temporary_url' => true,
            'stream' => false,
            default => ! in_array($driver, ['local'], true),
        };
    }

    /**
     * @return array<string, string>
     */
    private function temporaryDownloadOptions(?string $originalFilename): array
    {
        if (blank($originalFilename)) {
            return [];
        }

        $asciiFilename = Str::ascii($originalFilename);
        $asciiFilename = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '_', $asciiFilename) ?: 'download';
        $asciiFilename = trim($asciiFilename, " .\t\n\r\0\x0B") ?: 'download';

        return [
            'ResponseContentDisposition' => "attachment; filename=\"{$asciiFilename}\"",
        ];
    }

    private function temporaryUrlTtlMinutes(): int
    {
        return max(1, (int) config('learning_resources.download.temporary_url_ttl_minutes', 10));
    }

    private function logFileUploaded(LearningResource $learningResource, int $actorUserId, bool $isReplacement): void
    {
        $this->auditLogService->record(
            actorUserId: $actorUserId,
            actionType: AuditActionType::FILE_UPLOADED,
            module: AuditModule::LEARNING_RESOURCES,
            targetEntityType: 'learning_resource',
            targetEntityId: $learningResource->id,
            metadata: [
                'resource_type' => $learningResource->resource_type,
                'visibility' => $learningResource->visibility,
                'mime_type' => $learningResource->mime_type,
                'file_size' => $learningResource->file_size,
                'version_number' => $learningResource->currentVersionNumber(),
                'is_replacement' => $isReplacement,
            ],
        );
    }

    private function logFileDownloaded(LearningResource $learningResource, ?int $actorUserId, string $deliveryType): void
    {
        $this->auditLogService->record(
            actorUserId: $actorUserId,
            actionType: AuditActionType::FILE_DOWNLOADED,
            module: AuditModule::LEARNING_RESOURCES,
            targetEntityType: 'learning_resource',
            targetEntityId: $learningResource->id,
            metadata: [
                'resource_type' => $learningResource->resource_type,
                'visibility' => $learningResource->visibility,
                'mime_type' => $learningResource->mime_type,
                'file_size' => $learningResource->file_size,
                'delivery_type' => $deliveryType,
            ],
        );
    }
}
