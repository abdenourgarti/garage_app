<?php

namespace App\Services;

use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Caisse (RG-14) : entrées = paiements clients non annulés + autres entrées ; dépenses = toutes les dépenses ;
 * bénéfice = entrées − dépenses. Tout compte le jour du paiement, dans le fuseau du garage (Africa/Algiers).
 */
class CashService
{
    /**
     * @return array{incomes: string, expenses: string, profit: string, by_category: list<array{id: int, name: string, total: string}>, by_method: list<array{name: string, total: string}>}
     */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $payments = $this->payments($from, $to);
        $others = $this->otherIncomes($from, $to);
        $expenses = $this->expenses($from, $to);

        $incomes = Decimal::add((string) (clone $payments)->sum('payments.amount'), (string) (clone $others)->sum('other_incomes.amount'));
        $spent = Decimal::add((string) (clone $expenses)->sum('expenses.amount'));

        $byMethod = (clone $payments)
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->groupBy('payment_methods.name')
            ->selectRaw('payment_methods.name as name, sum(payments.amount) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['name' => $row->name ?? '—', 'total' => Decimal::add((string) $row->total)])
            ->all();

        $otherTotal = (string) (clone $others)->sum('other_incomes.amount');

        if (Decimal::cmp($otherTotal, 0) > 0) {
            $byMethod[] = ['name' => 'Autres entrées', 'total' => Decimal::add($otherTotal)];
        }

        return [
            'incomes' => $incomes,
            'expenses' => $spent,
            'profit' => Decimal::sub($incomes, $spent),
            'by_category' => (clone $expenses)
                ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                ->groupBy('expense_categories.id', 'expense_categories.name')
                ->selectRaw('expense_categories.id as id, expense_categories.name as name, sum(expenses.amount) as total')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->name, 'total' => Decimal::add((string) $row->total)])
                ->all(),
            'by_method' => $byMethod,
        ];
    }

    /**
     * Entrées, dépenses et bénéfice de la période, en une requête.
     *
     * @return array{incomes: string, expenses: string, profit: string}
     */
    public function totals(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = DB::query()->fromSub($this->flows($from, $to), 'flows')
            ->selectRaw('coalesce(sum(income), 0) as incomes, coalesce(sum(expense), 0) as expenses')
            ->first();

        return self::totalsOf($row);
    }

    /**
     * Entrées, dépenses et bénéfice mois par mois (mois sans mouvement à zéro), en une requête.
     *
     * @return list<array{month: string, incomes: string, expenses: string, profit: string}>
     */
    public function monthly(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = DB::query()->fromSub($this->flows($from, $to), 'flows')
            ->selectRaw("date_format(day, '%Y-%m') as month, sum(income) as incomes, sum(expense) as expenses")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $months = [];

        for ($month = CarbonImmutable::parse($from)->startOfMonth(); $month->lte($to); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $months[] = ['month' => $key, ...self::totalsOf($rows->get($key))];
        }

        return $months;
    }

    /**
     * Solde espèces du jour (indicatif) : entrées en espèces − dépenses en espèces.
     */
    public function cashOfDay(CarbonInterface $day): string
    {
        $cash = DB::table('payment_methods')->where('is_cash', true)->pluck('id');

        $in = Decimal::add(
            (string) $this->payments($day, $day)->whereIn('payments.payment_method_id', $cash)->sum('payments.amount'),
            (string) $this->otherIncomes($day, $day)->whereIn('other_incomes.payment_method_id', $cash)->sum('other_incomes.amount'),
        );

        return Decimal::sub($in, (string) $this->expenses($day, $day)->whereIn('expenses.payment_method_id', $cash)->sum('expenses.amount'));
    }

    /**
     * Mouvements de la période (entrées et dépenses mêlées), du plus récent au plus ancien.
     *
     * @param  array{kind?: string|null, category?: int|null, method?: int|null}  $filters
     */
    public function movements(CarbonInterface $from, CarbonInterface $to, array $filters = []): Builder
    {
        $kind = $filters['kind'] ?? null;
        $category = $filters['category'] ?? null;
        $method = $filters['method'] ?? null;

        $payments = $this->payments($from, $to)
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->join('clients', 'clients.id', '=', 'payments.client_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->when($method, fn (Builder $query) => $query->where('payments.payment_method_id', $method))
            ->selectRaw("'income' as kind, 'payment' as origin, payments.id as id, date(payments.paid_at) as day, payments.paid_at as moment,
                concat(invoices.number, ' · ', concat_ws(' ', clients.last_name, clients.first_name)) as label,
                'Paiement client' as category, payment_methods.name as method, payments.amount as amount,
                invoices.id as link_id, null as link_extra, 0 as editable, null as notes, null as category_id, payments.payment_method_id as method_id");

        $others = $this->otherIncomes($from, $to)
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'other_incomes.payment_method_id')
            ->when($method, fn (Builder $query) => $query->where('other_incomes.payment_method_id', $method))
            ->selectRaw("'income' as kind, 'other_income' as origin, other_incomes.id as id, other_incomes.received_at as day, other_incomes.created_at as moment,
                other_incomes.label as label, 'Autre entrée' as category, payment_methods.name as method, other_incomes.amount as amount,
                null as link_id, null as link_extra, 1 as editable, other_incomes.notes as notes, null as category_id, other_incomes.payment_method_id as method_id");

        $expenses = $this->expenses($from, $to)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'expenses.payment_method_id')
            ->leftJoin('purchase_payments', fn ($join) => $join->on('purchase_payments.id', '=', 'expenses.source_id')->where('expenses.source_type', 'purchase_payment'))
            ->leftJoin('salary_payments', fn ($join) => $join->on('salary_payments.id', '=', 'expenses.source_id')->where('expenses.source_type', 'salary_payment'))
            ->when($category, fn (Builder $query) => $query->where('expenses.expense_category_id', $category))
            ->when($method, fn (Builder $query) => $query->where('expenses.payment_method_id', $method))
            ->selectRaw("'expense' as kind, coalesce(expenses.source_type, case when expenses.employee_id is null then 'manual' else 'advance' end) as origin,
                expenses.id as id, expenses.spent_at as day, expenses.created_at as moment, expenses.label as label,
                expense_categories.name as category, payment_methods.name as method, expenses.amount as amount,
                coalesce(purchase_payments.purchase_id, expenses.employee_id, expenses.source_id) as link_id,
                coalesce(salary_payments.period, date_format(expenses.spent_at, '%Y-%m')) as link_extra,
                case when expenses.source_type is null and expenses.employee_id is null and expense_categories.system_key is null then 1 else 0 end as editable,
                expenses.notes as notes, expenses.expense_category_id as category_id, expenses.payment_method_id as method_id");

        // Une catégorie de dépense choisie : pas d'entrées. Le type « entrées » ou « dépenses » restreint l'union.
        $parts = match (true) {
            $kind === 'expense' || $category !== null => [$expenses],
            $kind === 'income' => [$payments, $others],
            default => [$payments, $others, $expenses],
        };

        $union = array_shift($parts);

        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return DB::query()->fromSub($union, 'movements')->orderByDesc('day')->orderByDesc('moment')->orderByDesc('id');
    }

    /**
     * Une ligne de movements() avec ses types (la requête dérivée rend tout en texte).
     *
     * @return array<string, mixed>
     */
    public static function row(object $row): array
    {
        $int = fn ($value) => $value === null ? null : (int) $value;

        return [
            ...(array) $row,
            'id' => (int) $row->id,
            'day' => substr((string) $row->day, 0, 10),
            'link_id' => $int($row->link_id),
            'category_id' => $int($row->category_id),
            'method_id' => $int($row->method_id),
            'editable' => (bool) $row->editable,
            'amount' => Decimal::add((string) $row->amount),
        ];
    }

    /**
     * @return array{incomes: string, expenses: string, profit: string}
     */
    private static function totalsOf(?object $row): array
    {
        $incomes = Decimal::add((string) ($row->incomes ?? 0));
        $expenses = Decimal::add((string) ($row->expenses ?? 0));

        return ['incomes' => $incomes, 'expenses' => $expenses, 'profit' => Decimal::sub($incomes, $expenses)];
    }

    /**
     * Une ligne (day, income, expense) par mouvement de la période.
     */
    private function flows(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $this->payments($from, $to)->selectRaw('date(payments.paid_at) as day, payments.amount as income, 0 as expense')
            ->unionAll($this->otherIncomes($from, $to)->selectRaw('other_incomes.received_at as day, other_incomes.amount as income, 0 as expense'))
            ->unionAll($this->expenses($from, $to)->selectRaw('expenses.spent_at as day, 0 as income, expenses.amount as expense'));
    }

    /**
     * Paiements non annulés, de from 00:00 à to+1 00:00 exclu (paid_at est une date et une heure).
     */
    private function payments(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return DB::table('payments')
            ->whereNull('payments.cancelled_at')
            ->where('payments.paid_at', '>=', CarbonImmutable::parse($from)->startOfDay())
            ->where('payments.paid_at', '<', CarbonImmutable::parse($to)->addDay()->startOfDay());
    }

    private function otherIncomes(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return DB::table('other_incomes')->whereBetween('other_incomes.received_at', [$from->toDateString(), $to->toDateString()]);
    }

    private function expenses(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return DB::table('expenses')->whereBetween('expenses.spent_at', [$from->toDateString(), $to->toDateString()]);
    }
}
