<?php

use App\Enums\ReferenceType;
use App\Http\Controllers\Admin\GarageSettingsController;
use App\Http\Controllers\Admin\ReferenceController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\LookupController;
use App\Http\Controllers\Clients\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // shortcut: pages « Bientôt disponible » le temps que chaque module soit livré (phases 3 à 14).
    Route::inertia('service-orders', 'coming-soon', ['title' => 'Prestations'])->name('service-orders.index');
    Route::inertia('service-orders/create', 'coming-soon', ['title' => 'Nouvelle prestation'])->name('service-orders.create');
    Route::inertia('appointments', 'coming-soon', ['title' => 'Rendez-vous'])->name('appointments.index');
    Route::inertia('invoices', 'coming-soon', ['title' => 'Factures'])->name('invoices.index');
    Route::inertia('stock', 'coming-soon', ['title' => 'Stock et achats'])->name('stock.index');

    Route::inertia('cash', 'coming-soon', ['title' => 'Caisse'])->middleware('can:manage-cash')->name('cash.index');
    Route::inertia('employees', 'coming-soon', ['title' => 'Employés'])->middleware('can:manage-employees')->name('employees.index');

    // Clients et véhicules (gérant et employé).
    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('clients/quick', [ClientController::class, 'quickStore'])->name('clients.quick-store');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::patch('clients/{client}/toggle', [ClientController::class, 'toggle'])->name('clients.toggle');
    Route::post('clients/{client}/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');

    Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');
    Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
    Route::patch('vehicles/{vehicle}/toggle', [VehicleController::class, 'toggle'])->name('vehicles.toggle');
    Route::post('vehicles/{vehicle}/transfer', [VehicleController::class, 'transfer'])->name('vehicles.transfer');

    // Recherches JSON des sélecteurs (SearchSelect).
    Route::prefix('lookup')->name('lookup.')->group(function () {
        Route::get('clients', [LookupController::class, 'clients'])->name('clients');
        Route::get('clients/{client}/vehicles', [LookupController::class, 'clientVehicles'])->name('client-vehicles');
        Route::get('vehicles', [LookupController::class, 'vehicles'])->name('vehicles');
    });

    // Logo du garage : lisible par tous (factures, bilans), modifiable par le gérant.
    Route::get('garage/logo', [GarageSettingsController::class, 'logo'])->name('garage.logo');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn (Request $request) => $request->user()->can('manage-admin')
            ? to_route('admin.garage.edit')
            : to_route('admin.references.index', ReferenceType::Brands)
        )->name('index');

        Route::middleware('can:manage-admin')->group(function () {
            Route::get('garage', [GarageSettingsController::class, 'edit'])->name('garage.edit');
            Route::put('garage', [GarageSettingsController::class, 'update'])->name('garage.update');
        });

        Route::middleware('can:manage-references')->prefix('references')->name('references.')->group(function () {
            Route::redirect('/', '/admin/references/'.ReferenceType::Brands->value);
            Route::get('{type}', [ReferenceController::class, 'index'])->name('index');
            Route::post('{type}', [ReferenceController::class, 'store'])->name('store');
            Route::put('{type}/{id}', [ReferenceController::class, 'update'])->whereNumber('id')->name('update');
            Route::patch('{type}/{id}/toggle', [ReferenceController::class, 'toggle'])->whereNumber('id')->name('toggle');
            Route::delete('{type}/{id}', [ReferenceController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
    });
});

if (app()->isLocal()) {
    Route::inertia('dev/ui', 'dev/ui')->name('dev.ui');
}

require __DIR__.'/settings.php';
