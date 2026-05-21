<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonJoinController extends Controller
{
    public function __invoke(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->userCanAccessMeeting($request->user()), 403);

        $canJoin = $lesson->userCanJoinMeeting($request->user());

        return response()->json([
            'data' => [
                'lesson_id' => $lesson->id,
                'status' => $lesson->status,
                'start_time' => $lesson->start_time,
                'end_time' => $lesson->end_time,
                'meeting_provider' => $lesson->meeting_provider,
                'is_join_available' => $canJoin,
                'join_starts_at' => $lesson->join_available_from ?? $lesson->start_time,
                'join_ends_at' => $lesson->join_available_until ?? $lesson->end_time,
                'meeting_link' => $canJoin ? $lesson->meeting_link : null,
            ],
        ]);
    }
}
