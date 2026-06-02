<?php

namespace App\Http\Requests\Reports;

use App\Models\Lesson;
use Illuminate\Validation\Rule;

class TeacherNoteCompletionReportRequest extends SchoolReportRequest
{
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
