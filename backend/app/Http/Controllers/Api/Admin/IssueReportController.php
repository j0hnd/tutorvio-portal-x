<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\IssueReports\IssueReportResource;
use App\Models\IssueComment;
use App\Models\IssueReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IssueReportController extends Controller
{
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

    public function show(IssueReport $issueReport): JsonResponse
    {
        return response()->json([
            'data' => new IssueReportResource(
                $issueReport->load(['reporter', 'assignedTo', 'resolvedBy', 'comments.author'])
            ),
        ]);
    }

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
        });

        return $this->issueResponse($issueReport);
    }

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

        DB::transaction(function () use ($request, $issueReport, $validated, $oldAssigneeId) {
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
        });

        return $this->issueResponse($issueReport);
    }

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
        });

        return $this->issueResponse($issueReport);
    }

    public function close(Request $request, IssueReport $issueReport): JsonResponse
    {
        return $this->finish($request, $issueReport, IssueReport::STATUS_CLOSED);
    }

    public function cancel(Request $request, IssueReport $issueReport): JsonResponse
    {
        return $this->finish($request, $issueReport, IssueReport::STATUS_CANCELLED);
    }

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

            if (! empty($validated['resolution_notes'])) {
                $this->recordHistory(
                    $issueReport,
                    $request->user(),
                    IssueComment::TYPE_RESOLUTION_NOTE,
                    $validated['resolution_notes'],
                    true
                );
            }
        });

        return $this->issueResponse($issueReport);
    }

    private function issueResponse(IssueReport $issueReport): JsonResponse
    {
        return response()->json([
            'data' => new IssueReportResource(
                $issueReport->refresh()->load(['reporter', 'assignedTo', 'resolvedBy', 'comments.author'])
            ),
        ]);
    }

    private function recordHistory(IssueReport $issueReport, ?User $author, string $type, string $body, bool $isInternal = true): void
    {
        $issueReport->comments()->create([
            'author_id' => $author?->id,
            'comment_type' => $type,
            'body' => $body,
            'is_internal' => $isInternal,
        ]);
    }
}
