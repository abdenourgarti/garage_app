<?php

use App\Models\User;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

test('name can be updated but not username or role', function () {
    $user = User::factory()->create(['username' => 'karim']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Karim B.', 'username' => 'autre', 'role' => 'gerant'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Karim B.')
        ->and($user->username)->toBe('karim')
        ->and($user->isGerant())->toBeFalse();
});
