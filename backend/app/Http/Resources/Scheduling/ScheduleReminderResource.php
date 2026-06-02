<?php

namespace App\Http\Resources\Scheduling;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\Scheduling\ClassSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleReminderResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a schedule reminder into a role-aware API response.
     *
     * Public fields expose reminder timing, channel, status, and related schedule
     * or recipient references using public IDs. Metadata is admin-only, so private
     * reminder internals are not exposed to regular viewers.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId($this->resource),
            'class_schedule_id' => $this->publicIdFor(ClassSchedule::class, $this->resource->class_schedule_id),
            'user_id' => $this->whenLoaded('user', fn () => $this->publicId($this->resource->user)),
            'channel' => $this->resource->channel,
            'status' => $this->resource->status,
            'scheduled_for' => $this->resource->scheduled_for,
            'sent_at' => $this->resource->sent_at,
            'timezone' => $this->resource->timezone,
            'metadata' => $this->when($this->canViewAdminFields($request), $this->resource->metadata ?? []),
            'class_schedule' => $this->whenLoaded('classSchedule', fn () => [
                'id' => $this->publicId($this->resource->classSchedule),
                'title' => $this->resource->classSchedule?->title,
                'starts_at' => $this->resource->classSchedule?->starts_at,
                'ends_at' => $this->resource->classSchedule?->ends_at,
                'status' => $this->resource->classSchedule?->status,
            ]),
            'user' => $this->whenLoaded('user', fn () => $this->userSummary($this->resource->user, $request)),
        ];
    }
}
