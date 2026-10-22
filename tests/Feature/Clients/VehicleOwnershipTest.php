<?php

use App\Actions\Vehicles\TransferVehicleOwnership;
use App\Models\Client;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->vehicle = Vehicle::factory()->create(['last_mileage' => 120000]);
    $this->seller = $this->vehicle->client;
    $this->buyer = Client::factory()->create();
});

test('a transfer closes the current ownership and keeps the history', function () {
    $this->post(route('vehicles.transfer', $this->vehicle), ['client_id' => $this->buyer->id, 'date' => today()->toDateString()])
        ->assertSessionHasNoErrors();

    $this->vehicle->refresh();
    $ownerships = $this->vehicle->ownerships;

    expect($this->vehicle->client_id)->toBe($this->buyer->id)
        ->and($this->vehicle->last_mileage)->toBe(120000)
        ->and($ownerships)->toHaveCount(2)
        ->and($ownerships[0]->client_id)->toBe($this->buyer->id)
        ->and($ownerships[0]->ended_at)->toBeNull()
        ->and($ownerships[1]->client_id)->toBe($this->seller->id)
        ->and($ownerships[1]->ended_at->isToday())->toBeTrue()
        ->and($this->seller->fresh()->vehicles)->toHaveCount(0);
});

test('a vehicle can change owner several times without losing anyone', function () {
    $third = Client::factory()->create();
    $action = app(TransferVehicleOwnership::class);

    $action->handle($this->vehicle, $this->buyer, today()->subMonth());
    $action->handle($this->vehicle->fresh(), $third, today());

    expect(VehicleOwnership::where('vehicle_id', $this->vehicle->id)->pluck('client_id')->all())
        ->toEqualCanonicalizing([$this->seller->id, $this->buyer->id, $third->id])
        ->and(VehicleOwnership::where('vehicle_id', $this->vehicle->id)->whereNull('ended_at')->count())->toBe(1);
});

test('a vehicle can not be transferred to its current owner', function () {
    $this->post(route('vehicles.transfer', $this->vehicle), ['client_id' => $this->seller->id, 'date' => today()->toDateString()])
        ->assertSessionHasErrors(['client_id' => 'Ce client est déjà le propriétaire du véhicule.']);

    expect($this->vehicle->ownerships()->count())->toBe(1);
});

test('the transfer date can be neither in the future nor before the current ownership', function () {
    $this->post(route('vehicles.transfer', $this->vehicle), ['client_id' => $this->buyer->id, 'date' => today()->addDay()->toDateString()])
        ->assertSessionHasErrors('date');

    expect(fn () => app(TransferVehicleOwnership::class)->handle($this->vehicle, $this->buyer, today()->subYears(5)))
        ->toThrow(ValidationException::class);

    expect($this->vehicle->fresh()->client_id)->toBe($this->seller->id);
});

test('a vehicle can not be given to an inactive client', function () {
    $inactive = Client::factory()->inactive()->create();

    $this->post(route('vehicles.transfer', $this->vehicle), ['client_id' => $inactive->id, 'date' => today()->toDateString()])
        ->assertSessionHasErrors('client_id');
});
