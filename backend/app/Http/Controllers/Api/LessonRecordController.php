<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LessonRecords\CancelLessonRecordRequest;
use App\Http\Requests\LessonRecords\StoreLessonRecordRequest;
use App\Http\Requests\LessonRecords\UpdateLessonRecordRequest;
use App\Http\Resources\LessonRecords\LessonRecordResource;
use App\Models\LessonRecord;
use App\Models\User;
use App\Repositories\LessonRecordRepository;
use App\Services\LessonRecordService;
use App\Support\PublicIdResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LessonRecordController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     */
    public function __construct(
        private readonly LessonRecordRepository $lessonRecords,
        private readonly LessonRecordService $lessonRecordService,
    ) {}

    /**
     * Display a filtered list of lesson records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LessonRecord::class);
        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'student_id' => User::class,
            'teacher_id' => User::class,
        ]));

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

    /**
     * Create a new lesson record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreLessonRecordRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     */
    public function store(StoreLessonRecordRequest $request): JsonResponse
    {
        Gate::authorize('create', LessonRecord::class);

        return response()->json([
            'data' => new LessonRecordResource(
                $this->lessonRecordService->create($request->validated(), $request->user())
            ),
        ], 201);
    }

    /**
     * Display the selected lesson record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $lessonRecord.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function show(LessonRecord $lessonRecord): JsonResponse
    {
        Gate::authorize('view', $lessonRecord);

        return response()->json([
            'data' => new LessonRecordResource($this->lessonRecords->findWithRelations($lessonRecord)),
        ]);
    }

    /**
     * Update the selected lesson record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $lessonRecord.
     * The UpdateLessonRecordRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function update(UpdateLessonRecordRequest $request, LessonRecord $lessonRecord): JsonResponse
    {
        Gate::authorize('update', $lessonRecord);

        return response()->json([
            'data' => new LessonRecordResource(
                $this->lessonRecordService->update($lessonRecord, $request->validated(), $request->user())
            ),
        ]);
    }

    /**
     * Delete the selected lesson record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $lessonRecord.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON confirmation after deletion.
     */
    public function destroy(LessonRecord $lessonRecord): JsonResponse
    {
        Gate::authorize('delete', $lessonRecord);

        $lessonRecord->delete();

        return response()->json(status: 204);
    }

    /**
     * Cancel the selected lesson record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $lessonRecord.
     * The CancelLessonRecordRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
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
