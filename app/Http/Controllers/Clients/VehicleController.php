<?php

namespace App\Http\Controllers\Clients;

use App\Actions\Vehicles\CreateVehicle;
use App\Actions\Vehicles\TransferVehicleOwnership;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ServiceOrders\ServiceOrderController;
use App\Http\Requests\Clients\TransferVehicleRequest;
use App\Http\Requests\Clients\VehicleRequest;
use App\Models\Brand;
use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $brandId = $request->integer('brand') ?: null;

        $vehicles = Vehicle::query()
            ->with(['brand:id,name', 'vehicleModel:id,name', 'client:id,first_name,last_name,phone'])
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->orderBy('plate_normalized')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'label' => $vehicle->label(),
                'year' => $vehicle->year,
                'owner' => ['id' => $vehicle->client->id, 'full_name' => $vehicle->client->full_name, 'phone' => $vehicle->client->phone],
                'last_mileage' => $vehicle->last_mileage,
                'is_active' => $vehicle->is_active,
            ]);

        return Inertia::render('vehicles/index', [
            'rows' => $vehicles,
            'filters' => ['search' => $search, 'brand' => $brandId],
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Vehicle $vehicle): Response
    {
        $vehicle->load([
            'brand:id,name', 'vehicleModel:id,name', 'energy:id,name',
            'client:id,first_name,last_name,phone,is_active',
            'ownerships.client:id,first_name,last_name,phone',
        ]);

        return Inertia::render('vehicles/show', [
            'vehicle' => [
                ...$vehicle->only(['id', 'plate', 'vin', 'brand_id', 'vehicle_model_id', 'year', 'energy_id', 'last_mileage', 'notes', 'is_active']),
                'label' => $vehicle->label(),
                'brand' => $vehicle->brand->name,
                'model' => $vehicle->vehicleModel?->name,
                'energy' => $vehicle->energy?->name,
                'owner' => [...$vehicle->client->only(['id', 'full_name', 'phone', 'is_active'])],
                'ownerships' => $vehicle->ownerships->map(fn (VehicleOwnership $ownership) => [
                    'id' => $ownership->id,
                    'client' => $ownership->client->only(['id', 'full_name', 'phone']),
                    'started_at' => $ownership->started_at->toDateString(),
                    'ended_at' => $ownership->ended_at?->toDateString(),
                ]),
            ],
            'serviceOrders' => ServiceOrderController::history(ServiceOrder::where('vehicle_id', $vehicle->id)),
            'options' => FormOptions::clientVehicle(),
        ]);
    }

    public function store(VehicleRequest $request, Client $client, CreateVehicle $action): RedirectResponse
    {
        $action->handle($client, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Véhicule ajouté.']);

        return back();
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Véhicule enregistré.']);

        return back();
    }

    public function toggle(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update(['is_active' => ! $vehicle->is_active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $vehicle->is_active ? 'Véhicule réactivé.' : 'Véhicule désactivé : il n\'apparaît plus dans les recherches.']);

        return back();
    }

    public function transfer(TransferVehicleRequest $request, Vehicle $vehicle, TransferVehicleOwnership $action): RedirectResponse
    {
        $action->handle($vehicle, Client::findOrFail($request->integer('client_id')), Carbon::parse($request->validated('date')));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Propriétaire changé. L\'historique du véhicule est conservé.']);

        return back();
    }
}
