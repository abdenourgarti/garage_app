<?php

use App\Actions\Invoices\RecordPayment;
use App\Actions\Payroll\PaySalary;
use App\Actions\Payroll\RecordSalaryAdvance;
use App\Enums\InvoiceStatus;
use App\Enums\SalaryType;
use App\Enums\SystemExpenseCategory;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\PaymentMethod;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\CashService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-31 18:00'));

    $this->gerant = User::factory()->gerant()->create();
    $this->actingAs($this->gerant);

    ExpenseCategory::factory()->system(SystemExpenseCategory::Advances)->create(['name' => 'Avances sur salaire']);
    ExpenseCategory::factory()->system(SystemExpenseCategory::Salaries)->create(['name' => 'Salaires']);
    $this->rent = ExpenseCategory::factory()->create(['name' => 'Loyer']);
    $this->power = ExpenseCategory::factory()->create(['name' => 'Électricité']);
    $this->cash = PaymentMethod::factory()->create(['name' => 'Espèces', 'is_cash' => true]);
    $this->cheque = PaymentMethod::factory()->create(['name' => 'Chèque', 'is_cash' => false]);
});

/**
 * Facture de démonstration (le calcul de la caisse ne regarde que les paiements).
 */
function cashInvoice(string $total): Invoice
{
    $order = ServiceOrder::factory()->create();

    return Invoice::forceCreate([
        'number' => 'FA-2026-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT), 'service_order_id' => $order->id, 'client_id' => $order->client_id,
        'issued_at' => '2026-10-01', 'labor_total' => $total, 'parts_total' => 0, 'total' => $total, 'amount_paid' => 0,
        'status' => InvoiceStatus::Unpaid, 'user_id' => test()->gerant->id,
    ]);
}

