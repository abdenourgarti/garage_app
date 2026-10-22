<?php

namespace Database\Factories;

use App\Enums\OilUsage;
use App\Models\OilProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OilProduct>
 */
class OilProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand' => fake()->randomElement(['Total', 'Shell', 'Mobil', 'Castrol', 'Naftal']),
            'name' => fake()->randomElement(['Quartz 9000', 'Helix HX7', 'Super 3000', null]),
            'viscosity' => fake()->randomElement(['5W-30', '5W-40', '10W-40', '15W-40']),
            'usage' => OilUsage::Engine,
            'approval' => null,
            'is_active' => true,
        ];
    }
}
