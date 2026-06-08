<?php

namespace App\Http\Controllers\Api\Messages;

use App\Http\Controllers\Controller;
use App\Http\Resources\Messages\MessageTemplateResource;
use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MessageTemplateController extends Controller
{
    /**
     * Display active message templates visible to the authenticated user's role.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->has('category') && is_string($request->input('category'))) {
            $request->merge(['category' => $this->normalizeValue($request->input('category'))]);
        }

        $validated = $request->validate([
            'category' => ['sometimes', 'string', Rule::in(MessageTemplate::CATEGORIES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $templates = MessageTemplate::query()
            ->with('teacher')
            ->visibleToUser($request->user())
            ->when($validated['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->orderBy('category')
            ->orderBy('title')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($templates->through(fn (MessageTemplate $template) => new MessageTemplateResource($template)));
    }

    /**
     * Display a single active message template when it is visible to the current user.
     */
    public function show(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $template = MessageTemplate::query()
            ->with('teacher')
            ->visibleToUser($request->user())
            ->whereKey($messageTemplate->id)
            ->firstOrFail();

        return response()->json([
            'data' => new MessageTemplateResource($template),
        ]);
    }

    private function normalizeValue(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();
    }
}
