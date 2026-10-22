<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Client;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'plate' => fake()->unique()->numerify('#####-1##-##'),
            'vin' => null,
            'brand_id' => Brand::factory(),
            'vehicle_model_id' => null,
            'year' => fake()->numberBetween(2000, 2025),
            'energy_id' => null,
            'last_mileage' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }

    /**
     * A vehicle always has an open ownership for its current owner.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (Vehicle $vehicle) => VehicleOwnership::create([
            'vehicle_id' => $vehicle->id,
            'client_id' => $vehicle->client_id,
            'started_at' => now()->subYear()->toDateString(),
        ]));
    }
}
