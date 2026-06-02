<?php

namespace App\Http\Requests\Reports;

use App\Models\LessonRecord;
use Illuminate\Validation\Rule;

class LessonCompletionReportRequest extends SchoolReportRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', 'string', Rule::in(LessonRecord::STATUSES)],
        ];
    }
}
