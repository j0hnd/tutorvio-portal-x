<?php

namespace App\Http\Requests\Announcements;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
{
    /**
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
        ];
    }
}
