<?php

use App\Actions\Invoices\ApplyDiscount;
use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\ServiceOrders\CompleteServiceOrder;
use App\Actions\ServiceOrders\SaveServiceOrder;
use App\Actions\Stock\CreateArticle;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceOrderStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemExpenseCategory;
use App\Models\ArticleCategory;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\ServiceOrder;
use App\Models\ServiceType;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->employe = User::factory()->create();
    $this->gerant = User::factory()->gerant()->create();
    $this->actingAs($this->employe);

    ExpenseCategory::factory()->system(SystemExpenseCategory::ExternalParts)->create();
    $this->cash = PaymentMethod::factory()->create(['name' => 'Espèces', 'is_cash' => true]);
    $this->cheque = PaymentMethod::factory()->create(['name' => 'Chèque', 'is_cash' => false]);

    $this->type = ServiceType::factory()->create(['name' => 'Kit de distribution', 'requires_mileage' => false]);
    $this->mechanic = Employee::factory()->create();
    $this->kit = app(CreateArticle::class)->handle(['article_category_id' => ArticleCategory::factory()->create()->id, 'name' => 'Kit de distribution', 'purchase_price' => '20000', 'sale_price' => '25000'], '2');
    $this->vehicle = Vehicle::factory()->create();
});

/**
 * Prestation terminée de 45 000 DA : MO 15 000 + kit 25 000 (stock) + pompe à eau 5 000 (achetée 4 000 à l'extérieur).
 */
function invoicedOrder(): ServiceOrder
{
    $order = app(SaveServiceOrder::class)->handle(null, ['vehicle_id' => test()->vehicle->id, 'date' => today()->toDateString(), 'mileage' => 120000], [[
        'service_type_id' => test()->type->id,
        'employee_id' => test()->mechanic->id,
        'labor_price' => '15000',
        'parts' => [
            ['origin' => 'stock', 'article_id' => test()->kit->id, 'quantity' => '1', 'unit_price' => '25000'],
            ['origin' => 'external', 'designation' => 'Pompe à eau', 'quantity' => '1', 'unit_price' => '5000', 'unit_cost' => '4000'],
        ],
    ]]);

    app(CompleteServiceOrder::class)->handle($order);

    return $order->fresh();
}

function pay(Invoice $invoice, string $amount, ?PaymentMethod $method = null): void
{
    app(RecordPayment::class)->handle($invoice, $amount, now(), $method ?? test()->cash);
}

test('S7: a 45 000 DA invoice, 30 000 paid, 15 000 of credit, then settled later', function () {
    $invoice = invoicedOrder()->invoice;

    expect($invoice->number)->toBe('FA-'.now()->year.'-00001')
        ->and($invoice->labor_total)->toBe('15000.00')
        ->and($invoice->parts_total)->toBe('30000.00')
        ->and($invoice->total)->toBe('45000.00')
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->client->credit())->toBe('45000.00');

    pay($invoice, '30000');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Partial)
        ->and($invoice->fresh()->remaining())->toBe('15000.00')
        ->and($invoice->client->credit())->toBe('15000.00');

    pay($invoice, '15000', $this->cheque);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->client->credit())->toBe('0.00')
        ->and($invoice->payments()->count())->toBe(2);
});

test('a payment above the remaining amount is refused', function () {
    $invoice = invoicedOrder()->invoice;

    expect(fn () => pay($invoice, '45000.01'))->toThrow(ValidationException::class, 'Le montant dépasse le reste à payer');

    pay($invoice, '30000');

    expect(fn () => pay($invoice, '20000'))->toThrow(ValidationException::class, 'Le montant dépasse le reste à payer')
        ->and($invoice->fresh()->amount_paid)->toBe('30000.00');
});

test('a discount is possible until the first payment', function () {
    $invoice = invoicedOrder()->invoice;

    app(ApplyDiscount::class)->handle($invoice, '5000');
    expect($invoice->fresh()->total)->toBe('40000.00');

    expect(fn () => app(ApplyDiscount::class)->handle($invoice, '50000'))->toThrow(ValidationException::class);

    pay($invoice, '40000');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(fn () => app(ApplyDiscount::class)->handle($invoice, '1000'))->toThrow(ValidationException::class, 'déjà un paiement');
});

