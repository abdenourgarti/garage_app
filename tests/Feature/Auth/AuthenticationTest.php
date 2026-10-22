<?php

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\RateLimiter;

test('login screen can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

test('users can log in with their username', function () {
    $user = User::factory()->create(['username' => 'karim']);

    $this->post(route('login.store'), ['username' => 'karim', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('username is case insensitive', function () {
    User::factory()->create(['username' => 'karim']);

    $this->post(route('login.store'), ['username' => 'Karim', 'password' => 'password']);

    $this->assertAuthenticated();
});

test('users can not log in with a wrong password', function () {
    User::factory()->create(['username' => 'karim']);

    $this->post(route('login.store'), ['username' => 'karim', 'password' => 'wrong-password'])
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('inactive users can not log in', function () {
    User::factory()->inactive()->create(['username' => 'karim']);

    $this->post(route('login.store'), ['username' => 'karim', 'password' => 'password'])
        ->assertSessionHasErrors(['username' => 'Ce compte est désactivé. Contactez le gérant.']);

    $this->assertGuest();
});

test('users can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

test('login is rate limited', function () {
    User::factory()->create(['username' => 'karim']);

    RateLimiter::increment(md5('login'.implode('|', ['karim', '127.0.0.1'])), amount: 5);

    $this->post(route('login.store'), ['username' => 'karim', 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});

test('registration and password reset by e-mail are disabled', function (string $uri) {
    $this->get($uri)->assertNotFound();
})->with(['/register', '/forgot-password', '/reset-password/token', '/email/verify', '/two-factor-challenge']);

test('the initial gerant account is seeded from config', function () {
    config(['garage.admin.username' => 'patron', 'garage.admin.password' => 'secret123']);

    $this->seed(ReferenceDataSeeder::class);

    $this->post(route('login.store'), ['username' => 'patron', 'password' => 'secret123']);

    expect(auth()->user()->isGerant())->toBeTrue();
});
