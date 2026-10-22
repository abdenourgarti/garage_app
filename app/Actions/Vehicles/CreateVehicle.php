<?php

namespace App\Actions\Vehicles;

use App\Models\Client;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Un véhicule naît avec sa première période de propriété : l'historique est complet dès le départ.
 */
class CreateVehicle
{
    /**
     * @param  array<string, mixed>  $data  validated vehicle fields
     */
    public function handle(Client $owner, array $data): Vehicle
    {
        return DB::transaction(function () use ($owner, $data) {
            $vehicle = $owner->vehicles()->create($data);

            $vehicle->ownerships()->create([
                'client_id' => $owner->id,
                'started_at' => today(),
            ]);

            return $vehicle;
        });
    }
}
