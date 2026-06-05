<?php

namespace App\Http\Requests\MessageTemplates;

use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMessageTemplateRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can manage message templates.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->hasAnyRole(['admin', 'staff']) && $user->can('message_templates.manage'));
    }

    /**
     * Normalize category, role aliases, and teacher public IDs before validation.
     */
    protected function prepareForValidation(): void
    {
        $updates = [];

        if ($this->has('category') && is_string($this->input('category'))) {
            $updates['category'] = $this->normalizeValue($this->input('category'));
        }

        if ($this->has('roles') && ! $this->has('role_visibility')) {
            $updates['role_visibility'] = $this->input('roles');
        }

        if ($this->has('role_visibility')) {
            $updates['role_visibility'] = collect(Arr::wrap($this->input('role_visibility')))
                ->filter(fn (mixed $role) => is_string($role) && trim($role) !== '')
                ->map(fn (string $role) => $this->normalizeValue($role))
                ->unique()
                ->values()
                ->all();
        }

        if ($this->has('teacher_id') && ! $this->has('teacher_public_id')) {
            $updates['teacher_public_id'] = $this->input('teacher_id');
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }

    /**
     * Get validation rules for creating a message template.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'category' => ['required', 'string', Rule::in(MessageTemplate::CATEGORIES)],
            'role_visibility' => ['required', 'array', 'min:1'],
            'role_visibility.*' => ['string', Rule::in(MessageTemplate::ROLES)],
            'status' => ['sometimes', 'string', Rule::in(MessageTemplate::STATUSES)],
            'teacher_public_id' => ['sometimes', 'nullable', 'string', Rule::exists('users', 'public_id')],
            'teacher_id' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Validate that teacher-specific templates are assigned to teacher users.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $teacherPublicId = $this->input('teacher_public_id');

            if ($teacherPublicId === null || $teacherPublicId === '') {
                return;
            }

            $teacher = User::query()->where('public_id', $teacherPublicId)->first();

            if ($teacher !== null && ! $teacher->hasRole('teacher')) {
                $validator->errors()->add('teacher_id', 'The selected user must be a teacher.');
            }
        });
    }

    /**
     * Return sanitized message template values for persistence.
     *
     * @return array<string, mixed>
     */
    public function messageTemplatePayload(): array
    {
        $validated = $this->safe()->except(['roles', 'teacher_id', 'teacher_public_id']);
        $teacherPublicId = $this->validated('teacher_public_id');

        if ($teacherPublicId !== null) {
            $validated['teacher_id'] = User::query()->where('public_id', $teacherPublicId)->valueOrFail('id');
        }

        return $validated;
    }

    private function normalizeValue(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();
    }
}
