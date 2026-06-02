<?php

namespace App\Http\Requests\Reports;

use App\Models\Scheduling\ClassSchedule;
use App\Reports\TrialEnrollmentReport;
use Illuminate\Validation\Rule;

class TrialEnrollmentReportRequest extends SchoolReportRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', 'string', Rule::in([
                ...ClassSchedule::STATUSES,
                ...TrialEnrollmentReport::ENROLLMENT_STATUSES,
            ])],
        ];
    }
}
