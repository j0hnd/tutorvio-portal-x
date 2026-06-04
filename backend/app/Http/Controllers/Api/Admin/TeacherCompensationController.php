<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherCompensations\StoreTeacherCompensationRequest;
use App\Http\Requests\TeacherCompensations\UpdateTeacherCompensationRequest;
use App\Http\Resources\TeacherCompensations\TeacherCompensationResource;
use App\Models\TeacherCompensation;
use App\Models\User;
use App\Support\PublicIdResolver;
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

    /**
     * Display a filtered list of teacher compensation records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Notable request fields include include_archived, only_archived.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TeacherCompensation::class);
        $request->merge(PublicIdResolver::resolveFields($request->all(), [
            'teacher_id' => User::class,
        ]));

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

    /**
     * Handle the teacher action for teacher compensation records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $teacher.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record. The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     */
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

    /**
     * Create a new teacher compensation record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * The StoreTeacherCompensationRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     */
    public function store(StoreTeacherCompensationRequest $request): JsonResponse
    {
        Gate::authorize('create', TeacherCompensation::class);

        $compensation = TeacherCompensation::create($this->payload($request->validated()));

        return response()->json([
            'data' => new TeacherCompensationResource($compensation->load(['teacher', 'rateRules'])),
        ], 201);
    }

    /**
     * Display the selected teacher compensation record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $teacherCompensation.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     */
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

    /**
     * Update the selected teacher compensation record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacherCompensation.
     * The UpdateTeacherCompensationRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
    public function update(UpdateTeacherCompensationRequest $request, TeacherCompensation $teacherCompensation): JsonResponse
    {
        Gate::authorize('update', $teacherCompensation);

        $teacherCompensation->update($this->payload($request->validated()));

        return response()->json([
            'data' => new TeacherCompensationResource($teacherCompensation->refresh()->load(['teacher', 'rateRules'])),
        ]);
    }

    /**
     * Archive the selected teacher compensation record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacherCompensation.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     */
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
