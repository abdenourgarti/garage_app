<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get('/')->assertRedirect('/dashboard');
});

test('both roles can open the shared modules', function (string $route) {
    $this->actingAs(User::factory()->create())->get(route($route))->assertOk();
    $this->actingAs(User::factory()->gerant()->create())->get(route($route))->assertOk();
})->with(['dashboard', 'service-orders.index', 'service-orders.create', 'clients.index', 'appointments.index', 'invoices.index', 'stock.index']);

test('only the gerant can open cash, employees and the garage settings', function (string $route) {
    $this->actingAs(User::factory()->create())->get(route($route))->assertForbidden();
    $this->actingAs(User::factory()->gerant()->create())->get(route($route))->assertOk();
})->with(['cash.index', 'employees.index', 'admin.garage.edit']);

test('the settings menu opens the garage for the gerant and the references for the employe', function () {
    $this->actingAs(User::factory()->gerant()->create())->get(route('admin.index'))->assertRedirect(route('admin.garage.edit'));
    $this->actingAs(User::factory()->create())->get(route('admin.index'))->assertRedirect(route('admin.references.index', 'brands'));
});

test('the menu permissions are shared with the front end', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'employe')
            ->where('auth.can', ['cash' => false, 'employees' => false, 'admin' => false, 'references' => true]));

    $this->actingAs(User::factory()->gerant()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.can', ['cash' => true, 'employees' => true, 'admin' => true, 'references' => true]));
});
