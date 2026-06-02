<?php

namespace App\Http\Requests\Homeworks;

use App\Models\Homework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomeworkProgressRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                Homework::STATUS_IN_PROGRESS,
                Homework::STATUS_COMPLETED,
            ])],
        ];
    }
}
