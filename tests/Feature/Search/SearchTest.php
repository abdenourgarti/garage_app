<?php

use App\Actions\ServiceOrders\CompleteServiceOrder;
use App\Actions\ServiceOrders\SaveServiceOrder;
use App\Enums\InvoiceStatus;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Employee;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\Wilaya;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Jeu commun : 2 clients (Alger, Blida), 3 véhicules, 5 prestations O1…O5 sur deux années.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->alger = Wilaya::factory()->create(['code' => '16', 'name' => 'Alger']);
    $this->blida = Wilaya::factory()->create(['code' => '09', 'name' => 'Blida']);
    $this->renault = Brand::factory()->create(['name' => 'Renault']);
    $this->peugeot = Brand::factory()->create(['name' => 'Peugeot']);
    $clio = VehicleModel::factory()->create(['brand_id' => $this->renault->id, 'name' => 'Clio 4']);
    $this->symbol = VehicleModel::factory()->create(['brand_id' => $this->renault->id, 'name' => 'Symbol']);

    $this->oil = ServiceType::factory()->create(['name' => 'Vidange moteur']);
    $this->brakes = ServiceType::factory()->create(['name' => 'Plaquettes de frein']);
    $this->timing = ServiceType::factory()->create(['name' => 'Kit de distribution']);

    $karim = Client::factory()->create(['first_name' => 'Karim', 'last_name' => 'Benali', 'phone' => '0550 11 22 33', 'wilaya_id' => $this->alger->id]);
    $nadia = Client::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Cherif', 'phone' => '0661 44 55 66', 'wilaya_id' => $this->blida->id]);

    $clioCar = Vehicle::factory()->for($karim)->create(['plate' => '01234-114-16', 'vin' => 'VF1ABC123', 'brand_id' => $this->renault->id, 'vehicle_model_id' => $clio->id, 'year' => 2014]);
    $peugeotCar = Vehicle::factory()->for($karim)->create(['plate' => '55555-119-16', 'brand_id' => $this->peugeot->id, 'year' => 2019]);
    $symbolCar = Vehicle::factory()->for($nadia)->create(['plate' => '77777-110-09', 'brand_id' => $this->renault->id, 'vehicle_model_id' => $this->symbol->id, 'year' => 2010]);

    $make = function (string $number, Vehicle $vehicle, string $date, array $types) {
        $order = ServiceOrder::factory()->create(['number' => $number, 'vehicle_id' => $vehicle->id, 'client_id' => $vehicle->client_id, 'date' => $date]);

        foreach ($types as $position => $type) {
            ServiceOrderLine::factory()->for($order)->create(['service_type_id' => $type->id, 'position' => $position]);
        }
    };

    $make('PR-2026-00026', $clioCar, '2026-01-10', [$this->oil]);
    $make('PR-2026-00002', $clioCar, '2026-06-05', [$this->brakes, $this->oil]);
    $make('PR-2026-00003', $peugeotCar, '2026-03-01', [$this->timing]);
    $make('PR-2026-00004', $symbolCar, '2026-06-20', [$this->oil]);
    $make('PR-2025-00005', $symbolCar, '2025-12-15', [$this->brakes]);
});

/**
 * Numéros des prestations trouvées, triés.
 *
 * @param  array<string, mixed>  $filters
 * @return list<string>
 */
function found(array $filters): array
{
    $numbers = [];

    test()->get(route('service-orders.index', $filters))->assertInertia(function (Assert $page) use (&$numbers) {
        $numbers = collect($page->toArray()['props']['rows']['data'])->pluck('number')->sort()->values()->all();
    });

    return $numbers;
}

test('each advanced filter alone', function () {
    $o1 = 'PR-2026-00026';
    [$o2, $o3, $o4, $o5] = ['PR-2026-00002', 'PR-2026-00003', 'PR-2026-00004', 'PR-2025-00005'];

    // Client : téléphone (début), nom, prénom, nom + prénom dans n'importe quel ordre.
    expect(found(['client' => '0550 11']))->toBe([$o2, $o3, $o1])
        ->and(found(['client' => '+213 661']))->toBe([$o5, $o4])
        ->and(found(['client' => 'cherif']))->toBe([$o5, $o4])
        ->and(found(['client' => 'karim benali']))->toBe([$o2, $o3, $o1]);

    expect(found(['wilaya' => $this->blida->id]))->toBe([$o5, $o4]);

    // Matricule (début ou milieu, tirets ignorés) et châssis.
    expect(found(['plate' => '01234']))->toBe([$o2, $o1])
        ->and(found(['plate' => '119-16']))->toBe([$o3])
        ->and(found(['plate' => 'vf1abc']))->toBe([$o2, $o1]);

    // Marque, modèle (seulement avec sa marque), année.
    expect(found(['brand' => $this->renault->id]))->toBe([$o5, $o2, $o4, $o1])
        ->and(found(['brand' => $this->renault->id, 'model' => $this->symbol->id]))->toBe([$o5, $o4])
        ->and(found(['model' => $this->symbol->id]))->toHaveCount(5)
        ->and(found(['year_from' => 2015]))->toBe([$o3])
        ->and(found(['year_to' => 2012]))->toBe([$o5, $o4])
        ->and(found(['year_from' => 2014, 'year_to' => 2014]))->toBe([$o2, $o1]);

    // Période.
    expect(found(['from' => '2026-06-01']))->toBe([$o2, $o4])
        ->and(found(['from' => '2026-01-01', 'to' => '2026-03-31']))->toBe([$o3, $o1])
        ->and(found(['to' => '2025-12-31']))->toBe([$o5]);

    // Types : au moins un des types cochés.
    expect(found(['types' => [$this->brakes->id]]))->toBe([$o5, $o2])
        ->and(found(['types' => [$this->brakes->id, $this->timing->id]]))->toBe([$o5, $o2, $o3])
        ->and(found(['types' => "{$this->brakes->id},{$this->timing->id}"]))->toBe([$o5, $o2, $o3]);
});

