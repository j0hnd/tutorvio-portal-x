<?php

namespace App\Http\Resources\TeacherEarnings;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\CourseProgram;
use App\Models\CourseType;
use App\Models\LessonRecord;
use App\Models\TeacherEarning;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TeacherEarning
 */
class TeacherEarningResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a teacher earning into a payroll API response.
     *
     * Public-safe payroll fields expose teacher, source, lesson, course, rate,
     * quantity, amount, currency, status, and payout-period context using public
     * IDs. Source database IDs are converted before leaving the API boundary.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->resource->calculation_metadata)
            ? $this->resource->calculation_metadata
            : [];

        return [
            'teacher_id' => $this->publicIdFor(User::class, $this->resource->teacher_id),
            'teacher_name' => $this->resource->teacher?->name,
            'earning_source' => [
                'type' => $this->resource->source_type,
                'id' => $this->sourcePublicId(),
            ],
            'lesson_reference' => $this->whenLoaded('lessonRecord', fn () => $this->resource->lessonRecord ? [
                'id' => $this->publicId($this->resource->lessonRecord),
                'scheduled_date' => $this->resource->lessonRecord->scheduled_date?->toDateString(),
                'lesson_type' => $this->resource->lessonRecord->lesson_type,
                'lesson_status' => $this->resource->lessonRecord->lesson_status,
            ] : null),
            'course_reference' => isset($metadata['course_program_id']) ? [
                'id' => $this->publicIdFor(CourseProgram::class, $metadata['course_program_id']),
                'course_type_id' => $this->publicIdFor(CourseType::class, $metadata['course_type_id'] ?? null),
            ] : null,
            'pay_model' => $this->resource->pay_model,
            'rate_used' => $this->resource->rate_used,
            'quantity' => $this->resource->quantity,
            'amount' => $this->resource->amount,
            'currency' => $this->resource->currency,
            'status' => $this->resource->status,
            'earning_date' => $this->earningDate($metadata),
            'payout_period' => $metadata['payout_period'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function earningDate(array $metadata): ?string
    {
        if (is_string($metadata['scheduled_date'] ?? null)) {
            return $metadata['scheduled_date'];
        }

        if ($this->resource->relationLoaded('lessonRecord') && $this->resource->lessonRecord?->scheduled_date) {
            return $this->resource->lessonRecord->scheduled_date->toDateString();
        }

        return $this->resource->created_at?->toDateString();
    }

    private function sourcePublicId(): mixed
    {
        if ($this->resource->source_type === TeacherEarning::SOURCE_LESSON_RECORD) {
            return $this->publicIdFor(LessonRecord::class, $this->resource->source_id);
        }

        return $this->resource->source_id;
    }
}
