<?php

namespace App\Http\Controllers\Clients;

use App\Enums\PartOrigin;
use App\Enums\ServiceOrderStatus;
use App\Enums\StockUnit;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use App\Models\ServiceOrderPart;
use App\Models\Vehicle;
use App\Services\ReminderService;
use App\Services\SettingsService;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bilan d'entretien (M9, S9) : toute l'histoire du véhicule, avec ou sans les prix (vente du véhicule).
 * Une seule mise en page pour l'aperçu, l'impression A4 et le PDF.
 */
class MaintenanceReportController extends Controller
{
    public function show(Vehicle $vehicle): Response
    {
        return Inertia::render('vehicles/report', [
            'vehicle' => ['id' => $vehicle->id, 'plate' => $vehicle->plate],
        ]);
    }

    public function document(Request $request, Vehicle $vehicle, string $format, ReminderService $reminders, SettingsService $settings): HttpResponse
    {
        $vehicle->load(['brand:id,name', 'vehicleModel:id,name', 'energy:id,name', 'client:id,first_name,last_name,phone']);

        $orders = ServiceOrder::where('vehicle_id', $vehicle->id)
            ->where('status', ServiceOrderStatus::Completed)
            ->with(['lines.serviceType:id,name', 'lines.parts.article:id,stock_unit', 'parts.article:id,stock_unit', 'invoices'])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return InvoiceController::render('documents.maintenance', $format, "bilan-{$vehicle->plate}", $request->boolean('print'), [
            'vehicle' => $vehicle,
            'withPrices' => $request->boolean('prices', true),
            'reminder' => $reminders->forVehicles([$vehicle->id])->get($vehicle->id),
            'orders' => $orders->map(fn (ServiceOrder $order) => [
                'number' => $order->number,
                'date' => $order->date->format('d/m/Y'),
                'mileage' => $order->mileage,
                'advice' => $order->advice,
                'lines' => $order->lines->map(fn (ServiceOrderLine $line) => [
                    'label' => $line->serviceType->name.($line->description ? " ({$line->description})" : ''),
                    'price' => $line->labor_price,
                    'parts' => $line->parts->map(fn (ServiceOrderPart $part) => self::part($part))->all(),
                ])->all(),
                'parts' => $order->parts->whereNull('service_order_line_id')->map(fn (ServiceOrderPart $part) => self::part($part))->all(),
                // Le montant facturé (remise comprise) ; sinon la somme des lignes.
                'total' => $order->invoices->first(fn (Invoice $invoice) => $invoice->cancelled_at === null)?->total
                    ?? $order->total(),
            ])->all(),
        ], $settings);
    }

    /**
     * « Total Quartz 9000 5W-40, bidon 5 L × 1 » ; vrac : « …, vrac — 4,5 L ».
     *
     * @return array{label: string, quantity: string, price: string|null}
     */
    private static function part(ServiceOrderPart $part): array
    {
        $liters = $part->article?->stock_unit === StockUnit::Liter;

        return [
            'label' => $part->designation.($part->origin === PartOrigin::Client ? ' (fournie par le client)' : ''),
            'quantity' => $liters ? Decimal::display($part->quantity).' L' : '× '.Decimal::display($part->quantity),
            'price' => $part->origin === PartOrigin::Client ? null : $part->line_total,
        ];
    }
}
