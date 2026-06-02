<?php

namespace App\Http\Requests\Reports;

use App\Models\User;
use Illuminate\Validation\Rule;

class RetentionContinuationReportRequest extends SchoolReportRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', 'string', Rule::in(User::STATUSES)],
        ];
    }
}
