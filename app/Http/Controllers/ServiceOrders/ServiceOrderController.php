<?php

namespace App\Http\Controllers\ServiceOrders;

use App\Actions\ServiceOrders\CompleteServiceOrder;
use App\Actions\ServiceOrders\SaveServiceOrder;
use App\Enums\AppointmentStatus;
use App\Enums\ImmobilizationUnit;
use App\Enums\PartOrigin;
use App\Enums\ServiceOrderStatus;
use App\Http\Controllers\Clients\LookupController;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceOrders\ServiceOrderRequest;
use App\Models\Appointment;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use App\Models\ServiceOrderPart;
use App\Models\ServiceType;
use App\Models\Vehicle;
use App\Support\Decimal;
use App\Support\FormOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceOrderController extends Controller
{
    /**
     * Liste et recherche avancée des prestations (CDC M9) : tous les filtres dans l'adresse.
     */
    public function index(Request $request): Response
    {
        $filters = ServiceOrderFilters::fromRequest($request);

        $orders = self::listQuery($filters)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ServiceOrder $order) => [
                ...self::summary($order),
                'works' => $order->lines->pluck('serviceType.name')->unique()->implode(', '),
                'total' => Decimal::add((string) ($order->lines_sum_labor_price ?? 0), (string) ($order->parts_sum_line_total ?? 0)),
                'invoice' => $order->invoice ? $order->invoice->only(['id', 'number']) + ['status' => $order->invoice->status->value] : null,
            ]);

        return Inertia::render('service-orders/index', [
            'rows' => $orders,
            'filters' => $filters->toArray(),
            'statuses' => ServiceOrderStatus::options(),
            'employees' => Employee::orderBy('name')->get(['id', 'name']),
            'serviceTypes' => ServiceType::orderBy('name')->get(['id', 'name']),
            ...FormOptions::clientVehicle(),
        ]);
    }

    /**
     * Export CSV des prestations filtrées, pour Excel : UTF-8 avec BOM, séparateur « ; », montants à virgule.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = self::listQuery(ServiceOrderFilters::fromRequest($request))
            ->with(['client.wilaya:id,code,name', 'lines.employee:id,name']);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}");
            fputcsv($out, ['N°', 'Date', 'Client', 'Téléphone', 'Wilaya', 'Matricule', 'Marque', 'Modèle', 'Année', 'Kilométrage', 'Travaux', 'Mécaniciens', 'Main-d’œuvre', 'Pièces', 'Total', 'État', 'Facture', 'État de la facture'], ';');

            $money = fn (string|int|null $value) => number_format((float) $value, 2, ',', '');

            $query->chunk(500, function ($orders) use ($out, $money) {
                foreach ($orders as $order) {
                    $labor = (string) ($order->lines_sum_labor_price ?? 0);
                    $parts = (string) ($order->parts_sum_line_total ?? 0);

                    fputcsv($out, [
                        $order->number,
                        $order->date->format('d/m/Y'),
                        $order->client->full_name,
                        $order->client->phone,
                        $order->client->wilaya ? "{$order->client->wilaya->code} {$order->client->wilaya->name}" : '',
                        $order->vehicle->plate,
                        $order->vehicle->brand->name,
                        $order->vehicle->vehicleModel?->name ?? '',
                        $order->vehicle->year ?? '',
                        $order->mileage ?? '',
                        $order->lines->pluck('serviceType.name')->unique()->implode(', '),
                        $order->lines->pluck('employee.name')->filter()->unique()->implode(', '),
                        $money($labor),
                        $money($parts),
                        $money(Decimal::add($labor, $parts)),
                        $order->status->label(),
                        $order->invoice?->number ?? '',
                        $order->invoice?->status->label() ?? '',
                    ], ';');
                }
            });

            fclose($out);
        }, 'prestations-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<ServiceOrder>
     */
    private static function listQuery(ServiceOrderFilters $filters): Builder
    {
        return $filters->query()
            ->with([
                'client:id,first_name,last_name,phone,wilaya_id',
                'vehicle:id,plate,brand_id,vehicle_model_id,year',
                'vehicle.brand:id,name',
                'vehicle.vehicleModel:id,name',
                'lines:id,service_order_id,service_type_id,employee_id,position',
                'lines.serviceType:id,name',
                'invoice:id,service_order_id,number,status',
            ])
            ->withSum('lines', 'labor_price')
            ->withSum('parts', 'line_total')
            ->latest('date')
            ->latest('id');
    }

    public function create(Request $request): Response
    {
        $vehicle = $request->integer('vehicle') ? Vehicle::find($request->integer('vehicle')) : null;

        return Inertia::render('service-orders/form', [
            'order' => null,
            'vehicle' => $vehicle ? LookupController::serviceVehicleOption($vehicle) : null,
            ...self::formOptions(),
        ]);
    }

    public function store(ServiceOrderRequest $request, SaveServiceOrder $save, CompleteServiceOrder $complete): RedirectResponse
    {
        return $this->persist($request, null, $save, $complete);
    }

    public function show(ServiceOrder $order): Response
    {
        $order->load(['client:id,first_name,last_name,phone', 'vehicle.brand:id,name', 'vehicle.vehicleModel:id,name', 'lines.serviceType:id,name', 'lines.employee:id,name', 'lines.parts.article:id,stock_unit', 'parts', 'user:id,name', 'invoices']);

        return Inertia::render('service-orders/show', [
            'order' => [
                ...self::summary($order),
                'mileage' => $order->mileage,
                'immobilization' => $order->immobilization_value ? $order->immobilization_value.' '.mb_strtolower($order->immobilization_unit->label()) : null,
                'notes' => $order->notes,
                'advice' => $order->advice,
                'client' => $order->client->only(['id', 'full_name', 'phone']),
                'completed_at' => $order->completed_at?->toIso8601String(),
                'user' => $order->user->name,
                'lines' => $order->lines->map(fn (ServiceOrderLine $line) => [
                    'id' => $line->id,
                    'type' => $line->serviceType->name,
                    'employee' => $line->employee?->name,
                    'description' => $line->description,
                    'labor_price' => $line->labor_price,
                    'parts' => $line->parts->map(fn (ServiceOrderPart $part) => [
                        'id' => $part->id,
                        'origin' => $part->origin->value,
                        'origin_label' => $part->origin->label(),
                        'article_id' => $part->article_id,
                        'designation' => $part->designation,
                        'quantity' => $part->quantity,
                        'unit' => $part->article?->stock_unit->symbol() ?? 'u',
                        'unit_price' => $part->unit_price,
                        'line_total' => $part->line_total,
                    ]),
                ]),
                'labor_total' => $order->laborTotal(),
                'parts_total' => $order->partsTotal(),
                'total' => $order->total(),
                'can_delete' => $order->isInProgress() && Gate::allows('delete-service-orders') && $order->invoices->isEmpty(),
                'invoices' => $order->invoices->map(fn (Invoice $invoice) => $invoice->only(['id', 'number', 'total']) + ['status' => $invoice->status->value]),
            ],
        ]);
    }

    public function edit(ServiceOrder $order): Response|RedirectResponse
    {
        if (! $order->isInProgress()) {
            return to_route('service-orders.show', $order);
        }

        $order->load(['vehicle', 'lines.serviceType', 'lines.parts.article']);

        return Inertia::render('service-orders/form', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'date' => $order->date->toDateString(),
                'mileage' => $order->mileage,
                'immobilization_value' => $order->immobilization_value,
                'immobilization_unit' => $order->immobilization_unit?->value,
                'notes' => $order->notes,
                'advice' => $order->advice,
                'lines' => $order->lines->map(fn (ServiceOrderLine $line) => [
                    'service_type_id' => $line->service_type_id,
                    'employee_id' => $line->employee_id,
                    'description' => $line->description,
                    'labor_price' => $line->labor_price,
                    'parts' => $line->parts->map(fn (ServiceOrderPart $part) => [
                        'origin' => $part->origin->value,
                        'article' => $part->article ? [
                            'value' => (string) $part->article_id,
                            'label' => $part->article->name,
                            'unit' => $part->article->stock_unit->symbol(),
                            'stock_qty' => $part->article->stock_qty,
                        ] : null,
                        'designation' => $part->designation,
                        'quantity' => $part->quantity,
                        'unit_price' => $part->unit_price,
                        'unit_cost' => $part->unit_cost,
                    ]),
                ]),
            ],
            'vehicle' => LookupController::serviceVehicleOption($order->vehicle),
            ...self::formOptions(),
        ]);
    }

    public function update(ServiceOrderRequest $request, ServiceOrder $order, SaveServiceOrder $save, CompleteServiceOrder $complete): RedirectResponse
    {
        return $this->persist($request, $order, $save, $complete);
    }

    public function complete(ServiceOrder $order, CompleteServiceOrder $complete): RedirectResponse
    {
        $warnings = $complete->handle($order);

        $this->flashCompleted($warnings);

        return to_route('invoices.show', $order->invoice()->firstOrFail());
    }

    public function destroy(ServiceOrder $order): RedirectResponse
    {
        Gate::authorize('delete-service-orders');

        if (! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'Une prestation terminée ne peut pas être supprimée.']);
        }

        if ($order->invoices()->exists()) {
            throw ValidationException::withMessages(['status' => 'Cette prestation a déjà été facturée : corrigez-la puis terminez-la à nouveau.']);
        }

        // Le rendez-vous qui l'avait commencée redevient Prévu.
        DB::transaction(function () use ($order) {
            Appointment::where('service_order_id', $order->id)->update(['status' => AppointmentStatus::Planned, 'service_order_id' => null]);
            $order->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Prestation {$order->number} supprimée."]);

        return to_route('service-orders.index');
    }

    /**
     * « Enregistrer » ou « Terminer et facturer » : un seul envoi, tout ou rien.
     */
    private function persist(ServiceOrderRequest $request, ?ServiceOrder $order, SaveServiceOrder $save, CompleteServiceOrder $complete): RedirectResponse
    {
        [$order, $warnings] = DB::transaction(function () use ($request, $order, $save, $complete) {
            $order = $save->handle($order, $request->orderData(), $request->lines());

            return [$order, $request->boolean('complete') ? $complete->handle($order) : null];
        });

        if ($warnings === null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => "Prestation {$order->number} enregistrée."]);
        } else {
            $this->flashCompleted($warnings);

            // « Terminer et facturer » mène à la facture, prête à encaisser.
            return to_route('invoices.show', $order->invoice()->firstOrFail());
        }

        return to_route('service-orders.show', $order);
    }

    /**
     * @param  list<string>  $warnings
     */
    private function flashCompleted(array $warnings): void
    {
        Inertia::flash('toast', $warnings === []
            ? ['type' => 'success', 'message' => 'Prestation terminée et facturée : le stock est à jour.']
            : ['type' => 'warning', 'message' => 'Prestation terminée. Attention : '.implode(' ', $warnings)]);
    }

    /**
     * Carnet d'entretien (fiche véhicule) et onglet Prestations (fiche client) : le plus récent d'abord.
     *
     * @param  Builder<ServiceOrder>  $query
     * @return list<array<string, mixed>>
     */
    public static function history(Builder $query): array
    {
        return $query
            ->with(['vehicle:id,plate', 'lines.serviceType:id,name', 'invoice:id,service_order_id,number,status'])
            ->withSum('lines', 'labor_price')
            ->withSum('parts', 'line_total')
            ->latest('date')
            ->latest('id')
            ->get()
            ->map(fn (ServiceOrder $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'date' => $order->date->toDateString(),
                'mileage' => $order->mileage,
                'status' => $order->status->value,
                'plate' => $order->vehicle->plate,
                'works' => $order->lines->pluck('serviceType.name')->unique()->implode(', '),
                'types' => $order->lines->pluck('serviceType.name')->unique()->values()->all(),
                'invoice' => $order->invoice ? $order->invoice->only(['id', 'number']) + ['status' => $order->invoice->status->value] : null,
                'advice' => $order->advice,
                'total' => Decimal::add((string) ($order->lines_sum_labor_price ?? 0), (string) ($order->parts_sum_line_total ?? 0)),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(ServiceOrder $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'date' => $order->date->toDateString(),
            'status' => $order->status->value,
            'client_name' => $order->client->full_name,
            'plate' => $order->vehicle->plate,
            'vehicle_id' => $order->vehicle_id,
            'vehicle_label' => $order->vehicle->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formOptions(): array
    {
        return [
            // Les types les plus utilisés d'abord : ce sont les boutons « travaux fréquents ».
            'serviceTypes' => ServiceType::active()->withCount('lines')->orderByDesc('lines_count')->orderBy('name')->get(['id', 'name', 'default_labor_price', 'requires_mileage']),
            'employees' => Employee::active()->orderBy('name')->get(['id', 'name']),
            'immobilizationUnits' => ImmobilizationUnit::options(),
            'partOrigins' => PartOrigin::options(),
            ...FormOptions::clientVehicle(),
        ];
    }
}
