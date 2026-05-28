<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormTemplates\StoreFormTemplateRequest;
use App\Http\Requests\FormTemplates\UpdateFormTemplateRequest;
use App\Http\Resources\FormTemplates\FormTemplateResource;
use App\Models\FormTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        foreach (['include_archived', 'only_archived'] as $key) {
            $value = $request->input($key);

            if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
                $request->merge([$key => strtolower($value) === 'true']);
            }
        }

        foreach (['category', 'template_type'] as $key) {
            $value = $request->input($key);

            if (is_string($value)) {
                $request->merge([$key => $this->normalizeTemplateType($value)]);
            }
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(FormTemplate::STATUSES)],
            'category' => ['sometimes', 'string', Rule::in(FormTemplate::TEMPLATE_TYPES)],
            'template_type' => ['sometimes', 'string', Rule::in(FormTemplate::TEMPLATE_TYPES)],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'include_archived' => ['sometimes', 'boolean'],
            'only_archived' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $includeArchived = $request->boolean('include_archived')
            || $request->boolean('only_archived')
            || ($validated['status'] ?? null) === FormTemplate::STATUS_ARCHIVED;
        $templateType = $validated['template_type'] ?? $validated['category'] ?? null;

        $templates = FormTemplate::query()
            ->with(['createdBy', 'updatedBy'])
            ->when(! $includeArchived, fn (Builder $query) => $query->where('status', '!=', FormTemplate::STATUS_ARCHIVED))
            ->when($request->boolean('only_archived'), fn (Builder $query) => $query->where('status', FormTemplate::STATUS_ARCHIVED))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($templateType, fn (Builder $query, string $type) => $query->where('template_type', $type))
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('template_type')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($templates->through(fn (FormTemplate $template) => new FormTemplateResource($template)));
    }

    public function store(StoreFormTemplateRequest $request): JsonResponse
    {
        $validated = $request->formTemplatePayload();

        $template = FormTemplate::create([
            ...$validated,
            'key' => $this->uniqueKey($validated['template_type'], $validated['name']),
            'status' => $validated['status'] ?? FormTemplate::STATUS_ACTIVE,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new FormTemplateResource($template->load(['createdBy', 'updatedBy'])),
        ], 201);
    }

    public function show(FormTemplate $formTemplate): JsonResponse
    {
        return response()->json([
            'data' => new FormTemplateResource($formTemplate->load(['createdBy', 'updatedBy'])),
        ]);
    }

    public function update(UpdateFormTemplateRequest $request, FormTemplate $formTemplate): JsonResponse
    {
        $validated = $request->formTemplatePayload();

        if (array_key_exists('schema', $validated)) {
            $validated['version'] = $formTemplate->version + 1;
        }

        $formTemplate->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new FormTemplateResource($formTemplate->refresh()->load(['createdBy', 'updatedBy'])),
        ]);
    }

    public function archive(Request $request, FormTemplate $formTemplate): JsonResponse
    {
        $formTemplate->update([
            'status' => FormTemplate::STATUS_ARCHIVED,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new FormTemplateResource($formTemplate->refresh()->load(['createdBy', 'updatedBy'])),
        ]);
    }

    private function uniqueKey(string $templateType, string $name): string
    {
        $base = Str::slug($templateType.'-'.$name);
        $key = $base;
        $counter = 2;

        while (FormTemplate::query()->where('key', $key)->exists()) {
            $key = "{$base}-{$counter}";
            $counter++;
        }

        return $key;
    }

    private function normalizeTemplateType(string $templateType): string
    {
        return Str::of($templateType)
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();
    }
}
