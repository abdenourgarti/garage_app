<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // shortcut: pages « Bientôt disponible » le temps que chaque module soit livré (phases 3 à 14).
    Route::inertia('service-orders', 'coming-soon', ['title' => 'Prestations'])->name('service-orders.index');
    Route::inertia('service-orders/create', 'coming-soon', ['title' => 'Nouvelle prestation'])->name('service-orders.create');
    Route::inertia('clients', 'coming-soon', ['title' => 'Clients et véhicules'])->name('clients.index');
    Route::inertia('appointments', 'coming-soon', ['title' => 'Rendez-vous'])->name('appointments.index');
    Route::inertia('invoices', 'coming-soon', ['title' => 'Factures'])->name('invoices.index');
    Route::inertia('stock', 'coming-soon', ['title' => 'Stock et achats'])->name('stock.index');

    Route::inertia('cash', 'coming-soon', ['title' => 'Caisse'])->middleware('can:manage-cash')->name('cash.index');
    Route::inertia('employees', 'coming-soon', ['title' => 'Employés'])->middleware('can:manage-employees')->name('employees.index');
    Route::inertia('admin', 'coming-soon', ['title' => 'Administration'])->middleware('can:manage-admin')->name('admin.index');
});

if (app()->isLocal()) {
    Route::inertia('dev/ui', 'dev/ui')->name('dev.ui');
}

require __DIR__.'/settings.php';
