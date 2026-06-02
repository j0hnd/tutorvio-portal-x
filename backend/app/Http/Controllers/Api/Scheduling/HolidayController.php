<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Http\Resources\Scheduling\HolidayResource;
use App\Models\Scheduling\Holiday;
use App\Services\Scheduling\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function __construct(private readonly HolidayService $holidayService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Holiday::class);

        $validated = $request->validate([
            'timezone' => ['sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'data' => HolidayResource::collection(Holiday::query()
                ->when($validated['timezone'] ?? null, fn ($query, string $timezone) => $query->where('timezone', $timezone))
                ->when($validated['country_code'] ?? null, fn ($query, string $countryCode) => $query->where('country_code', strtoupper($countryCode)))
                ->when(array_key_exists('is_active', $validated), fn ($query) => $query->where('is_active', $validated['is_active']))
                ->orderBy('date')
                ->get()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Holiday::class);

        $validated = $this->validatePayload($request, true);

        return response()->json(['data' => new HolidayResource($this->holidayService->create($validated))], 201);
    }

    public function show(Holiday $holiday): JsonResponse
    {
        Gate::authorize('view', $holiday);

        return response()->json(['data' => new HolidayResource($holiday)]);
    }

    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        Gate::authorize('update', $holiday);

        $validated = $this->validatePayload($request, false);

        return response()->json(['data' => new HolidayResource($this->holidayService->update($holiday, $validated))]);
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
