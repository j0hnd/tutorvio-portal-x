<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonJoinController extends Controller
{
    public function __invoke(Request $request, Lesson $lesson): JsonResponse
    {
        if (! $lesson->userCanAccessMeeting($request->user())) {
            return response()->json([
                'message' => 'Unauthorized.',
                'reason' => 'unauthorized',
            ], 403);
        }

        $availability = $lesson->joinAvailability();
        $canJoin = $availability['can_join'];
        $reason = $availability['reason'];

        $data = [
            'lesson_id' => $lesson->id,
            'status' => $lesson->status,
            'can_join' => $canJoin,
            'available_from' => $this->timestamp($availability['available_from']),
            'available_until' => $this->timestamp($availability['available_until']),
            'starts_at' => $this->timestamp($availability['starts_at']),
            'ends_at' => $this->timestamp($availability['ends_at']),
            'seconds_until_available' => $availability['seconds_until_available'],
            'meeting_provider' => $lesson->meeting_provider,
            'meeting_link' => $canJoin ? $lesson->meeting_link : null,
            'start_time' => $lesson->start_time,
            'end_time' => $lesson->end_time,
            'is_join_available' => $canJoin,
            'join_starts_at' => $availability['available_from'],
            'join_ends_at' => $availability['available_until'],
        ];

        if (! $canJoin) {
            $data['reason'] = $reason;

            if ($reason === 'lesson_rescheduled') {
                $data['message'] = 'This lesson has been rescheduled.';

                if ($replacementLesson = $lesson->replacementLesson()->first()) {
                    $data['replacement_lesson'] = [
                        'id' => $replacementLesson->id,
                    ];
                }
            }
        }

        return response()->json(['data' => $data]);
    }

    private function timestamp(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
    }
}
