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
    public function __invoke(StudentProgressReportRequest $request, StudentProgressReport $report): JsonResponse
    {
        $actor = $request->user();

        $this->authorizeReportAccess($actor);
        $this->authorizeRequestedStudent($actor, $request->validated('student_id'));

        return ReportResponse::json($report->generate($request->filters(), $actor), $request->pagination());
    }

    private function authorizeReportAccess(User $actor): void
    {
        if ($actor->hasRole('admin')
            || ($actor->hasRole('staff') && ($actor->can('school_reports.view') || $actor->can('student_progress_records.view')))
            || $actor->hasAnyRole(['teacher', 'student'])) {
            return;
        }

        abort(403);
    }

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
