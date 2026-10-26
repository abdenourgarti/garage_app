<?php

namespace App\Actions\Payroll;

use App\Enums\SalaryType;
use App\Enums\SystemExpenseCategory;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\SalaryPayment;
use App\Services\PayrollService;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Paie d'un mois : SalaryPayment + dépense « Salaires » du reste à verser. Une seule paie par employé et par mois.
 * Si les avances dépassent le salaire, rien n'est versé et l'excédent est repris le mois suivant (PayrollService::carriedIn).
 */
class PaySalary
{
    public function __construct(private PayrollService $payroll) {}

    public function handle(Employee $employee, string $period, ?int $daysWorked, CarbonInterface $paidAt, PaymentMethod $method): SalaryPayment
    {
        $month = PayrollService::month($period);

        if ($month->isAfter(now()->startOfMonth())) {
            throw ValidationException::withMessages(['period' => 'On ne paie pas un mois à venir.']);
        }

        if ($employee->salary_type === SalaryType::Daily && ($daysWorked === null || $daysWorked < 0 || $daysWorked > 31)) {
            throw ValidationException::withMessages(['days_worked' => 'Indiquez les jours travaillés (0 à 31).']);
        }

        return DB::transaction(function () use ($employee, $period, $daysWorked, $paidAt, $method) {
            // Verrou sur l'employé : deux clics « Payer » simultanés ne font qu'une paie.
            $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();

            if (SalaryPayment::where('employee_id', $employee->id)->where('period', $period)->exists()) {
                throw ValidationException::withMessages(['period' => "{$employee->name} est déjà payé pour ".PayrollService::label($period).'.']);
            }

            $pay = $this->payroll->compute($employee, $period, $daysWorked);

            $payment = SalaryPayment::forceCreate([
                'employee_id' => $employee->id,
                'period' => $period,
                'days_worked' => $pay['days_worked'],
                'base_amount' => $pay['base'],
                'advances_total' => $pay['advances'],
                'carried_in' => $pay['carried_in'],
                'net_paid' => $pay['net'],
                'paid_at' => $paidAt->toDateString(),
                'payment_method_id' => $method->id,
                'user_id' => Auth::id(),
            ]);

            if (Decimal::cmp($pay['net'], 0) > 0) {
                $payment->expense()->create([
                    'expense_category_id' => ExpenseCategory::system(SystemExpenseCategory::Salaries)->id,
                    'label' => 'Salaire '.PayrollService::label($period)." : {$employee->name}",
                    'amount' => $pay['net'],
                    'spent_at' => $paidAt->toDateString(),
                    'payment_method_id' => $method->id,
                    'employee_id' => $employee->id,
                    'user_id' => Auth::id(),
                ]);
            }

            return $payment;
        });
    }
}
