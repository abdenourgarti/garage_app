<?php

use App\Actions\Payroll\PaySalary;
use App\Actions\Payroll\RecordSalaryAdvance;
use App\Enums\SalaryType;
use App\Enums\SystemExpenseCategory;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\SalaryPayment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-05 10:00'));

    $this->gerant = User::factory()->gerant()->create();
    $this->actingAs($this->gerant);

    $this->advancesCategory = ExpenseCategory::factory()->system(SystemExpenseCategory::Advances)->create();
    $this->salariesCategory = ExpenseCategory::factory()->system(SystemExpenseCategory::Salaries)->create();
    $this->cash = PaymentMethod::factory()->create(['name' => 'Espèces', 'is_cash' => true]);

    $this->monthly = Employee::factory()->create(['name' => 'Sofiane', 'salary_type' => SalaryType::Monthly, 'salary_amount' => '45000']);
    $this->daily = Employee::factory()->create(['name' => 'Bilal', 'salary_type' => SalaryType::Daily, 'salary_amount' => '1500']);
});

function advance(Employee $employee, string $amount, string $date): Expense
{
    return app(RecordSalaryAdvance::class)->handle($employee, $amount, Carbon::parse($date), test()->cash);
}

function paySalary(Employee $employee, string $period, ?int $days = null): SalaryPayment
{
    return app(PaySalary::class)->handle($employee, $period, $days, Carbon::parse('2026-11-01'), test()->cash);
}

test('monthly: salary minus the advances of that month only', function () {
    advance($this->monthly, '5000', '2026-10-05');
    advance($this->monthly, '3000', '2026-10-18');
    advance($this->monthly, '2000', '2026-09-30');

    $advance = Expense::where('amount', 5000)->sole();
    expect($advance->expense_category_id)->toBe($this->advancesCategory->id)
        ->and($advance->employee_id)->toBe($this->monthly->id)
        ->and($advance->spent_at->toDateString())->toBe('2026-10-05');

    $payment = paySalary($this->monthly, '2026-10');

    expect($payment->days_worked)->toBeNull()
        ->and($payment->base_amount)->toBe('45000.00')
        ->and($payment->advances_total)->toBe('8000.00')
        ->and($payment->net_paid)->toBe('37000.00');

    $salary = $payment->expense;
    expect($salary->amount)->toBe('37000.00')
        ->and($salary->expense_category_id)->toBe($this->salariesCategory->id)
        ->and($salary->employee_id)->toBe($this->monthly->id)
        ->and($salary->spent_at->toDateString())->toBe('2026-11-01')
        ->and($salary->label)->toBe('Salaire octobre 2026 : Sofiane');
});

test('daily: rate times the days worked, proposed from the days with work', function () {
    foreach (['2026-10-02', '2026-10-02', '2026-10-09'] as $date) {
        $order = ServiceOrder::factory()->create(['date' => $date]);
        ServiceOrderLine::factory()->for($order)->create(['employee_id' => $this->daily->id]);
    }
    advance($this->daily, '5000', '2026-10-15');

    $payroll = app(PayrollService::class);
    expect($payroll->daysWithWork($this->daily, '2026-10'))->toBe(2)
        ->and($payroll->compute($this->daily, '2026-10')['base'])->toBe('3000.00');

    expect(fn () => paySalary($this->daily, '2026-10'))->toThrow(ValidationException::class, 'jours travaillés');

    $payment = paySalary($this->daily, '2026-10', 22);

    expect($payment->days_worked)->toBe(22)
        ->and($payment->base_amount)->toBe('33000.00')
        ->and($payment->net_paid)->toBe('28000.00');
});

test('advances above the salary: nothing paid, the excess is carried to the next month', function () {
    advance($this->monthly, '30000', '2026-09-10');
    advance($this->monthly, '22000', '2026-09-25');

    $preview = app(PayrollService::class)->compute($this->monthly, '2026-09');
    expect($preview['net'])->toBe('0.00')->and($preview['excess'])->toBe('7000.00');

    $payment = paySalary($this->monthly, '2026-09');
    expect($payment->net_paid)->toBe('0.00')
        ->and($payment->expense)->toBeNull();

    advance($this->monthly, '3000', '2026-10-10');
    $october = paySalary($this->monthly, '2026-10');

    expect($october->carried_in)->toBe('7000.00')
        ->and($october->advances_total)->toBe('3000.00')
        ->and($october->net_paid)->toBe('35000.00');
});

