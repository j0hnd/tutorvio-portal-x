<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionHistory>
 */
class SubscriptionHistoryFactory extends Factory
{
    protected $model = SubscriptionHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalLessonCount = fake()->randomElement([8, 12, 20, 40]);
        $consumedLessonCount = fake()->numberBetween(0, $totalLessonCount);

        return [
            'subscription_id' => Subscription::factory(),
            'student_id' => User::factory(),
            'event_type' => SubscriptionHistory::EVENT_ASSIGNED,
            'plan_name' => fake()->randomElement(['Starter', 'Standard', 'Intensive']),
            'package_type' => Subscription::TYPE_SUBSCRIPTION,
            'total_lesson_count' => $totalLessonCount,
            'consumed_lesson_count' => $consumedLessonCount,
            'remaining_lesson_count' => $totalLessonCount - $consumedLessonCount,
            'status' => Subscription::STATUS_ACTIVE,
            'is_frozen' => false,
            'payment_status' => Subscription::PAYMENT_STATUS_PAID,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'previous_values' => null,
            'new_values' => null,
            'notes' => null,
            'effective_at' => now(),
            'created_by' => null,
        ];
    }

    public function forSubscription(?Subscription $subscription = null): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_id' => $subscription?->id ?? Subscription::factory(),
            'student_id' => $subscription?->user_id ?? User::factory(),
        ]);
    }
}
