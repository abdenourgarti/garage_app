<?php

use App\Enums\ReferenceType;
use App\Http\Controllers\Admin\GarageSettingsController;
use App\Http\Controllers\Admin\ReferenceController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\LookupController;
use App\Http\Controllers\Clients\VehicleController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Invoices\PaymentController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceOrders\ServiceOrderController;
use App\Http\Controllers\Stock\ArticleController;
use App\Http\Controllers\Stock\OilProductController;
use App\Http\Controllers\Stock\PurchaseController;
use App\Http\Controllers\Stock\PurchasePaymentController;
use App\Http\Controllers\Stock\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('search', SearchController::class)->name('search');

    // shortcut: pages « Bientôt disponible » le temps que chaque module soit livré (phases 3 à 14).
    Route::inertia('appointments', 'coming-soon', ['title' => 'Rendez-vous'])->name('appointments.index');

    Route::inertia('cash', 'coming-soon', ['title' => 'Caisse'])->middleware('can:manage-cash')->name('cash.index');
    Route::middleware('can:manage-employees')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::patch('employees/{employee}/toggle', [EmployeeController::class, 'toggle'])->name('employees.toggle');
    });

    // Prestations (gérant et employé ; suppression : gérant).
    Route::get('service-orders', [ServiceOrderController::class, 'index'])->name('service-orders.index');
    Route::get('service-orders/create', [ServiceOrderController::class, 'create'])->name('service-orders.create');
    Route::get('service-orders/export', [ServiceOrderController::class, 'export'])->name('service-orders.export');
    Route::post('service-orders', [ServiceOrderController::class, 'store'])->name('service-orders.store');
    Route::get('service-orders/{order}', [ServiceOrderController::class, 'show'])->name('service-orders.show');
    Route::get('service-orders/{order}/edit', [ServiceOrderController::class, 'edit'])->name('service-orders.edit');
    Route::put('service-orders/{order}', [ServiceOrderController::class, 'update'])->name('service-orders.update');
    Route::post('service-orders/{order}/complete', [ServiceOrderController::class, 'complete'])->name('service-orders.complete');
    Route::delete('service-orders/{order}', [ServiceOrderController::class, 'destroy'])->name('service-orders.destroy');

    // Factures et paiements (gérant et employé ; annulation : gérant).
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/credits', [InvoiceController::class, 'credits'])->name('invoices.credits');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::patch('invoices/{invoice}/discount', [InvoiceController::class, 'discount'])->name('invoices.discount');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::get('invoices/{invoice}/document/{format}', [InvoiceController::class, 'document'])->whereIn('format', ['a4', 'ticket', 'pdf'])->name('invoices.document');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::get('payments/{payment}/proof', [PaymentController::class, 'proof'])->name('payments.proof');
    Route::get('payments/{payment}/receipt/{format}', [PaymentController::class, 'receipt'])->whereIn('format', ['a4', 'ticket', 'pdf'])->name('payments.receipt');

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

    // Stock, huiles, achats et fournisseurs (gérant et employé).
    Route::prefix('stock')->group(function () {
        Route::get('/', [ArticleController::class, 'index'])->name('stock.index');
        Route::post('articles', [ArticleController::class, 'store'])->name('articles.store');
        Route::get('articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
        Route::put('articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
        Route::patch('articles/{article}/toggle', [ArticleController::class, 'toggle'])->name('articles.toggle');
        Route::post('articles/{article}/adjust', [ArticleController::class, 'adjust'])->name('articles.adjust');
        Route::post('articles/{article}/open-can', [ArticleController::class, 'openCan'])->name('articles.open-can');

        Route::post('oils', [OilProductController::class, 'store'])->name('oils.store');
        Route::put('oils/{oilProduct}', [OilProductController::class, 'update'])->name('oils.update');

        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::get('purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
        Route::put('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
        Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
        Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
        Route::get('purchases/{purchase}/document', [PurchaseController::class, 'document'])->name('purchases.document');
        Route::post('purchases/{purchase}/payments', [PurchasePaymentController::class, 'store'])->name('purchases.payments.store');
        Route::get('purchase-payments/{payment}/proof', [PurchasePaymentController::class, 'proof'])->name('purchase-payments.proof');

        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    });

    // Recherches JSON des sélecteurs (SearchSelect).
    Route::prefix('lookup')->name('lookup.')->group(function () {
        Route::get('clients', [LookupController::class, 'clients'])->name('clients');
        Route::get('clients/{client}/vehicles', [LookupController::class, 'clientVehicles'])->name('client-vehicles');
        Route::get('vehicles', [LookupController::class, 'vehicles'])->name('vehicles');
        Route::get('articles', [LookupController::class, 'articles'])->name('articles');
        Route::get('service-vehicles', [LookupController::class, 'serviceVehicles'])->name('service-vehicles');
        Route::get('oils', [LookupController::class, 'oils'])->name('oils');
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
