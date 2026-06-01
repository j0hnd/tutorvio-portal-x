<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Resources\Scheduling\ClassScheduleResource;
use App\Models\Scheduling\ClassSchedule;
use App\Services\Scheduling\ClassScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LessonBookingController extends Controller
{
    public function __construct(private readonly ClassScheduleService $scheduleService) {}

    public function store(Request $request): JsonResponse
    {
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
