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

    private function normalizeTemplateType(string $templateType): string
    {
        return Str::of($templateType)
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();
    }
}
