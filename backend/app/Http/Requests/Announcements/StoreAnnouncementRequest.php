<?php

namespace App\Http\Requests\Announcements;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required_without:body', 'string'],
            'body' => ['required_without:content', 'string'],
            'status' => ['sometimes', 'string', Rule::in([
                Announcement::STATUS_DRAFT,
                Announcement::STATUS_SCHEDULED,
                Announcement::STATUS_PUBLISHED,
            ])],
            'scheduled_at' => ['required_if:status,'.Announcement::STATUS_SCHEDULED, 'nullable', 'date', 'after:now'],
        ];
    }
}
