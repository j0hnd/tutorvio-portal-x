<?php

namespace App\Http\Requests\PayoutPeriods;

use App\Models\PayoutPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayoutPeriodRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PayoutPeriod|null $payoutPeriod */
        $payoutPeriod = $this->route('payoutPeriod');

        $rules = [
            'status' => ['sometimes', 'string', Rule::in(PayoutPeriod::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];

        if ($payoutPeriod?->canRefreshEarnings()) {
            $startDate = $this->input('start_date', $payoutPeriod->start_date?->toDateString());
            $endDate = $this->input('end_date', $payoutPeriod->end_date?->toDateString());
            $cutoffDate = $this->input('cutoff_date', $payoutPeriod->cutoff_date?->toDateString());

            $rules += [
                'name' => ['sometimes', 'string', 'max:255'],
                'start_date' => ['sometimes', 'date'],
                'end_date' => ['sometimes', 'date', 'after_or_equal:'.$startDate],
                'cutoff_date' => ['sometimes', 'date', 'after_or_equal:'.$endDate],
                'payout_date' => ['sometimes', 'date', 'after_or_equal:'.$cutoffDate],
            ];
        } else {
            $rules += [
                'name' => ['prohibited'],
                'start_date' => ['prohibited'],
                'end_date' => ['prohibited'],
                'cutoff_date' => ['prohibited'],
                'payout_date' => ['prohibited'],
            ];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var PayoutPeriod|null $payoutPeriod */
            $payoutPeriod = $this->route('payoutPeriod');

            if (! $payoutPeriod || ! $this->has('status')) {
                return;
            }

            $status = $this->input('status');

            if ($payoutPeriod->status === PayoutPeriod::STATUS_PAID && $status !== PayoutPeriod::STATUS_PAID) {
                $validator->errors()->add('status', 'Paid payout periods cannot be moved to another status.');
            }

            if (in_array($payoutPeriod->status, [PayoutPeriod::STATUS_DRAFT, PayoutPeriod::STATUS_OPEN], true)
                && $status === PayoutPeriod::STATUS_PAID) {
                $validator->errors()->add('status', 'Payout periods must be locked before they can be marked paid.');
            }

            if ($payoutPeriod->status === PayoutPeriod::STATUS_LOCKED && ! in_array($status, [PayoutPeriod::STATUS_LOCKED, PayoutPeriod::STATUS_PAID, PayoutPeriod::STATUS_CANCELLED], true)) {
                $validator->errors()->add('status', 'Locked payout periods can only remain locked, be marked paid, or be cancelled.');
            }

            if ($payoutPeriod->status === PayoutPeriod::STATUS_CANCELLED && $status !== PayoutPeriod::STATUS_CANCELLED) {
                $validator->errors()->add('status', 'Cancelled payout periods cannot be reopened.');
            }
        });
    }
}
