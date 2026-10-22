<?php

use App\Enums\SystemExpenseCategory;
use App\Models\Brand;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
use App\Models\VehicleModel;
use App\Models\Wilaya;
use Database\Seeders\ReferenceDataSeeder;

test('the reference data is seeded', function () {
    $this->seed(ReferenceDataSeeder::class);

    expect(Wilaya::count())->toBe(69)
        ->and(Wilaya::where('code', '16')->value('name'))->toBe('Alger')
        ->and(Wilaya::where('code', '69')->value('name'))->toBe('El Abiodh Sidi Cheikh')
        ->and(Brand::count())->toBeGreaterThanOrEqual(25)
        ->and(VehicleModel::whereNotNull('oil_capacity_l')->count())->toBe(0)
        ->and(ServiceType::where('name', 'Vidange moteur')->first())
        ->requires_mileage->toBeTrue()
        ->reminder_km->toBe(10000)
        ->default_labor_price->toBe('0.00')
        ->and(PaymentMethod::where('is_cash', true)->pluck('name')->all())->toBe(['Espèces']);

    foreach (SystemExpenseCategory::cases() as $key) {
        expect(ExpenseCategory::system($key)->isLocked())->toBeTrue();
    }
});

test('running the seeder again neither duplicates nor overwrites the gerant changes', function () {
    $this->seed(ReferenceDataSeeder::class);
    $counts = [Wilaya::count(), Brand::count(), VehicleModel::count(), ExpenseCategory::count()];

    ServiceType::where('name', 'Vidange moteur')->update(['reminder_km' => 8000, 'default_labor_price' => 1500]);

    $this->seed(ReferenceDataSeeder::class);

    expect([Wilaya::count(), Brand::count(), VehicleModel::count(), ExpenseCategory::count()])->toBe($counts)
        ->and(ServiceType::where('name', 'Vidange moteur')->value('reminder_km'))->toBe(8000);
});
