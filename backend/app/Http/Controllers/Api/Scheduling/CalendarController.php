<?php

namespace App\Http\Controllers\Api\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Scheduling\ClassSchedule;
use App\Services\Scheduling\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CalendarController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  CalendarService  $calendarService
     */
    public function __construct(private readonly CalendarService $calendarService) {}

    /**
     * Handle the calendar endpoint.
     *
     * Authenticated users only; role, permission, ownership, and policy limits are enforced by route middleware, FormRequest authorization, or method checks.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing. Authorization checks in this method can reject users who do not own or cannot manage the target record.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ClassSchedule::class);

        $validated = $request->validate([
            'view' => ['required', 'string', Rule::in(['day', 'week', 'month'])],
            'date' => ['sometimes', 'date'],
            'timezone' => ['sometimes', 'string', Rule::in(timezone_identifiers_list())],
        ]);

        $timezone = $validated['timezone'] ?? $request->user()->timezone ?? config('app.timezone');
        $date = CarbonImmutable::parse($validated['date'] ?? now($timezone)->toDateString(), $timezone);

        return response()->json([
            'data' => $this->calendarService->calendar($request->user(), $validated['view'], $date, $timezone),
        ]);
    }
}
