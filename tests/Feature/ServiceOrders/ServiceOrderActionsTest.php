<?php

use App\Actions\ServiceOrders\CompleteServiceOrder;
use App\Actions\ServiceOrders\SaveServiceOrder;
use App\Actions\Stock\CreateArticle;
use App\Actions\Stock\SaveOilProduct;
use App\Enums\ImmobilizationUnit;
use App\Enums\OilFormat;
use App\Enums\PartOrigin;
use App\Enums\ServiceOrderStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemExpenseCategory;
use App\Models\ArticleCategory;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\ServiceOrder;
use App\Models\ServiceType;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Services\NumberingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    ExpenseCategory::factory()->system(SystemExpenseCategory::ExternalParts)->create();
    $this->cash = PaymentMethod::factory()->create(['is_cash' => true]);

    $this->oilChange = ServiceType::factory()->create(['name' => 'Vidange moteur', 'requires_mileage' => true, 'reminder_km' => 10000, 'default_labor_price' => '1500']);
    $this->gearbox = ServiceType::factory()->create(['name' => 'Vidange boîte', 'requires_mileage' => true]);
    $this->chainKit = ServiceType::factory()->create(['name' => 'Kit chaîne', 'requires_mileage' => false]);
    [$this->yacine, $this->samir, $this->omar] = Employee::factory()->count(3)->create()->all();

    $category = ArticleCategory::factory()->create();
    $oil = app(SaveOilProduct::class)->handle(null, ['brand' => 'Total', 'viscosity' => '5W-30', 'usage' => 'moteur'], $category->id, [
        'can_5l' => ['purchase_price' => '4500', 'sale_price' => '5500', 'initial_stock' => '3'],
        'bulk' => ['purchase_price' => '900', 'sale_price' => '1200', 'initial_stock' => '20'],
    ]);
    $this->can = $oil->articles()->where('oil_format', OilFormat::Can5L)->sole();
    $this->bulk = $oil->articles()->where('oil_format', OilFormat::Bulk)->sole();
    $this->filter = app(CreateArticle::class)->handle(['article_category_id' => $category->id, 'name' => 'Filtre à huile', 'purchase_price' => '600', 'sale_price' => '1000'], '5');

    $this->vehicle = Vehicle::factory()->create([
        'last_mileage' => 85000,
        'vehicle_model_id' => VehicleModel::factory()->create(['oil_capacity_l' => '4.5'])->id,
    ]);
});

/**
 * @param  list<array<string, mixed>>  $lines
 */
function saveOrder(array $lines, array $data = [], ?ServiceOrder $order = null): ServiceOrder
{
    return app(SaveServiceOrder::class)->handle($order, [
        'vehicle_id' => test()->vehicle->id,
        'date' => today()->toDateString(),
        'mileage' => 95000,
        ...$data,
    ], $lines);
}

function oilChangeLine(array $oilPart): array
{
    return [
        'service_type_id' => test()->oilChange->id,
        'employee_id' => test()->yacine->id,
        'labor_price' => '1500',
        'parts' => [
            $oilPart,
            ['origin' => 'stock', 'article_id' => test()->filter->id, 'quantity' => '1', 'unit_price' => '1000'],
        ],
    ];
}

test('S1 with a 5 L can: numbered, stock out at frozen cost, mileage updated, completed', function () {
    $order = saveOrder([oilChangeLine(['origin' => 'stock', 'article_id' => $this->can->id, 'quantity' => '1', 'unit_price' => '5500'])]);

    expect($order->number)->toBe('PR-'.now()->year.'-00001')
        ->and($order->client_id)->toBe($this->vehicle->client_id)
        ->and($order->load('lines', 'parts')->total())->toBe('8000.00');

    $warnings = app(CompleteServiceOrder::class)->handle($order);

    expect($warnings)->toBe([])
        ->and($order->fresh()->status)->toBe(ServiceOrderStatus::Completed)
        ->and($this->can->fresh()->stock_qty)->toBe('2.00')
        ->and($this->filter->fresh()->stock_qty)->toBe('4.00')
        ->and($this->vehicle->fresh()->last_mileage)->toBe(95000)
        ->and($order->parts()->where('article_id', $this->can->id)->sole()->unit_cost)->toBe('4500.00');

    $out = StockMovement::where('type', StockMovementType::ServiceOut)->where('article_id', $this->can->id)->sole();
    expect($out->quantity)->toBe('-1.00')->and($out->source)->toBeInstanceOf(ServiceOrder::class);
});

