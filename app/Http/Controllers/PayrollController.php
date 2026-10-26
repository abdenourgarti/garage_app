<?php

namespace App\Http\Controllers;

use App\Actions\Payroll\PaySalary;
use App\Actions\Payroll\RecordSalaryAdvance;
use App\Enums\SalaryType;
use App\Enums\SystemExpenseCategory;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\SalaryPayment;
use App\Services\PayrollService;
use App\Services\SettingsService;
use App\Support\FormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Avances et paie du mois (gérant seulement).
 */
class PayrollController extends Controller
{
    /**
     * Paie d'un mois : employés actifs (et ceux déjà payés ce mois-là), base, avances, reste à verser.
     */
    public function index(Request $request, PayrollService $payroll): Response
    {
        $period = $request->string('period')->toString() ?: now()->format('Y-m');
        PayrollService::month($period);

        $payments = SalaryPayment::where('period', $period)->get()->keyBy('employee_id');
        $employees = Employee::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $payments->keys()))
            ->orderBy('name')
            ->get();

        return Inertia::render('employees/payroll', [
            'period' => $period,
            'periodLabel' => PayrollService::label($period),
            'isFuture' => PayrollService::month($period)->isAfter(now()->startOfMonth()),
            'rows' => $employees->map(function (Employee $employee) use ($payroll, $period, $payments) {
                $payment = $payments->get($employee->id);

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'salary_type' => $employee->salary_type->value,
                    'salary_amount' => $employee->salary_amount,
                    'suggested_days' => $employee->salary_type === SalaryType::Daily ? $payroll->daysWithWork($employee, $period) : null,
                    'pay' => $payment ? null : $payroll->compute($employee, $period),
                    'payment' => $payment ? [
                        ...$payment->only(['id', 'days_worked', 'base_amount', 'advances_total', 'carried_in', 'net_paid']),
                        'paid_at' => $payment->paid_at->toDateString(),
                    ] : null,
                ];
            }),
            ...FormOptions::payment(),
        ]);
    }

    public function pay(Request $request, Employee $employee, PaySalary $action): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'string'],
            'days_worked' => ['nullable', 'integer', 'min:0', 'max:31'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
        ], [], ['days_worked' => 'jours travaillés', 'paid_at' => 'date de paiement', 'payment_method_id' => 'mode de paiement']);

        $payment = $action->handle(
            $employee,
            $data['period'],
            isset($data['days_worked']) ? (int) $data['days_worked'] : null,
            Carbon::parse($data['paid_at']),
            PaymentMethod::findOrFail($data['payment_method_id']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Paie de {$employee->name} enregistrée : ".number_format((float) $payment->net_paid, 0, ',', ' ').' DA versés.']);

        return back();
    }

    public function storeAdvance(Request $request, RecordSalaryAdvance $action): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'spent_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
        ], [], ['employee_id' => 'employé', 'amount' => 'montant', 'spent_at' => 'date', 'payment_method_id' => 'mode de paiement']);

        $employee = Employee::findOrFail($data['employee_id']);
        $action->handle($employee, (string) $data['amount'], Carbon::parse($data['spent_at']), PaymentMethod::findOrFail($data['payment_method_id']));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Avance de {$employee->name} enregistrée."]);

        return back();
    }

    /**
     * Correction d'une faute de frappe, tant que la paie du mois n'est pas faite.
     */
    public function destroyAdvance(Expense $advance): RedirectResponse
    {
        abort_unless($advance->expense_category_id === ExpenseCategory::system(SystemExpenseCategory::Advances)->id && $advance->employee_id, 404);

        $period = $advance->spent_at->format('Y-m');

        if (SalaryPayment::where('employee_id', $advance->employee_id)->where('period', $period)->exists()) {
            throw ValidationException::withMessages(['advance' => 'La paie de '.PayrollService::label($period).' est faite : cette avance ne peut plus être supprimée.']);
        }

        $advance->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Avance supprimée.']);

        return back();
    }

    /**
     * Bulletin simplifié (A4 ou PDF), même mise en page que les factures.
     */
    public function payslip(Request $request, SalaryPayment $payment, string $format, PayrollService $payroll, SettingsService $settings): HttpResponse
    {
        $payment->load(['employee', 'paymentMethod']);

        return InvoiceController::render('documents.payslip', $format, "bulletin-{$payment->period}-".str($payment->employee->name)->slug(), $request->boolean('print'), [
            'payment' => $payment,
            'periodLabel' => PayrollService::label($payment->period),
            'advances' => $payroll->advances($payment->employee, $payment->period)->get(),
        ], $settings);
    }
}
