<?php

namespace Database\Factories;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalSetting>
 */
class PortalSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'custom.'.fake()->unique()->slug(2),
            'category' => 'custom',
            'value' => ['enabled' => true],
            'value_type' => PortalSetting::TYPE_JSON,
            'description' => fake()->sentence(),
            'is_public' => false,
            'updated_by' => User::factory(),
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }
}
