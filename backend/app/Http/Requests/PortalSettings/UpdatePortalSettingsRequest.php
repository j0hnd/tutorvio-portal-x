<?php

namespace App\Http\Requests\PortalSettings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdatePortalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->has('settings')
            ? ['settings' => ['required', 'array', 'min:1']]
            : [];
    }

    /**
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
