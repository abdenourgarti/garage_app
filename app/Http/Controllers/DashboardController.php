<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accueil (M8). Les chiffres d'argent ne partent que vers le gérant : l'employé ne les reçoit pas du tout.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        return Inertia::render('dashboard', [
            ...$dashboard->activity(),
            'money' => $request->user()->can('manage-cash') ? $dashboard->money() : null,
        ]);
    }
}
