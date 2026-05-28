<?php

namespace Database\Factories;

use App\Models\FormTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FormTemplate>
 */
class FormTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(FormTemplate::TEMPLATE_TYPES);
        $name = Str::headline($type);

        return [
            'key' => Str::slug($type.'-'.$this->faker->unique()->word()),
            'name' => $name,
            'description' => fake()->sentence(),
            'template_type' => $type,
            'status' => FormTemplate::STATUS_ACTIVE,
            'version' => 1,
            'schema' => [
                'fields' => [
                    [
                        'name' => 'reason',
                        'label' => 'Reason',
                        'type' => 'textarea',
                        'required' => true,
                    ],
                ],
            ],
            'instructions' => null,
        ];
    }
}
