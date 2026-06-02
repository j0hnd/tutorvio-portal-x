<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherCompensations\StoreTeacherCompensationRequest;
use App\Http\Requests\TeacherCompensations\UpdateTeacherCompensationRequest;
use App\Http\Resources\TeacherCompensations\TeacherCompensationResource;
use App\Models\TeacherCompensation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeacherCompensationController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'created_at',
        'updated_at',
        'effective_start_date',
        'effective_end_date',
        'pay_model',
        'currency',
    ];

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TeacherCompensation::class);

        $validated = $request->validate([
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'pay_model' => ['sometimes', 'string', Rule::in(TeacherCompensation::PAY_MODELS)],
            'currency' => ['sometimes', 'string', 'size:3'],
            'effective_on' => ['sometimes', 'date'],
            'include_archived' => ['sometimes', 'boolean'],
            'only_archived' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string', Rule::in(self::SORTABLE_COLUMNS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $compensations = TeacherCompensation::query()
            ->with(['teacher', 'rateRules' => fn ($query) => $query->orderByDesc('priority')->orderBy('id')])
            ->when(! $request->boolean('include_archived') && ! $request->boolean('only_archived'), fn (Builder $query) => $query->active())
            ->when($request->boolean('only_archived'), fn (Builder $query) => $query->whereNotNull('archived_at'))
            ->when($validated['teacher_id'] ?? null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($validated['pay_model'] ?? null, fn (Builder $query, string $payModel) => $query->where('pay_model', $payModel))
            ->when($validated['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when($validated['effective_on'] ?? null, fn (Builder $query, string $date) => $query->effectiveOn($date))
            ->orderBy($validated['sort'] ?? 'effective_start_date', $validated['direction'] ?? 'desc')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($compensations->through(fn (TeacherCompensation $compensation) => new TeacherCompensationResource($compensation)));
    }

    public function teacher(User $teacher): JsonResponse
    {
        Gate::authorize('viewAny', TeacherCompensation::class);

        abort_unless($teacher->hasRole('teacher'), 404);

        return response()->json([
            'data' => TeacherCompensationResource::collection(
                $teacher->teacherCompensations()
                    ->with(['teacher', 'rateRules' => fn ($query) => $query->orderByDesc('priority')->orderBy('id')])
                    ->latest('effective_start_date')
                    ->get()
            ),
        ]);
    }

    public function store(StoreTeacherCompensationRequest $request): JsonResponse
    {
        Gate::authorize('create', TeacherCompensation::class);

        $compensation = TeacherCompensation::create($this->payload($request->validated()));

        return response()->json([
            'data' => new TeacherCompensationResource($compensation->load(['teacher', 'rateRules'])),
        ], 201);
    }

    public function show(TeacherCompensation $teacherCompensation): JsonResponse
    {
        Gate::authorize('view', $teacherCompensation);

        return response()->json([
            'data' => new TeacherCompensationResource($teacherCompensation->load([
                'teacher',
                'rateRules.courseType',
                'rateRules.courseProgram',
            ])),
        ]);
    }

    public function update(UpdateTeacherCompensationRequest $request, TeacherCompensation $teacherCompensation): JsonResponse
    {
        Gate::authorize('update', $teacherCompensation);

        $teacherCompensation->update($this->payload($request->validated()));

        return response()->json([
            'data' => new TeacherCompensationResource($teacherCompensation->refresh()->load(['teacher', 'rateRules'])),
        ]);
    }

    public function archive(Request $request, TeacherCompensation $teacherCompensation): JsonResponse
    {
        Gate::authorize('delete', $teacherCompensation);

        $teacherCompensation->update([
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new TeacherCompensationResource($teacherCompensation->refresh()->load(['teacher', 'rateRules'])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        unset($validated['base_rate']);

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        return $validated;
    }
}
