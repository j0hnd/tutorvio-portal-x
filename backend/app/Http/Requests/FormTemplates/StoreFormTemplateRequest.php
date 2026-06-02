<?php

namespace App\Http\Requests\FormTemplates;

use App\Http\Requests\FormTemplates\Concerns\ValidatesFormTemplatePayload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates create form template requests.
 *
 * Expected roles: Admin or staff users with form template management access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class StoreFormTemplateRequest extends FormRequest
{
    use ValidatesFormTemplatePayload;

    /**
     * Determine whether the authenticated user can manage form templates. Requires admin or staff role plus form template management permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->hasAnyRole(['admin', 'staff']) && $user->can('form_templates.manage'));
    }

    /**
     * Normalize template aliases such as title, category/type, form_schema, and fields into canonical payload keys.
     */
    protected function prepareForValidation(): void
    {
        $this->prepareFormTemplatePayload();
    }

    /**
     * Get validation rules for create form template requests.
     *
     * Important rules: required rules define the minimum payload for creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->baseRules(creating: true);
    }

    /**
     * Register after-validation checks for cross-field or database-backed constraints on create form template requests.
     *
     * @param  mixed  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->validateFormSchema($validator));
    }
}
