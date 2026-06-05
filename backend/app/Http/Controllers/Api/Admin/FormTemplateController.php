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
    /**
     * Display a filtered list of form template records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Notable request fields include include_archived, only_archived.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
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

    /**
     * Create a new form template record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreFormTemplateRequest handles authorization and validation before the controller action runs.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  StoreFormTemplateRequest  $request
     * @return JsonResponse
     */
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

    /**
     * Display the selected form template record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $formTemplate.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  FormTemplate  $formTemplate
     * @return JsonResponse
     */
    public function show(FormTemplate $formTemplate): JsonResponse
    {
        return response()->json([
            'data' => new FormTemplateResource($formTemplate->load(['createdBy', 'updatedBy'])),
        ]);
    }

    /**
     * Update the selected form template record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $formTemplate.
     * The UpdateFormTemplateRequest handles authorization and validation before the controller action runs.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  UpdateFormTemplateRequest  $request
     * @param  FormTemplate  $formTemplate
     * @return JsonResponse
     */
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

    /**
     * Archive the selected form template record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $formTemplate.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  Request  $request
     * @param  FormTemplate  $formTemplate
     * @return JsonResponse
     */
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

    /**
     * Handle the unique key action for form template records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $templateType, $name.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  string  $templateType
     * @param  string  $name
     * @return string
     */
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

    /**
     * Handle the normalize template type action for form template records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $templateType.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  string  $templateType
     * @return string
     */
    private function normalizeTemplateType(string $templateType): string
    {
        return Str::of($templateType)
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();
    }
}
