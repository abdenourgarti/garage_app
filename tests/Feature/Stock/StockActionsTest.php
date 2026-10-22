<?php

use App\Actions\Purchases\CancelPurchase;
use App\Actions\Purchases\PaySupplier;
use App\Actions\Purchases\ReceivePurchase;
use App\Actions\Purchases\RecordPurchase;
use App\Actions\Stock\AdjustStock;
use App\Actions\Stock\CreateArticle;
use App\Actions\Stock\OpenOilCan;
use App\Actions\Stock\SaveOilProduct;
use App\Enums\OilFormat;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemExpenseCategory;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OilProduct;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    ExpenseCategory::factory()->system(SystemExpenseCategory::Purchases)->create();
    $this->cash = PaymentMethod::factory()->create(['name' => 'Espèces', 'is_cash' => true]);
    $this->supplier = Supplier::factory()->create(['name' => 'Naftal Blida']);
    $this->oils = ArticleCategory::factory()->create(['name' => 'Huiles']);

    // Une huile 5W-30 en bidon 5 L et en vrac, chacun avec ses prix (RG-09).
    $this->oil = app(SaveOilProduct::class)->handle(null, ['brand' => 'Total', 'name' => 'Quartz 9000', 'viscosity' => '5W-30', 'usage' => 'moteur'], $this->oils->id, [
        'can_5l' => ['purchase_price' => '4500', 'sale_price' => '5500', 'alert_threshold' => '2'],
        'bulk' => ['purchase_price' => '900', 'sale_price' => '1200'],
    ]);
    $this->can = Article::where('oil_format', OilFormat::Can5L)->sole();
    $this->bulk = Article::where('oil_format', OilFormat::Bulk)->sole();
});

/**
 * L'invariant de StockService : pour chaque article, stock = somme de ses mouvements.
 */
function assertStockMatchesMovements(): void
{
    foreach (Article::all() as $article) {
        $sum = StockMovement::where('article_id', $article->id)->sum('quantity');
        expect(bccomp((string) $sum, $article->stock_qty, 2))->toBe(0, "Stock de {$article->name}");
    }
}

function buy(array $lines, PurchaseStatus $status, ?array $payment = null, ?Purchase $purchase = null): Purchase
{
    return app(RecordPurchase::class)->handle($purchase, ['supplier_id' => test()->supplier->id, 'purchased_at' => today()->toDateString()], $lines, $status, payment: $payment);
}

test('an oil is one sheet and one article per format, each with its own prices', function () {
    expect($this->oil->articles)->toHaveCount(2)
        ->and($this->can->name)->toBe('Total Quartz 9000 5W-30, bidon 5 L')
        ->and($this->can->volume_l)->toBe('5.00')
        ->and($this->can->stock_unit->value)->toBe('unit')
        ->and($this->bulk->stock_unit->value)->toBe('liter')
        ->and($this->can->sale_price)->toBe('5500.00')
        ->and($this->bulk->sale_price)->toBe('1200.00');
});

test('S6: 10 cans of 5W-30 bought, partly paid: stock, expense and supplier debt', function () {
    $purchase = buy([['article_id' => $this->can->id, 'quantity' => '10', 'unit_price' => '4500']], PurchaseStatus::Received, [
        'amount' => '30000', 'paid_at' => today(), 'method' => $this->cash,
    ]);

    expect($purchase->status)->toBe(PurchaseStatus::Received)
        ->and($purchase->total)->toBe('45000.00')
        ->and($purchase->amount_paid)->toBe('30000.00')
        ->and($purchase->remaining())->toBe('15000.00')
        ->and($purchase->paymentStatus())->toBe('partielle')
        ->and($this->can->fresh()->stock_qty)->toBe('10.00');

    $expense = Expense::sole();
    expect($expense->amount)->toBe('30000.00')
        ->and($expense->category->system_key)->toBe(SystemExpenseCategory::Purchases)
        ->and($expense->source)->toBeInstanceOf(PurchasePayment::class)
        ->and($expense->payment_method_id)->toBe($this->cash->id);

    app(PaySupplier::class)->handle($purchase, '15000', today(), $this->cash);

    expect($purchase->fresh()->paymentStatus())->toBe('payee')
        ->and(Expense::sum('amount'))->toEqual(45000);
    assertStockMatchesMovements();
});

test('a supplier can not be paid more than what is left', function () {
    $purchase = buy([['article_id' => $this->can->id, 'quantity' => '2', 'unit_price' => '4500']], PurchaseStatus::Received);

    expect(fn () => app(PaySupplier::class)->handle($purchase, '9000.01', today(), $this->cash))->toThrow(ValidationException::class)
        ->and(fn () => app(PaySupplier::class)->handle($purchase, '0', today(), $this->cash))->toThrow(ValidationException::class);

    expect(Expense::count())->toBe(0);
});

test('opening a 5 L can moves 1 can to 5 litres of bulk', function () {
    buy([['article_id' => $this->can->id, 'quantity' => '3', 'unit_price' => '4500']], PurchaseStatus::Received);

    app(OpenOilCan::class)->handle($this->can);

    expect($this->can->fresh()->stock_qty)->toBe('2.00')
        ->and($this->bulk->fresh()->stock_qty)->toBe('5.00');

    $in = StockMovement::where('type', StockMovementType::CanOpenedIn)->sole();
    expect($in->quantity)->toBe('5.00')->and($in->unit_cost)->toBe('900.00');
    expect(StockMovement::where('type', StockMovementType::CanOpenedOut)->sole()->quantity)->toBe('-1.00');
    assertStockMatchesMovements();
});

