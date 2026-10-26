<?php

use App\Enums\OilFormat;
use App\Enums\PartOrigin;
use App\Models\Article;
use App\Models\OilProduct;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use App\Models\ServiceOrderPart;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-26 10:00'));
    $this->actingAs(User::factory()->create());
    $this->oil = ServiceType::factory()->create(['name' => 'Vidange moteur', 'reminder_km' => 10000, 'default_labor_price' => '1500']);
    $this->timing = ServiceType::factory()->create(['name' => 'Kit de distribution', 'reminder_km' => 60000]);
    $this->vehicle = Vehicle::factory()->create(['last_mileage' => null]);
});

/**
 * Prestation terminée avec km et une ligne par type donné.
 */
function doneOrder(Vehicle $vehicle, string $date, ?int $mileage, array $types): ServiceOrder
{
    $order = ServiceOrder::factory()->completed()->create(['vehicle_id' => $vehicle->id, 'date' => $date, 'mileage' => $mileage]);

    foreach ($types as $type) {
        ServiceOrderLine::factory()->create(['service_order_id' => $order->id, 'service_type_id' => $type->id, 'labor_price' => '1500']);
    }

    return $order;
}

function reading(int $mileage, string $date): object
{
    return (object) ['mileage' => $mileage, 'date' => $date];
}

test('estimated mileage: none, one reading, two readings, and readings that make no sense', function () {
    $today = CarbonImmutable::parse('2026-10-26');

    // 0 relevé : le km de la fiche véhicule, ou rien.
    expect(ReminderService::estimate([], null, $today))->toBeNull()
        ->and(ReminderService::estimate([], 42000, $today))->toBe(42000)
        // 1 relevé : ce km.
        ->and(ReminderService::estimate([reading(50000, '2026-06-01')], 42000, $today))->toBe(50000)
        // 2 relevés : 3 000 km en 60 jours = 50 km/jour ; 25 jours depuis le dernier → + 1 250.
        ->and(ReminderService::estimate([reading(53000, '2026-10-01'), reading(50000, '2026-08-02')], null, $today))->toBe(54250)
        // Même jour, ou compteur qui recule : le dernier km.
        ->and(ReminderService::estimate([reading(53000, '2026-10-01'), reading(50000, '2026-10-01')], null, $today))->toBe(53000)
        ->and(ReminderService::estimate([reading(49000, '2026-10-01'), reading(50000, '2026-08-02')], null, $today))->toBe(49000);
});

test('the threshold decides between OK, soon and overdue', function () {
    expect(ReminderService::status(1001, 1000))->toBe('ok')
        ->and(ReminderService::status(1000, 1000))->toBe('soon')
        ->and(ReminderService::status(1, 1000))->toBe('soon')
        ->and(ReminderService::status(0, 1000))->toBe('overdue')
        ->and(ReminderService::status(-500, 1000))->toBe('overdue')
        ->and(ReminderService::status(null, 1000))->toBe('ok');
});

test('one due date per reminder type, from the last completed service order of that type', function () {
    doneOrder($this->vehicle, '2026-02-01', 40000, [$this->oil, $this->timing]);
    doneOrder($this->vehicle, '2026-08-02', 50000, [$this->oil]);
    // Une prestation en cours compte pour le km, pas pour l'échéance.
    ServiceOrder::factory()->create(['vehicle_id' => $this->vehicle->id, 'date' => '2026-10-01', 'mileage' => 53000]);

    $reminder = app(ReminderService::class)->forVehicles([$this->vehicle->id])->get($this->vehicle->id);

    // 53 000 − 50 000 en 60 jours, + 25 jours → 54 250.
    expect($reminder['estimated_km'])->toBe(54250)
        ->and($reminder['due'])->toBe([
            ['type_id' => $this->oil->id, 'type' => 'Vidange moteur', 'done_at' => '2026-08-02', 'done_km' => 50000, 'due_km' => 60000, 'remaining_km' => 5750, 'status' => 'ok'],
            ['type_id' => $this->timing->id, 'type' => 'Kit de distribution', 'done_at' => '2026-02-01', 'done_km' => 40000, 'due_km' => 100000, 'remaining_km' => 45750, 'status' => 'ok'],
        ])
        ->and($reminder['status'])->toBe('ok');

    // Sans prestation : pas d'échéance.
    $empty = Vehicle::factory()->create(['last_mileage' => 12000]);
    expect(app(ReminderService::class)->forVehicles([$empty->id])->get($empty->id))
        ->toBe(['estimated_km' => 12000, 'last_km_date' => null, 'due' => [], 'status' => 'ok']);
});

