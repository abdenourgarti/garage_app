<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Paramètres > Sauvegardes (gérant) : sauvegarder maintenant, télécharger, restaurer (double confirmation), dossier de copie.
 */
class BackupController extends Controller
{
    public const CONFIRMATION = 'RESTAURER';

    public function index(BackupService $backups, SettingsService $settings): Response
    {
        $secondary = $settings->get(SettingsService::BACKUP_SECONDARY_PATH);

        return Inertia::render('admin/backups', [
            'backups' => $backups->all(),
            'directory' => $backups->directory(),
            'secondary' => $secondary,
            'secondaryError' => $secondary ? $backups->checkSecondary($secondary) : null,
            'keepDays' => (int) config('garage.backup.keep_days'),
            'confirmation' => self::CONFIRMATION,
        ]);
    }

    public function store(BackupService $backups): RedirectResponse
    {
        try {
            $backup = $backups->create();
        } catch (RuntimeException $exception) {
            report($exception);

            throw ValidationException::withMessages(['backup' => $exception->getMessage()]);
        }

        Inertia::flash('toast', $backup['copy_error']
            ? ['type' => 'warning', 'message' => "Sauvegarde créée, mais la copie a échoué : {$backup['copy_error']}"]
            : ['type' => 'success', 'message' => 'Sauvegarde créée'.($backup['copied'] ? ' et copiée.' : '.')]);

        return back();
    }

    public function download(string $name, BackupService $backups): BinaryFileResponse
    {
        try {
            return response()->download($backups->path($name));
        } catch (RuntimeException) {
            abort(404);
        }
    }

    public function restore(Request $request, string $name, BackupService $backups): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'string']]);

        if (mb_strtoupper(trim($request->string('confirmation')->toString())) !== self::CONFIRMATION) {
            throw ValidationException::withMessages(['confirmation' => 'Tapez '.self::CONFIRMATION.' pour confirmer.']);
        }

        try {
            $backups->restore($name);
        } catch (RuntimeException $exception) {
            report($exception);

            throw ValidationException::withMessages(['confirmation' => $exception->getMessage()]);
        }

        // Les comptes et les sessions viennent de la sauvegarde : tout le monde se reconnecte.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', 'Restauration terminée. Reconnectez-vous.');
    }

    public function updateSettings(Request $request, BackupService $backups, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate(['secondary' => ['nullable', 'string', 'max:255']], [], ['secondary' => 'dossier de copie']);
        $secondary = trim((string) ($data['secondary'] ?? '')) ?: null;

        if ($secondary && ($error = $backups->checkSecondary($secondary))) {
            throw ValidationException::withMessages(['secondary' => $error]);
        }

        $settings->set([SettingsService::BACKUP_SECONDARY_PATH => $secondary]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $secondary ? 'Dossier de copie enregistré.' : 'Copie désactivée.']);

        return back();
    }
}
