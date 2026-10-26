<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OtherIncome;
use App\Services\CashService;
use App\Support\FormOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Caisse (gérant) : totaux de la période, détails, mouvements, dépenses et autres entrées saisies à la main.
 */
class CashController extends Controller
{
    public function index(Request $request, CashService $cash): Response
    {
        [$from, $to] = $this->period($request);
        $filters = $this->filters($request);

        return Inertia::render('cash/index', [
            // Sans période choisie : le mois en cours, bouton « Mois » actif.
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'preset' => $request->string('preset')->toString() ?: ($request->filled('from') ? null : 'month')],
            'filters' => $filters,
            'summary' => $cash->summary($from, $to),
            'cashToday' => $cash->cashOfDay(CarbonImmutable::today()),
            'rows' => $cash->movements($from, $to, $filters)->paginate(50)->withQueryString()->through(fn (object $row) => CashService::row($row)),
            'categories' => ExpenseCategory::orderBy('name')->get(['id', 'name', 'system_key'])->map(fn (ExpenseCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'manual' => $category->system_key === null && $category->is_active,
            ]),
            ...FormOptions::payment(),
        ]);
    }

    public function export(Request $request, CashService $cash): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $rows = $cash->movements($from, $to, $this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}");
            fputcsv($out, ['Date', 'Type', 'Catégorie', 'Libellé', 'Mode', 'Montant'], ';');

            foreach ($rows->cursor() as $row) {
                $sign = $row->kind === 'expense' ? -1 : 1;

                fputcsv($out, [
                    CarbonImmutable::parse($row->day)->format('d/m/Y'),
                    $row->kind === 'expense' ? 'Dépense' : 'Entrée',
                    $row->category,
                    $row->label,
                    $row->method ?? '',
                    number_format($sign * (float) $row->amount, 2, ',', ''),
                ], ';');
            }

            fclose($out);
        }, 'caisse-'.$from->format('Y-m-d').'-au-'.$to->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        Expense::create([...$this->validateExpense($request), 'user_id' => Auth::id()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dépense enregistrée.']);

        return back();
    }

    public function updateExpense(Request $request, Expense $expense): RedirectResponse
    {
        $this->ensureManual($expense);
        $expense->update($this->validateExpense($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dépense modifiée.']);

        return back();
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $this->ensureManual($expense);
        $expense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dépense supprimée.']);

        return back();
    }

    public function storeIncome(Request $request): RedirectResponse
    {
        OtherIncome::create([...$this->validateIncome($request), 'user_id' => Auth::id()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entrée enregistrée.']);

        return back();
    }

    public function updateIncome(Request $request, OtherIncome $income): RedirectResponse
    {
        $income->update($this->validateIncome($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entrée modifiée.']);

        return back();
    }

    public function destroyIncome(OtherIncome $income): RedirectResponse
    {
        $income->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entrée supprimée.']);

        return back();
    }

    /**
     * Les dépenses écrites par les actions (achats, salaires, avances, pièces extérieures) se corrigent à leur source.
     */
    private function ensureManual(Expense $expense): void
    {
        if (! $expense->isManual()) {
            throw ValidationException::withMessages(['expense' => 'Cette dépense vient d’un achat, d’une paie ou d’une prestation : corrigez-la à sa source.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->whereNull('system_key')],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'spent_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], self::ATTRIBUTES);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIncome(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], self::ATTRIBUTES);
    }

    /**
     * Période de ?from=…&to=… (dates du garage) ; ce mois par défaut.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $from = rescue(fn () => CarbonImmutable::parse($request->string('from')->toString())->startOfDay(), null, false);
        $to = rescue(fn () => CarbonImmutable::parse($request->string('to')->toString())->startOfDay(), null, false);

        if (! $request->filled('from') || ! $from) {
            $from = CarbonImmutable::today()->startOfMonth();
        }

        if (! $request->filled('to') || ! $to) {
            $to = $from->endOfMonth()->startOfDay();
        }

        return $to->lt($from) ? [$to, $from] : [$from, $to];
    }

    /**
     * @return array{kind: string|null, category: int|null, method: int|null}
     */
    private function filters(Request $request): array
    {
        $kind = $request->string('kind')->toString();

        return [
            'kind' => in_array($kind, ['income', 'expense'], true) ? $kind : null,
            'category' => $request->integer('category') ?: null,
            'method' => $request->integer('method') ?: null,
        ];
    }

    private const ATTRIBUTES = [
        'expense_category_id' => 'catégorie',
        'label' => 'libellé',
        'amount' => 'montant',
        'spent_at' => 'date',
        'received_at' => 'date',
        'payment_method_id' => 'mode de paiement',
        'notes' => 'remarque',
    ];
}