test('the reminders list: urgent first, called vehicles last until the next oil change, inactive ones hidden', function () {
    // Dépassée : vidange à 50 000 (échéance 60 000), 100 km/jour → 62 000 aujourd'hui.
    $late = $this->vehicle;
    doneOrder($late, '2026-06-28', 50000, [$this->oil]);
    doneOrder($late, '2026-09-26', 59000, []);
    // Bientôt : vidange à 51 000 (échéance 61 000), relevé du jour 60 500 → reste 500 km.
    $soon = Vehicle::factory()->create();
    doneOrder($soon, '2026-10-26', 51000, [$this->oil]);
    ServiceOrder::factory()->create(['vehicle_id' => $soon->id, 'date' => '2026-10-26', 'mileage' => 60500]);
    // OK : vidange récente.
    $fine = Vehicle::factory()->create();
    doneOrder($fine, '2026-10-20', 70000, [$this->oil]);
    // Inactif : jamais listé.
    $gone = Vehicle::factory()->create(['is_active' => false]);
    doneOrder($gone, '2025-01-01', 10000, [$this->oil]);

    $this->get(route('reminders.index'))->assertInertia(fn (Assert $page) => $page
        ->component('vehicles/reminders')
        ->has('reminders', 2)
        ->where('reminders.0.id', $late->id)
        ->where('reminders.0.status', 'overdue')
        ->where('reminders.0.remaining_km', -2000)
        ->where('reminders.0.due.0.type', 'Vidange moteur')
        ->where('reminders.1.id', $soon->id)
        ->where('reminders.1.status', 'soon')
        ->where('reminders.1.called', false));

    $this->post(route('reminders.call', $late))->assertSessionHasNoErrors();

    $this->get(route('reminders.index'))->assertInertia(fn (Assert $page) => $page
        ->where('reminders.0.id', $soon->id)
        ->where('reminders.1.id', $late->id)
        ->where('reminders.1.called', true));

    // La vidange est faite : le véhicule sort de la liste.
    $this->travel(1)->days();
    doneOrder($late, '2026-10-27', 61500, [$this->oil]);

    $this->get(route('reminders.index'))->assertInertia(fn (Assert $page) => $page
        ->has('reminders', 1)
        ->where('reminders.0.id', $soon->id));
});

test('the vehicle page shows the next oil change, the dashboard counts the due ones', function () {
    doneOrder($this->vehicle, '2026-06-28', 50000, [$this->oil]);
    doneOrder($this->vehicle, '2026-09-26', 59000, []);

    $this->get(route('vehicles.show', $this->vehicle))->assertInertia(fn (Assert $page) => $page
        ->where('reminder.estimated_km', 62000)
        ->where('reminder.status', 'overdue')
        ->where('reminder.due.0.due_km', 60000));

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('dueOilChanges', 1)
        ->where('dueOilChanges.0.plate', $this->vehicle->plate));
});

test('the maintenance report lists the whole history, with or without prices', function () {
    $oil = OilProduct::factory()->create(['brand' => 'Total', 'name' => 'Quartz 9000', 'viscosity' => '5W-40']);
    $can = Article::factory()->oil(OilFormat::Can5L, $oil)->create();
    $bulk = Article::factory()->oil(OilFormat::Bulk, $oil)->create();

    $first = doneOrder($this->vehicle, '2026-02-01', 40000, [$this->oil]);
    $line = $first->lines()->first();
    ServiceOrderPart::factory()->create(['service_order_id' => $first->id, 'service_order_line_id' => $line->id, 'origin' => PartOrigin::Stock, 'article_id' => $can->id, 'designation' => $can->name, 'quantity' => '1', 'unit_price' => '5200', 'line_total' => '5200']);
    $first->update(['advice' => 'Prévoir les plaquettes avant']);
    $second = doneOrder($this->vehicle, '2026-08-02', 50000, [$this->oil]);
    ServiceOrderPart::factory()->create(['service_order_id' => $second->id, 'service_order_line_id' => $second->lines()->first()->id, 'origin' => PartOrigin::Stock, 'article_id' => $bulk->id, 'designation' => $bulk->name, 'quantity' => '4.5', 'unit_price' => '1000', 'line_total' => '4500']);

    $with = $this->get(route('vehicles.report.document', [$this->vehicle, 'a4']))->assertOk()->getContent();
    $nbsp = "\u{00A0}";

    expect($with)->toContain('BILAN D')
        ->toContain('01/02/2026')->toContain("40{$nbsp}000{$nbsp}km")
        ->toContain('Total Quartz 9000 5W-40, bidon 5 L')->toContain('× 1')
        ->toContain('Total Quartz 9000 5W-40, vrac')->toContain('4,5 L')
        ->toContain('Prévoir les plaquettes avant')
        ->toContain("5{$nbsp}200{$nbsp}DA")->toContain("6{$nbsp}700{$nbsp}DA")
        ->toContain('Prochains entretiens conseillés')->toContain("60{$nbsp}000{$nbsp}km")
        // Ordre chronologique.
        ->and(strpos($with, '01/02/2026'))->toBeLessThan(strpos($with, '02/08/2026'));

    $without = $this->get(route('vehicles.report.document', [$this->vehicle, 'a4', 'prices' => 0]))->assertOk()->getContent();

    expect($without)->toContain('Total Quartz 9000 5W-40, vrac')->toContain('Prévoir les plaquettes avant')
        ->not->toContain('DA<')->not->toContain("{$nbsp}DA")->not->toContain('Montant');

    $pdf = $this->get(route('vehicles.report.document', [$this->vehicle, 'pdf', 'prices' => 0]))->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf')
        ->and($pdf->headers->get('content-disposition'))->toContain('bilan-');

    $this->get(route('vehicles.report', $this->vehicle))->assertInertia(fn (Assert $page) => $page
        ->component('vehicles/report')
        ->where('vehicle.id', $this->vehicle->id));
});
