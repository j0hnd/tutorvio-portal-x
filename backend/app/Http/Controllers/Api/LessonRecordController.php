<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LessonRecords\CancelLessonRecordRequest;
use App\Http\Requests\LessonRecords\StoreLessonRecordRequest;
use App\Http\Requests\LessonRecords\UpdateLessonRecordRequest;
use App\Http\Resources\LessonRecords\LessonRecordResource;
use App\Models\LessonRecord;
use App\Repositories\LessonRecordRepository;
use App\Services\LessonRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LessonRecordController extends Controller
{
    public function __construct(
        private readonly LessonRecordRepository $lessonRecords,
        private readonly LessonRecordService $lessonRecordService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LessonRecord::class);

        $validated = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'lesson_type' => ['sometimes', 'string', Rule::in(LessonRecord::LESSON_TYPES)],
            'lesson_status' => ['sometimes', 'string', Rule::in(LessonRecord::STATUSES)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'is_completed' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->lessonRecords
                ->paginateForUser($request->user(), $validated)
                ->through(fn (LessonRecord $lessonRecord) => new LessonRecordResource($lessonRecord))
        );
    }

    public function store(StoreLessonRecordRequest $request): JsonResponse
    {
        Gate::authorize('create', LessonRecord::class);

        return response()->json([
            'data' => new LessonRecordResource(
                $this->lessonRecordService->create($request->validated(), $request->user())
            ),
        ], 201);
    }

    public function show(LessonRecord $lessonRecord): JsonResponse
    {
        Gate::authorize('view', $lessonRecord);

        return response()->json([
            'data' => new LessonRecordResource($this->lessonRecords->findWithRelations($lessonRecord)),
        ]);
    }

    public function update(UpdateLessonRecordRequest $request, LessonRecord $lessonRecord): JsonResponse
    {
        Gate::authorize('update', $lessonRecord);

        return response()->json([
            'data' => new LessonRecordResource(
                $this->lessonRecordService->update($lessonRecord, $request->validated(), $request->user())
            ),
        ]);
    }

    public function destroy(LessonRecord $lessonRecord): JsonResponse
    {
        Gate::authorize('delete', $lessonRecord);

        $lessonRecord->delete();

        return response()->json(status: 204);
    }

    public function cancel(CancelLessonRecordRequest $request, LessonRecord $lessonRecord): JsonResponse
    {
        Gate::authorize('cancel', $lessonRecord);

        return response()->json([
            'data' => new LessonRecordResource(
                $this->lessonRecordService->cancel($lessonRecord, $request->user(), $request->validated('reason'))
            ),
        ]);
    }
}
