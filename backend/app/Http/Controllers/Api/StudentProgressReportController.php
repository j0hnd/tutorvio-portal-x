<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StudentProgressReportRequest;
use App\Models\User;
use App\Reports\StudentProgressReport;
use App\Support\Reports\ReportResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class StudentProgressReportController extends Controller
{
    /**
     * Handle the student progress report endpoint.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $report.
     * The StudentProgressReportRequest handles authorization and validation before the controller action runs.
     * Returns a JSON response containing the requested data.
     */
    public function __invoke(StudentProgressReportRequest $request, StudentProgressReport $report): JsonResponse
    {
        $actor = $request->user();

        $this->authorizeReportAccess($actor);
        $this->authorizeRequestedStudent($actor, $request->validated('student_id'));

        return ReportResponse::json($report->generate($request->filters(), $actor), $request->pagination());
    }

    /**
     * Authorize access to the student progress report endpoint.
     *
     * Admins, teachers, and students are allowed. Staff need either
     * `school_reports.view` or `student_progress_records.view`. Users outside
     * those roles or staff without permission are denied.
     */
    private function authorizeReportAccess(User $actor): void
    {
        if ($actor->hasRole('admin')
            || ($actor->hasRole('staff') && ($actor->can('school_reports.view') || $actor->can('student_progress_records.view')))
            || $actor->hasAnyRole(['teacher', 'student'])) {
            return;
        }

        abort(403);
    }

    /**
     * Authorize the requested student filter for progress reports.
     *
     * Admins, staff, and teachers may request a specific student filter.
     * Students can request only their own user id. Non-student ids fail
     * validation, and students requesting another user are denied.
     */
    private function authorizeRequestedStudent(User $actor, mixed $studentId): void
    {
        if ($studentId === null || $actor->hasRole('admin') || $actor->hasRole('staff') || $actor->hasRole('teacher')) {
            return;
        }

        if (! $actor->hasRole('student') || (int) $studentId !== (int) $actor->id) {
            abort(403);
        }

        if (! User::query()->whereKey($studentId)->whereHas('roles', fn ($query) => $query->where('name', 'student'))->exists()) {
            throw ValidationException::withMessages([
                'student_id' => 'The selected user must be a student.',
            ]);
        }
    }
}
