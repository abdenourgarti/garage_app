<?php

namespace App\Services;

use App\Enums\ServiceOrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rappels de vidange (RG-15, docs/02-conception-technique.md §8), calculés à la volée.
 *
 * Une échéance par type à rappel (vidange moteur, boîte, distribution…) : km de la dernière prestation terminée
 * contenant ce type + son rappel conseillé. Km estimé = dernier km + km/jour entre les deux derniers relevés × jours écoulés.
 */
class ReminderService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * Km estimé et échéances de chaque véhicule (tous si $vehicleIds est null), en deux requêtes.
     *
     * @param  list<int>|null  $vehicleIds
     * @return Collection<int, array{estimated_km: int|null, last_km_date: string|null, due: list<array{type_id: int, type: string, done_at: string, done_km: int, due_km: int, remaining_km: int|null, status: string}>, status: string}>
     */
    public function forVehicles(?array $vehicleIds = null): Collection
    {
        $threshold = $this->settings->reminderThresholdKm();
        $readings = $this->readings($vehicleIds);
        $lastDone = $this->lastDone($vehicleIds);

        $vehicles = DB::table('vehicles')->when($vehicleIds !== null, fn ($query) => $query->whereIn('id', $vehicleIds))->pluck('last_mileage', 'id');

        return $vehicles->map(function ($lastMileage, $vehicleId) use ($readings, $lastDone, $threshold) {
            $vehicleReadings = $readings->get($vehicleId, collect());
            $estimated = self::estimate($vehicleReadings->all(), $lastMileage === null ? null : (int) $lastMileage);

            $due = $lastDone->get($vehicleId, collect())->map(function (object $row) use ($estimated, $threshold) {
                $dueKm = (int) $row->mileage + (int) $row->reminder_km;
                $remaining = $estimated === null ? null : $dueKm - $estimated;

                return [
                    'type_id' => (int) $row->service_type_id,
                    'type' => $row->name,
                    'done_at' => substr((string) $row->date, 0, 10),
                    'done_km' => (int) $row->mileage,
                    'due_km' => $dueKm,
                    'remaining_km' => $remaining,
                    'status' => self::status($remaining, $threshold),
                ];
            })->sortBy(fn (array $item) => $item['remaining_km'] ?? PHP_INT_MAX)->values()->all();

            return [
                'estimated_km' => $estimated,
                'last_km_date' => $vehicleReadings->first()?->date ? substr((string) $vehicleReadings->first()->date, 0, 10) : null,
                'due' => $due,
                'status' => $due[0]['status'] ?? 'ok',
            ];
        });
    }

    /**
     * Véhicules actifs à relancer (bientôt ou dépassée), les plus urgents d'abord.
     * « called » : appelé depuis la dernière prestation qui a fixé l'échéance la plus urgente.
     *
     * @return list<array<string, mixed>>
     */
    public function toCall(): array
    {
        $reminders = $this->forVehicles()->filter(fn (array $reminder) => $reminder['status'] !== 'ok');

        if ($reminders->isEmpty()) {
            return [];
        }

        $vehicles = DB::table('vehicles')
            ->join('brands', 'brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('vehicle_models', 'vehicle_models.id', '=', 'vehicles.vehicle_model_id')
            ->join('clients', 'clients.id', '=', 'vehicles.client_id')
            ->whereIn('vehicles.id', $reminders->keys())
            ->where('vehicles.is_active', true)
            ->selectRaw("vehicles.id, vehicles.plate, vehicles.last_reminder_call_at,
                trim(concat(brands.name, ' ', coalesce(vehicle_models.name, ''))) as vehicle,
                clients.id as client_id, concat_ws(' ', clients.last_name, clients.first_name) as client, clients.phone")
            ->get();

        return $vehicles->map(function (object $vehicle) use ($reminders) {
            $reminder = $reminders->get($vehicle->id);
            $urgent = $reminder['due'][0];
            $calledAt = $vehicle->last_reminder_call_at ? CarbonImmutable::parse($vehicle->last_reminder_call_at) : null;

            return [
                'id' => (int) $vehicle->id,
                'plate' => $vehicle->plate,
                'vehicle' => $vehicle->vehicle,
                'client_id' => (int) $vehicle->client_id,
                'client' => $vehicle->client,
                'phone' => $vehicle->phone,
                'estimated_km' => $reminder['estimated_km'],
                'due' => array_values(array_filter($reminder['due'], fn (array $item) => $item['status'] !== 'ok')),
                'remaining_km' => $urgent['remaining_km'],
                'status' => $urgent['status'],
                'called_at' => $calledAt?->toIso8601String(),
                'called' => $calledAt !== null && $calledAt->toDateString() >= $urgent['done_at'],
            ];
        })->sortBy([['called', 'asc'], ['remaining_km', 'asc'], ['plate', 'asc']])->values()->all();
    }

    /**
     * Km estimé aujourd'hui à partir des deux derniers relevés (le plus récent d'abord).
     *
     * @param  list<object{mileage: int|string, date: string}>  $readings
     */
    public static function estimate(array $readings, ?int $fallback = null, ?CarbonImmutable $today = null): ?int
    {
        if ($readings === []) {
            return $fallback;
        }

        $last = $readings[0];
        $lastKm = (int) $last->mileage;

        if (! isset($readings[1])) {
            return $lastKm;
        }

        $lastDate = CarbonImmutable::parse($last->date)->startOfDay();
        $days = (int) CarbonImmutable::parse($readings[1]->date)->startOfDay()->diffInDays($lastDate);
        $driven = $lastKm - (int) $readings[1]->mileage;

        // Même jour ou compteur qui recule (erreur de saisie) : pas de moyenne fiable.
        if ($days <= 0 || $driven <= 0) {
            return $lastKm;
        }

        $elapsed = max(0, (int) $lastDate->diffInDays(($today ?? CarbonImmutable::today())->startOfDay()));

        return $lastKm + (int) round($driven / $days * $elapsed);
    }

    public static function status(?int $remaining, int $threshold): string
    {
        return match (true) {
            $remaining === null => 'ok',
            $remaining <= 0 => 'overdue',
            $remaining <= $threshold => 'soon',
            default => 'ok',
        };
    }

    /**
     * Les deux derniers relevés de km par véhicule (prestations avec km, le plus récent d'abord).
     *
     * @param  list<int>|null  $vehicleIds
     * @return Collection<int, Collection<int, object>>
     */
    private function readings(?array $vehicleIds): Collection
    {
        $ranked = DB::table('service_orders')
            ->whereNotNull('mileage')
            ->when($vehicleIds !== null, fn ($query) => $query->whereIn('vehicle_id', $vehicleIds))
            ->selectRaw('vehicle_id, mileage, date, row_number() over (partition by vehicle_id order by date desc, id desc) as rn');

        return DB::query()->fromSub($ranked, 'ranked')->where('rn', '<=', 2)->orderBy('vehicle_id')->orderBy('rn')->get()->groupBy('vehicle_id');
    }

    /**
     * Dernière prestation terminée (avec km) de chaque type à rappel, par véhicule.
     *
     * @param  list<int>|null  $vehicleIds
     * @return Collection<int, Collection<int, object>>
     */
    private function lastDone(?array $vehicleIds): Collection
    {
        $ranked = DB::table('service_order_lines')
            ->join('service_orders', 'service_orders.id', '=', 'service_order_lines.service_order_id')
            ->join('service_types', 'service_types.id', '=', 'service_order_lines.service_type_id')
            ->where('service_orders.status', ServiceOrderStatus::Completed->value)
            ->whereNotNull('service_orders.mileage')
            ->whereNotNull('service_types.reminder_km')
            ->when($vehicleIds !== null, fn ($query) => $query->whereIn('service_orders.vehicle_id', $vehicleIds))
            ->selectRaw('service_orders.vehicle_id, service_order_lines.service_type_id, service_types.name, service_types.reminder_km,
                service_orders.mileage, service_orders.date,
                row_number() over (partition by service_orders.vehicle_id, service_order_lines.service_type_id order by service_orders.date desc, service_orders.id desc) as rn');

        return DB::query()->fromSub($ranked, 'ranked')->where('rn', 1)->get()->groupBy('vehicle_id');
    }
}