test('one pay per employee and per month, never for a future month', function () {
    paySalary($this->monthly, '2026-10');

    expect(fn () => paySalary($this->monthly, '2026-10'))->toThrow(ValidationException::class, 'déjà payé');

    $this->post(route('payroll.pay', $this->monthly), ['period' => '2026-10', 'paid_at' => '2026-11-02', 'payment_method_id' => $this->cash->id])
        ->assertSessionHasErrors('period');

    expect(fn () => paySalary($this->monthly, '2026-12'))->toThrow(ValidationException::class, 'mois à venir')
        ->and(SalaryPayment::count())->toBe(1)
        ->and(Expense::where('expense_category_id', $this->salariesCategory->id)->count())->toBe(1);
});

test('an advance cannot be added to or removed from a month already paid', function () {
    $typo = advance($this->monthly, '50000', '2026-10-05');

    $this->delete(route('advances.destroy', $typo))->assertSessionHasNoErrors();
    expect(Expense::find($typo->id))->toBeNull();

    $kept = advance($this->monthly, '5000', '2026-10-06');
    paySalary($this->monthly, '2026-10');

    expect(fn () => advance($this->monthly, '1000', '2026-10-20'))->toThrow(ValidationException::class, 'déjà faite');
    $this->delete(route('advances.destroy', $kept))->assertSessionHasErrors('advance');
    expect(Expense::find($kept->id))->not->toBeNull();

    // Une dépense ordinaire ne passe pas pour une avance.
    $other = Expense::factory()->create();
    $this->delete(route('advances.destroy', $other))->assertNotFound();
});

test('screens: employee page, payroll of the month, payslip in A4 and PDF', function () {
    advance($this->monthly, '5000', '2026-10-05');

    $this->get(route('employees.show', ['employee' => $this->monthly, 'period' => '2026-10']))->assertInertia(fn (Assert $page) => $page
        ->component('employees/show')
        ->where('periodLabel', 'octobre 2026')
        ->has('advances', 1)
        ->where('paid', false));

    $this->get(route('payroll.index', ['period' => '2026-10']))->assertInertia(fn (Assert $page) => $page
        ->component('employees/payroll')
        ->has('rows', 2)
        ->where('rows.1.name', 'Sofiane')
        ->where('rows.1.pay.net', '40000.00'));

    $this->post(route('payroll.pay', $this->monthly), ['period' => '2026-10', 'paid_at' => '2026-11-02', 'payment_method_id' => $this->cash->id])
        ->assertSessionHasNoErrors();
    $payment = SalaryPayment::sole();

    $this->get(route('payroll.payslip', [$payment, 'a4']))->assertOk()->assertSee('BULLETIN DE PAIE')->assertSee('Sofiane')->assertSee('Avance du 05/10/2026');
    expect($this->get(route('payroll.payslip', [$payment, 'pdf']))->headers->get('content-type'))->toBe('application/pdf');
});

test('only the manager reaches employees, advances and pay', function () {
    $this->actingAs(User::factory()->create());
    $payment = SalaryPayment::forceCreate(['employee_id' => $this->monthly->id, 'period' => '2026-09', 'base_amount' => 1, 'net_paid' => 1, 'paid_at' => '2026-10-01', 'user_id' => $this->gerant->id]);

    $this->get(route('employees.show', $this->monthly))->assertForbidden();
    $this->get(route('payroll.index'))->assertForbidden();
    $this->post(route('advances.store'), ['employee_id' => $this->monthly->id, 'amount' => 1000, 'spent_at' => '2026-11-01', 'payment_method_id' => $this->cash->id])->assertForbidden();
    $this->post(route('payroll.pay', $this->monthly), ['period' => '2026-10'])->assertForbidden();
    $this->get(route('payroll.payslip', [$payment, 'a4']))->assertForbidden();

    expect(Expense::count())->toBe(0);
});
