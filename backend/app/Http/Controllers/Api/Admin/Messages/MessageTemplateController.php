<?php

namespace App\Http\Controllers\Api\Admin\Messages;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageTemplates\StoreMessageTemplateRequest;
use App\Http\Requests\MessageTemplates\UpdateMessageTemplateRequest;
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
     * Display message templates for admin or authorized staff management.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->has('category') && is_string($request->input('category'))) {
            $request->merge(['category' => $this->normalizeValue($request->input('category'))]);
        }

        $validated = $request->validate([
            'category' => ['sometimes', 'string', Rule::in(MessageTemplate::CATEGORIES)],
            'status' => ['sometimes', 'string', Rule::in(MessageTemplate::STATUSES)],
            'teacher_id' => ['sometimes', 'nullable', 'string', Rule::exists('users', 'public_id')],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $templates = MessageTemplate::query()
            ->with(['teacher', 'createdBy', 'updatedBy'])
            ->when($validated['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(array_key_exists('teacher_id', $validated), function (Builder $query) use ($validated): void {
                if ($validated['teacher_id'] === null) {
                    $query->whereNull('teacher_id');

                    return;
                }

                $query->whereHas('teacher', fn (Builder $query) => $query->where('public_id', $validated['teacher_id']));
            })
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->orderBy('category')
            ->orderBy('title')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($templates->through(fn (MessageTemplate $template) => new MessageTemplateResource($template)));
    }

    /**
     * Create a new global or teacher-specific message template.
     */
    public function store(StoreMessageTemplateRequest $request): JsonResponse
    {
        $validated = $request->messageTemplatePayload();

        $template = MessageTemplate::create([
            ...$validated,
            'status' => $validated['status'] ?? MessageTemplate::STATUS_ACTIVE,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new MessageTemplateResource($template->load(['teacher', 'createdBy', 'updatedBy'])),
        ], 201);
    }

    /**
     * Display a selected message template for admin or authorized staff.
     */
    public function show(MessageTemplate $messageTemplate): JsonResponse
    {
        return response()->json([
            'data' => new MessageTemplateResource($messageTemplate->load(['teacher', 'createdBy', 'updatedBy'])),
        ]);
    }

    /**
     * Update a selected global or teacher-specific message template.
     */
    public function update(UpdateMessageTemplateRequest $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $messageTemplate->update([
            ...$request->messageTemplatePayload(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new MessageTemplateResource($messageTemplate->refresh()->load(['teacher', 'createdBy', 'updatedBy'])),
        ]);
    }

    /**
     * Remove a selected message template from active use.
     */
    public function destroy(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $messageTemplate->update([
            'status' => MessageTemplate::STATUS_INACTIVE,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new MessageTemplateResource($messageTemplate->refresh()->load(['teacher', 'createdBy', 'updatedBy'])),
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
