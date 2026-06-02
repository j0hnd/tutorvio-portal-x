<?php

namespace App\Http\Requests\Payroll;

use App\Models\TeacherPayoutAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherPayoutAdjustmentRequest extends FormRequest
{
    /**
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
