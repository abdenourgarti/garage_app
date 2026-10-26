<?php

use App\Enums\OilFormat;
use App\Enums\SalaryType;
use App\Enums\ServiceOrderStatus;
use App\Models\Article;
use App\Models\Employee;
use App\Models\OilProduct;
use App\Models\ServiceOrder;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->employe = User::factory()->create();
    $this->gerant = User::factory()->gerant()->create();
    $this->actingAs($this->employe);

    $this->vehicle = Vehicle::factory()->create(['plate' => '01234-118-16', 'last_mileage' => 50000]);
    $this->type = ServiceType::factory()->create(['name' => 'Vidange moteur', 'requires_mileage' => true]);
    $this->mechanic = Employee::factory()->create(['name' => 'Yacine']);
});

function orderPayload(array $overrides = []): array
{
    return [
        'vehicle_id' => test()->vehicle->id,
        'date' => today()->toDateString(),
        'mileage' => 60000,
        'lines' => [[
            'service_type_id' => test()->type->id,
            'employee_id' => test()->mechanic->id,
            'labor_price' => '1500',
            'parts' => [['origin' => 'client', 'designation' => 'Huile apportée', 'quantity' => '4']],
        ]],
        ...$overrides,
    ];
}

test('both roles open the list, the form and an order', function () {
    $order = ServiceOrder::factory()->for($this->vehicle)->create();

    foreach ([$this->employe, $this->gerant] as $user) {
        $this->actingAs($user)->get(route('service-orders.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('service-orders/index'));
        $this->get(route('service-orders.create', ['vehicle' => $this->vehicle->id]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('vehicle.label', '01234-118-16')->where('vehicle.last_mileage', 50000));
        $this->get(route('service-orders.show', $order))->assertOk();
    }
});

test('« Terminer et facturer » saves and completes in one request', function () {
    $this->post(route('service-orders.store'), orderPayload(['complete' => true]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    $order = ServiceOrder::sole();
    expect($order->status)->toBe(ServiceOrderStatus::Completed)
        ->and($this->vehicle->fresh()->last_mileage)->toBe(60000);

    $this->get(route('service-orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.total', '1500.00')->where('order.lines.0.parts.0.line_total', '0.00'));
});

test('nothing is saved when the completion fails (all or nothing)', function () {
    $this->post(route('service-orders.store'), orderPayload(['mileage' => null, 'complete' => true]))
        ->assertSessionHasErrors('mileage');

    expect(ServiceOrder::count())->toBe(0);
});

test('an order can be saved in progress without mileage nor mechanic, then edited', function () {
    $payload = orderPayload(['mileage' => null]);
    $payload['lines'][0]['employee_id'] = null;

    $this->post(route('service-orders.store'), $payload)->assertSessionHasNoErrors();
    $order = ServiceOrder::sole();
    expect($order->status)->toBe(ServiceOrderStatus::InProgress);

    $this->put(route('service-orders.update', $order), orderPayload(['advice' => 'Plaquettes à surveiller']))->assertSessionHasNoErrors();
    expect($order->fresh()->advice)->toBe('Plaquettes à surveiller');

    $this->post(route('service-orders.complete', $order))->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe(ServiceOrderStatus::Completed);
});

test('part rules: stock needs an article, external needs a purchase price', function () {
    $payload = orderPayload();
    $payload['lines'][0]['parts'] = [
        ['origin' => 'stock', 'quantity' => '1', 'unit_price' => '100'],
        ['origin' => 'external', 'designation' => 'Rotule', 'quantity' => '1', 'unit_price' => '3000'],
    ];

    $this->post(route('service-orders.store'), $payload)
        ->assertSessionHasErrors(['lines.0.parts.0.article_id', 'lines.0.parts.1.unit_cost']);
});

test('only the gerant deletes an order, and only while in progress', function () {
    $order = ServiceOrder::factory()->for($this->vehicle)->create();

    $this->delete(route('service-orders.destroy', $order))->assertForbidden();
    $this->get(route('service-orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('order.can_delete', false));

    $this->actingAs($this->gerant)->get(route('service-orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('order.can_delete', true));
    $this->delete(route('service-orders.destroy', $order))->assertRedirect(route('service-orders.index'));
    expect(ServiceOrder::count())->toBe(0);

    $done = ServiceOrder::factory()->for($this->vehicle)->completed()->create();
    $this->delete(route('service-orders.destroy', $done))->assertSessionHasErrors('status');
    expect($done->fresh())->not->toBeNull();
});

test('a completed order can not be edited', function () {
    $done = ServiceOrder::factory()->for($this->vehicle)->completed()->create();

    $this->get(route('service-orders.edit', $done))->assertRedirect(route('service-orders.show', $done));
    $this->put(route('service-orders.update', $done), orderPayload())->assertSessionHasErrors('status');
});

test('the list filters by status, mechanic, date and plate', function () {
    $this->post(route('service-orders.store'), orderPayload(['complete' => true]));
    ServiceOrder::factory()->create(['date' => today()->subMonth()]);

    $count = fn (array $query, int $expected) => $this->get(route('service-orders.index', $query))->assertInertia(fn (Assert $page) => $page->has('rows.data', $expected));

    $count([], 2);
    $count(['status' => 'terminee'], 1);
    $count(['employee' => $this->mechanic->id], 1);
    $count(['from' => today()->toDateString()], 1);
    $count(['search' => '01234 118'], 1);
});

test('the logbook of the vehicle and the client tab list the orders', function () {
    $this->post(route('service-orders.store'), orderPayload(['complete' => true, 'advice' => 'Revoir freins']));

    $this->get(route('vehicles.show', $this->vehicle))
        ->assertInertia(fn (Assert $page) => $page->has('serviceOrders', 1)->where('serviceOrders.0.works', 'Vidange moteur')->where('serviceOrders.0.mileage', 60000));
    $this->get(route('clients.show', $this->vehicle->client))
        ->assertInertia(fn (Assert $page) => $page->has('serviceOrders', 1)->where('serviceOrders.0.plate', '01234-118-16'));
});

test('the vehicle lookup finds by plate or owner phone, the oil lookup returns the formats', function () {
    $this->getJson(route('lookup.service-vehicles', ['q' => $this->vehicle->client->phone]))
        ->assertJsonPath('0.label', '01234-118-16')
        ->assertJsonPath('0.client.id', $this->vehicle->client_id);

    $oil = OilProduct::factory()->create(['brand' => 'Total', 'name' => 'Quartz', 'viscosity' => '5W-30']);
    Article::factory()->oil(OilFormat::Can5L, $oil)->create();
    Article::factory()->oil(OilFormat::Bulk, $oil)->create();

    $this->getJson(route('lookup.oils', ['q' => 'quartz 5w']))
        ->assertJsonCount(1)
        ->assertJsonPath('0.formats.0.format', 'can_5l')
        ->assertJsonPath('0.formats.1.unit', 'L');
});

test('the gerant manages the employees, the employe can not', function () {
    $this->get(route('employees.index'))->assertForbidden();

    $this->actingAs($this->gerant)->post(route('employees.store'), ['name' => 'Samir', 'position' => 'Électricien', 'phone' => '0550 00 00 00'])->assertSessionHasErrors(['salary_type', 'salary_amount']);
    $this->post(route('employees.store'), ['name' => 'Samir', 'position' => 'Électricien', 'phone' => '0550 00 00 00', 'salary_type' => 'daily', 'salary_amount' => '1800'])->assertSessionHasNoErrors();
    $samir = Employee::where('name', 'Samir')->sole();
    expect($samir->salary_type)->toBe(SalaryType::Daily)->and($samir->salary_amount)->toBe('1800.00');
    $this->patch(route('employees.toggle', $samir));

    expect($samir->fresh()->is_active)->toBeFalse();
    $this->get(route('service-orders.create'))->assertInertia(fn (Assert $page) => $page->has('employees', 1));
});
