<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherCompensations\StoreTeacherCompensationRateRuleRequest;
use App\Http\Requests\TeacherCompensations\UpdateTeacherCompensationRateRuleRequest;
use App\Http\Resources\TeacherCompensations\TeacherCompensationRateRuleResource;
use App\Models\TeacherCompensation;
use App\Models\TeacherCompensationRateRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TeacherCompensationRateRuleController extends Controller
{
    /**
     * Display a filtered list of teacher compensation rate rule records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $teacherCompensation.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  TeacherCompensation  $teacherCompensation
     * @return JsonResponse
     */
    public function index(TeacherCompensation $teacherCompensation): JsonResponse
    {
        Gate::authorize('view', $teacherCompensation);

        return response()->json([
            'data' => TeacherCompensationRateRuleResource::collection(
                $teacherCompensation->rateRules()
                    ->with(['courseType', 'courseProgram'])
                    ->orderByDesc('priority')
                    ->orderBy('id')
                    ->get()
            ),
        ]);
    }

    /**
     * Create a new teacher compensation rate rule record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacherCompensation.
     * The StoreTeacherCompensationRateRuleRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  StoreTeacherCompensationRateRuleRequest  $request
     * @param  TeacherCompensation  $teacherCompensation
     * @return JsonResponse
     */
    public function store(StoreTeacherCompensationRateRuleRequest $request, TeacherCompensation $teacherCompensation): JsonResponse
    {
        Gate::authorize('update', $teacherCompensation);

        $rateRule = $teacherCompensation->rateRules()->create($this->payload($request->validated()));

        return response()->json([
            'data' => new TeacherCompensationRateRuleResource($rateRule->load(['courseType', 'courseProgram'])),
        ], 201);
    }

    /**
     * Update the selected teacher compensation rate rule record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $teacherCompensation, $teacherCompensationRateRule.
     * The UpdateTeacherCompensationRateRuleRequest handles authorization and validation before the controller action runs. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  UpdateTeacherCompensationRateRuleRequest  $request
     * @param  TeacherCompensation  $teacherCompensation
     * @param  TeacherCompensationRateRule  $teacherCompensationRateRule
     * @return JsonResponse
     */
    public function update(
        UpdateTeacherCompensationRateRuleRequest $request,
        TeacherCompensation $teacherCompensation,
        TeacherCompensationRateRule $teacherCompensationRateRule
    ): JsonResponse {
        Gate::authorize('update', $teacherCompensation);
        $this->assertRuleBelongsToCompensation($teacherCompensation, $teacherCompensationRateRule);

        $teacherCompensationRateRule->update($this->payload($request->validated()));

        return response()->json([
            'data' => new TeacherCompensationRateRuleResource($teacherCompensationRateRule->refresh()->load(['courseType', 'courseProgram'])),
        ]);
    }

    /**
     * Archive the selected teacher compensation rate rule record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $teacherCompensation, $teacherCompensationRateRule.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  TeacherCompensation  $teacherCompensation
     * @param  TeacherCompensationRateRule  $teacherCompensationRateRule
     * @return JsonResponse
     */
    public function archive(
        TeacherCompensation $teacherCompensation,
        TeacherCompensationRateRule $teacherCompensationRateRule
    ): JsonResponse {
        Gate::authorize('update', $teacherCompensation);
        $this->assertRuleBelongsToCompensation($teacherCompensation, $teacherCompensationRateRule);

        $teacherCompensationRateRule->update(['is_active' => false]);

        return response()->json([
            'data' => new TeacherCompensationRateRuleResource($teacherCompensationRateRule->refresh()->load(['courseType', 'courseProgram'])),
        ]);
    }

    /**
     * Delete the selected teacher compensation rate rule record.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $teacherCompensation, $teacherCompensationRateRule.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON confirmation after deletion.
     *
     * @param  TeacherCompensation  $teacherCompensation
     * @param  TeacherCompensationRateRule  $teacherCompensationRateRule
     * @return JsonResponse
     */
    public function destroy(
        TeacherCompensation $teacherCompensation,
        TeacherCompensationRateRule $teacherCompensationRateRule
    ): JsonResponse {
        $this->archive($teacherCompensation, $teacherCompensationRateRule);

        return response()->json(status: 204);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     *
     * @param  array  $validated
     */
    private function payload(array $validated): array
    {
        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        return $validated;
    }

    /**
     * Handle the assert rule belongs to compensation action for teacher compensation rate rule records.
     *
     * Admin or staff users only, with the route-specific permission middleware required for this action.
     * Route model parameters include $teacherCompensation, $teacherCompensationRateRule.
     * The method can return a forbidden response when authorization or ownership checks fail.
     * Returns a JSON response containing the requested data.
     *
     * @param  TeacherCompensation  $teacherCompensation
     * @param  TeacherCompensationRateRule  $teacherCompensationRateRule
     * @return void
     */
    private function assertRuleBelongsToCompensation(
        TeacherCompensation $teacherCompensation,
        TeacherCompensationRateRule $teacherCompensationRateRule
    ): void {
        abort_unless((int) $teacherCompensationRateRule->teacher_compensation_id === (int) $teacherCompensation->id, 404);
    }
}
