<?php

use App\Models\Brand;
use App\Models\Client;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\Wilaya;
use App\Support\Normalize;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('phones and plates are normalized', function (string $raw, string $expected) {
    expect(Normalize::phone($raw))->toBe($expected);
})->with([
    ['0550 12 34 56', '0550123456'],
    ['0550-12-34-56', '0550123456'],
    ['+213 550 12 34 56', '0550123456'],
    ['00213550123456', '0550123456'],
    ['021 23 45 67', '021234567'],
]);

test('plates keep letters and digits only, in upper case', function () {
    expect(Normalize::plate('01234-118-16'))->toBe('0123411816')
        ->and(Normalize::plate(' 01234 118 16 '))->toBe('0123411816')
        ->and(Normalize::plate('ab-123.cd'))->toBe('AB123CD')
        ->and(Normalize::vin(' vf1 abc '))->toBe('VF1ABC')
        ->and(Normalize::vin('  '))->toBeNull();
});

test('a client and his first vehicle are created together with an ownership', function () {
    $brand = Brand::factory()->create();
    $model = VehicleModel::factory()->for($brand)->create();

    $this->postJson(route('clients.quick-store'), [
        'last_name' => 'Benali', 'first_name' => 'Karim', 'phone' => '0550 12 34 56',
        'with_vehicle' => true, 'plate' => '01234-118-16', 'brand_id' => $brand->id,
        'vehicle_model_id' => $model->id, 'year' => 2019, 'last_mileage' => 85000,
    ])
        ->assertCreated()
        ->assertJsonPath('client.label', 'Benali Karim')
        ->assertJsonPath('vehicle.label', '01234-118-16');

    $vehicle = Vehicle::with('ownerships')->sole();
    expect($vehicle->client->phone_normalized)->toBe('0550123456')
        ->and($vehicle->plate_normalized)->toBe('0123411816')
        ->and($vehicle->last_mileage)->toBe(85000)
        ->and($vehicle->ownerships)->toHaveCount(1)
        ->and($vehicle->ownerships->first()->ended_at)->toBeNull();
});

test('a client can be created alone', function () {
    $this->postJson(route('clients.quick-store'), ['last_name' => 'Haddad', 'phone' => '0661987654'])
        ->assertCreated()
        ->assertJsonPath('vehicle', null);

    expect(Client::count())->toBe(1)->and(Vehicle::count())->toBe(0);
});

test('nothing is saved when the vehicle part is invalid', function () {
    $this->postJson(route('clients.quick-store'), ['last_name' => 'Haddad', 'phone' => '0661987654', 'with_vehicle' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['plate', 'brand_id']);

    expect(Client::count())->toBe(0);
});

test('the phone is unique whatever its format', function () {
    Client::factory()->create(['last_name' => 'Benali', 'first_name' => 'Karim', 'phone' => '0550 12 34 56']);

    $this->postJson(route('clients.quick-store'), ['last_name' => 'Autre', 'phone' => '+213 550-12-34-56'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Ce numéro appartient déjà au client Benali Karim.']);
});

test('the plate and the VIN are unique whatever their format', function () {
    $existing = Vehicle::factory()->create(['plate' => '01234-118-16', 'vin' => 'VF1ABC123']);
    $client = Client::factory()->create();

    $this->post(route('vehicles.store', $client), ['plate' => '01234 118 16', 'vin' => 'vf1 abc123', 'brand_id' => $existing->brand_id])
        ->assertSessionHasErrors(['plate', 'vin']);

    expect($client->vehicles()->count())->toBe(0);
});

test('a phone must have 9 to 13 digits', function () {
    $this->postJson(route('clients.quick-store'), ['last_name' => 'X', 'phone' => '0550'])
        ->assertJsonValidationErrors(['phone' => 'Le numéro de téléphone doit contenir entre 9 et 13 chiffres.']);
});

test('a vehicle model must belong to the chosen brand', function () {
    $peugeot = Brand::factory()->create();
    $renaultModel = VehicleModel::factory()->create();

    $this->post(route('vehicles.store', Client::factory()->create()), ['plate' => '11111-111-16', 'brand_id' => $peugeot->id, 'vehicle_model_id' => $renaultModel->id])
        ->assertSessionHasErrors('vehicle_model_id');
});

test('a client can be updated and keeps his phone', function () {
    $client = Client::factory()->create(['phone' => '0550123456']);

    $this->put(route('clients.update', $client), ['last_name' => 'Nouveau', 'phone' => '0550 12 34 56'])
        ->assertSessionHasNoErrors();

    expect($client->fresh()->last_name)->toBe('Nouveau');
});

test('the client list searches by phone, name and plate, and filters by wilaya', function () {
    $alger = Wilaya::factory()->create(['code' => '16']);
    $karim = Client::factory()->create(['last_name' => 'Benali', 'first_name' => 'Karim', 'phone' => '0550123456', 'wilaya_id' => $alger->id]);
    Vehicle::factory()->for($karim)->create(['plate' => '01234-118-16']);
    Client::factory()->create(['last_name' => 'Haddad', 'first_name' => 'Samira', 'phone' => '0661987654']);

    $search = fn (array $query) => $this->get(route('clients.index', $query))->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.full_name', 'Benali Karim'));

    $search(['search' => '0550 12']);
    $search(['search' => 'karim benali']);
    $search(['search' => 'Ben']);
    $search(['search' => '01234 118']);
    $search(['wilaya' => $alger->id]);
});

test('a client page and a vehicle page can be opened by both roles', function () {
    $vehicle = Vehicle::factory()->create();

    foreach ([User::factory()->create(), User::factory()->gerant()->create()] as $user) {
        $this->actingAs($user)->get(route('clients.show', $vehicle->client))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('clients/show')->has('vehicles', 1));
        $this->actingAs($user)->get(route('vehicles.show', $vehicle))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('vehicles/show')->has('vehicle.ownerships', 1));
        $this->actingAs($user)->get(route('vehicles.index'))->assertOk();
    }
});
