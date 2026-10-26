<?php

namespace App\Actions\Payroll;

use App\Enums\SystemExpenseCategory;
use App\Models\Employee;
use App\Models\Expense;
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
 * Avance sur salaire : une dépense « Avances sur salaire » rattachée à l'employé, comptée le jour où elle est donnée (RG-14).
 */
class RecordSalaryAdvance
{
    public function handle(Employee $employee, string $amount, CarbonInterface $date, PaymentMethod $method): Expense
    {
        if (Decimal::cmp($amount, 0) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être supérieur à 0.']);
        }

        return DB::transaction(function () use ($employee, $amount, $date, $method) {
            $period = $date->format('Y-m');

            // La paie de ce mois est faite : l'avance n'y serait jamais déduite.
            if (SalaryPayment::where('employee_id', $employee->id)->where('period', $period)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['spent_at' => 'La paie de '.PayrollService::label($period).' est déjà faite : datez l’avance du mois suivant.']);
            }

            return Expense::create([
                'expense_category_id' => ExpenseCategory::system(SystemExpenseCategory::Advances)->id,
                'label' => "Avance : {$employee->name}",
                'amount' => $amount,
                'spent_at' => $date->toDateString(),
                'payment_method_id' => $method->id,
                'employee_id' => $employee->id,
                'user_id' => Auth::id(),
            ]);
        });
    }
}
