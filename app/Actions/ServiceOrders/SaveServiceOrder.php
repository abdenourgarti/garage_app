<?php

namespace App\Actions\ServiceOrders;

use App\Enums\PartOrigin;
use App\Models\Article;
use App\Models\ServiceOrder;
use App\Models\Vehicle;
use App\Services\NumberingService;
use App\Support\Decimal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création (numéro PR attribué) ou modification d'une prestation En cours : en-tête, lignes, pièces.
 * Le client est le propriétaire actuel du véhicule (copié : le carnet suit le véhicule, RG-04).
 */
class SaveServiceOrder
{
    public function __construct(private NumberingService $numbering) {}

    /**
     * @param  array<string, mixed>  $data  vehicle_id, date, mileage, immobilization_value, immobilization_unit, notes, advice
     * @param  list<array{service_type_id: int, employee_id?: int|null, description?: string|null, labor_price: string, parts?: list<array<string, mixed>>}>  $lines
     */
    public function handle(?ServiceOrder $order, array $data, array $lines): ServiceOrder
    {
        if ($order && ! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'Une prestation terminée ne peut plus être modifiée.']);
        }

        return DB::transaction(function () use ($order, $data, $lines) {
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);

            $order ??= new ServiceOrder;
            $order->fill($data);
            $order->client_id = $vehicle->client_id;
            $order->user_id ??= Auth::id();
            $order->number ??= $this->numbering->serviceOrder($order->date);
            $order->save();

            $order->lines()->delete();
            $order->parts()->delete();

            $articles = Article::whereIn('id', collect($lines)->flatMap(fn (array $line) => array_column($line['parts'] ?? [], 'article_id'))->filter())->get()->keyBy('id');

            foreach (array_values($lines) as $position => $line) {
                $saved = $order->lines()->create([
                    'service_type_id' => $line['service_type_id'],
                    'employee_id' => $line['employee_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'labor_price' => $line['labor_price'],
                    'position' => $position,
                ]);

                foreach ($line['parts'] ?? [] as $part) {
                    $origin = PartOrigin::from($part['origin']);
                    $article = $origin === PartOrigin::Stock ? $articles->get($part['article_id']) : null;
                    $unitPrice = $origin === PartOrigin::Client ? '0' : (string) $part['unit_price'];

                    $order->parts()->create([
                        'service_order_line_id' => $saved->id,
                        'origin' => $origin,
                        'article_id' => $article?->id,
                        'designation' => $article?->name ?? $part['designation'],
                        'quantity' => $part['quantity'],
                        'unit_price' => $unitPrice,
                        // Coût du stock figé à la sortie (CompleteServiceOrder) ; prix d'achat saisi pour l'extérieur.
                        'unit_cost' => $origin === PartOrigin::External ? $part['unit_cost'] : null,
                        'line_total' => Decimal::mul($part['quantity'], $unitPrice),
                    ]);
                }
            }

            return $order->refresh();
        });
    }
}
