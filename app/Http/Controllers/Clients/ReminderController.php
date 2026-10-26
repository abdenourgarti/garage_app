<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\ReminderService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rappels de vidange (M9) : véhicules à relancer par téléphone, les plus urgents d'abord.
 */
class ReminderController extends Controller
{
    public function index(ReminderService $reminders, SettingsService $settings): Response
    {
        return Inertia::render('vehicles/reminders', [
            'reminders' => $reminders->toCall(),
            'threshold' => $settings->reminderThresholdKm(),
        ]);
    }

    public function call(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->forceFill(['last_reminder_call_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$vehicle->plate} : appel noté."]);

        return back();
    }
}
