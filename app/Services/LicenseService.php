<?php

namespace App\Services;

use App\Support\ProcessEnv;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Licence liée au PC : identifiant machine (disque + nom du PC) → clé de 20 caractères, facile à dicter.
 * shortcut: le secret est dans le code, livré en clair ; cela empêche la copie simple sur un autre PC, pas un
 * contournement par un informaticien. Passer à une signature asymétrique (clé longue à coller) si besoin.
 */
class LicenseService
{
    /** Sans 0/O ni 1/I : rien à confondre en dictant la clé. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const SECRET = 'GarageApp/2026/c8f1e2b7a94d4e5f9b3a6d7c1e0f2a8b';

    public function enabled(): bool
    {
        return (bool) config('garage.license.enabled');
    }

    /**
     * « GA-7F3K-92QD-X4M1 », recalculé au plus toutes les heures (lancer PowerShell à chaque page serait lent).
     */
    public function machineId(): string
    {
        if ($cached = Cache::get('license.machine-id')) {
            return $cached;
        }

        $fingerprint = $this->fingerprint();
        $id = 'GA-'.implode('-', str_split(self::encode(hash('sha256', $fingerprint, true), 12), 4));

        // Numéro de disque illisible cette fois-ci (PowerShell lent ou refusé) : on réessaiera à la page suivante.
        if (! str_starts_with($fingerprint, '|')) {
            Cache::put('license.machine-id', $id, 3600);
        }

        return $id;
    }

    /**
     * Clé attendue pour un identifiant machine (aussi utilisée par la commande développeur).
     */
    public function keyFor(string $machineId): string
    {
        $digest = hash_hmac('sha256', strtoupper(trim($machineId)), self::SECRET, true);

        return implode('-', str_split(self::encode($digest, 20), 5));
    }

    public function isValid(?string $key): bool
    {
        return $key !== null && hash_equals($this->keyFor($this->machineId()), self::normalize($key));
    }

    public function isActivated(): bool
    {
        return ! $this->enabled() || $this->isValid($this->storedKey());
    }

    /**
     * Enregistre la clé si elle correspond à ce PC.
     */
    public function activate(string $key): bool
    {
        if (! $this->isValid($key)) {
            return false;
        }

        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), self::normalize($key));

        return true;
    }

    public function storedKey(): ?string
    {
        return File::exists($this->path()) ? trim(File::get($this->path())) : null;
    }

    /**
     * Numéro de série du disque + nom du PC (Windows) ; /etc/machine-id + nom (Linux).
     */
    protected function fingerprint(): string
    {
        $host = (string) gethostname();

        if (PHP_OS_FAMILY === 'Windows') {
            $serial = Process::timeout(15)->env(ProcessEnv::system())->run(['powershell', '-NoProfile', '-NonInteractive', '-Command', '(Get-CimInstance Win32_DiskDrive | Sort-Object Index | Select-Object -First 1).SerialNumber']);

            return trim($serial->output()).'|'.$host;
        }

        $machine = File::exists('/etc/machine-id') ? trim(File::get('/etc/machine-id')) : '';

        return $machine.'|'.$host;
    }

    /**
     * Clé saisie : majuscules, sans espaces ni tirets, regroupée par 5.
     */
    public static function normalize(string $key): string
    {
        $clean = preg_replace('/[^A-Z0-9]/', '', strtoupper($key));

        return implode('-', str_split((string) $clean, 5));
    }

    private function path(): string
    {
        return (string) config('garage.license.path');
    }

    private static function encode(string $bytes, int $length): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';

        foreach (str_split(substr($bits, 0, $length * 5), 5) as $chunk) {
            $out .= self::ALPHABET[bindec($chunk)];
        }

        return $out;
    }
}
