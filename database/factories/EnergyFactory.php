<?php

namespace Database\Factories;

use App\Models\Energy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Energy>
 */
class EnergyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'is_active' => true,
        ];
    }
}
