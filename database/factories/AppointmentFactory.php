<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'client_id' => fn (array $attributes) => Vehicle::find($attributes['vehicle_id'])->client_id,
            'scheduled_at' => today()->setTime(9, 0),
            'notes' => null,
            'status' => AppointmentStatus::Planned,
            'user_id' => User::factory(),
        ];
    }
}
