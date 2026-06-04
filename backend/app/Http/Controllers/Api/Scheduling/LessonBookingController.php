<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Resources\Scheduling\ClassScheduleResource;
use App\Models\Scheduling\ClassSchedule;
use App\Models\User;
use App\Services\Scheduling\ClassScheduleService;
use App\Support\PublicIdResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LessonBookingController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     */
    public function __construct(private readonly ClassScheduleService $scheduleService) {}

    /**
     * Create a new lesson booking record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON payload with the created resource or action result.
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'teacher_id' => User::class,
        ]));

        $schedule = $this->scheduleService->bookOneTimeLesson($request->validate([
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in([
                ClassSchedule::STATUS_SCHEDULED,
                ClassSchedule::STATUS_PENDING_CONFIRMATION,
            ])],
            'class_type' => ['sometimes', 'string', Rule::in(ClassSchedule::CLASS_TYPES)],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'meeting_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]), $request->user());

        return response()->json([
            'data' => new ClassScheduleResource($schedule->load(['student:id,public_id,name,email,timezone', 'teacher:id,public_id,name,email,timezone'])),
        ], 201);
    }
}
