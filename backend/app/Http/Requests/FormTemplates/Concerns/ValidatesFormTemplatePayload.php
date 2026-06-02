<?php

namespace App\Http\Requests\FormTemplates\Concerns;

use App\Models\FormTemplate;
use App\Support\FormTemplateSchemaValidator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Provides validation behavior for form template payload normalization, validation, and sanitized payload extraction.
 *
 * Expected roles: Admin or staff users with form template management access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
trait ValidatesFormTemplatePayload
{
    /**
     * Normalize template aliases such as title, category/type, form_schema, and fields into canonical payload keys.
     */
    protected function prepareFormTemplatePayload(): void
    {
        $updates = [];

        if ($this->has('title') && ! $this->has('name')) {
            $updates['name'] = $this->input('title');
        }

        $templateType = $this->input('template_type', $this->input('category', $this->input('type')));

        if (is_string($templateType)) {
            $updates['template_type'] = $this->normalizeTemplateType($templateType);
        }

        if ($this->has('form_schema') && ! $this->has('schema')) {
            $updates['schema'] = $this->input('form_schema');
        }

        if ($this->has('fields') && ! $this->has('schema')) {
            $updates['schema'] = ['fields' => $this->input('fields')];
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }

    /**
     * Get validation rules for form template payload normalization, validation, and sanitized payload extraction.
     *
     * Important rules: sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants.
     *
     * @return array<string, mixed>
     */
    protected function baseRules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'category' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string'],
            'template_type' => [$required, 'string', Rule::in(FormTemplate::TEMPLATE_TYPES)],
            'status' => ['sometimes', 'string', Rule::in(FormTemplate::STATUSES)],
            'schema' => [$required, 'array'],
            'form_schema' => ['sometimes', 'array'],
            'fields' => ['sometimes', 'array'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * Add dynamic form-schema validation errors after the base schema array rule has run.
     *
     * @param  mixed  $validator
     */
    public function validateFormSchema($validator): void
    {
        if (! $this->has('schema') || ! is_array($this->input('schema'))) {
            return;
        }

        foreach (FormTemplateSchemaValidator::errors($this->input('schema')) as $field => $message) {
            $validator->errors()->add($field, $message);
        }
    }

    /**
     * Return the sanitized payload for form template payload normalization, validation, and sanitized payload extraction.
     *
     * @return array<string, mixed>
     */
    public function formTemplatePayload(): array
    {
        $validated = $this->safe()->except(['title', 'category', 'type', 'form_schema', 'fields']);

        if (array_key_exists('schema', $validated)) {
            $validated['schema'] = FormTemplateSchemaValidator::normalize($validated['schema']);
        }

        return $validated;
    }

    /**
     * Support validation for form template payload normalization, validation, and sanitized payload extraction.
     */
    private function normalizeTemplateType(string $templateType): string
    {
        return Str::of($templateType)
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();
    }
}
