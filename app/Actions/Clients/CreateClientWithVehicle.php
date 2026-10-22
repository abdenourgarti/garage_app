<?php

namespace App\Actions\Clients;

use App\Actions\Vehicles\CreateVehicle;
use App\Models\Client;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Création rapide (liste des clients, écran prestation) : client + premier véhicule en une seule fois.
 */
class CreateClientWithVehicle
{
    public function __construct(private CreateVehicle $createVehicle) {}

    /**
     * @param  array<string, mixed>  $client  validated client fields
     * @param  array<string, mixed>|null  $vehicle  validated vehicle fields, or null for a client alone
     * @return array{0: Client, 1: Vehicle|null}
     */
    public function handle(array $client, ?array $vehicle = null): array
    {
        return DB::transaction(function () use ($client, $vehicle) {
            $created = Client::create($client);

            return [$created, $vehicle === null ? null : $this->createVehicle->handle($created, $vehicle)];
        });
    }
}
