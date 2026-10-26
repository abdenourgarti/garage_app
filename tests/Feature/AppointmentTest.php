<?php

use App\Enums\AppointmentStatus;
use App\Enums\ServiceOrderStatus;
use App\Models\Appointment;
use App\Models\ServiceOrder;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-26 08:00'));
    // Le rendez-vous est l'affaire de l'employé autant que du gérant.
    $this->actingAs(User::factory()->create());
    $this->vehicle = Vehicle::factory()->create();
    $this->drain = ServiceType::factory()->create(['name' => 'Vidange moteur', 'default_labor_price' => '1500']);
    $this->filter = ServiceType::factory()->create(['name' => 'Filtre à air', 'default_labor_price' => '500']);
});

test('S4: booked for tomorrow, shown on the day, started into a pre-filled service order', function () {
    $this->post(route('appointments.store'), [
        'client_id' => $this->vehicle->client_id,
        'vehicle_id' => $this->vehicle->id,
        'date' => '2026-10-27',
        'time' => '09:30',
        'service_type_ids' => [$this->drain->id, $this->filter->id],
        'notes' => 'Apporte son huile',
    ])->assertSessionHasNoErrors();

    $appointment = Appointment::sole();
    expect($appointment->status)->toBe(AppointmentStatus::Planned)
        ->and($appointment->scheduled_at->toDateTimeString())->toBe('2026-10-27 09:30:00');

    // Aujourd'hui : rien.
    $this->get(route('appointments.index'))->assertInertia(fn (Assert $page) => $page
        ->component('appointments/index')
        ->where('view', 'day')
        ->has('appointments', 0));

    // Le jour J : dans la liste du jour et sur le tableau de bord.
    $this->travelTo(Carbon::parse('2026-10-27 08:00'));
    $this->get(route('appointments.index'))->assertInertia(fn (Assert $page) => $page
        ->has('appointments', 1)
        ->where('appointments.0.time', '09:30')
        ->where('appointments.0.services.0.name', 'Filtre à air'));
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('appointmentsToday', 1)
        ->where('appointmentsToday.0.id', $appointment->id));

    $response = $this->post(route('appointments.start', $appointment));

    $order = ServiceOrder::sole();
    $response->assertRedirect(route('service-orders.edit', $order));
    expect($order->status)->toBe(ServiceOrderStatus::InProgress)
        ->and($order->vehicle_id)->toBe($this->vehicle->id)
        ->and($order->date->toDateString())->toBe('2026-10-27')
        ->and($order->notes)->toBe('Apporte son huile')
        ->and($order->lines()->orderBy('position')->get(['service_type_id', 'labor_price'])->toArray())->toBe([
            ['service_type_id' => $this->filter->id, 'labor_price' => '500.00'],
            ['service_type_id' => $this->drain->id, 'labor_price' => '1500.00'],
        ])
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Done)
        ->and($appointment->fresh()->service_order_id)->toBe($order->id);

    // Le tableau de bord voit le changement tout de suite (cache vidé).
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('appointmentsToday.0.status', 'effectue')
        ->where('appointmentsToday.0.order.number', $order->number));
});

test('S4: the client did not come, the appointment is cancelled and can no longer start', function () {
    $appointment = Appointment::factory()->create(['vehicle_id' => $this->vehicle->id]);

    $this->post(route('appointments.cancel', $appointment))->assertSessionHasNoErrors();
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);

    $this->post(route('appointments.start', $appointment))->assertSessionHasErrors('appointment');
    $this->post(route('appointments.cancel', $appointment))->assertSessionHasErrors('appointment');
    $this->put(route('appointments.update', $appointment), ['client_id' => $this->vehicle->client_id, 'date' => '2026-10-28', 'time' => '10:00'])->assertSessionHasErrors('appointment');
    expect(ServiceOrder::count())->toBe(0);
});

test('a done appointment cannot start twice nor be cancelled', function () {
    $appointment = Appointment::factory()->create(['vehicle_id' => $this->vehicle->id]);

    $this->post(route('appointments.start', $appointment))->assertSessionHasNoErrors();
    $this->post(route('appointments.start', $appointment))->assertSessionHasErrors('appointment');
    $this->post(route('appointments.cancel', $appointment))->assertSessionHasErrors('appointment');

    expect(ServiceOrder::count())->toBe(1)
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Done);
});

test('an appointment without vehicle asks for one before starting', function () {
    $appointment = Appointment::factory()->create(['vehicle_id' => null, 'client_id' => $this->vehicle->client_id]);

    $this->post(route('appointments.start', $appointment))->assertSessionHasErrors('vehicle_id');
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Planned);

    $this->put(route('appointments.update', $appointment), ['client_id' => $this->vehicle->client_id, 'vehicle_id' => $this->vehicle->id, 'date' => '2026-10-26', 'time' => '11:00'])->assertSessionHasNoErrors();
    $this->post(route('appointments.start', $appointment))->assertSessionHasNoErrors();
    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Done);
});

test('the vehicle must belong to the client, client date and time are required', function () {
    $other = Vehicle::factory()->create();

    $this->post(route('appointments.store'), ['client_id' => $this->vehicle->client_id, 'vehicle_id' => $other->id, 'date' => '2026-10-27', 'time' => '09:00'])
        ->assertSessionHasErrors('vehicle_id');
    $this->post(route('appointments.store'), [])->assertSessionHasErrors(['client_id', 'date', 'time']);

    // Sans véhicule ni prestation prévue : accepté.
    $this->post(route('appointments.store'), ['client_id' => $this->vehicle->client_id, 'date' => '2026-10-27', 'time' => '09:00'])->assertSessionHasNoErrors();
    expect(Appointment::sole()->vehicle_id)->toBeNull();
});

test('deleting the started service order puts the appointment back to planned', function () {
    $appointment = Appointment::factory()->create(['vehicle_id' => $this->vehicle->id]);
    $this->post(route('appointments.start', $appointment));

    $this->actingAs(User::factory()->gerant()->create())->delete(route('service-orders.destroy', ServiceOrder::sole()))->assertSessionHasNoErrors();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Planned)
        ->and($appointment->fresh()->service_order_id)->toBeNull();
});

test('week and month views', function () {
    Appointment::factory()->create(['vehicle_id' => $this->vehicle->id, 'scheduled_at' => '2026-10-26 09:00']);
    Appointment::factory()->create(['vehicle_id' => $this->vehicle->id, 'scheduled_at' => '2026-11-01 17:00']);
    Appointment::factory()->create(['vehicle_id' => $this->vehicle->id, 'scheduled_at' => '2026-11-02 09:00']);
    Appointment::factory()->create(['vehicle_id' => $this->vehicle->id, 'scheduled_at' => '2026-10-26 10:00', 'status' => AppointmentStatus::Cancelled]);

    // Semaine du lundi 26 au dimanche 1er novembre.
    $this->get(route('appointments.index', ['view' => 'week', 'date' => '2026-10-28']))->assertInertia(fn (Assert $page) => $page
        ->where('range', ['from' => '2026-10-26', 'to' => '2026-11-01'])
        ->where('previous', '2026-10-21')
        ->has('appointments', 3));

    // Mois : nombre de rendez-vous non annulés par jour.
    $this->get(route('appointments.index', ['view' => 'month']))->assertInertia(fn (Assert $page) => $page
        ->where('range', ['from' => '2026-10-01', 'to' => '2026-10-31'])
        ->where('days', ['2026-10-26' => 1])
        ->where('next', '2026-11-26'));
});
