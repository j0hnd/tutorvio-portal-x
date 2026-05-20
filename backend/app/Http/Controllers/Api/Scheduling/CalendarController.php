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
    public function __construct(private readonly CalendarService $calendarService) {}

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
