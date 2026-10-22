<?php

namespace App\Actions\Vehicles;

use App\Models\Client;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * « Changer de propriétaire » (RG-04) : seule la propriété change ; les prestations
 * restent attachées au véhicule et l'ancienne période est conservée.
 */
class TransferVehicleOwnership
{
    public function handle(Vehicle $vehicle, Client $newOwner, CarbonInterface $date): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $newOwner, $date) {
            // Verrou : deux transferts simultanés ne doivent pas ouvrir deux périodes.
            $vehicle = Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            if ($vehicle->client_id === $newOwner->id) {
                throw ValidationException::withMessages(['client_id' => 'Ce client est déjà le propriétaire du véhicule.']);
            }

            $current = VehicleOwnership::where('vehicle_id', $vehicle->id)->whereNull('ended_at')->first();

            if ($current && $date->lt($current->started_at)) {
                throw ValidationException::withMessages([
                    'date' => 'La date doit être postérieure au '.$current->started_at->format('d/m/Y').', début du propriétaire actuel.',
                ]);
            }

            $current?->update(['ended_at' => $date]);

            VehicleOwnership::create([
                'vehicle_id' => $vehicle->id,
                'client_id' => $newOwner->id,
                'started_at' => $date,
            ]);

            $vehicle->client_id = $newOwner->id;
            $vehicle->save();

            return $vehicle;
        });
    }
}