test('filters combine', function () {
    expect(found(['wilaya' => $this->alger->id, 'types' => [$this->oil->id], 'from' => '2026-06-01']))->toBe(['PR-2026-00002'])
        ->and(found(['brand' => $this->renault->id, 'year_to' => 2012, 'client' => 'nadia']))->toBe(['PR-2025-00005', 'PR-2026-00004'])
        ->and(found(['client' => 'karim', 'plate' => '55555']))->toBe(['PR-2026-00003'])
        ->and(found(['client' => 'karim', 'wilaya' => $this->blida->id]))->toBe([])
        ->and(found(['types' => [$this->oil->id], 'to' => '2026-01-31', 'brand' => $this->renault->id]))->toBe(['PR-2026-00026']);
});

test('the list shows the works, the invoice and the filter options', function () {
    $this->get(route('service-orders.index', ['brand' => $this->renault->id, 'plate' => '01234', 'from' => '2026-06-01']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-orders/index')
            ->has('rows.data', 1)
            ->where('rows.data.0.works', 'Plaquettes de frein, Vidange moteur')
            ->where('rows.data.0.invoice', null)
            ->has('serviceTypes', 3)
            ->where('filters.plate', '01234'));
});

test('the CSV export follows the filters and opens in Excel', function () {
    $response = $this->get(route('service-orders.export', ['types' => [$this->brakes->id]]))->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('prestations-');

    $csv = $response->streamedContent();
    $lines = explode("\n", trim($csv));

    expect(str_starts_with($csv, "\u{FEFF}"))->toBeTrue()
        ->and($lines[0])->toContain('N°;Date;Client;Téléphone;Wilaya;Matricule')
        ->and($lines)->toHaveCount(3)
        ->and($lines[1])->toContain('PR-2026-00002;05/06/2026;"Benali Karim";"0550 11 22 33";"16 Alger";01234-114-16;Renault;"Clio 4";2014')
        ->and($lines[1])->toContain('"Plaquettes de frein, Vidange moteur"')
        ->and($lines[1])->toContain('3000,00');
});

test('the global search groups clients, vehicles, invoices and orders', function () {
    $this->getJson(route('search', ['q' => 'k']))->assertExactJson(['clients' => [], 'vehicles' => [], 'invoices' => [], 'orders' => []]);

    $this->getJson(route('search', ['q' => '0550 11']))
        ->assertJsonCount(1, 'clients')
        ->assertJsonPath('clients.0.label', 'Benali Karim')
        ->assertJsonPath('clients.0.description', '0550 11 22 33 · 2 véhicules');

    $this->getJson(route('search', ['q' => '01234']))
        ->assertJsonCount(1, 'vehicles')
        ->assertJsonPath('vehicles.0.label', '01234-114-16');

    $this->getJson(route('search', ['q' => 'cherif']))
        ->assertJsonCount(1, 'clients')
        ->assertJsonCount(0, 'vehicles')
        ->assertJsonCount(0, 'orders');

    // « 26 » : la fin du numéro, pas l'année.
    $this->getJson(route('search', ['q' => '26']))
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.label', 'PR-2026-00026');

    Client::factory()->count(7)->create(['last_name' => 'Zitouni']);
    $this->getJson(route('search', ['q' => 'zitouni']))->assertJsonCount(5, 'clients');
});

test('S5: a complaint is found by phone or plate, the logbook shows the history, the redo is free', function () {
    $this->getJson(route('search', ['q' => '0661 44']))->assertJsonPath('clients.0.label', 'Cherif Nadia');
    $vehicleId = $this->getJson(route('search', ['q' => '77777']))->json('vehicles.0.id');

    $this->get(route('vehicles.show', $vehicleId))->assertInertia(fn (Assert $page) => $page
        ->has('serviceOrders', 2)
        ->where('serviceOrders.0.number', 'PR-2026-00004')
        ->where('serviceOrders.0.types', ['Vidange moteur']));

    $order = app(SaveServiceOrder::class)->handle(null, ['vehicle_id' => $vehicleId, 'date' => today()->toDateString(), 'notes' => 'Reprise : fuite après vidange'], [[
        'service_type_id' => $this->brakes->id, 'employee_id' => Employee::factory()->create()->id, 'labor_price' => '0', 'parts' => [],
    ]]);
    app(CompleteServiceOrder::class)->handle($order);

    expect($order->fresh()->invoice->total)->toBe('0.00')
        ->and($order->fresh()->invoice->status)->toBe(InvoiceStatus::Paid);
});
