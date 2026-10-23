<?php

namespace Database\Factories;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Prestation vide ; les vraies prestations passent par SaveServiceOrder (numéro, lignes, pièces).
 *
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vehicle = Vehicle::factory();

        return [
            'number' => 'PR-'.now()->year.'-'.fake()->unique()->numerify('#####'),
            'vehicle_id' => $vehicle,
            'client_id' => fn (array $attributes) => Vehicle::find($attributes['vehicle_id'])->client_id,
            'date' => today(),
            'mileage' => null,
            'status' => ServiceOrderStatus::InProgress,
            'user_id' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServiceOrderStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
