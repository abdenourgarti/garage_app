<?php

namespace App\Services;

use App\Enums\SalaryType;
use App\Enums\SystemExpenseCategory;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\SalaryPayment;
use App\Models\ServiceOrderLine;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Calcul de la paie d'un mois (M6) : base (mensuel, ou journalier × jours travaillés) − avances du mois
 * − report des avances du mois précédent qui dépassaient le salaire. Jamais de reste négatif : l'excédent est reporté.
 */
class PayrollService
{
    /**
     * Premier jour du mois « 2026-10 » ; refuse un format invalide.
     */
    public static function month(string $period): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages(['period' => 'Mois invalide.']);
        }

        return CarbonImmutable::createFromFormat('!Y-m', $period);
    }

    public static function label(string $period): string
    {
        return self::month($period)->locale('fr')->translatedFormat('F Y');
    }

    /**
     * @return Builder<Expense>
     */
    public function advances(Employee $employee, string $period): Builder
    {
        $month = self::month($period);

        return Expense::query()
            ->where('employee_id', $employee->id)
            ->where('expense_category_id', ExpenseCategory::system(SystemExpenseCategory::Advances)->id)
            ->whereBetween('spent_at', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->orderBy('spent_at')
            ->orderBy('id');
    }

    /**
     * Avances du mois précédent restées au-delà du salaire (seulement si ce mois-là a été payé).
     */
    public function carriedIn(Employee $employee, string $period): string
    {
        $previous = SalaryPayment::where('employee_id', $employee->id)
            ->where('period', self::month($period)->subMonth()->format('Y-m'))
            ->first();

        if (! $previous) {
            return '0.00';
        }

        $excess = Decimal::sub(Decimal::add($previous->advances_total, $previous->carried_in), $previous->base_amount);

        return Decimal::cmp($excess, 0) > 0 ? $excess : '0.00';
    }

    /**
     * Jours où l'employé a eu au moins une prestation : proposé pour un salaire journalier.
     */
    public function daysWithWork(Employee $employee, string $period): int
    {
        $month = self::month($period);

        return ServiceOrderLine::query()
            ->join('service_orders', 'service_orders.id', '=', 'service_order_lines.service_order_id')
            ->where('service_order_lines.employee_id', $employee->id)
            ->whereBetween('service_orders.date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->distinct()
            ->count('service_orders.date');
    }

    /**
     * @return array{base: string, advances: string, carried_in: string, due: string, net: string, excess: string, days_worked: int|null}
     */
    public function compute(Employee $employee, string $period, ?int $daysWorked = null): array
    {
        $daily = $employee->salary_type === SalaryType::Daily;
        $days = $daily ? ($daysWorked ?? $this->daysWithWork($employee, $period)) : null;
        $base = $daily ? Decimal::mul($employee->salary_amount, (string) $days) : Decimal::add($employee->salary_amount);
        $advances = Decimal::add((string) $this->advances($employee, $period)->sum('amount'));
        $carried = $this->carriedIn($employee, $period);
        $due = Decimal::add($advances, $carried);
        $net = Decimal::sub($base, $due);
        $negative = Decimal::cmp($net, 0) < 0;

        return [
            'base' => $base,
            'advances' => $advances,
            'carried_in' => $carried,
            'due' => $due,
            'net' => $negative ? '0.00' : $net,
            'excess' => $negative ? Decimal::sub($due, $base) : '0.00',
            'days_worked' => $days,
        ];
    }
}
