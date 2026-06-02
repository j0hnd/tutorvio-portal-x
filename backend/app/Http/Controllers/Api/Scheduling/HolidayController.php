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
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  HolidayService  $holidayService
     */
    public function __construct(private readonly HolidayService $holidayService) {}

    /**
     * Display a filtered list of holiday records.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Holiday::class);

        $validated = $request->validate([
            'timezone' => ['sometimes', 'string', Rule::in(timezone_identifiers_list())],
            'country_code' => ['sometimes', 'string', 'size:2'],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $holidays = Holiday::query()
            ->when($validated['timezone'] ?? null, fn ($query, string $timezone) => $query->where('timezone', $timezone))
            ->when($validated['country_code'] ?? null, fn ($query, string $countryCode) => $query->where('country_code', strtoupper($countryCode)))
            ->when(array_key_exists('is_active', $validated), fn ($query) => $query->where('is_active', $validated['is_active']))
            ->orderBy('date')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json(
            $holidays->through(fn (Holiday $holiday) => new HolidayResource($holiday))
        );
    }

    /**
     * Create a new holiday record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the created resource or action result.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Holiday::class);

        $validated = $this->validatePayload($request, true);

        return response()->json(['data' => new HolidayResource($this->holidayService->create($validated))], 201);
    }

    /**
     * Display the selected holiday record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $holiday.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Holiday  $holiday
     * @return JsonResponse
     */
    public function show(Holiday $holiday): JsonResponse
    {
        Gate::authorize('view', $holiday);

        return response()->json(['data' => new HolidayResource($holiday)]);
    }

    /**
     * Update the selected holiday record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $holiday.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON payload with the updated resource or status result.
     *
     * @param  Request  $request
     * @param  Holiday  $holiday
     * @return JsonResponse
     */
    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        Gate::authorize('update', $holiday);

        $validated = $this->validatePayload($request, false);

        return response()->json(['data' => new HolidayResource($this->holidayService->update($holiday, $validated))]);
    }

    /**
     * Delete the selected holiday record.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Route model parameters include $holiday.
     * Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON confirmation after deletion.
     *
     * @param  Holiday  $holiday
     * @return JsonResponse
     */
    public function destroy(Holiday $holiday): JsonResponse
    {
        Gate::authorize('delete', $holiday);

        $holiday->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     *
     * @param  Request  $request
     * @param  bool  $creating
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
