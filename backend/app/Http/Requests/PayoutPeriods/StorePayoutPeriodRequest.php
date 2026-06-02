<?php

namespace App\Http\Requests\PayoutPeriods;

use App\Models\PayoutPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayoutPeriodRequest extends FormRequest
{
    /**
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
