<?php

namespace Database\Factories;

use App\Models\PayoutPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayoutPeriod>
 */
class PayoutPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->monthName().' '.fake()->year().' payout',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-15',
            'cutoff_date' => '2026-06-16',
            'payout_date' => '2026-06-20',
            'status' => PayoutPeriod::STATUS_DRAFT,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
