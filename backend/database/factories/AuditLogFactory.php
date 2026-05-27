<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'action_type' => fake()->randomElement([
                'created',
                'updated',
                'deleted',
                'assigned',
                'status_changed',
            ]),
            'module' => fake()->randomElement([
                'admin',
                'teacher',
                'student',
                'billing',
                'permission',
            ]),
            'target_entity_type' => fake()->randomElement([
                'user',
                'lesson',
                'invoice',
                'role',
                'subscription',
            ]),
            'target_entity_id' => fake()->numberBetween(1, 100000),
            'metadata' => [
                'context' => fake()->word(),
                'source' => 'system',
            ],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
