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

    public function store(StoreTeacherCompensationRateRuleRequest $request, TeacherCompensation $teacherCompensation): JsonResponse
    {
        Gate::authorize('update', $teacherCompensation);

        $rateRule = $teacherCompensation->rateRules()->create($this->payload($request->validated()));

        return response()->json([
            'data' => new TeacherCompensationRateRuleResource($rateRule->load(['courseType', 'courseProgram'])),
        ], 201);
    }

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
     */
    private function payload(array $validated): array
    {
        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        return $validated;
    }

    private function assertRuleBelongsToCompensation(
        TeacherCompensation $teacherCompensation,
        TeacherCompensationRateRule $teacherCompensationRateRule
    ): void {
        abort_unless((int) $teacherCompensationRateRule->teacher_compensation_id === (int) $teacherCompensation->id, 404);
    }
}
