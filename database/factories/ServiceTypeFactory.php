<?php

namespace Database\Factories;

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceType>
 */
class ServiceTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'default_labor_price' => fake()->randomElement([0, 1500, 2500, 4000]),
            'requires_mileage' => false,
            'reminder_km' => null,
            'is_active' => true,
        ];
    }
}
