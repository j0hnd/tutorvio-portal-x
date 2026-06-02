<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        foreach (['category', 'template_type'] as $key) {
            $value = $request->input($key);

            if (is_string($value)) {
                $request->merge([$key => $this->normalizeTemplateType($value)]);
            }
        }

        $validated = $request->validate([
            'category' => ['sometimes', 'string', Rule::in(FormTemplate::TEMPLATE_TYPES)],
            'template_type' => ['sometimes', 'string', Rule::in(FormTemplate::TEMPLATE_TYPES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $roles = $user?->roles->pluck('name')->all() ?? [];
        $templateType = $validated['template_type'] ?? $validated['category'] ?? null;
        $visibleTypes = array_values(array_filter(
            FormTemplate::TEMPLATE_TYPES,
            fn (string $type) => array_intersect($roles, FormTemplate::submitterRolesFor($type)) !== []
        ));

        $templates = FormTemplate::query()
            ->where('status', FormTemplate::STATUS_ACTIVE)
            ->when($templateType, fn (Builder $query, string $type) => $query->where('template_type', $type))
            ->whereIn('template_type', $visibleTypes)
            ->orderBy('template_type')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($templates->through(fn (FormTemplate $template) => new FormTemplateResource($template)));
    }

    /**
     * Display the selected form template record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $formTemplate.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  FormTemplate  $formTemplate
     * @return JsonResponse
     */
    public function show(Request $request, FormTemplate $formTemplate): JsonResponse
    {
        $userRoles = $request->user()?->roles->pluck('name')->all() ?? [];

        if (
            $formTemplate->status !== FormTemplate::STATUS_ACTIVE
            || array_intersect($userRoles, FormTemplate::submitterRolesFor($formTemplate->template_type)) === []
        ) {
            abort(403);
        }

        return response()->json([
            'data' => new FormTemplateResource($formTemplate),
        ]);
    }

    /**
     * Handle the normalize template type action for form template records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
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
