<?php

namespace App\Http\Requests\Reports;

use App\Models\StudentProgressRecord;
use Illuminate\Validation\Rule;

class StudentProgressReportRequest extends SchoolReportRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', 'string', Rule::in(StudentProgressRecord::STATUSES)],
        ];
    }
}
