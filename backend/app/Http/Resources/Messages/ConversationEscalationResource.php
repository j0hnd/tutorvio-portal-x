<?php

namespace App\Http\Resources\Messages;

use App\Http\Resources\Concerns\SanitizesApiResponses;
use App\Models\IssueReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationEscalationResource extends JsonResource
{
    use SanitizesApiResponses;

    /**
     * Transform a conversation escalation into a public-safe API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canReview = $this->canReviewEscalations($request);

        $data = [
            'id' => $this->publicId($this->resource),
            'conversation_id' => $this->whenLoaded('conversation', fn () => $this->publicId($this->resource->conversation)),
            'message_id' => $this->whenLoaded('message', fn () => $this->publicId($this->resource->message)),
            'issue_report_id' => $this->publicIdFor(IssueReport::class, $this->resource->issue_report_id),
            'status' => $this->resource->status,
            'reason' => $this->resource->reason,
            'notes' => $this->resource->notes,
            'escalated_by' => $this->publicIdFor(User::class, $this->resource->escalated_by),
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];

        if ($canReview) {
            $data += [
                'review_notes' => $this->resource->review_notes,
                'reviewed_by' => $this->publicIdFor(User::class, $this->resource->reviewed_by),
                'reviewed_at' => $this->resource->reviewed_at,
                'resolved_at' => $this->resource->resolved_at,
                'dismissed_at' => $this->resource->dismissed_at,
                'metadata' => $this->resource->metadata ?? [],
                'conversation' => $this->whenLoaded('conversation', fn () => new ConversationResource($this->resource->conversation)),
                'message' => $this->whenLoaded('message', fn () => new ConversationMessageResource($this->resource->message)),
                'escalated_by_user' => $this->whenLoaded('escalatedBy', fn () => $this->userSummary($this->resource->escalatedBy, $request)),
                'reviewed_by_user' => $this->whenLoaded('reviewedBy', fn () => $this->userSummary($this->resource->reviewedBy, $request)),
            ];
        }

        return $data;
    }

    private function canReviewEscalations(Request $request): bool
    {
        $user = $request->user();

        return $user?->hasRole('admin') === true
            || ($user?->hasRole('staff') === true && ($user->can('chat_escalations.view') || $user->can('chat_escalations.manage')));
    }
}
