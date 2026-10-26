<?php

namespace App\Console\Commands\Dev;

use App\Services\LicenseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Outil du développeur : jamais livré au garage (l'installateur exclut app/Console/Commands/Dev).
 */
#[Signature('license:generate {machine : Identifiant affiché sur l’écran d’activation (GA-XXXX-XXXX-XXXX)}')]
#[Description('Génère la clé de licence d’un PC à partir de son identifiant machine')]
class LicenseGenerate extends Command
{
    public function handle(LicenseService $license): int
    {
        if (app()->isProduction()) {
            $this->error('Commande réservée au développeur.');

            return self::FAILURE;
        }

        $machine = strtoupper(trim((string) $this->argument('machine')));

        if (! preg_match('/^GA-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $machine)) {
            $this->error('Identifiant attendu : GA-XXXX-XXXX-XXXX.');

            return self::FAILURE;
        }

        $this->info("Clé pour {$machine} : {$license->keyFor($machine)}");

        return self::SUCCESS;
    }
}
