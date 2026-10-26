<?php

namespace App\Services;

use App\Support\ProcessEnv;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Sauvegardes : garage-AAAA-MM-JJ-HHmm.zip = database.sql (mysqldump --single-transaction) + files/ (storage/app/private :
 * logo, factures fournisseurs, preuves de paiement). Copie vers le dossier secondaire, rotation sur 30 jours,
 * restauration précédée d'une sauvegarde de sécurité.
 */
class BackupService
{
    private const PATTERN = '/^garage-\d{4}-\d{2}-\d{2}-\d{4}(-[a-z0-9-]+)?\.zip$/';

    public function __construct(private SettingsService $settings) {}

    /**
     * @return array{name: string, size: int, copied: bool|null, copy_error: string|null}
     */
    public function create(?string $label = null): array
    {
        $directory = $this->directory();
        File::ensureDirectoryExists($directory);

        $name = 'garage-'.now()->format('Y-m-d-Hi').($label ? '-'.Str::slug($label) : '').'.zip';
        $target = $directory.DIRECTORY_SEPARATOR.$name;

        if (File::exists($target)) {
            $name = Str::replaceLast('.zip', '-'.now()->format('s').'.zip', $name);
            $target = $directory.DIRECTORY_SEPARATOR.$name;
        }

        $work = $this->workDirectory();

        try {
            $sql = $work.DIRECTORY_SEPARATOR.'database.sql';
            $this->dump($sql);

            $zip = new ZipArchive;

            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Impossible de créer {$target}.");
            }

            $zip->addFile($sql, 'database.sql');

            // Fichiers cachés compris : la restauration vide tout le dossier, rien ne doit s'y perdre.
            foreach (File::allFiles($this->filesDirectory(), true) as $file) {
                $zip->addFile($file->getPathname(), 'files/'.str_replace('\\', '/', $file->getRelativePathname()));
            }

            $zip->close();
        } finally {
            File::deleteDirectory($work);
        }

        [$copied, $error] = $this->copyToSecondary($target, $name);
        $this->rotate($directory);

        return ['name' => $name, 'size' => File::size($target), 'copied' => $copied, 'copy_error' => $error];
    }

    /**
     * Sauvegardes du dossier principal, la plus récente d'abord.
     *
     * @return list<array{name: string, size: int, created_at: string}>
     */
    public function all(): array
    {
        $directory = $this->directory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => preg_match(self::PATTERN, $file->getFilename()))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => Carbon::createFromTimestamp($file->getMTime())->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function latestAt(): ?CarbonInterface
    {
        $latest = $this->all()[0] ?? null;

        return $latest ? Carbon::parse($latest['created_at']) : null;
    }

    /**
     * Chemin d'une sauvegarde de la liste ; refuse tout autre nom (pas de ../).
     */
    public function path(string $name): string
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        if (! preg_match(self::PATTERN, $name) || ! File::exists($path)) {
            throw new RuntimeException('Sauvegarde introuvable.');
        }

        return $path;
    }

    /**
     * Remplace la base et les fichiers par ceux de la sauvegarde. Une sauvegarde « avant restauration » est faite d'abord.
     */
    public function restore(string $name): void
    {
        $archive = $this->path($name);
        $work = $this->workDirectory();

        try {
            $zip = new ZipArchive;

            if ($zip->open($archive) !== true || $zip->locateName('database.sql') === false) {
                throw new RuntimeException('Ce fichier n’est pas une sauvegarde GarageApp valide.');
            }

            $zip->extractTo($work);
            $zip->close();

            $this->create('avant-restauration');
            $this->import($work.DIRECTORY_SEPARATOR.'database.sql');

            $files = $this->filesDirectory();
            File::cleanDirectory($files);

            if (File::isDirectory($work.DIRECTORY_SEPARATOR.'files')) {
                File::copyDirectory($work.DIRECTORY_SEPARATOR.'files', $files);
            }
        } finally {
            File::deleteDirectory($work);
            Cache::flush();
        }
    }

    /**
     * Dossier de copie : vérifie qu'on peut y écrire (disque branché, droits).
     */
    public function checkSecondary(string $directory): ?string
    {
        try {
            File::ensureDirectoryExists($directory);
            $probe = $directory.DIRECTORY_SEPARATOR.'.garageapp-test';
            File::put($probe, 'ok');
            File::delete($probe);

            return null;
        } catch (\Throwable) {
            return "Impossible d’écrire dans « {$directory} » : vérifiez que le disque ou la clé USB est branché.";
        }
    }

    public function directory(): string
    {
        return rtrim((string) config('garage.backup.path'), '\\/');
    }

    private function filesDirectory(): string
    {
        $directory = (string) config('garage.backup.files');
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    private function workDirectory(): string
    {
        $directory = storage_path('framework/backup-'.Str::random(12));
        File::ensureDirectoryExists($directory);

        return $directory;
    }

    private function dump(string $target): void
    {
        $this->withCredentials(fn (string $options, string $database) => Process::timeout(600)->env(ProcessEnv::system())->run([
            config('garage.backup.dump_binary'),
            "--defaults-extra-file={$options}",
            '--single-transaction',
            '--routines',
            '--default-character-set=utf8mb4',
            "--result-file={$target}",
            $database,
        ])->throw(fn ($result) => throw new RuntimeException('La sauvegarde de la base a échoué : '.trim($result->errorOutput()))));
    }

    private function import(string $source): void
    {
        $input = fopen($source, 'r');

        try {
            $this->withCredentials(fn (string $options, string $database) => Process::timeout(600)->env(ProcessEnv::system())->input($input)->run([
                config('garage.backup.client_binary'),
                "--defaults-extra-file={$options}",
                '--default-character-set=utf8mb4',
                $database,
            ])->throw(fn ($result) => throw new RuntimeException('La restauration de la base a échoué : '.trim($result->errorOutput()))));
        } finally {
            fclose($input);
        }
    }

    /**
     * Identifiants dans un fichier temporaire : jamais sur la ligne de commande (visible dans la liste des processus).
     */
    private function withCredentials(callable $callback): void
    {
        $connection = config('database.connections.'.config('database.default'));
        $options = tempnam(sys_get_temp_dir(), 'garage');

        File::put($options, implode("\n", [
            '[client]',
            'host="'.$connection['host'].'"',
            'port='.$connection['port'],
            'user="'.$connection['username'].'"',
            'password="'.addcslashes((string) $connection['password'], '"\\').'"',
        ])."\n");

        try {
            $callback($options, $connection['database']);
        } finally {
            File::delete($options);
        }
    }

    /**
     * @return array{0: bool|null, 1: string|null} null = pas de dossier de copie réglé
     */
    private function copyToSecondary(string $source, string $name): array
    {
        $directory = $this->settings->get(SettingsService::BACKUP_SECONDARY_PATH);

        if (! $directory) {
            return [null, null];
        }

        $directory = rtrim($directory, '\\/');
        $error = $this->checkSecondary($directory);

        if ($error) {
            return [false, $error];
        }

        File::copy($source, $directory.DIRECTORY_SEPARATOR.$name);
        $this->rotate($directory);

        return [true, null];
    }

    /**
     * Garde 30 jours (BACKUP_KEEP_DAYS) ; ne touche qu'aux fichiers garage-….zip.
     */
    private function rotate(string $directory): void
    {
        $limit = now()->subDays((int) config('garage.backup.keep_days'))->getTimestamp();

        foreach (File::files($directory) as $file) {
            if (preg_match(self::PATTERN, $file->getFilename()) && $file->getMTime() < $limit) {
                File::delete($file->getPathname());
            }
        }
    }
}
