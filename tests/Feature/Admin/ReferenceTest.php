<?php

use App\Enums\SystemExpenseCategory;
use App\Models\ArticleCategory;
use App\Models\Brand;
use App\Models\Energy;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VehicleModel;
use App\Models\Wilaya;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Un référentiel par ligne : [type d'URL, classe, données valides, données modifiées].
 */
dataset('references', [
    'marques' => ['brands', Brand::class, ['name' => 'Renault'], ['name' => 'Renault Trucks']],
    'énergies' => ['energies', Energy::class, ['name' => 'GPL'], ['name' => 'GPL-c']],
    'catégories d’articles' => ['article-categories', ArticleCategory::class, ['name' => 'Filtres'], ['name' => 'Filtration']],
    'fournisseurs' => ['suppliers', Supplier::class, ['name' => 'Pièces Auto Blida', 'phone' => '0550 11 22 33', 'notes' => null], ['name' => 'Pièces Auto Blida', 'phone' => '0661 00 00 00', 'email' => 'contact@pieces-blida.dz', 'address' => 'Zone industrielle, Blida', 'maps_url' => 'https://maps.app.goo.gl/AbC123', 'notes' => 'Livraison le mardi']],
    'catégories de dépenses' => ['expense-categories', ExpenseCategory::class, ['name' => 'Loyer'], ['name' => 'Loyer local']],
    'modes de paiement' => ['payment-methods', PaymentMethod::class, ['name' => 'Espèces', 'is_cash' => true], ['name' => 'Espèces DA', 'is_cash' => true]],
    'wilayas' => ['wilayas', Wilaya::class, ['code' => '16', 'name' => 'Alger'], ['code' => '16', 'name' => 'Alger Centre']],
    'types de prestations' => ['service-types', ServiceType::class, ['name' => 'Vidange moteur', 'default_labor_price' => '1500', 'requires_mileage' => true, 'reminder_km' => 10000], ['name' => 'Vidange moteur', 'default_labor_price' => '2000.50', 'requires_mileage' => true, 'reminder_km' => 15000]],
]);

test('a reference can be created, listed, searched, updated and toggled', function (string $type, string $model, array $data, array $changes) {
    $this->post(route('admin.references.store', $type), $data)->assertSessionHasNoErrors()->assertRedirect();

    $item = $model::firstOrFail();
    expect($item->is_active)->toBeTrue();

    $this->get(route('admin.references.index', ['type' => $type, 'search' => mb_substr($data['name'], 0, 3)]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/references')
            ->where('type', $type)
            ->has('rows.data', 1));

    $this->put(route('admin.references.update', [$type, $item->id]), $changes)->assertSessionHasNoErrors();
    expect($item->fresh()->name)->toBe($changes['name']);

    $this->patch(route('admin.references.toggle', [$type, $item->id]));
    expect($item->fresh()->is_active)->toBeFalse();
    expect($model::active()->count())->toBe(0);
})->with('references');

test('an unused reference can be deleted', function () {
    $brand = Brand::factory()->create();

    $this->delete(route('admin.references.destroy', ['brands', $brand->id]))->assertRedirect();

    expect(Brand::count())->toBe(0);
});

test('a used reference can not be deleted', function () {
    $brand = Brand::factory()->create();
    VehicleModel::factory()->for($brand)->create();

    $this->delete(route('admin.references.destroy', ['brands', $brand->id]))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Cet élément est utilisé. Désactivez-le plutôt.']);

    expect($brand->fresh())->not->toBeNull();
});

test('names must be unique and a vehicle model is unique per brand only', function () {
    Brand::factory()->create(['name' => 'Peugeot']);
    $this->post(route('admin.references.store', 'brands'), ['name' => 'Peugeot'])->assertSessionHasErrors('name');

    [$peugeot, $renault] = [Brand::first(), Brand::factory()->create()];
    VehicleModel::factory()->for($peugeot)->create(['name' => 'Partner']);

    $this->post(route('admin.references.store', 'vehicle-models'), ['brand_id' => $peugeot->id, 'name' => 'Partner'])
        ->assertSessionHasErrors('name');
    $this->post(route('admin.references.store', 'vehicle-models'), ['brand_id' => $renault->id, 'name' => 'Partner', 'oil_capacity_l' => '4.5'])
        ->assertSessionHasNoErrors();

    expect(VehicleModel::where('brand_id', $renault->id)->value('oil_capacity_l'))->toBe('4.50');
});

test('vehicle models are listed by brand, then by name', function () {
    VehicleModel::factory()->for(Brand::factory()->create(['name' => 'Peugeot']))->create(['name' => '1007']);
    VehicleModel::factory()->for(Brand::factory()->create(['name' => 'Audi']))->create(['name' => 'A3']);
    VehicleModel::factory()->for(Brand::firstWhere('name', 'Audi'))->create(['name' => '100']);

    $this->get(route('admin.references.index', 'vehicle-models'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.name', '100')
            ->where('rows.data.1.name', 'A3')
            ->where('rows.data.2.brand.name', 'Peugeot'));
});

test('supplier contact fields are optional but must be valid', function () {
    $this->post(route('admin.references.store', 'suppliers'), ['name' => 'Sans contact'])->assertSessionHasNoErrors();

    $this->post(route('admin.references.store', 'suppliers'), [
        'name' => 'Mauvais contact',
        'email' => 'pas-un-email',
        'maps_url' => 'javascript:alert(1)',
    ])->assertSessionHasErrors(['email', 'maps_url']);

    $this->put(route('admin.references.update', ['suppliers', Supplier::firstOrFail()->id]), [
        'name' => 'Sans contact',
        'email' => 'a@b.dz',
        'address' => 'Baba Ali',
        'maps_url' => 'https://maps.app.goo.gl/x',
    ])->assertSessionHasNoErrors();

    expect(Supplier::firstOrFail()->only(['email', 'address', 'maps_url']))
        ->toBe(['email' => 'a@b.dz', 'address' => 'Baba Ali', 'maps_url' => 'https://maps.app.goo.gl/x']);
});

test('a wilaya code has exactly two digits', function () {
    $this->post(route('admin.references.store', 'wilayas'), ['code' => '7', 'name' => 'Biskra'])->assertSessionHasErrors('code');
});

test('vehicle models can be filtered by brand', function () {
    $peugeot = Brand::factory()->create();
    VehicleModel::factory()->for($peugeot)->count(2)->create();
    VehicleModel::factory()->count(3)->create();

    $this->get(route('admin.references.index', ['type' => 'vehicle-models', 'brand' => $peugeot->id]))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 2)->has('brands', 4));
});

test('system expense categories can not be renamed, deactivated or deleted', function () {
    $category = ExpenseCategory::factory()->system(SystemExpenseCategory::Purchases)->create();
    $this->actingAs(User::factory()->gerant()->create());

    $this->put(route('admin.references.update', ['expense-categories', $category->id]), ['name' => 'Autre nom'])
        ->assertInertiaFlash('toast.type', 'error');
    $this->patch(route('admin.references.toggle', ['expense-categories', $category->id]));
    $this->delete(route('admin.references.destroy', ['expense-categories', $category->id]));

    $category->refresh();
    expect($category->name)->toBe('Achats pièces')
        ->and($category->is_active)->toBeTrue()
        ->and(ExpenseCategory::system(SystemExpenseCategory::Purchases)->is($category))->toBeTrue();
});

test('the system key can not be set from the form', function () {
    $this->post(route('admin.references.store', 'expense-categories'), ['name' => 'Piratage', 'system_key' => 'salaries']);

    expect(ExpenseCategory::where('name', 'Piratage')->value('system_key'))->toBeNull();
});

test('an unknown reference type is not found', function () {
    $this->get('/admin/references/users')->assertNotFound();
});

test('guests can not reach the references', function () {
    auth()->logout();

    $this->get(route('admin.references.index', 'brands'))->assertRedirect(route('login'));
});
