<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->gerant = User::factory()->gerant()->create(['username' => 'patron']);
    $this->employe = User::factory()->create(['username' => 'sofiane']);
});

test('only the manager reaches users and backups', function () {
    $this->actingAs($this->employe);

    $this->get(route('admin.users.index'))->assertForbidden();
    $this->post(route('admin.users.store'), [])->assertForbidden();
    $this->patch(route('admin.users.toggle', $this->gerant))->assertForbidden();
    $this->get(route('admin.backups.index'))->assertForbidden();
    $this->post(route('admin.backups.store'))->assertForbidden();
    $this->post(route('admin.backups.restore', 'garage-2026-01-01-1200.zip'), ['confirmation' => 'RESTAURER'])->assertForbidden();

    $this->actingAs($this->gerant)->get(route('admin.users.index'))->assertInertia(fn (Assert $page) => $page
        ->component('admin/users')
        ->has('rows.data', 2));
});

test('the manager creates an account with a temporary password', function () {
    $this->actingAs($this->gerant)
        ->post(route('admin.users.store'), ['name' => 'Bilal', 'username' => 'bilal', 'role' => 'employe', 'password' => 'Provisoire1'])
        ->assertSessionHasNoErrors();

    $bilal = User::where('username', 'bilal')->sole();
    expect($bilal->role)->toBe(UserRole::Employe)
        ->and($bilal->must_change_password)->toBeTrue()
        ->and(Hash::check('Provisoire1', $bilal->password))->toBeTrue();

    $this->post(route('admin.users.store'), ['name' => 'Autre', 'username' => 'bilal', 'role' => 'employe', 'password' => 'Provisoire1'])
        ->assertSessionHasErrors('username');
});

test('the last active manager can be neither deactivated nor demoted', function () {
    $this->actingAs($this->gerant);

    $this->patch(route('admin.users.toggle', $this->gerant))->assertSessionHasErrors('is_active');

    $other = User::factory()->gerant()->create(['username' => 'adjoint']);
    $this->put(route('admin.users.update', $other), ['name' => $other->name, 'username' => 'adjoint', 'role' => 'employe'])
        ->assertSessionHasNoErrors();
    expect($other->fresh()->role)->toBe(UserRole::Employe);

    // Un autre gérant, désactivé, ne compte pas.
    User::factory()->gerant()->create(['is_active' => false]);
    $this->put(route('admin.users.update', $this->gerant), ['name' => 'Patron', 'username' => 'patron', 'role' => 'employe'])
        ->assertSessionHasErrors('role');
    expect($this->gerant->fresh()->role)->toBe(UserRole::Gerant);

    // Avec un second gérant actif, le premier peut être désactivé par lui.
    $second = User::factory()->gerant()->create();
    $this->actingAs($second)->patch(route('admin.users.toggle', $this->gerant))->assertSessionHasNoErrors();
    expect($this->gerant->fresh()->is_active)->toBeFalse();
});

test('a deactivated account is logged out on its next page', function () {
    $this->actingAs($this->employe)->get(route('dashboard'))->assertOk();

    $this->employe->update(['is_active' => false]);

    $this->get(route('dashboard'))->assertRedirect(route('login'))->assertSessionHasErrors('username');
    $this->assertGuest();
});

test('after a reset, the user must choose a password before anything else', function () {
    $this->actingAs($this->gerant)
        ->put(route('admin.users.password', $this->employe), ['password' => 'Provisoire2'])
        ->assertSessionHasNoErrors();

    expect($this->employe->fresh()->must_change_password)->toBeTrue();

    $this->actingAs($this->employe->fresh());
    $this->get(route('service-orders.index'))->assertRedirect(route('security.edit'));
    $this->get(route('security.edit'))->assertOk();

    $this->put(route('user-password.update'), [
        'current_password' => 'Provisoire2',
        'password' => 'MonSecret3',
        'password_confirmation' => 'MonSecret3',
    ])->assertSessionHasNoErrors();

    expect($this->employe->fresh()->must_change_password)->toBeFalse();
    $this->get(route('service-orders.index'))->assertOk();
});