function cashSummary(string $from, string $to): array
{
    return app(CashService::class)->summary(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
}

test('S8: rent, electricity, advance, salaries, customer payments and another income give the profit of the month', function () {
    $this->post(route('cash.expenses.store'), ['expense_category_id' => $this->rent->id, 'label' => 'Loyer octobre', 'amount' => '40000', 'spent_at' => '2026-10-01', 'payment_method_id' => $this->cash->id])->assertSessionHasNoErrors();
    $this->post(route('cash.expenses.store'), ['expense_category_id' => $this->power->id, 'label' => 'Sonelgaz', 'amount' => '8500', 'spent_at' => '2026-10-15', 'payment_method_id' => $this->cheque->id])->assertSessionHasNoErrors();

    $employee = Employee::factory()->create(['salary_type' => SalaryType::Monthly, 'salary_amount' => '45000']);
    app(RecordSalaryAdvance::class)->handle($employee, '5000', Carbon::parse('2026-10-10'), $this->cash);
    app(PaySalary::class)->handle($employee, '2026-10', null, Carbon::parse('2026-10-31'), $this->cash);

    $invoice = cashInvoice('30000');
    app(RecordPayment::class)->handle($invoice, '25000', Carbon::parse('2026-10-20 10:00'), $this->cash);

    // Un paiement annulé (facture annulée) ne compte plus.
    $cancelled = cashInvoice('10000');
    app(RecordPayment::class)->handle($cancelled, '10000', Carbon::parse('2026-10-21 11:00'), $this->cash);
    $cancelled->payments()->update(['cancelled_at' => now()]);

    $this->post(route('cash.incomes.store'), ['label' => 'Vente batterie d’occasion', 'amount' => '3000', 'received_at' => '2026-10-22', 'payment_method_id' => $this->cash->id])->assertSessionHasNoErrors();

    $summary = cashSummary('2026-10-01', '2026-10-31');

    expect($summary['incomes'])->toBe('28000.00')
        ->and($summary['expenses'])->toBe('93500.00')
        ->and($summary['profit'])->toBe('-65500.00')
        ->and(collect($summary['by_category'])->pluck('total', 'name')->all())->toBe(['Loyer' => '40000.00', 'Salaires' => '40000.00', 'Électricité' => '8500.00', 'Avances sur salaire' => '5000.00'])
        ->and($summary['by_method'])->toBe([['name' => 'Espèces', 'total' => '25000.00'], ['name' => 'Autres entrées', 'total' => '3000.00']]);

    // Espèces du 31 : la paie (40 000) est sortie en espèces, rien n'est entré.
    expect(app(CashService::class)->cashOfDay(CarbonImmutable::parse('2026-10-31')))->toBe('-40000.00');

    $this->get(route('cash.index', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertInertia(fn (Assert $page) => $page
        ->component('cash/index')
        ->where('summary.profit', '-65500.00')
        ->where('cashToday', '-40000.00')
        ->has('rows.data', 6)
        ->where('rows.data.0.origin', 'salary_payment')
        ->where('rows.data.0.link_id', $employee->id)
        ->where('rows.data.0.link_extra', '2026-10'));

    $this->get(route('cash.index', ['from' => '2026-10-01', 'to' => '2026-10-31', 'kind' => 'income']))->assertInertia(fn (Assert $page) => $page
        ->has('rows.data', 2)
        ->where('rows.data.1.origin', 'payment')
        ->where('rows.data.1.link_id', $invoice->id));

    $this->get(route('cash.index', ['from' => '2026-10-01', 'to' => '2026-10-31', 'category' => $this->rent->id]))->assertInertia(fn (Assert $page) => $page
        ->has('rows.data', 1)
        ->where('rows.data.0.editable', true));
});

test('period bounds follow the garage clock: 23:30 on the last day counts, 00:10 the next day does not', function () {
    expect(config('app.timezone'))->toBe('Africa/Algiers');

    $invoice = cashInvoice('20000');
    app(RecordPayment::class)->handle($invoice, '7000', Carbon::parse('2026-10-31 23:30'), $this->cash);
    app(RecordPayment::class)->handle($invoice, '5000', Carbon::parse('2026-11-01 00:10'), $this->cash);
    app(RecordPayment::class)->handle($invoice, '1000', Carbon::parse('2026-10-01 00:00'), $this->cash);

    expect(cashSummary('2026-10-01', '2026-10-31')['incomes'])->toBe('8000.00')
        ->and(cashSummary('2026-11-01', '2026-11-30')['incomes'])->toBe('5000.00')
        ->and(cashSummary('2026-10-31', '2026-10-31')['incomes'])->toBe('7000.00')
        // Semaine du lundi 26 au dimanche 1er novembre.
        ->and(cashSummary('2026-10-26', '2026-11-01')['incomes'])->toBe('12000.00');
});

test('automatic expenses are read-only here, manual ones can be changed', function () {
    $employee = Employee::factory()->create(['salary_amount' => '45000']);
    $advance = app(RecordSalaryAdvance::class)->handle($employee, '5000', Carbon::parse('2026-10-10'), $this->cash);

    $this->put(route('cash.expenses.update', $advance), ['expense_category_id' => $this->rent->id, 'label' => 'x', 'amount' => '1', 'spent_at' => '2026-10-10', 'payment_method_id' => $this->cash->id])->assertSessionHasErrors('expense');
    $this->delete(route('cash.expenses.destroy', $advance))->assertSessionHasErrors('expense');
    expect($advance->fresh()->amount)->toBe('5000.00');

    // Une catégorie système ne se choisit pas à la main.
    $this->post(route('cash.expenses.store'), ['expense_category_id' => $advance->expense_category_id, 'label' => 'Avance', 'amount' => '1000', 'spent_at' => '2026-10-10', 'payment_method_id' => $this->cash->id])->assertSessionHasErrors('expense_category_id');

    $rent = Expense::factory()->create(['expense_category_id' => $this->rent->id, 'amount' => '40000']);
    $this->put(route('cash.expenses.update', $rent), ['expense_category_id' => $this->rent->id, 'label' => 'Loyer corrigé', 'amount' => '42000', 'spent_at' => '2026-10-01', 'payment_method_id' => $this->cash->id])->assertSessionHasNoErrors();
    expect($rent->fresh()->amount)->toBe('42000.00');
    $this->delete(route('cash.expenses.destroy', $rent))->assertSessionHasNoErrors();
    expect(Expense::find($rent->id))->toBeNull();

    $income = OtherIncome::factory()->create();
    $this->put(route('cash.incomes.update', $income), ['label' => 'Remboursement', 'amount' => '1500', 'received_at' => '2026-10-05', 'payment_method_id' => $this->cash->id])->assertSessionHasNoErrors();
    expect($income->fresh()->label)->toBe('Remboursement');
    $this->delete(route('cash.incomes.destroy', $income))->assertSessionHasNoErrors();
    expect(OtherIncome::count())->toBe(0);
});

test('the CSV export lists the movements of the period with signed amounts', function () {
    Expense::factory()->create(['expense_category_id' => $this->rent->id, 'label' => 'Loyer octobre', 'amount' => '40000', 'spent_at' => '2026-10-01', 'payment_method_id' => $this->cash->id]);
    OtherIncome::factory()->create(['label' => 'Vente pneus', 'amount' => '6000', 'received_at' => '2026-10-02', 'payment_method_id' => $this->cash->id]);
    Expense::factory()->create(['expense_category_id' => $this->rent->id, 'amount' => '40000', 'spent_at' => '2026-09-01']);

    $response = $this->get(route('cash.export', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk();
    $lines = explode("\n", trim($response->streamedContent()));

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toStartWith("\u{FEFF}Date;Type;Catégorie;Libellé;Mode;Montant")
        ->and($lines[1])->toBe('02/10/2026;Entrée;"Autre entrée";"Vente pneus";Espèces;6000,00')
        ->and($lines[2])->toBe('01/10/2026;Dépense;Loyer;"Loyer octobre";Espèces;-40000,00');
});

test('without a period the cash shows the current month', function () {
    $this->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page
        ->where('period', ['from' => '2026-10-01', 'to' => '2026-10-31', 'preset' => 'month']));
});

test('only the manager reaches the cash', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('cash.index'))->assertForbidden();
    $this->get(route('cash.export'))->assertForbidden();
    $this->post(route('cash.expenses.store'), [])->assertForbidden();
    $this->post(route('cash.incomes.store'), [])->assertForbidden();
});