test('a can can not be opened without a bulk format or without stock', function () {
    expect(fn () => app(OpenOilCan::class)->handle($this->can))->toThrow(ValidationException::class, 'Aucun bidon en stock');

    $other = OilProduct::factory()->create();
    $lonelyCan = Article::factory()->oil(OilFormat::Can4L, $other)->create();
    expect(fn () => app(OpenOilCan::class)->handle($lonelyCan))->toThrow(ValidationException::class, 'pas de format vrac');

    expect(fn () => app(OpenOilCan::class)->handle($this->bulk))->toThrow(ValidationException::class, 'Seul un bidon');
    expect(StockMovement::count())->toBe(0);
});

test('a stock correction records the counted difference with a mandatory reason', function () {
    buy([['article_id' => $this->can->id, 'quantity' => '10', 'unit_price' => '4500']], PurchaseStatus::Received);

    $movement = app(AdjustStock::class)->handle($this->can, '7', 'Inventaire du samedi');

    expect($movement->quantity)->toBe('-3.00')
        ->and($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->reason)->toBe('Inventaire du samedi')
        ->and($this->can->fresh()->stock_qty)->toBe('7.00');

    expect(fn () => app(AdjustStock::class)->handle($this->can, '5', '  '))->toThrow(ValidationException::class)
        ->and(fn () => app(AdjustStock::class)->handle($this->can, '7', 'Rien'))->toThrow(ValidationException::class);
    assertStockMatchesMovements();
});

test('an article can start with an initial stock, recorded as a movement', function () {
    $article = app(CreateArticle::class)->handle(['article_category_id' => $this->oils->id, 'name' => 'Filtre à huile', 'purchase_price' => '600', 'sale_price' => '1000'], '12');

    expect($article->stock_qty)->toBe('12.00')
        ->and(StockMovement::where('article_id', $article->id)->sole()->reason)->toBe('Stock initial');
    assertStockMatchesMovements();
});

test('the stock only enters when the order is received; the last price becomes the purchase price', function () {
    $purchase = buy([['article_id' => $this->can->id, 'quantity' => '4', 'unit_price' => '4300']], PurchaseStatus::Draft);
    expect($this->can->fresh()->stock_qty)->toBe('0.00');

    $purchase = buy([['article_id' => $this->can->id, 'quantity' => '5', 'unit_price' => '4300']], PurchaseStatus::Ordered, purchase: $purchase);
    expect($purchase->total)->toBe('21500.00')->and($this->can->fresh()->stock_qty)->toBe('0.00');

    app(ReceivePurchase::class)->handle($purchase);

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Received)
        ->and($purchase->fresh()->received_at->isToday())->toBeTrue()
        ->and($this->can->fresh()->stock_qty)->toBe('5.00')
        ->and($this->can->fresh()->purchase_price)->toBe('4300.00');

    expect(fn () => app(ReceivePurchase::class)->handle($purchase))->toThrow(ValidationException::class)
        ->and(fn () => buy([['article_id' => $this->can->id, 'quantity' => '1', 'unit_price' => '1']], PurchaseStatus::Ordered, purchase: $purchase->fresh()))->toThrow(ValidationException::class)
        ->and(fn () => app(CancelPurchase::class)->handle($purchase))->toThrow(ValidationException::class);
    assertStockMatchesMovements();
});

test('only an unpaid order that was not received can be cancelled; a draft can not be paid', function () {
    $draft = buy([['article_id' => $this->can->id, 'quantity' => '1', 'unit_price' => '4500']], PurchaseStatus::Draft);
    expect(fn () => app(PaySupplier::class)->handle($draft, '100', today(), $this->cash))->toThrow(ValidationException::class);

    app(CancelPurchase::class)->handle($draft);
    expect($draft->fresh()->status)->toBe(PurchaseStatus::Cancelled)->and($draft->fresh()->remaining())->toBe('0.00');

    $deposit = buy([['article_id' => $this->can->id, 'quantity' => '1', 'unit_price' => '4500']], PurchaseStatus::Ordered, ['amount' => '1000', 'paid_at' => today(), 'method' => $this->cash]);
    expect(fn () => app(CancelPurchase::class)->handle($deposit))->toThrow(ValidationException::class, 'payé');
});

test('the payment proof and the supplier document are stored privately', function () {
    Storage::fake('local');

    $purchase = app(RecordPurchase::class)->handle(null, ['supplier_id' => $this->supplier->id, 'purchased_at' => today()->toDateString()],
        [['article_id' => $this->can->id, 'quantity' => '1', 'unit_price' => '4500']], PurchaseStatus::Received,
        UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf'),
        ['amount' => '4500', 'paid_at' => today(), 'method' => $this->cash, 'proof' => UploadedFile::fake()->image('cheque.jpg')]);

    Storage::disk('local')->assertExists($purchase->document_path);
    Storage::disk('local')->assertExists($purchase->payments()->sole()->proof_path);
});

test('a failed payment leaves neither the purchase nor its files', function () {
    Storage::fake('local');

    expect(fn () => app(RecordPurchase::class)->handle(null, ['supplier_id' => $this->supplier->id, 'purchased_at' => today()->toDateString()],
        [['article_id' => $this->can->id, 'quantity' => '1', 'unit_price' => '4500']], PurchaseStatus::Received,
        UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf'),
        ['amount' => '9999', 'paid_at' => today(), 'method' => $this->cash, 'proof' => UploadedFile::fake()->image('cheque.jpg')]))
        ->toThrow(ValidationException::class);

    expect(Purchase::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
