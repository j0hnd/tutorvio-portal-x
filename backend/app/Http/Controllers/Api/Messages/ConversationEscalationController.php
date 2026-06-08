<?php

namespace App\Http\Controllers\Api\Messages;

use App\Http\Controllers\Controller;
use App\Http\Resources\Messages\ConversationEscalationResource;
use App\Models\Conversation;
use App\Models\ConversationEscalation;
use App\Models\ConversationMessage;
use App\Models\IssueReport;
use App\Models\User;
use App\Support\PublicIdResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversationEscalationController extends Controller
{
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->visibleConversationFor($actor, $conversation);
        $this->assertCanEscalate($actor, $conversation);

        $validated = $this->validatedEscalationPayload($request);

        $escalation = $conversation->escalations()->create([
            'escalated_by' => $actor->id,
            'issue_report_id' => $validated['issue_report_id'] ?? null,
            'status' => ConversationEscalation::STATUS_OPEN,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'data' => new ConversationEscalationResource($escalation->load($this->resourceRelations())),
        ], 201);
    }

    public function storeMessage(Request $request, Conversation $conversation, ConversationMessage $message): JsonResponse
    {
        $actor = $request->user();
        $conversation = $this->visibleConversationFor($actor, $conversation);
        $message = $this->visibleMessageFor($conversation, $message);
        $this->assertCanEscalate($actor, $conversation);

        $validated = $this->validatedEscalationPayload($request);

        $escalation = $conversation->escalations()->create([
            'conversation_message_id' => $message->id,
            'escalated_by' => $actor->id,
            'issue_report_id' => $validated['issue_report_id'] ?? null,
            'status' => ConversationEscalation::STATUS_OPEN,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'data' => new ConversationEscalationResource($escalation->load($this->resourceRelations())),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertCanViewQueue($request->user());

        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(ConversationEscalation::STATUSES)],
            'conversation_id' => ['sometimes', 'string'],
            'issue_report_id' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $conversationId = isset($validated['conversation_id'])
            ? PublicIdResolver::toKey($validated['conversation_id'], Conversation::class)
            : null;
        $issueReportId = isset($validated['issue_report_id'])
            ? PublicIdResolver::toKey($validated['issue_report_id'], IssueReport::class)
            : null;

        return response()->json(
            ConversationEscalation::query()
                ->with($this->resourceRelations(includeAdminContext: true))
                ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($conversationId !== null, fn (Builder $query) => $query->where('conversation_id', $conversationId))
                ->when($issueReportId !== null, fn (Builder $query) => $query->where('issue_report_id', $issueReportId))
                ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_review' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
                ->latest('created_at')
                ->latest('id')
                ->paginate($validated['per_page'] ?? 25)
                ->through(fn (ConversationEscalation $escalation) => new ConversationEscalationResource($escalation))
        );
    }

    public function show(Request $request, ConversationEscalation $conversationEscalation): JsonResponse
    {
        $this->assertCanViewQueue($request->user());

        return response()->json([
            'data' => new ConversationEscalationResource($conversationEscalation->load($this->resourceRelations(includeAdminContext: true))),
        ]);
    }

    public function updateStatus(Request $request, ConversationEscalation $conversationEscalation): JsonResponse
    {
        $actor = $request->user();
        $this->assertCanManageQueue($actor);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(ConversationEscalation::STATUSES)],
            'review_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'issue_report_id' => ['sometimes', 'nullable', 'string'],
        ]);

        if (array_key_exists('issue_report_id', $validated) && $validated['issue_report_id'] !== null) {
            $validated['issue_report_id'] = PublicIdResolver::toKey($validated['issue_report_id'], IssueReport::class);

            validator($validated, [
                'issue_report_id' => ['nullable', 'integer', 'exists:issue_reports,id'],
            ])->validate();
        }

        $now = now();
        $conversationEscalation->forceFill([
            'status' => $validated['status'],
            'reviewed_by' => $actor->id,
            'reviewed_at' => $now,
            'review_notes' => $validated['review_notes'] ?? $conversationEscalation->review_notes,
            'issue_report_id' => array_key_exists('issue_report_id', $validated)
                ? $validated['issue_report_id']
                : $conversationEscalation->issue_report_id,
            'resolved_at' => $validated['status'] === ConversationEscalation::STATUS_RESOLVED ? $now : null,
            'dismissed_at' => $validated['status'] === ConversationEscalation::STATUS_DISMISSED ? $now : null,
        ])->save();

        return response()->json([
            'data' => new ConversationEscalationResource($conversationEscalation->load($this->resourceRelations(includeAdminContext: true))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedEscalationPayload(Request $request): array
    {
        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'issue_report_id' => IssueReport::class,
        ]));

        return $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'issue_report_id' => ['sometimes', 'nullable', 'integer', 'exists:issue_reports,id'],
            'metadata' => ['sometimes', 'array'],
        ]);
    }

    private function visibleConversationFor(User $user, Conversation $conversation): Conversation
    {
        return Conversation::query()
            ->whereKey($conversation->id)
            ->when(! $user->can('viewAll', Conversation::class), function (Builder $query) use ($user): void {
                $query->whereHas('participants', function (Builder $query) use ($user): void {
                    $query
                        ->where('user_id', $user->id)
                        ->whereNull('archived_at')
                        ->whereNull('deleted_at');
                });
            })
            ->firstOrFail();
    }

    private function visibleMessageFor(Conversation $conversation, ConversationMessage $message): ConversationMessage
    {
        if ((int) $message->conversation_id !== (int) $conversation->id) {
            abort(404);
        }

        return $message;
    }

    private function assertCanEscalate(User $actor, Conversation $conversation): void
    {
        if (! $actor->can('escalate', $conversation)) {
            abort(403);
        }
    }

    private function assertCanViewQueue(User $actor): void
    {
        if (! $actor->can('viewEscalationQueue', Conversation::class)) {
            abort(403);
        }
    }

    private function assertCanManageQueue(User $actor): void
    {
        if (! $actor->can('manageEscalationQueue', Conversation::class)) {
            abort(403);
        }
    }

    /**
     * @return array<int, string>
     */
    private function resourceRelations(bool $includeAdminContext = false): array
    {
        $relations = [
            'conversation:id,public_id,type,title,status,student_id,teacher_id,course_program_id,created_by,last_message_at,last_message_by,last_message_preview,last_message_metadata,metadata,created_at,updated_at',
            'message:id,public_id,conversation_id,sender_id,body,status,metadata,created_at,updated_at,deleted_at',
        ];

        if ($includeAdminContext) {
            $relations[] = 'conversation.student:id,public_id,name,email';
            $relations[] = 'conversation.teacher:id,public_id,name,email';
            $relations[] = 'conversation.participants.user:id,public_id,name,email';
            $relations[] = 'conversation.participants.lastReadMessage:id,public_id';
            $relations[] = 'message.sender:id,public_id,name,email';
            $relations[] = 'message.attachmentRecords';
            $relations[] = 'escalatedBy:id,public_id,name,email';
            $relations[] = 'reviewedBy:id,public_id,name,email';
        }

        return $relations;
    }
}
