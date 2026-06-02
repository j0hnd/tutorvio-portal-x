<?php

namespace App\Http\Requests\FormTemplates;

use App\Http\Requests\FormTemplates\Concerns\ValidatesFormTemplatePayload;
use Illuminate\Foundation\Http\FormRequest;

class StoreFormTemplateRequest extends FormRequest
{
    use ValidatesFormTemplatePayload;

    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->hasAnyRole(['admin', 'staff']) && $user->can('form_templates.manage'));
    }

    protected function prepareForValidation(): void
    {
        $this->prepareFormTemplatePayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->baseRules(creating: true);
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->validateFormSchema($validator));
    }
}
