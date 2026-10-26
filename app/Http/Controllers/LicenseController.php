<?php

namespace App\Http\Controllers;

use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Activation hors ligne : le garage envoie l'identifiant affiché, le développeur renvoie la clé.
 */
class LicenseController extends Controller
{
    public function show(LicenseService $license): Response|RedirectResponse
    {
        if ($license->isActivated()) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/license', [
            'machineId' => $license->machineId(),
            'hasKey' => $license->storedKey() !== null,
        ]);
    }

    public function activate(Request $request, LicenseService $license): RedirectResponse
    {
        $request->validate(['key' => ['required', 'string', 'max:40']], [], ['key' => 'clé']);

        if (! $license->activate($request->string('key')->toString())) {
            throw ValidationException::withMessages(['key' => 'Cette clé ne correspond pas à ce PC. Vérifiez-la ou envoyez à nouveau l’identifiant.']);
        }

        $request->session()->put('license.checked_until', time() + 3600);

        return to_route('login')->with('status', 'Licence activée. Bienvenue !');
    }
}
