<?php

namespace App\Http\Requests\Reports;

use App\Models\Lesson;
use Illuminate\Validation\Rule;

/**
 * Validates teacher note completion report requests.
 *
 * Expected roles: Admins, staff with school report access, or teachers with lesson note access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class TeacherNoteCompletionReportRequest extends SchoolReportRequest
{
    /**
     * Determine whether the authenticated user can view teacher note completion reporting. Allows admins, staff with school report access, and teachers with lesson note access.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasRole('admin')
            || ($user->hasRole('staff') && $user->can('school_reports.view'))
            || ($user->hasRole('teacher') && $user->can('lesson_notes.view'))
        );
    }

    /**
     * Get validation rules for teacher note completion report requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', 'string', Rule::in(Lesson::STATUSES)],
        ];
    }
}
