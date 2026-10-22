<?php

use App\Models\Client;
use App\Models\User;
use App\Models\Vehicle;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->karim = Client::factory()->create(['last_name' => 'Benali', 'first_name' => 'Karim', 'phone' => '0550123456']);
    $this->vehicle = Vehicle::factory()->for($this->karim)->create(['plate' => '01234-118-16']);
});

test('clients are found by phone, name or plate', function (string $q) {
    $this->getJson(route('lookup.clients', ['q' => $q]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.value', (string) $this->karim->id)
        ->assertJsonPath('0.label', 'Benali Karim')
        ->assertJsonPath('0.description', '0550123456, 01234-118-16');
})->with(['0550 12', 'benali', 'Karim', '01234-118']);

test('a too short search returns nothing', function () {
    $this->getJson(route('lookup.clients', ['q' => 'B']))->assertExactJson([]);
});

test('the vehicles of a client and a vehicle by plate are listed with their owner', function () {
    $this->getJson(route('lookup.client-vehicles', $this->karim))
        ->assertJsonCount(1)
        ->assertJsonPath('0.label', '01234-118-16');

    $this->getJson(route('lookup.vehicles', ['plate' => '01234 118']))
        ->assertJsonCount(1)
        ->assertJsonPath('0.client.label', 'Benali Karim');
});

test('deactivated clients and vehicles disappear from the lookups but stay in the lists', function () {
    $this->patch(route('vehicles.toggle', $this->vehicle));
    $this->getJson(route('lookup.vehicles', ['plate' => '01234']))->assertExactJson([]);
    $this->getJson(route('lookup.client-vehicles', $this->karim))->assertExactJson([]);

    $this->patch(route('clients.toggle', $this->karim));
    $this->getJson(route('lookup.clients', ['q' => 'Benali']))->assertExactJson([]);

    expect($this->karim->fresh()->is_active)->toBeFalse();
    $this->get(route('clients.index', ['search' => 'Benali']))->assertInertia(fn ($page) => $page->has('rows.data', 1));
});

test('guests can not use the lookups', function () {
    auth()->logout();

    $this->getJson(route('lookup.clients', ['q' => 'Benali']))->assertUnauthorized();
});
