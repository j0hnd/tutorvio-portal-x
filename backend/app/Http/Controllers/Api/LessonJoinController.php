<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonJoinAccessLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonJoinController extends Controller
{
    /**
     * Handle the lesson join endpoint.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $lesson.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  Lesson  $lesson
     * @return JsonResponse
     */
    public function __invoke(Request $request, Lesson $lesson): JsonResponse
    {
        $user = $request->user();

        if (! $lesson->userCanAccessMeeting($user)) {
            $this->logAccessAttempt($request, $lesson, $user, LessonJoinAccessLog::RESULT_DENIED, 'unauthorized');

            return response()->json([
                'message' => 'Unauthorized.',
                'reason' => 'unauthorized',
            ], 403);
        }

        $availability = $lesson->joinAvailability();
        $canJoin = $availability['can_join'];
        $reason = $availability['reason'];
        $accessResult = $canJoin
            ? LessonJoinAccessLog::RESULT_ALLOWED
            : $this->accessResultForReason($reason);

        $this->logAccessAttempt($request, $lesson, $user, $accessResult, $reason);

        $data = [
            'lesson_id' => $lesson->public_id,
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
                        'id' => $replacementLesson->public_id,
                    ];
                }
            }
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Handle the log access attempt action for lesson join records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $lesson, $user, $accessResult, $reason.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  Lesson  $lesson
     * @param  User  $user
     * @param  string  $accessResult
     * @param  ?string  $reason
     * @return void
     */
    private function logAccessAttempt(Request $request, Lesson $lesson, User $user, string $accessResult, ?string $reason): void
    {
        LessonJoinAccessLog::create([
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
            'user_role' => $user->getRoleNames()->implode(',') ?: null,
            'access_result' => $accessResult,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accessed_at' => now(),
        ]);
    }

    /**
     * Handle the access result for reason action for lesson join records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $reason.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  ?string  $reason
     * @return string
     */
    private function accessResultForReason(?string $reason): string
    {
        return match ($reason) {
            'not_yet_available' => LessonJoinAccessLog::RESULT_NOT_YET_AVAILABLE,
            'lesson_expired' => LessonJoinAccessLog::RESULT_EXPIRED,
            'lesson_cancelled' => LessonJoinAccessLog::RESULT_CANCELLED,
            'lesson_rescheduled' => LessonJoinAccessLog::RESULT_RESCHEDULED,
            default => LessonJoinAccessLog::RESULT_DENIED,
        };
    }

    /**
     * Handle the timestamp action for lesson join records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $date.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  ?CarbonInterface  $date
     * @return ?string
     */
    private function timestamp(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
    }
}
