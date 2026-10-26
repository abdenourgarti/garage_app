<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Models\Vehicle;
use App\Support\Normalize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche globale (Ctrl+K) : clients, véhicules, factures et prestations, 5 résultats par groupe, dès 2 caractères.
 */
class SearchController extends Controller
{
    private const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return response()->json(['clients' => [], 'vehicles' => [], 'invoices' => [], 'orders' => []]);
        }

        $hasDigit = (bool) preg_match('/\d/', $term);

        return response()->json([
            'clients' => Client::identity($term)
                ->withCount('vehicles')
                ->orderByDesc('is_active')
                ->orderBy('last_name')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'label' => $client->full_name,
                    'description' => $client->phone.' · '.trans_choice('{0} aucun véhicule|{1} 1 véhicule|[2,*] :count véhicules', $client->vehicles_count),
                ]),
            'vehicles' => $hasDigit || mb_strlen($term) >= 3
                ? self::vehicles($term)
                : [],
            'invoices' => $hasDigit
                ? self::byNumber(Invoice::query(), $term)
                    ->with(['client:id,first_name,last_name'])
                    ->get()
                    ->map(fn (Invoice $invoice) => [
                        'id' => $invoice->id,
                        'label' => $invoice->number,
                        'description' => $invoice->client->full_name.' · '.$invoice->status->label(),
                    ])
                : [],
            'orders' => $hasDigit
                ? self::byNumber(ServiceOrder::query(), $term)
                    ->with(['vehicle:id,plate', 'lines.serviceType:id,name'])
                    ->get()
                    ->map(fn (ServiceOrder $order) => [
                        'id' => $order->id,
                        'label' => $order->number,
                        'description' => $order->date->format('d/m/Y').' · '.$order->vehicle->plate.' · '.$order->lines->pluck('serviceType.name')->unique()->implode(', '),
                    ])
                : [],
        ]);
    }

    /**
     * Matricule ou châssis : ceux qui commencent par le terme d'abord, puis ceux qui le contiennent.
     *
     * @return list<array<string, mixed>>
     */
    private static function vehicles(string $term): array
    {
        $plate = Normalize::plate($term);

        if ($plate === '') {
            return [];
        }

        return Vehicle::query()
            ->with(['brand:id,name', 'vehicleModel:id,name', 'client:id,first_name,last_name'])
            ->where(fn (Builder $query) => $query->where('plate_normalized', 'like', "%{$plate}%")->orWhere('vin', 'like', "%{$plate}%"))
            ->orderByRaw('plate_normalized like ? desc', ["{$plate}%"])
            ->orderBy('plate_normalized')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'label' => $vehicle->plate,
                'description' => $vehicle->label().' · '.$vehicle->client->full_name,
            ])
            ->all();
    }

    /**
     * « 26 » trouve FA-2026-00026 (fin du numéro) ; « FA-2026 » cherche dans tout le numéro. Les plus récents d'abord.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function byNumber(Builder $query, string $term): Builder
    {
        $term = strtoupper(str_replace(' ', '', $term));

        return $query
            ->where('number', 'like', ctype_digit($term) ? "%{$term}" : "%{$term}%")
            ->latest('id')
            ->limit(self::LIMIT);
    }
}
