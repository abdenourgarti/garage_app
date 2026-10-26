<?php

use App\Actions\Invoices\RecordPayment;
use App\Enums\InvoiceStatus;
use App\Enums\OilFormat;
use App\Enums\PartOrigin;
use App\Models\Article;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\OilProduct;
use App\Models\OtherIncome;
use App\Models\PaymentMethod;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use App\Models\ServiceOrderPart;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-26 10:00'));
    $this->gerant = User::factory()->gerant()->create();
    $this->actingAs($this->gerant);
    $this->cash = PaymentMethod::factory()->create(['name' => 'Espèces', 'is_cash' => true]);
});

function dashboardInvoice(string $total): Invoice
{
    $order = ServiceOrder::factory()->completed()->create();

    return Invoice::forceCreate([
        'number' => 'FA-2026-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT), 'service_order_id' => $order->id, 'client_id' => $order->client_id,
        'issued_at' => '2026-10-01', 'labor_total' => $total, 'parts_total' => 0, 'total' => $total, 'amount_paid' => 0,
        'status' => InvoiceStatus::Unpaid, 'user_id' => test()->gerant->id,
    ]);
}

test('the manager sees today, twelve months and the client credits', function () {
    $invoice = dashboardInvoice('30000');
    app(RecordPayment::class)->handle($invoice, '20000', Carbon::parse('2026-10-26 09:00'), $this->cash);
    app(RecordPayment::class)->handle(dashboardInvoice('8000'), '8000', Carbon::parse('2026-08-14 09:00'), $this->cash);
    OtherIncome::factory()->create(['amount' => '1000', 'received_at' => '2026-10-26']);
    Expense::factory()->create(['amount' => '4500', 'spent_at' => '2026-10-26']);
    Expense::factory()->create(['amount' => '40000', 'spent_at' => '2025-11-01']);
    // Hors fenêtre : octobre 2025 n'est plus dans les 12 derniers mois.
    Expense::factory()->create(['amount' => '99999', 'spent_at' => '2025-10-31']);

    $this->actingAs($this->gerant)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('money.today', ['incomes' => '21000.00', 'expenses' => '4500.00', 'profit' => '16500.00'])
        ->has('money.months', 12)
        ->where('money.months.0', ['month' => '2025-11', 'incomes' => '0.00', 'expenses' => '40000.00', 'profit' => '-40000.00'])
        ->where('money.months.9', ['month' => '2026-08', 'incomes' => '8000.00', 'expenses' => '0.00', 'profit' => '8000.00'])
        ->where('money.months.10.incomes', '0.00')
        ->where('money.months.11', ['month' => '2026-10', 'incomes' => '21000.00', 'expenses' => '4500.00', 'profit' => '16500.00'])
        ->where('money.credits', ['clients' => 1, 'total' => '10000.00'])
        ->where('ordersToday', 2));
});

test('an employee receives no money at all, only the workshop activity', function () {
    app(RecordPayment::class)->handle(dashboardInvoice('30000'), '20000', Carbon::parse('2026-10-26 09:00'), $this->cash);
    ServiceOrder::factory()->create();

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('money', null)
        ->has('inProgress', 1)
        ->has('lowStock')
        ->has('rankings.services'));

    // Rien d'argent dans la page envoyée, sous aucune clé.
    $props = json_encode($response->viewData('page')['props']);
    expect($props)->not->toContain('20000')->not->toContain('incomes')->not->toContain('credits');
});

test('the cached figures are refreshed as soon as a payment is recorded', function () {
    $invoice = dashboardInvoice('30000');
    $this->actingAs($this->gerant);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('money.today.incomes', '0.00'));

    app(RecordPayment::class)->handle($invoice, '5000', now(), $this->cash);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('money.today.incomes', '5000.00'));

    Expense::factory()->create(['amount' => '1200', 'spent_at' => today()]);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('money.today.expenses', '1200.00'));
});

