<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OilFormat;
use App\Enums\PartOrigin;
use App\Enums\ServiceOrderStatus;
use App\Enums\StockUnit;
use App\Http\Controllers\AppointmentController;
use App\Models\Article;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord (M8) : une requête agrégée par bloc, gardée 60 s.
 * Le cache est vidé à chaque paiement, dépense, autre entrée, facture ou prestation enregistrée (AppServiceProvider).
 */
class DashboardService
{
    private const TTL = 60;

    private const RANKING_SIZE = 5;

    public function __construct(private CashService $cash) {}

    public static function forget(): void
    {
        Cache::forget(self::key('money'));
        Cache::forget(self::key('activity'));
    }

    /**
     * Chiffres d'argent : réservés au gérant (ne jamais les envoyer à l'employé).
     *
     * @return array{today: array{incomes: string, expenses: string, profit: string}, months: list<array{month: string, incomes: string, expenses: string, profit: string}>, credits: array{clients: int, total: string}}
     */
    public function money(): array
    {
        return Cache::remember(self::key('money'), self::TTL, function () {
            $today = CarbonImmutable::today();

            return [
                'today' => $this->cash->totals($today, $today),
                'months' => $this->cash->monthly(self::since(), $today),
                'credits' => $this->credits(),
            ];
        });
    }

    /**
     * Activité de l'atelier : sans montants, visible par tous.
     *
     * @return array<string, mixed>
     */
    public function activity(): array
    {
        return Cache::remember(self::key('activity'), self::TTL, fn () => [
            'ordersToday' => DB::table('service_orders')->whereDate('date', CarbonImmutable::today())->count(),
            'appointmentsToday' => AppointmentController::list(CarbonImmutable::today(), CarbonImmutable::today()),
            'inProgress' => $this->inProgress(),
            'lowStock' => $this->lowStock(),
            'rankings' => [
                'services' => $this->topServices(),
                'oils' => $this->topOils(),
                'parts' => $this->topParts(),
                'mechanics' => $this->topMechanics(),
            ],
        ]);
    }

    /**
     * Premier jour de la fenêtre de 12 mois (graphique et classements).
     */
    public static function since(): CarbonImmutable
    {
        return CarbonImmutable::today()->subMonths(11)->startOfMonth();
    }

    /**
     * Le jour fait partie de la clé : à minuit, « aujourd'hui » repart de zéro.
     */
    private static function key(string $block): string
    {
        return 'dashboard.'.$block.'.'.CarbonImmutable::today()->toDateString();
    }

    /**
     * @return array{clients: int, total: string}
     */
    private function credits(): array
    {
        $row = DB::table('invoices')
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->selectRaw('count(distinct client_id) as clients, coalesce(sum(total - amount_paid), 0) as total')
            ->first();

        return ['clients' => (int) $row->clients, 'total' => Decimal::add((string) $row->total)];
    }

