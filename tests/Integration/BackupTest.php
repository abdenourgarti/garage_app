<?php

use App\Models\Client;
use App\Models\User;
use App\Services\BackupService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\File;

/**
 * Vraie sauvegarde et vraie restauration (mariadb-dump / mariadb) sur garage_app_test.
 * Dossiers de sauvegarde et de fichiers temporaires : les vrais fichiers du poste ne sont jamais touchés.
 */
beforeEach(function () {
    $binary = (string) config('garage.backup.dump_binary');

    if (! is_file($binary) && trim((string) shell_exec((PHP_OS_FAMILY === 'Windows' ? 'where ' : 'command -v ').escapeshellarg($binary))) === '') {
        $this->markTestSkipped("Binaire introuvable : {$binary} (BACKUP_DUMP_BINARY).");
    }

    $root = storage_path('framework/testing/backup-'.uniqid());
    config([
        'garage.backup.path' => "{$root}/backups",
        'garage.backup.files' => "{$root}/files",
    ]);
    $this->root = $root;

    File::ensureDirectoryExists("{$root}/files/garage");
    File::put("{$root}/files/garage/logo.png", 'logo-avant');
    File::put("{$root}/files/.gitignore", "*\n");

    $this->backups = app(BackupService::class);
});

afterEach(fn () => File::deleteDirectory($this->root));

test('a backup holds the database and the attached files, keeps 30 days and is copied', function () {
    Client::factory()->create(['last_name' => 'Avant']);
    app(SettingsService::class)->set([SettingsService::BACKUP_SECONDARY_PATH => "{$this->root}/usb"]);

    File::ensureDirectoryExists("{$this->root}/backups");
    File::put("{$this->root}/backups/garage-2025-01-01-1200.zip", 'ancienne');
    touch("{$this->root}/backups/garage-2025-01-01-1200.zip", now()->subDays(40)->getTimestamp());
    File::put("{$this->root}/backups/notes.txt", 'à garder');

    $backup = $this->backups->create();

    $zip = new ZipArchive;
    $zip->open("{$this->root}/backups/{$backup['name']}");

    expect($backup['name'])->toMatch('/^garage-\d{4}-\d{2}-\d{2}-\d{4}\.zip$/')
        ->and($zip->getFromName('database.sql'))->toContain('Avant')
        ->and($zip->getFromName('files/garage/logo.png'))->toBe('logo-avant')
        ->and($backup['copied'])->toBeTrue()
        ->and(File::exists("{$this->root}/usb/{$backup['name']}"))->toBeTrue()
        ->and(File::exists("{$this->root}/backups/garage-2025-01-01-1200.zip"))->toBeFalse()
        ->and(File::exists("{$this->root}/backups/notes.txt"))->toBeTrue()
        ->and($this->backups->all())->toHaveCount(1);

    $zip->close();
});

test('restoring brings back the data and the files, after a safety backup', function () {
    Client::factory()->create(['last_name' => 'Avant']);
    $backup = $this->backups->create();

    Client::where('last_name', 'Avant')->delete();
    Client::factory()->create(['last_name' => 'Après']);
    File::put("{$this->root}/files/garage/logo.png", 'logo-après');
    File::put("{$this->root}/files/garage/nouveau.png", 'en trop');

    $this->backups->restore($backup['name']);

    expect(Client::pluck('last_name')->all())->toBe(['Avant'])
        ->and(File::get("{$this->root}/files/garage/logo.png"))->toBe('logo-avant')
        ->and(File::exists("{$this->root}/files/garage/nouveau.png"))->toBeFalse()
        ->and(File::exists("{$this->root}/files/.gitignore"))->toBeTrue()
        ->and(collect($this->backups->all())->pluck('name')->filter(fn ($name) => str_contains($name, 'avant-restauration')))->toHaveCount(1);
});

test('from the screen: save now, then restore only after typing RESTAURER', function () {
    $gerant = User::factory()->gerant()->create();
    $this->actingAs($gerant)->post(route('admin.backups.store'))->assertSessionHasNoErrors();

    $name = $this->backups->all()[0]['name'];

    $this->post(route('admin.backups.restore', $name), ['confirmation' => 'oui'])->assertSessionHasErrors('confirmation');
    $this->post(route('admin.backups.restore', '../.env'), ['confirmation' => 'RESTAURER'])->assertNotFound();

    $this->post(route('admin.backups.restore', $name), ['confirmation' => 'restaurer'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');
    $this->assertGuest();
});

test('the hourly task only backs up when the last one is older than a day', function () {
    $this->artisan('backup:run --if-missing')->assertSuccessful();
    $this->artisan('backup:run --if-missing')->expectsOutputToContain('encore récente')->assertSuccessful();

    expect($this->backups->all())->toHaveCount(1);
});
