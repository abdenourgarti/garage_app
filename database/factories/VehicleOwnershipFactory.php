<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleOwnership>
 */
class VehicleOwnershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'client_id' => Client::factory(),
            'started_at' => now()->subYear()->toDateString(),
            'ended_at' => null,
        ];
    }
}
