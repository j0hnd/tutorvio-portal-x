<?php

namespace App\Http\Requests\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates update announcement requests.
 *
 * Expected roles: Admin or staff users with announcement management access.
 * Request-level authorize() documents any additional checks; otherwise route middleware, controller gates, and policies handle access.
 */
class UpdateAnnouncementRequest extends FormRequest
{
    /**
     * Get validation rules for update announcement requests.
     *
     * Important rules: conditional required rules require companion fields for specific status values; sometimes rules support partial updates or optional filters; enum rules constrain values to the relevant model constants; date and time ordering rules keep ranges consistent.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'body' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', Rule::in([
                Announcement::STATUS_DRAFT,
                Announcement::STATUS_SCHEDULED,
                Announcement::STATUS_PUBLISHED,
            ])],
            'scheduled_at' => ['required_if:status,'.Announcement::STATUS_SCHEDULED, 'nullable', 'date', 'after:now'],
            'targets' => ['sometimes', 'array'],
            'targets.*.type' => ['required_with:targets', 'string', Rule::in(AnnouncementTarget::TARGETS)],
            'targets.*.target_type' => ['sometimes', 'string', Rule::in(AnnouncementTarget::TARGETS)],
            'targets.*.target_id' => ['nullable', 'integer'],
            'targets.*.user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'targets.*.role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'targets.*.group' => ['nullable', 'string', 'max:255'],
            'targets.*.metadata' => ['sometimes', 'array'],
        ];
    }
}
