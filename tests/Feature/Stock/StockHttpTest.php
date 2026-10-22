<?php

use App\Enums\OilFormat;
use App\Enums\PurchaseStatus;
use App\Enums\SystemExpenseCategory;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\ExpenseCategory;
use App\Models\OilProduct;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    ExpenseCategory::factory()->system(SystemExpenseCategory::Purchases)->create();
    $this->cash = PaymentMethod::factory()->create(['is_cash' => true]);
    $this->category = ArticleCategory::factory()->create(['name' => 'Huiles']);
    $this->supplier = Supplier::factory()->create();
});

test('both roles can open every stock screen', function () {
    $article = Article::factory()->create();
    $purchase = Purchase::factory()->for($this->supplier)->create();

    foreach ([User::factory()->create(), User::factory()->gerant()->create()] as $user) {
        $this->actingAs($user);
        $this->get(route('stock.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('stock/index'));
        $this->get(route('articles.show', $article))->assertOk();
        $this->get(route('purchases.index'))->assertOk();
        $this->get(route('purchases.create'))->assertOk();
        $this->get(route('purchases.show', $purchase))->assertOk();
        $this->get(route('suppliers.index'))->assertOk();
        $this->get(route('suppliers.show', $this->supplier))->assertOk();
    }
});

test('an article is created with its initial stock, then corrected', function () {
    $this->post(route('articles.store'), [
        'article_category_id' => $this->category->id, 'name' => 'Filtre à huile Purflux', 'reference' => 'LS933',
        'stock_unit' => 'unit', 'purchase_price' => '650', 'sale_price' => '1000', 'alert_threshold' => '3', 'initial_stock' => '10',
    ])->assertSessionHasNoErrors();

    $article = Article::sole();
    expect($article->stock_qty)->toBe('10.00');

    $this->post(route('articles.adjust', $article), ['counted_quantity' => '8', 'reason' => ''])->assertSessionHasErrors('reason');
    $this->post(route('articles.adjust', $article), ['counted_quantity' => '2', 'reason' => 'Inventaire'])->assertSessionHasNoErrors();

    expect($article->fresh()->stock_qty)->toBe('2.00')->and($article->fresh()->stockStatus())->toBe('bas');

    $this->get(route('stock.index', ['low' => 1]))->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('lowCount', 1));
});

test('an oil is created with its checked formats, and a format can be added later', function () {
    $this->post(route('oils.store'), [
        'brand' => 'Shell', 'name' => 'Helix HX7', 'viscosity' => '10W-40', 'usage' => 'moteur', 'article_category_id' => $this->category->id,
        'formats' => [
            ['format' => 'can_4l', 'purchase_price' => '3600', 'sale_price' => '4400', 'initial_stock' => '6'],
            ['format' => 'bulk', 'purchase_price' => '850', 'sale_price' => '1100'],
        ],
    ])->assertSessionHasNoErrors();

    $oil = OilProduct::sole();
    expect($oil->articles)->toHaveCount(2)
        ->and(Article::where('oil_format', OilFormat::Can4L)->sole()->stock_qty)->toBe('6.00');

    $this->put(route('oils.update', $oil), [
        'brand' => 'Shell', 'name' => 'Helix HX7', 'viscosity' => '10W-40', 'usage' => 'moteur', 'article_category_id' => $this->category->id,
        'formats' => [['format' => 'can_1l', 'purchase_price' => '1000', 'sale_price' => '1300']],
    ])->assertSessionHasNoErrors();

    expect($oil->articles()->count())->toBe(3);

    $can = Article::where('oil_format', OilFormat::Can4L)->sole();
    $this->post(route('articles.open-can', $can))->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Bidon ouvert : +4 L dans le vrac (4 L en stock).');
});

test('an oil needs at least one format', function () {
    $this->post(route('oils.store'), [
        'brand' => 'Shell', 'viscosity' => '10W-40', 'usage' => 'moteur', 'article_category_id' => $this->category->id, 'formats' => [],
    ])->assertSessionHasErrors('formats');
});

test('a purchase is received and paid with a proof, its files are served to logged in users only', function () {
    Storage::fake('local');
    $article = Article::factory()->create();

    $this->post(route('purchases.store'), [
        'supplier_id' => $this->supplier->id, 'purchased_at' => today()->toDateString(), 'status' => 'received',
        'lines' => [['article_id' => $article->id, 'quantity' => '4', 'unit_price' => '750']],
        'document' => UploadedFile::fake()->create('facture.pdf', 50, 'application/pdf'),
        'paid_amount' => '1000', 'payment_method_id' => $this->cash->id, 'paid_at' => today()->toDateString(),
        'proof' => UploadedFile::fake()->image('recu.jpg'),
    ])->assertSessionHasNoErrors();

    $purchase = Purchase::sole();
    expect($purchase->status)->toBe(PurchaseStatus::Received)
        ->and($article->fresh()->stock_qty)->toBe('4.00');

    $this->get(route('purchases.document', $purchase))->assertOk();
    $this->get(route('purchase-payments.proof', $purchase->payments()->sole()))->assertOk();

    $this->get(route('suppliers.show', $this->supplier))
        ->assertInertia(fn (Assert $page) => $page->where('totals.debt', '2000.00')->where('purchases.0.is_payable', true));

    $this->post(route('purchases.payments.store', $purchase), ['amount' => '2000', 'paid_at' => today()->toDateString(), 'payment_method_id' => $this->cash->id])
        ->assertSessionHasNoErrors();
    expect($purchase->fresh()->paymentStatus())->toBe('payee');

    auth()->logout();
    $this->get(route('purchases.document', $purchase))->assertRedirect(route('login'));
});

test('a payment needs a method, and a draft is saved then ordered with POST + _method=put', function () {
    $article = Article::factory()->create();
    $lines = [['article_id' => $article->id, 'quantity' => '2', 'unit_price' => '500']];

    $this->post(route('purchases.store'), ['supplier_id' => $this->supplier->id, 'purchased_at' => today()->toDateString(), 'status' => 'ordered', 'lines' => $lines, 'paid_amount' => '100'])
        ->assertSessionHasErrors('payment_method_id');

    $this->post(route('purchases.store'), ['supplier_id' => $this->supplier->id, 'purchased_at' => today()->toDateString(), 'status' => 'draft', 'lines' => $lines])
        ->assertSessionHasNoErrors();
    $purchase = Purchase::sole();

    $this->post(route('purchases.update', $purchase), ['_method' => 'put', 'supplier_id' => $this->supplier->id, 'purchased_at' => today()->toDateString(), 'status' => 'ordered', 'lines' => [['article_id' => $article->id, 'quantity' => '3', 'unit_price' => '500']]])
        ->assertSessionHasNoErrors();

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Ordered)
        ->and($purchase->fresh()->total)->toBe('1500.00')
        ->and($article->fresh()->stock_qty)->toBe('0.00');

    $this->post(route('purchases.receive', $purchase))->assertSessionHasNoErrors();
    expect($article->fresh()->stock_qty)->toBe('3.00');

    $this->get(route('purchases.edit', $purchase))->assertRedirect(route('purchases.show', $purchase));
});

test('articles are found word by word for the purchase lines', function () {
    $oil = OilProduct::factory()->create(['brand' => 'Total', 'name' => 'Quartz 9000', 'viscosity' => '5W-30']);
    Article::factory()->oil(OilFormat::Can5L, $oil)->create();
    Article::factory()->create(['name' => 'Filtre à air']);

    $this->getJson(route('lookup.articles', ['q' => 'quartz 5w-30']))
        ->assertJsonCount(1)
        ->assertJsonPath('0.label', 'Total Quartz 9000 5W-30, bidon 5 L')
        ->assertJsonPath('0.unit', 'u');
});
