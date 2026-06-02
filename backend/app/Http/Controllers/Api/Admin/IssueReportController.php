<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Http\Resources\IssueReports\IssueReportResource;
use App\Models\IssueComment;
use App\Models\IssueReport;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IssueReportController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * Display a filtered list of issue report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(IssueReport::STATUSES)],
            'issue_type' => ['sometimes', 'string', Rule::in(IssueReport::ISSUE_TYPES)],
            'type' => ['sometimes', 'string', Rule::in(IssueReport::ISSUE_TYPES)],
            'priority' => ['sometimes', 'string', Rule::in(IssueReport::PRIORITIES)],
            'reporter_id' => ['sometimes', 'integer', 'exists:users,id'],
            'assigned_to_id' => ['sometimes', 'integer', 'exists:users,id'],
            'related_student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'related_teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'lesson_id' => ['sometimes', 'integer', 'exists:lessons,id'],
            'class_schedule_id' => ['sometimes', 'integer', 'exists:class_schedules,id'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $issueType = $validated['issue_type'] ?? $validated['type'] ?? null;

        $issues = IssueReport::query()
            ->with(['reporter', 'assignedTo'])
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($issueType, fn (Builder $query, string $type) => $query->where('issue_type', $type))
            ->when($validated['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($validated['reporter_id'] ?? null, fn (Builder $query, int $id) => $query->where('reporter_id', $id))
            ->when($validated['assigned_to_id'] ?? null, fn (Builder $query, int $id) => $query->where('assigned_to_id', $id))
            ->when($validated['related_student_id'] ?? null, fn (Builder $query, int $id) => $query->where('related_student_id', $id))
            ->when($validated['related_teacher_id'] ?? null, fn (Builder $query, int $id) => $query->where('related_teacher_id', $id))
            ->when($validated['lesson_id'] ?? null, fn (Builder $query, int $id) => $query->where('lesson_id', $id))
            ->when($validated['class_schedule_id'] ?? null, fn (Builder $query, int $id) => $query->where('class_schedule_id', $id))
            ->when($validated['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($issues->through(fn (IssueReport $issueReport) => new IssueReportResource($issueReport)));
    }

    /**
     * Display the selected issue report record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $issueReport.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  IssueReport  $issueReport
     * @return JsonResponse
     */
    public function show(IssueReport $issueReport): JsonResponse
    {
        return response()->json([
            'data' => new IssueReportResource(
                $issueReport->load(['reporter', 'assignedTo', 'resolvedBy', 'comments.author'])
            ),
        ]);
    }

    /**
     * Handle the update status action for issue report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $issueReport.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  IssueReport  $issueReport
     * @return JsonResponse
     */
    public function updateStatus(Request $request, IssueReport $issueReport): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(IssueReport::STATUSES)],
            'note' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $oldStatus = $issueReport->status;

        DB::transaction(function () use ($request, $issueReport, $validated, $oldStatus) {
            $updates = ['status' => $validated['status']];

            if ($validated['status'] === IssueReport::STATUS_RESOLVED) {
                $updates['resolved_at'] = now();
                $updates['resolved_by'] = $request->user()->id;
            }

            if ($validated['status'] !== IssueReport::STATUS_RESOLVED) {
                $updates['resolved_at'] = null;
                $updates['resolved_by'] = null;
            }

            $issueReport->update($updates);

            $this->recordHistory(
                $issueReport,
                $request->user(),
                IssueComment::TYPE_STATUS_CHANGE,
                ($validated['note'] ?? null) ?: "Status changed from {$oldStatus} to {$validated['status']}."
            );

            $this->logIssueStatusChanged($issueReport, $request->user(), $oldStatus, $validated['status'], notePresent: filled($validated['note'] ?? null));
        });

        return $this->issueResponse($issueReport);
    }

    /**
     * Handle the assign action for issue report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $issueReport.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  IssueReport  $issueReport
     * @return JsonResponse
     */
    public function assign(Request $request, IssueReport $issueReport): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $assignee = User::query()->findOrFail($validated['assigned_to_id']);

        if (! $assignee->hasAnyRole(['admin', 'staff']) || $assignee->status !== User::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'assigned_to_id' => 'The selected assignee must be an active admin or staff user.',
            ]);
        }

        $oldAssigneeId = $issueReport->assigned_to_id;
        $oldStatus = $issueReport->status;

        DB::transaction(function () use ($request, $issueReport, $validated, $oldAssigneeId, $oldStatus) {
            $issueReport->update([
                'assigned_to_id' => $validated['assigned_to_id'],
                'status' => $issueReport->status === IssueReport::STATUS_OPEN
                    ? IssueReport::STATUS_IN_PROGRESS
                    : $issueReport->status,
            ]);

            $this->recordHistory(
                $issueReport,
                $request->user(),
                IssueComment::TYPE_STATUS_CHANGE,
                ($validated['note'] ?? null) ?: "Assignment changed from {$oldAssigneeId} to {$validated['assigned_to_id']}."
            );

            if ($oldAssigneeId !== (int) $validated['assigned_to_id'] || $oldStatus !== $issueReport->status) {
                $this->auditLogService->record(
                    actorUserId: $request->user()?->id,
                    actionType: AuditActionType::ISSUE_STATUS_CHANGED,
                    module: AuditModule::ISSUE_REPORTS,
                    targetEntityType: 'issue_report',
                    targetEntityId: $issueReport->id,
                    metadata: [
                        'issue_type' => $issueReport->issue_type,
                        'previous_assigned_to_id' => $oldAssigneeId,
                        'new_assigned_to_id' => (int) $validated['assigned_to_id'],
                        'previous_status' => $oldStatus,
                        'new_status' => $issueReport->status,
                        'changed_fields' => [
                            'assigned_to_id' => $oldAssigneeId !== (int) $validated['assigned_to_id'],
                            'status' => $oldStatus !== $issueReport->status,
                        ],
                        'note_present' => filled($validated['note'] ?? null),
                    ],
                );
            }
        });

        return $this->issueResponse($issueReport);
    }

    /**
     * Handle the add resolution note action for issue report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $issueReport.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  IssueReport  $issueReport
     * @return JsonResponse
     */
    public function addResolutionNote(Request $request, IssueReport $issueReport): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['required_without:note', 'string', 'max:20000'],
            'note' => ['required_without:resolution_notes', 'string', 'max:20000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $note = $validated['resolution_notes'] ?? $validated['note'];

        DB::transaction(function () use ($request, $issueReport, $validated, $note) {
            $issueReport->update([
                'resolution_notes' => $note,
            ]);

            $this->recordHistory(
                $issueReport,
                $request->user(),
                IssueComment::TYPE_RESOLUTION_NOTE,
                $note,
                $validated['is_internal'] ?? true
            );

            $this->logIssueResolutionUpdated($issueReport, $request->user(), notePresent: true);
        });

        return $this->issueResponse($issueReport);
    }

    /**
     * Handle the close action for issue report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $issueReport.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  IssueReport  $issueReport
     * @return JsonResponse
     */
    public function close(Request $request, IssueReport $issueReport): JsonResponse
    {
        return $this->finish($request, $issueReport, IssueReport::STATUS_CLOSED);
    }

    /**
     * Cancel the selected issue report record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $issueReport.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  Request  $request
     * @param  IssueReport  $issueReport
     * @return JsonResponse
     */
    public function cancel(Request $request, IssueReport $issueReport): JsonResponse
    {
        return $this->finish($request, $issueReport, IssueReport::STATUS_CANCELLED);
    }

    /**
     * Handle the finish action for issue report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $issueReport, $status.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  IssueReport  $issueReport
     * @param  string  $status
     * @return JsonResponse
     */
    private function finish(Request $request, IssueReport $issueReport, string $status): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'resolution_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ]);

        $oldStatus = $issueReport->status;

        DB::transaction(function () use ($request, $issueReport, $validated, $status, $oldStatus) {
            $issueReport->update([
                'status' => $status,
                'resolution_notes' => $validated['resolution_notes'] ?? $issueReport->resolution_notes,
                'resolved_at' => $issueReport->resolved_at ?? now(),
                'resolved_by' => $issueReport->resolved_by ?? $request->user()->id,
            ]);

            $this->recordHistory(
                $issueReport,
                $request->user(),
                IssueComment::TYPE_STATUS_CHANGE,
                ($validated['note'] ?? null) ?: "Status changed from {$oldStatus} to {$status}."
            );

            $this->logIssueStatusChanged($issueReport, $request->user(), $oldStatus, $status, notePresent: filled($validated['note'] ?? null));

            if (! empty($validated['resolution_notes'])) {
                $this->recordHistory(
                    $issueReport,
                    $request->user(),
                    IssueComment::TYPE_RESOLUTION_NOTE,
                    $validated['resolution_notes'],
                    true
                );
                $this->logIssueResolutionUpdated($issueReport, $request->user(), notePresent: true);
            }
        });

        return $this->issueResponse($issueReport);
    }

    /**
     * Handle the issue response action for issue report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $issueReport.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  IssueReport  $issueReport
     * @return JsonResponse
     */
    private function issueResponse(IssueReport $issueReport): JsonResponse
    {
        return response()->json([
            'data' => new IssueReportResource(
                $issueReport->refresh()->load(['reporter', 'assignedTo', 'resolvedBy', 'comments.author'])
            ),
        ]);
    }

    /**
     * Handle the record history action for issue report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $issueReport, $author, $type, $body, $isInternal.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  IssueReport  $issueReport
     * @param  ?User  $author
     * @param  string  $type
     * @param  string  $body
     * @param  bool  $isInternal
     * @return void
     */
    private function recordHistory(IssueReport $issueReport, ?User $author, string $type, string $body, bool $isInternal = true): void
    {
        $issueReport->comments()->create([
            'author_id' => $author?->id,
            'comment_type' => $type,
            'body' => $body,
            'is_internal' => $isInternal,
        ]);
    }

    /**
     * Handle the log issue status changed action for issue report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $issueReport, $actor, $previousStatus, $newStatus, $notePresent.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  IssueReport  $issueReport
     * @param  ?User  $actor
     * @param  string  $previousStatus
     * @param  string  $newStatus
     * @param  bool  $notePresent
     * @return void
     */
    private function logIssueStatusChanged(IssueReport $issueReport, ?User $actor, string $previousStatus, string $newStatus, bool $notePresent): void
    {
        if ($previousStatus === $newStatus) {
            return;
        }

        $this->auditLogService->record(
            actorUserId: $actor?->id,
            actionType: AuditActionType::ISSUE_STATUS_CHANGED,
            module: AuditModule::ISSUE_REPORTS,
            targetEntityType: 'issue_report',
            targetEntityId: $issueReport->id,
            metadata: [
                'issue_type' => $issueReport->issue_type,
                'reporter_id' => $issueReport->reporter_id,
                'assigned_to_id' => $issueReport->assigned_to_id,
                'related_student_id' => $issueReport->related_student_id,
                'related_teacher_id' => $issueReport->related_teacher_id,
                'lesson_id' => $issueReport->lesson_id,
                'class_schedule_id' => $issueReport->class_schedule_id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'note_present' => $notePresent,
            ],
        );
    }

    /**
     * Handle the log issue resolution updated action for issue report records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $issueReport, $actor, $notePresent.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  IssueReport  $issueReport
     * @param  ?User  $actor
     * @param  bool  $notePresent
     * @return void
     */
    private function logIssueResolutionUpdated(IssueReport $issueReport, ?User $actor, bool $notePresent): void
    {
        $this->auditLogService->record(
            actorUserId: $actor?->id,
            actionType: AuditActionType::ISSUE_RESOLUTION_UPDATED,
            module: AuditModule::ISSUE_REPORTS,
            targetEntityType: 'issue_report',
            targetEntityId: $issueReport->id,
            metadata: [
                'issue_type' => $issueReport->issue_type,
                'reporter_id' => $issueReport->reporter_id,
                'assigned_to_id' => $issueReport->assigned_to_id,
                'related_student_id' => $issueReport->related_student_id,
                'related_teacher_id' => $issueReport->related_teacher_id,
                'lesson_id' => $issueReport->lesson_id,
                'class_schedule_id' => $issueReport->class_schedule_id,
                'note_present' => $notePresent,
            ],
        );
    }
}
