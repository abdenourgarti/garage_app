<?php

use App\Enums\InvoiceStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceOrderStatus;
use App\Models\Article;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\Vehicle;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ReferenceDataSeeder;

it('fills a coherent year of demo data, only once', function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Client::count())->toBe(30)
        ->and(Vehicle::count())->toBe(50)
        ->and(ServiceOrder::count())->toBe(200)
        ->and(ServiceOrder::where('status', ServiceOrderStatus::InProgress)->count())->toBe(2)
        ->and(ServiceOrder::min('date'))->toBeLessThanOrEqual(today()->subDays(300)->toDateString());

    // Numéros dans l'ordre des dates.
    $numbers = ServiceOrder::orderBy('date')->orderBy('id')->pluck('number');
    expect($numbers->all())->toBe($numbers->sort(fn ($a, $b) => [substr($a, 3, 4), $a] <=> [substr($b, 3, 4), $b])->values()->all());

    // Le stock de chaque article est la somme de ses mouvements.
    Article::all()->each(fn (Article $article) => expect((float) $article->stock_qty)
        ->toEqual((float) StockMovement::where('article_id', $article->id)->sum('quantity')));

    // Une facture par prestation terminée + une annulée, des crédits, jamais plus payé que le total.
    expect(Invoice::valid()->count())->toBe(198)
        ->and(Invoice::where('status', InvoiceStatus::Cancelled)->count())->toBe(1)
        ->and(Invoice::outstanding()->count())->toBeGreaterThan(3)
        ->and(Invoice::whereColumn('amount_paid', '>', 'total')->exists())->toBeFalse()
        ->and(Purchase::where('status', PurchaseStatus::Received)->count())->toBeGreaterThan(10)
        ->and(Vehicle::whereNull('last_mileage')->exists())->toBeFalse();

    $payments = Payment::count();
    $this->seed(DemoSeeder::class);

    expect(ServiceOrder::count())->toBe(200)->and(Payment::count())->toBe($payments)->and(Client::count())->toBe(30);
});
