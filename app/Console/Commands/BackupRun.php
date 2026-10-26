<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sauvegarde planifiée toutes les heures avec --if-missing : le PC du garage est souvent éteint la nuit,
 * la sauvegarde se fait dès que la dernière a plus de 24 h.
 */
#[Signature('backup:run {--if-missing : Seulement si la dernière sauvegarde a plus de 24 h}')]
#[Description('Sauvegarde la base et les fichiers joints (zip), copie et rotation 30 jours')]
class BackupRun extends Command
{
    public function handle(BackupService $backups): int
    {
        $latest = $backups->latestAt();

        if ($this->option('if-missing') && $latest && $latest->gt(now()->subDay())) {
            $this->line('Sauvegarde du '.$latest->format('d/m/Y H:i').' encore récente : rien à faire.');

            return self::SUCCESS;
        }

        try {
            $backup = $backups->create();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            report($exception);

            return self::FAILURE;
        }

        $this->info("Sauvegarde créée : {$backup['name']}.");

        if ($backup['copy_error']) {
            $this->warn($backup['copy_error']);
        }

        return self::SUCCESS;
    }
}