test('S1 with 4.5 L of bulk oil: the litre price differs from the can price', function () {
    $order = saveOrder([oilChangeLine(['origin' => 'stock', 'article_id' => $this->bulk->id, 'quantity' => '4.5', 'unit_price' => '1200'])]);

    expect($order->load('lines', 'parts')->partsTotal())->toBe('6400.00');

    app(CompleteServiceOrder::class)->handle($order);

    expect($this->bulk->fresh()->stock_qty)->toBe('15.50');
});

test('S2: three lines, three mechanics, two days of immobilization, one order', function () {
    $order = saveOrder([
        oilChangeLine(['origin' => 'stock', 'article_id' => $this->can->id, 'quantity' => '1', 'unit_price' => '5500']),
        ['service_type_id' => $this->gearbox->id, 'employee_id' => $this->samir->id, 'labor_price' => '2000', 'parts' => [['origin' => 'stock', 'article_id' => $this->bulk->id, 'quantity' => '2', 'unit_price' => '1200']]],
        ['service_type_id' => $this->chainKit->id, 'employee_id' => $this->omar->id, 'labor_price' => '8000', 'parts' => [['origin' => 'external', 'designation' => 'Kit chaîne complet', 'quantity' => '1', 'unit_price' => '22000', 'unit_cost' => '18000']]],
    ], ['immobilization_value' => 2, 'immobilization_unit' => 'days']);

    $order->load('lines.parts', 'parts');
    expect($order->lines)->toHaveCount(3)
        ->and($order->lines->pluck('employee_id')->all())->toBe([$this->yacine->id, $this->samir->id, $this->omar->id])
        ->and($order->lines[2]->parts)->toHaveCount(1)
        ->and($order->immobilization_unit)->toBe(ImmobilizationUnit::Days)
        ->and($order->laborTotal())->toBe('11500.00')
        ->and($order->total())->toBe('42400.00');

    app(CompleteServiceOrder::class)->handle($order);
    expect($order->fresh()->status)->toBe(ServiceOrderStatus::Completed);
});

test('S3: a part from the client is free, a part bought outside becomes an expense', function () {
    $order = saveOrder([[
        'service_type_id' => $this->chainKit->id, 'employee_id' => $this->yacine->id, 'labor_price' => '3000',
        'parts' => [
            ['origin' => 'client', 'designation' => 'Croisillon apporté', 'quantity' => '1', 'unit_price' => '9999'],
            ['origin' => 'external', 'designation' => 'Roulement', 'quantity' => '2', 'unit_price' => '1500', 'unit_cost' => '1100'],
        ],
    ]]);

    $order->load('lines', 'parts');
    $client = $order->parts->firstWhere('origin', PartOrigin::Client);
    expect($client->line_total)->toBe('0.00')->and($client->unit_price)->toBe('0.00')
        ->and($order->total())->toBe('6000.00');

    app(CompleteServiceOrder::class)->handle($order);

    $expense = Expense::sole();
    expect($expense->amount)->toBe('2200.00')
        ->and($expense->category->system_key)->toBe(SystemExpenseCategory::ExternalParts)
        ->and($expense->payment_method_id)->toBe($this->cash->id)
        ->and($expense->source->is($order))->toBeTrue()
        ->and($expense->label)->toContain('Roulement')
        ->and(StockMovement::where('type', StockMovementType::ServiceOut)->count())->toBe(0);
});

