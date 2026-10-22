<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GarageSettingsRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Paramètres > Garage. Le logo est stocké en privé et servi par une route :
 * pas de `storage:link` (lien symbolique souvent refusé sous Windows sans droits admin).
 */
class GarageSettingsController extends Controller
{
    public function edit(SettingsService $settings): Response
    {
        $logo = $settings->get(SettingsService::GARAGE_LOGO);

        return Inertia::render('admin/garage', [
            'settings' => [
                'name' => $settings->get(SettingsService::GARAGE_NAME) ?? '',
                'address' => $settings->get(SettingsService::GARAGE_ADDRESS) ?? '',
                'phone' => $settings->get(SettingsService::GARAGE_PHONE) ?? '',
                'reminder_threshold_km' => $settings->reminderThresholdKm(),
            ],
            // Le nom du fichier change à chaque envoi : sert aussi à contourner le cache du navigateur.
            'logoUrl' => $logo ? route('garage.logo', ['v' => basename($logo)]) : null,
        ]);
    }

    public function update(GarageSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        $values = [
            SettingsService::GARAGE_NAME => $request->validated('name'),
            SettingsService::GARAGE_ADDRESS => $request->validated('address'),
            SettingsService::GARAGE_PHONE => $request->validated('phone'),
            SettingsService::REMINDER_THRESHOLD_KM => $request->validated('reminder_threshold_km'),
        ];

        $oldLogo = $settings->get(SettingsService::GARAGE_LOGO);

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $values[SettingsService::GARAGE_LOGO] = $file->storeAs('garage', 'logo-'.now()->format('YmdHis').'.'.$file->extension(), 'local');
        } elseif ($request->boolean('remove_logo')) {
            $values[SettingsService::GARAGE_LOGO] = null;
        }

        $settings->set($values);

        if ($oldLogo && array_key_exists(SettingsService::GARAGE_LOGO, $values)) {
            Storage::disk('local')->delete($oldLogo);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paramètres du garage enregistrés.']);

        return back();
    }

    public function logo(SettingsService $settings): StreamedResponse
    {
        $logo = $settings->get(SettingsService::GARAGE_LOGO);

        abort_unless($logo && Storage::disk('local')->exists($logo), 404);

        return Storage::disk('local')->response($logo);
    }
}
