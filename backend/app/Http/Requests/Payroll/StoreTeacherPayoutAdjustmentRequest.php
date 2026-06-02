<?php

namespace App\Http\Requests\Payroll;

use App\Models\TeacherPayoutAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates create teacher payout adjustment requests.
 *
 * Expected roles: Admin or staff users with payroll or payout-period management access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StoreTeacherPayoutAdjustmentRequest extends FormRequest
{
    /**
     * Get validation rules for create teacher payout adjustment requests.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; exists rules require referenced records to be present; required rules define the minimum payload for creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'payout_period_id' => ['sometimes', 'nullable', 'integer', 'exists:payout_periods,id'],
            'type' => ['required', 'string', Rule::in(TeacherPayoutAdjustment::TYPES)],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'reason' => ['required', 'string', 'max:10000'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Register after-validation checks for cross-field or database-backed constraints on create teacher payout adjustment requests.
     *
     * @param  mixed  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = $this->input('type');
            $amount = (float) $this->input('amount');

            if (in_array($type, [TeacherPayoutAdjustment::TYPE_BONUS, TeacherPayoutAdjustment::TYPE_REIMBURSEMENT], true) && $amount <= 0) {
                $validator->errors()->add('amount', 'Bonus and reimbursement adjustments must be positive.');
            }
        });
    }
}
