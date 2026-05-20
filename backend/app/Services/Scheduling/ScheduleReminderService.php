<?php

namespace App\Services\Scheduling;

use App\Models\Scheduling\ScheduleReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class ScheduleReminderService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): ScheduleReminder
    {
        return ScheduleReminder::create([
            ...Arr::only($payload, ['class_schedule_id', 'user_id', 'channel', 'status', 'metadata']),
            'scheduled_for' => CarbonImmutable::parse($payload['scheduled_for'], $payload['timezone'] ?? config('app.timezone'))->utc(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(ScheduleReminder $reminder, array $payload): ScheduleReminder
    {
        $reminder->fill(Arr::only($payload, ['class_schedule_id', 'user_id', 'channel', 'status', 'sent_at', 'metadata']));

        if (array_key_exists('scheduled_for', $payload)) {
            $reminder->scheduled_for = CarbonImmutable::parse($payload['scheduled_for'], $payload['timezone'] ?? config('app.timezone'))->utc();
        }

        $reminder->save();

        return $reminder->refresh();
    }
}
