<?php

namespace App\Http\Requests\PortalSettings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Validates update portal settings requests.
 *
 * Expected roles: Admin or staff users with portal settings management access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdatePortalSettingsRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user can submit update portal settings requests. This request adds no request-local authorization beyond route middleware, controller gates, or policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for update portal settings requests.
     *
     * Important rules: partial update rules validate only supplied fields unless a supplied field is explicitly required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->has('settings')
            ? ['settings' => ['required', 'array', 'min:1']]
            : [];
    }

    /**
     * Return the sanitized payload for update portal settings requests.
     *
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        $settings = $this->has('settings')
            ? $this->validated('settings')
            : $this->all();

        if ($settings === []) {
            throw ValidationException::withMessages([
                'settings' => 'At least one portal setting is required.',
            ]);
        }

        return $settings;
    }
}
