<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Vehicle;
use App\Support\Normalize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherches JSON pour SearchSelect : éléments actifs, 10 résultats, format {value, label, description}.
 */
class LookupController extends Controller
{
    private const LIMIT = 10;

    public function clients(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $clients = Client::active()
            ->with(['vehicles' => fn ($query) => $query->active()->select(['id', 'client_id', 'plate'])])
            ->search($term)
            ->orderBy('last_name')
            ->limit(self::LIMIT)
            ->get();

        return response()->json($clients->map(fn (Client $client) => [
            'value' => (string) $client->id,
            'label' => $client->full_name,
            'description' => collect([$client->phone, ...$client->vehicles->pluck('plate')])->implode(', '),
        ]));
    }

    public function clientVehicles(Client $client): JsonResponse
    {
        $vehicles = $client->vehicles()->active()->with(['brand:id,name', 'vehicleModel:id,name'])->orderBy('plate')->get();

        return response()->json($vehicles->map(fn (Vehicle $vehicle) => $this->vehicleOption($vehicle)));
    }

    public function vehicles(Request $request): JsonResponse
    {
        $plate = Normalize::plate($request->string('plate')->toString());

        if (mb_strlen($plate) < 2) {
            return response()->json([]);
        }

        $vehicles = Vehicle::active()
            ->with(['brand:id,name', 'vehicleModel:id,name', 'client:id,first_name,last_name,phone'])
            ->where('plate_normalized', 'like', "{$plate}%")
            ->orderBy('plate_normalized')
            ->limit(self::LIMIT)
            ->get();

        return response()->json($vehicles->map(fn (Vehicle $vehicle) => [
            ...$this->vehicleOption($vehicle),
            'client' => ['value' => (string) $vehicle->client->id, 'label' => $vehicle->client->full_name, 'description' => $vehicle->client->phone],
        ]));
    }

    /**
     * @return array{value: string, label: string, description: string}
     */
    private function vehicleOption(Vehicle $vehicle): array
    {
        return [
            'value' => (string) $vehicle->id,
            'label' => $vehicle->plate,
            'description' => trim($vehicle->label().' '.$vehicle->year),
        ];
    }
}
