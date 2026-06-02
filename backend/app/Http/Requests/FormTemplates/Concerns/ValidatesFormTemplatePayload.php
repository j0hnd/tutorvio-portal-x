<?php

namespace App\Http\Requests\FormTemplates\Concerns;

use App\Models\FormTemplate;
use App\Support\FormTemplateSchemaValidator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesFormTemplatePayload
{
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

    private function normalizeTemplateType(string $templateType): string
    {
        return Str::of($templateType)
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();
    }
}