    /**
     * @return list<array{id: int, number: string, date: string, plate: string, vehicle: string, client: string}>
     */
    private function inProgress(): array
    {
        return DB::table('service_orders')
            ->join('vehicles', 'vehicles.id', '=', 'service_orders.vehicle_id')
            ->join('brands', 'brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('vehicle_models', 'vehicle_models.id', '=', 'vehicles.vehicle_model_id')
            ->join('clients', 'clients.id', '=', 'service_orders.client_id')
            ->where('service_orders.status', ServiceOrderStatus::InProgress->value)
            ->orderBy('service_orders.date')
            ->orderBy('service_orders.id')
            ->selectRaw("service_orders.id, service_orders.number, service_orders.date, vehicles.plate,
                trim(concat(brands.name, ' ', coalesce(vehicle_models.name, ''))) as vehicle,
                concat_ws(' ', clients.last_name, clients.first_name) as client")
            ->get()
            ->map(fn (object $row) => [...(array) $row, 'id' => (int) $row->id, 'date' => substr((string) $row->date, 0, 10)])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, stock: string, unit: string}>
     */
    private function lowStock(): array
    {
        return Article::query()
            ->where('is_active', true)
            ->lowStock()
            ->orderBy('stock_qty')
            ->orderBy('name')
            ->get(['id', 'name', 'stock_qty', 'stock_unit'])
            ->map(fn (Article $article) => ['id' => $article->id, 'name' => $article->name, 'stock' => $article->stock_qty, 'unit' => $article->stock_unit->symbol()])
            ->all();
    }

    /**
     * Lignes de travaux des prestations terminées des 12 derniers mois.
     */
    private function completedLines(): Builder
    {
        return DB::table('service_order_lines')
            ->join('service_orders', 'service_orders.id', '=', 'service_order_lines.service_order_id')
            ->where('service_orders.status', ServiceOrderStatus::Completed->value)
            ->where('service_orders.date', '>=', self::since()->toDateString());
    }

    /**
     * Pièces et huiles sorties du stock par les prestations terminées des 12 derniers mois.
     */
    private function soldParts(): Builder
    {
        return DB::table('service_order_parts')
            ->join('service_orders', 'service_orders.id', '=', 'service_order_parts.service_order_id')
            ->join('articles', 'articles.id', '=', 'service_order_parts.article_id')
            ->where('service_order_parts.origin', PartOrigin::Stock->value)
            ->where('service_orders.status', ServiceOrderStatus::Completed->value)
            ->where('service_orders.date', '>=', self::since()->toDateString());
    }

    /**
     * @return list<array{name: string, count: int}>
     */
    private function topServices(): array
    {
        return $this->completedLines()
            ->join('service_types', 'service_types.id', '=', 'service_order_lines.service_type_id')
            ->groupBy('service_types.id', 'service_types.name')
            ->selectRaw('service_types.name as name, count(*) as count')
            ->orderByDesc('count')->orderBy('name')
            ->limit(self::RANKING_SIZE)
            ->get()
            ->map(fn (object $row) => ['name' => $row->name, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * Huiles : litres (bidons × volume + vrac) et nombre de bidons.
     *
     * @return list<array{name: string, liters: string, cans: int}>
     */
    private function topOils(): array
    {
        $bulk = OilFormat::Bulk->value;

        return $this->soldParts()
            ->join('oil_products', 'oil_products.id', '=', 'articles.oil_product_id')
            ->groupBy('oil_products.id', 'oil_products.brand', 'oil_products.name', 'oil_products.viscosity')
            ->selectRaw("trim(concat_ws(' ', oil_products.brand, oil_products.name, oil_products.viscosity)) as name,
                sum(case when articles.oil_format = '{$bulk}' then service_order_parts.quantity else service_order_parts.quantity * articles.volume_l end) as liters,
                sum(case when articles.oil_format = '{$bulk}' then 0 else service_order_parts.quantity end) as cans")
            ->orderByDesc('liters')->orderBy('name')
            ->limit(self::RANKING_SIZE)
            ->get()
            ->map(fn (object $row) => ['name' => $row->name, 'liters' => Decimal::add((string) $row->liters), 'cans' => (int) $row->cans])
            ->all();
    }

    /**
     * @return list<array{name: string, quantity: string, unit: string}>
     */
    private function topParts(): array
    {
        return $this->soldParts()
            ->whereNull('articles.oil_product_id')
            ->groupBy('articles.id', 'articles.name', 'articles.stock_unit')
            ->selectRaw('articles.name as name, articles.stock_unit as unit, sum(service_order_parts.quantity) as quantity')
            ->orderByDesc('quantity')->orderBy('name')
            ->limit(self::RANKING_SIZE)
            ->get()
            ->map(fn (object $row) => ['name' => $row->name, 'quantity' => Decimal::add((string) $row->quantity), 'unit' => StockUnit::from($row->unit)->symbol()])
            ->all();
    }

    /**
     * Travaux par mécanicien : nombre de lignes et de prestations.
     *
     * @return list<array{name: string, count: int, orders: int}>
     */
    private function topMechanics(): array
    {
        return $this->completedLines()
            ->join('employees', 'employees.id', '=', 'service_order_lines.employee_id')
            ->groupBy('employees.id', 'employees.name')
            ->selectRaw('employees.name as name, count(*) as count, count(distinct service_order_lines.service_order_id) as orders')
            ->orderByDesc('count')->orderBy('name')
            ->limit(self::RANKING_SIZE)
            ->get()
            ->map(fn (object $row) => ['name' => $row->name, 'count' => (int) $row->count, 'orders' => (int) $row->orders])
            ->all();
    }
}
