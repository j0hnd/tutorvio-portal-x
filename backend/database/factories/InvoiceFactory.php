<?php

namespace Database\Factories;

use App\Models\CourseProgram;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 1500);
        $taxAmount = round($amount * 0.12, 2);
        $issuedDate = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'student_id' => User::factory(),
            'subscription_id' => null,
            'course_program_id' => null,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('########'),
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $amount + $taxAmount,
            'currency' => 'USD',
            'issued_date' => $issuedDate,
            'due_date' => fake()->dateTimeBetween($issuedDate, '+1 month'),
            'paid_date' => null,
            'status' => Invoice::STATUS_UNPAID,
            'payment_gateway' => null,
            'gateway_customer_id' => null,
            'gateway_invoice_id' => null,
            'gateway_payment_intent_id' => null,
            'gateway_checkout_session_id' => null,
            'gateway_payment_method_id' => null,
            'gateway_status' => null,
            'payment_reference' => null,
            'gateway_payload' => null,
            'metadata' => null,
        ];
    }

    public function forSubscription(?Subscription $subscription = null): static
    {
        if ($subscription) {
            return $this->state(fn (array $attributes) => [
                'student_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
            ]);
        }

        return $this->state(fn (array $attributes) => [
            'subscription_id' => Subscription::factory(),
        ]);
    }

    public function forCourseProgram(?CourseProgram $courseProgram = null): static
    {
        return $this->state(fn (array $attributes) => [
            'course_program_id' => $courseProgram?->id ?? CourseProgram::factory(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_PAID,
            'paid_date' => now()->toDateString(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_OVERDUE,
            'due_date' => now()->subDay()->toDateString(),
            'paid_date' => null,
        ]);
    }
}
