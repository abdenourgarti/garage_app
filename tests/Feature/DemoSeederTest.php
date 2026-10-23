<?php

use App\Enums\ServiceOrderStatus;
use App\Models\Article;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\Vehicle;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ReferenceDataSeeder;

it('fills a coherent demo dataset, only once', function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Client::count())->toBe(14)
        ->and(Purchase::count())->toBe(6)
        ->and(ServiceOrder::where('status', ServiceOrderStatus::Completed)->count())->toBe(24)
        ->and(ServiceOrder::where('status', ServiceOrderStatus::InProgress)->count())->toBe(2)
        ->and(ServiceOrder::orderBy('date')->orderBy('id')->pluck('number')->first())->toBe('PR-'.today()->subDays(88)->year.'-00001');

    // Le stock de chaque article est la somme de ses mouvements.
    Article::all()->each(fn (Article $article) => expect((float) $article->stock_qty)
        ->toEqual((float) StockMovement::where('article_id', $article->id)->sum('quantity')));

    // Pièce extérieure payée en dépense, kilométrages repris par les véhicules.
    expect(Expense::where('source_type', 'service_order_part')->exists())->toBeTrue()
        ->and(Vehicle::whereNull('last_mileage')->exists())->toBeFalse();

    $this->seed(DemoSeeder::class);

    expect(Client::count())->toBe(14)->and(ServiceOrder::count())->toBe(26)->and(Purchase::count())->toBe(6);
});
