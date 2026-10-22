<?php

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->gerant = User::factory()->gerant()->create();
});

test('the reminder threshold defaults to 1000 km', function () {
    $this->actingAs($this->gerant)
        ->get(route('admin.garage.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/garage')
            ->where('settings.reminder_threshold_km', 1000)
            ->where('logoUrl', null));
});

test('the gerant can save the garage settings and its logo', function () {
    $this->actingAs($this->gerant)
        ->put(route('admin.garage.update'), [
            'name' => 'Garage El Amel',
            'address' => 'Rue des frères Bouadou, Bir Mourad Raïs',
            'phone' => '0550 12 34 56',
            'reminder_threshold_km' => 1500,
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])
        ->assertSessionHasNoErrors();

    $settings = app(SettingsService::class);
    expect($settings->get(SettingsService::GARAGE_NAME))->toBe('Garage El Amel')
        ->and($settings->reminderThresholdKm())->toBe(1500);

    $logo = $settings->get(SettingsService::GARAGE_LOGO);
    Storage::disk('local')->assertExists($logo);

    $this->get(route('garage.logo'))->assertOk();
});

test('the form is sent as POST with _method=put, like the browser does with a file', function () {
    $this->actingAs($this->gerant)
        ->post(route('admin.garage.update'), [
            '_method' => 'put',
            'name' => 'AMINE AUTO',
            'reminder_threshold_km' => '10000',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])
        ->assertSessionHasNoErrors();

    expect(app(SettingsService::class)->get(SettingsService::GARAGE_NAME))->toBe('AMINE AUTO');
});

test('replacing or removing the logo deletes the old file', function () {
    $payload = ['name' => 'Garage', 'reminder_threshold_km' => 1000];
    $this->actingAs($this->gerant)->put(route('admin.garage.update'), [...$payload, 'logo' => UploadedFile::fake()->image('a.png')]);
    $first = app(SettingsService::class)->get(SettingsService::GARAGE_LOGO);

    $this->travel(1)->seconds();
    $this->put(route('admin.garage.update'), [...$payload, 'logo' => UploadedFile::fake()->image('b.png')]);
    Storage::disk('local')->assertMissing($first);

    $this->put(route('admin.garage.update'), [...$payload, 'remove_logo' => true]);
    expect(app(SettingsService::class)->get(SettingsService::GARAGE_LOGO))->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    $this->get(route('garage.logo'))->assertNotFound();
});

test('the logo must be an image of 2 MB at most', function (UploadedFile $file) {
    $this->actingAs($this->gerant)
        ->put(route('admin.garage.update'), ['name' => 'Garage', 'reminder_threshold_km' => 1000, 'logo' => $file])
        ->assertSessionHasErrors('logo');
})->with([
    'svg' => fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    'pdf' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
    'trop lourd' => fn () => UploadedFile::fake()->image('logo.png')->size(3000),
]);

test('the employe can not open the garage settings', function () {
    $employe = User::factory()->create();

    $this->actingAs($employe)->get(route('admin.garage.edit'))->assertForbidden();
    $this->actingAs($employe)->put(route('admin.garage.update'), ['name' => 'X', 'reminder_threshold_km' => 1])->assertForbidden();
});
