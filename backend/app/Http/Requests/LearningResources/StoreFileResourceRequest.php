<?php

namespace App\Http\Requests\LearningResources;

use App\Models\LearningResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFileResourceRequest extends FormRequest
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
        $fileTypes = array_values(array_diff(
            LearningResource::RESOURCE_TYPES,
            [LearningResource::TYPE_LINK]
        ));

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'resource_type' => ['required', 'string', Rule::in($fileTypes)],
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', config('learning_resources.allowed_mime_types', [])),
                'max:'.config('learning_resources.max_upload_kilobytes', 10240),
            ],
            'course' => ['sometimes', 'nullable', 'string', 'max:255'],
            'level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visibility' => ['sometimes', 'string', Rule::in(LearningResource::VISIBILITIES)],
        ];
    }
}