test('cancelling restores stock and cash, reopens the order, and re-invoicing does not spend twice', function () {
    $order = invoicedOrder();
    $invoice = $order->invoice;
    pay($invoice, '10000');

    expect($this->kit->fresh()->stock_qty)->toBe('1.00')
        ->and(Expense::sole()->amount)->toBe('4000.00');

    expect(fn () => app(CancelInvoice::class)->handle($invoice, '  '))->toThrow(ValidationException::class, 'motif');

    app(CancelInvoice::class)->handle($invoice, 'Erreur sur la pièce');

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Cancelled)
        ->and($invoice->cancel_reason)->toBe('Erreur sur la pièce')
        ->and($invoice->payments()->whereNull('cancelled_at')->count())->toBe(0)
        ->and($invoice->remaining())->toBe('0.00')
        ->and($invoice->client->credit())->toBe('0.00')
        ->and($this->kit->fresh()->stock_qty)->toBe('2.00')
        ->and(StockMovement::where('type', StockMovementType::CancellationIn)->sole()->unit_cost)->toBe('20000.00')
        ->and($order->fresh()->status)->toBe(ServiceOrderStatus::InProgress)
        ->and($order->fresh()->completed_at)->toBeNull()
        ->and(Expense::count())->toBe(1);

    // Correction puis nouvelle facture : nouveau numéro, la pompe déjà payée ne crée pas de seconde dépense.
    app(CompleteServiceOrder::class)->handle($order->fresh());

    expect($order->fresh()->invoice->number)->toBe('FA-'.now()->year.'-00002')
        ->and($order->invoices()->count())->toBe(2)
        ->and($this->kit->fresh()->stock_qty)->toBe('1.00')
        ->and(Expense::count())->toBe(1);

    expect(fn () => app(CancelInvoice::class)->handle($invoice, 'Encore'))->toThrow(ValidationException::class, 'déjà annulée');
});

test('an employee cannot cancel an invoice, the manager can', function () {
    $invoice = invoicedOrder()->invoice;

    $this->post(route('invoices.cancel', $invoice), ['reason' => 'Test'])->assertForbidden();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);

    $this->actingAs($this->gerant)
        ->post(route('invoices.cancel', $invoice), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $this->post(route('invoices.cancel', $invoice), ['reason' => 'Client parti sans la voiture'])->assertRedirect();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled);
});

test('"Terminer et facturer" leads to the invoice, where an employee can cash it', function () {
    $order = app(SaveServiceOrder::class)->handle(null, ['vehicle_id' => $this->vehicle->id, 'date' => today()->toDateString()], [[
        'service_type_id' => $this->type->id, 'employee_id' => $this->mechanic->id, 'labor_price' => '3000', 'parts' => [],
    ]]);

    $this->post(route('service-orders.complete', $order))->assertRedirect(route('invoices.show', $order->fresh()->invoice));

    $invoice = $order->fresh()->invoice;

    $this->get(route('invoices.show', $invoice))->assertInertia(fn (Assert $page) => $page
        ->component('invoices/show')
        ->where('invoice.remaining', '3000.00')
        ->where('invoice.can_pay', true)
        ->where('invoice.can_cancel', false));

    $this->post(route('invoices.payments.store', $invoice), ['amount' => '3500', 'paid_at' => today()->toDateString(), 'payment_method_id' => $this->cash->id])
        ->assertSessionHasErrors('amount');

    $this->post(route('invoices.payments.store', $invoice), ['amount' => '3000', 'paid_at' => today()->toDateString(), 'payment_method_id' => $this->cash->id])
        ->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('the invoice and the receipt print in A4, ticket and PDF', function () {
    $invoice = invoicedOrder()->invoice;
    pay($invoice, '30000');
    $payment = $invoice->payments()->sole();

    $nbsp = "\u{00A0}";

    $this->get(route('invoices.document', [$invoice, 'a4']))->assertOk()->assertSee($invoice->number)->assertSee('Pompe à eau')->assertSee("15{$nbsp}000{$nbsp}DA", false);
    $this->get(route('invoices.document', [$invoice, 'ticket']))->assertOk()->assertSee('80mm auto');
    expect($this->get(route('invoices.document', [$invoice, 'pdf']))->assertOk()->headers->get('content-type'))->toBe('application/pdf');

    $this->get(route('payments.receipt', [$payment, 'a4']))->assertOk()->assertSee('REÇU DE PAIEMENT')->assertSee("30{$nbsp}000{$nbsp}DA", false)->assertSee("15{$nbsp}000{$nbsp}DA", false);
    $this->get(route('invoices.document', [$invoice, 'docx']))->assertNotFound();
});

test('the invoice list, the credit list and the client page show what is owed', function () {
    $this->travelTo(now()->subDays(3));
    $invoice = invoicedOrder()->invoice;
    pay($invoice, '30000');
    $this->travelBack();

    $this->get(route('invoices.index', ['status' => 'partielle']))->assertInertia(fn (Assert $page) => $page
        ->component('invoices/index')
        ->has('rows.data', 1)
        ->where('rows.data.0.remaining', '15000.00'));

    $this->get(route('invoices.credits'))->assertInertia(fn (Assert $page) => $page
        ->component('invoices/credits')
        ->where('total', '15000.00')
        ->where('rows.data.0.credit', '15000.00')
        ->where('rows.data.0.days', 3));

    $this->get(route('clients.show', $invoice->client_id))->assertInertia(fn (Assert $page) => $page
        ->where('credit', '15000.00')
        ->has('invoices', 1));
});
