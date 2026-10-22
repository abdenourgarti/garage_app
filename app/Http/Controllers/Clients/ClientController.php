<?php

namespace App\Http\Controllers\Clients;

use App\Actions\Clients\CreateClientWithVehicle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Requests\Clients\QuickClientRequest;
use App\Models\Client;
use App\Models\Vehicle;
use App\Support\FormOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $wilayaId = $request->integer('wilaya') ?: null;

        $clients = Client::query()
            ->with(['wilaya:id,code,name', 'vehicles:id,client_id,plate'])
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when($wilayaId, fn ($query) => $query->where('wilaya_id', $wilayaId))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Client $client) => [
                'id' => $client->id,
                'full_name' => $client->full_name,
                'phone' => $client->phone,
                'wilaya' => $client->wilaya ? "{$client->wilaya->code} {$client->wilaya->name}" : null,
                'plates' => $client->vehicles->pluck('plate'),
                'is_active' => $client->is_active,
            ]);

        return Inertia::render('clients/index', [
            'rows' => $clients,
            'filters' => ['search' => $search, 'wilaya' => $wilayaId],
            'options' => FormOptions::clientVehicle(),
        ]);
    }

    public function show(Client $client): Response
    {
        $client->load(['wilaya:id,code,name', 'vehicles' => fn ($query) => $query->with(['brand:id,name', 'vehicleModel:id,name'])->orderBy('plate')]);

        return Inertia::render('clients/show', [
            'client' => [
                ...$client->only(['id', 'first_name', 'last_name', 'full_name', 'phone', 'wilaya_id', 'notes', 'is_active']),
                'wilaya' => $client->wilaya ? "{$client->wilaya->code} {$client->wilaya->name}" : null,
                'created_at' => $client->created_at?->toDateString(),
            ],
            'vehicles' => $client->vehicles->map(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'label' => $vehicle->label(),
                'year' => $vehicle->year,
                'last_mileage' => $vehicle->last_mileage,
                'is_active' => $vehicle->is_active,
            ]),
            'options' => FormOptions::clientVehicle(),
        ]);
    }

    /**
     * Quick creation (JSON): used from the client list and, later, the service order screen.
     */
    public function quickStore(QuickClientRequest $request, CreateClientWithVehicle $action): JsonResponse
    {
        [$client, $vehicle] = $action->handle($request->clientData(), $request->vehicleData());

        return response()->json([
            'client' => ['value' => (string) $client->id, 'label' => $client->full_name, 'description' => $client->phone],
            'vehicle' => $vehicle ? ['value' => (string) $vehicle->id, 'label' => $vehicle->plate, 'description' => $vehicle->label()] : null,
        ], 201);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Client enregistré.']);

        return back();
    }

    public function toggle(Client $client): RedirectResponse
    {
        $client->update(['is_active' => ! $client->is_active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $client->is_active ? 'Client réactivé.' : 'Client désactivé : il n\'apparaît plus dans les recherches.']);

        return back();
    }
}
