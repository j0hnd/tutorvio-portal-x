<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class HolidayService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Holiday
    {
        $payload = $this->normalizePayload($payload);
        $this->assertNoOverlappingHoliday($payload);

        return Holiday::create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Holiday $holiday, array $payload): Holiday
    {
        $payload = $this->normalizePayload($payload);

        $candidate = [
            'date' => $payload['date'] ?? $holiday->date,
            'timezone' => $payload['timezone'] ?? $holiday->timezone,
            'repeats_annually' => $payload['repeats_annually'] ?? $holiday->repeats_annually,
            'is_active' => $payload['is_active'] ?? $holiday->is_active,
        ];

        $this->assertNoOverlappingHoliday($candidate, $holiday->id);

        $holiday->fill($payload)->save();

        return $holiday->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        if (array_key_exists('country_code', $payload) && $payload['country_code'] !== null) {
            $payload['country_code'] = strtoupper((string) $payload['country_code']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertNoOverlappingHoliday(array $payload, ?int $exceptHolidayId = null): void
    {
        if (! ($payload['is_active'] ?? true)) {
            return;
        }

        $date = CarbonImmutable::parse($payload['date'], $payload['timezone']);
        $repeatsAnnually = (bool) ($payload['repeats_annually'] ?? false);

        $overlaps = Holiday::query()
            ->where('is_active', true)
            ->where('timezone', $payload['timezone'])
            ->when($exceptHolidayId, fn ($query) => $query->whereKeyNot($exceptHolidayId))
            ->get()
            ->contains(function (Holiday $holiday) use ($date, $repeatsAnnually): bool {
                $existingDate = CarbonImmutable::parse($holiday->date->toDateString(), $holiday->timezone);

                if ($repeatsAnnually || $holiday->repeats_annually) {
                    return $existingDate->month === $date->month && $existingDate->day === $date->day;
                }

                return $existingDate->toDateString() === $date->toDateString();
            });

        if ($overlaps) {
            throw ValidationException::withMessages([
                'date' => 'Holiday blocks for the same timezone cannot overlap.',
            ]);
        }
    }
}