test('rankings count completed work of the last twelve months: services, oils in liters and cans, parts, mechanics', function () {
    $oil = OilProduct::factory()->create(['brand' => 'Total', 'name' => 'Quartz 9000', 'viscosity' => '5W-40']);
    $can = Article::factory()->oil(OilFormat::Can5L, $oil)->create();
    $bulk = Article::factory()->oil(OilFormat::Bulk, $oil)->create();
    $filter = Article::factory()->create(['name' => 'Filtre à huile W712']);
    $drain = ServiceType::factory()->create(['name' => 'Vidange moteur']);
    $brakes = ServiceType::factory()->create(['name' => 'Plaquettes']);
    $mourad = Employee::factory()->create(['name' => 'Mourad']);
    $bilal = Employee::factory()->create(['name' => 'Bilal']);

    $done = ServiceOrder::factory()->completed()->create();
    ServiceOrderLine::factory()->create(['service_order_id' => $done->id, 'service_type_id' => $drain->id, 'employee_id' => $mourad->id]);
    ServiceOrderLine::factory()->create(['service_order_id' => $done->id, 'service_type_id' => $brakes->id, 'employee_id' => $mourad->id]);
    ServiceOrderPart::factory()->create(['service_order_id' => $done->id, 'origin' => PartOrigin::Stock, 'article_id' => $can->id, 'quantity' => '2']);
    ServiceOrderPart::factory()->create(['service_order_id' => $done->id, 'origin' => PartOrigin::Stock, 'article_id' => $bulk->id, 'quantity' => '4.5']);
    ServiceOrderPart::factory()->create(['service_order_id' => $done->id, 'origin' => PartOrigin::Stock, 'article_id' => $filter->id, 'quantity' => '1']);

    $other = ServiceOrder::factory()->completed()->create(['date' => '2026-03-02']);
    ServiceOrderLine::factory()->create(['service_order_id' => $other->id, 'service_type_id' => $drain->id, 'employee_id' => $bilal->id]);
    ServiceOrderPart::factory()->create(['service_order_id' => $other->id, 'origin' => PartOrigin::Stock, 'article_id' => $filter->id, 'quantity' => '1']);
    // L'huile apportée par le client n'est pas une vente.
    ServiceOrderPart::factory()->create(['service_order_id' => $other->id, 'origin' => PartOrigin::Client, 'article_id' => $can->id, 'quantity' => '9']);

    // Ne comptent pas : une prestation en cours et une prestation d'il y a plus de 12 mois.
    $open = ServiceOrder::factory()->create();
    ServiceOrderLine::factory()->create(['service_order_id' => $open->id, 'service_type_id' => $brakes->id, 'employee_id' => $bilal->id]);
    $old = ServiceOrder::factory()->completed()->create(['date' => '2025-10-31']);
    ServiceOrderLine::factory()->create(['service_order_id' => $old->id, 'service_type_id' => $brakes->id, 'employee_id' => $bilal->id]);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('rankings.services', [['name' => 'Vidange moteur', 'count' => 2], ['name' => 'Plaquettes', 'count' => 1]])
        ->where('rankings.oils', [['name' => 'Total Quartz 9000 5W-40', 'liters' => '14.50', 'cans' => 2]])
        ->where('rankings.parts', [['name' => 'Filtre à huile W712', 'quantity' => '2.00', 'unit' => $filter->stock_unit->symbol()]])
        ->where('rankings.mechanics', [['name' => 'Mourad', 'count' => 2, 'orders' => 1], ['name' => 'Bilal', 'count' => 1, 'orders' => 1]])
        ->has('inProgress', 1)
        ->where('inProgress.0.id', $open->id));
});

test('articles at or below their alert threshold are listed, inactive ones are not', function () {
    Article::factory()->create(['name' => 'Filtre bas', 'stock_qty' => '1', 'alert_threshold' => '3']);
    Article::factory()->create(['name' => 'Filtre plein', 'stock_qty' => '10', 'alert_threshold' => '3']);
    Article::factory()->create(['name' => 'Filtre retiré', 'stock_qty' => '0', 'alert_threshold' => '3', 'is_active' => false]);

    $this->actingAs($this->gerant)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('lowStock', 1)
        ->where('lowStock.0.name', 'Filtre bas'));
});
