<?php

use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Support\Facades\File;

/**
 * Licence active, identifiant machine figé (empreinte « PC du garage ») et fichier de clé temporaire.
 */
beforeEach(function () {
    config([
        'garage.license.enabled' => true,
        'garage.license.path' => storage_path('framework/testing/license-'.uniqid().'.key'),
    ]);

    $this->app->instance(LicenseService::class, new class extends LicenseService
    {
        public string $fingerprint = 'WD-123456|PC-GARAGE';

        protected function fingerprint(): string
        {
            return $this->fingerprint;
        }
    });

    $this->license = app(LicenseService::class);
});

afterEach(fn () => File::delete(config('garage.license.path')));

test('the machine id and the key have a readable shape, and the key belongs to one machine', function () {
    $machine = $this->license->machineId();

    expect($machine)->toMatch('/^GA-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/')
        ->and($this->license->keyFor($machine))->toMatch('/^([A-HJ-NP-Z2-9]{5}-){3}[A-HJ-NP-Z2-9]{5}$/')
        ->and($this->license->keyFor($machine))->not->toBe($this->license->keyFor('GA-AAAA-BBBB-CCCC'))
        ->and($this->license->isActivated())->toBeFalse();
});

test('without a license every page leads to the activation screen', function () {
    $this->get(route('login'))->assertRedirect(route('license.show'));
    $this->actingAs(User::factory()->gerant()->create())->get(route('dashboard'))->assertRedirect(route('license.show'));

    $this->get(route('license.show'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('auth/license')
        ->where('machineId', $this->license->machineId()));
});

test('an invalid key or the key of another PC is refused', function () {
    $this->post(route('license.activate'), ['key' => 'AAAAA-BBBBB-CCCCC-DDDDD'])->assertSessionHasErrors('key');
    $this->post(route('license.activate'), ['key' => $this->license->keyFor('GA-AAAA-BBBB-CCCC')])->assertSessionHasErrors('key');

    expect(File::exists(config('garage.license.path')))->toBeFalse();
});

test('the right key activates the PC, typed in any case and spacing', function () {
    $key = strtolower(str_replace('-', ' ', $this->license->keyFor($this->license->machineId())));

    $this->post(route('license.activate'), ['key' => $key])->assertRedirect(route('login'));

    expect($this->license->isActivated())->toBeTrue();
    $this->get(route('login'))->assertOk();
});

test('the license copied to another PC is no longer valid', function () {
    $this->license->activate($this->license->keyFor($this->license->machineId()));
    expect($this->license->isActivated())->toBeTrue();

    cache()->forget('license.machine-id');
    $this->license->fingerprint = 'SAMSUNG-999|AUTRE-PC';

    expect($this->license->isActivated())->toBeFalse();
});

test('the license check is off in development', function () {
    config(['garage.license.enabled' => false]);

    $this->get(route('login'))->assertOk();
});

test('the developer command prints the key of a machine id', function () {
    $machine = $this->license->machineId();

    $this->artisan('license:generate', ['machine' => $machine])
        ->expectsOutputToContain($this->license->keyFor($machine))
        ->assertSuccessful();

    $this->artisan('license:generate', ['machine' => 'n’importe quoi'])->assertFailed();
});
