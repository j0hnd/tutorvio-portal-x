<?php

namespace App\Http\Requests\Subscriptions;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPaymentStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_status' => ['required_without:status', 'string', Rule::in(Subscription::PAYMENT_STATUSES)],
            'status' => ['required_without:payment_status', 'string', Rule::in(Subscription::PAYMENT_STATUSES)],
        ];
    }
}