test('the mileage is required by an oil change, and a lower mileage only warns', function () {
    $order = saveOrder([oilChangeLine(['origin' => 'stock', 'article_id' => $this->can->id, 'quantity' => '1', 'unit_price' => '5500'])], ['mileage' => null]);

    expect(fn () => app(CompleteServiceOrder::class)->handle($order))
        ->toThrow(ValidationException::class, 'Le kilométrage est obligatoire pour : Vidange moteur.');
    expect($order->fresh()->status)->toBe(ServiceOrderStatus::InProgress)
        ->and(StockMovement::where('type', StockMovementType::ServiceOut)->count())->toBe(0);

    $order = saveOrder([oilChangeLine(['origin' => 'stock', 'article_id' => $this->can->id, 'quantity' => '1', 'unit_price' => '5500'])], ['mileage' => 80000], $order);
    $warnings = app(CompleteServiceOrder::class)->handle($order);

    expect($warnings[0])->toContain('Kilométrage inférieur au dernier relevé (85 000 km)')
        ->and($order->fresh()->status)->toBe(ServiceOrderStatus::Completed)
        ->and($this->vehicle->fresh()->last_mileage)->toBe(85000);
});

test('a mechanic is required on each line, the labour can be 0 DA', function () {
    $order = saveOrder([['service_type_id' => $this->chainKit->id, 'employee_id' => null, 'labor_price' => '0']]);

    expect(fn () => app(CompleteServiceOrder::class)->handle($order))->toThrow(ValidationException::class, 'Choisissez le mécanicien.');

    $order = saveOrder([['service_type_id' => $this->chainKit->id, 'employee_id' => $this->omar->id, 'labor_price' => '0']], [], $order);
    app(CompleteServiceOrder::class)->handle($order);
    expect($order->fresh()->load('lines', 'parts')->total())->toBe('0.00');
});

test('an insufficient stock warns without blocking (RG-11)', function () {
    $order = saveOrder([oilChangeLine(['origin' => 'stock', 'article_id' => $this->can->id, 'quantity' => '5', 'unit_price' => '5500'])]);

    $warnings = app(CompleteServiceOrder::class)->handle($order);

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('Stock insuffisant pour Total 5W-30, bidon 5 L')
        ->and($this->can->fresh()->stock_qty)->toBe('-2.00')
        ->and($order->fresh()->status)->toBe(ServiceOrderStatus::Completed);
});

test('a completed order can neither be changed nor completed again', function () {
    $order = saveOrder([oilChangeLine(['origin' => 'stock', 'article_id' => $this->can->id, 'quantity' => '1', 'unit_price' => '5500'])]);
    app(CompleteServiceOrder::class)->handle($order);

    expect(fn () => saveOrder([], [], $order->fresh()))->toThrow(ValidationException::class, 'ne peut plus être modifiée')
        ->and(fn () => app(CompleteServiceOrder::class)->handle($order))->toThrow(ValidationException::class);
    expect($this->can->fresh()->stock_qty)->toBe('2.00');
});

test('numbers are continuous per year and restart each year', function () {
    $numbering = app(NumberingService::class);

    expect($numbering->serviceOrder(now()))->toBe('PR-'.now()->year.'-00001')
        ->and($numbering->serviceOrder(now()))->toBe('PR-'.now()->year.'-00002')
        ->and($numbering->serviceOrder(now()->addYear()))->toBe('PR-'.(now()->year + 1).'-00001');
});

test('a second workstation waits while the counter is locked (no duplicate number)', function () {
    // Poste A : numéro pris, transaction ouverte (RefreshDatabase) → le compteur reste verrouillé.
    app(NumberingService::class)->serviceOrder(now());

    // Poste B : une autre connexion MariaDB, qui abandonne au bout d'une seconde d'attente.
    config(['database.connections.second_workstation' => config('database.connections.'.config('database.default'))]);
    DB::connection('second_workstation')->statement('SET SESSION innodb_lock_wait_timeout = 1');
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('second_workstation');

    try {
        // 1205 : délai d'attente du verrou dépassé (le message de MariaDB dépend de sa langue).
        expect(fn () => app(NumberingService::class)->serviceOrder(now()))
            ->toThrow(fn (QueryException $exception) => expect($exception->errorInfo[1])->toBe(1205));
    } finally {
        DB::setDefaultConnection($default);
        DB::purge('second_workstation');
    }
});
