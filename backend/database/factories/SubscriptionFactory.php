<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

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
            'user_id' => User::factory(),
            'plan_name' => fake()->randomElement(['Starter', 'Standard', 'Intensive']),
            'package_type' => Subscription::TYPE_SUBSCRIPTION,
            'total_lesson_count' => $totalLessonCount,
            'consumed_lesson_count' => $consumedLessonCount,
            'remaining_lesson_count' => $totalLessonCount - $consumedLessonCount,
            'status' => Subscription::STATUS_ACTIVE,
            'is_frozen' => false,
            'frozen_at' => null,
            'payment_status' => Subscription::PAYMENT_STATUS_PAID,
            'invoice_id' => null,
            'invoice_reference' => null,
            'internal_notes' => null,
            'renewed_from_subscription_id' => null,
            'renewal_reminder_due_at' => null,
            'renewal_reminder_last_sent_at' => null,
            'renewal_reminder_status' => Subscription::RENEWAL_REMINDER_STATUS_NONE,
            'renewal_reminder_window_key' => null,
            'renewal_eligible' => true,
            'renewal_reminder_notes' => null,
            'created_by' => null,
            'updated_by' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ];
    }

    public function package(): static
    {
        return $this->state(fn (array $attributes) => [
            'package_type' => Subscription::TYPE_PACKAGE,
        ]);
    }

    public function frozen(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_frozen' => true,
            'frozen_at' => now(),
            'status' => Subscription::STATUS_INACTIVE,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Subscription::STATUS_EXPIRED,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => Subscription::PAYMENT_STATUS_UNPAID,
        ]);
    }
}
