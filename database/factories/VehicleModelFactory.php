<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleModel>
 */
class VehicleModelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'name' => fake()->unique()->bothify('Modèle ??-##'),
            'oil_capacity_l' => null,
            'is_active' => true,
        ];
    }
}
