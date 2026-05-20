<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Scheduling\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Holiday::class);

        $validated = $request->validate([
            'timezone' => ['sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'data' => Holiday::query()
                ->when($validated['timezone'] ?? null, fn ($query, string $timezone) => $query->where('timezone', $timezone))
                ->when($validated['country_code'] ?? null, fn ($query, string $countryCode) => $query->where('country_code', strtoupper($countryCode)))
                ->when(array_key_exists('is_active', $validated), fn ($query) => $query->where('is_active', $validated['is_active']))
                ->orderBy('date')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Holiday::class);

        $validated = $this->validatePayload($request, true);

        if (array_key_exists('country_code', $validated) && $validated['country_code'] !== null) {
            $validated['country_code'] = strtoupper($validated['country_code']);
        }

        return response()->json(['data' => Holiday::create($validated)], 201);
    }

    public function show(Holiday $holiday): JsonResponse
    {
        Gate::authorize('view', $holiday);

        return response()->json(['data' => $holiday]);
    }

    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        Gate::authorize('update', $holiday);

        $validated = $this->validatePayload($request, false);

        if (array_key_exists('country_code', $validated) && $validated['country_code'] !== null) {
            $validated['country_code'] = strtoupper($validated['country_code']);
        }

        $holiday->fill($validated)->save();

        return response()->json(['data' => $holiday->refresh()]);
    }

    public function destroy(Holiday $holiday): JsonResponse
    {
        Gate::authorize('delete', $holiday);

        $holiday->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'date' => [$creating ? 'required' : 'sometimes', 'date'],
            'timezone' => [$creating ? 'required' : 'sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'repeats_annually' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
    }
}
