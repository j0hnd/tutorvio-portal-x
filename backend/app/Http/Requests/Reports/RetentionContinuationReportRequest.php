<?php

namespace App\Http\Requests\Reports;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Validates retention continuation report requests.
 *
 * Expected roles: Admin or staff users with school report access unless the route narrows access further.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class RetentionContinuationReportRequest extends SchoolReportRequest
{
    /**
     * Get validation rules for retention continuation report requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants.
     *
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
