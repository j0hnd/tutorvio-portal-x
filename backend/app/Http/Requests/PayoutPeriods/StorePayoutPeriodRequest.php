<?php

namespace App\Http\Requests\PayoutPeriods;

use App\Models\PayoutPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create payout period requests.
 *
 * Expected roles: Admin or staff users with payroll or payout-period management access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StorePayoutPeriodRequest extends FormRequest
{
    /**
     * Get validation rules for create payout period requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; date and time ordering rules keep ranges consistent; required rules define the minimum payload for creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'cutoff_date' => ['required', 'date', 'after_or_equal:end_date'],
            'payout_date' => ['required', 'date', 'after_or_equal:cutoff_date'],
            'status' => ['sometimes', 'string', Rule::in([PayoutPeriod::STATUS_DRAFT, PayoutPeriod::STATUS_OPEN])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
