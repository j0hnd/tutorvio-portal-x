<?php

namespace App\Http\Requests\Reports;

use App\Models\Subscription;
use Illuminate\Validation\Rule;

class PackageUsageReportRequest extends SchoolReportRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', 'string', Rule::in(Subscription::STATUSES)],
        ];
    }
}
