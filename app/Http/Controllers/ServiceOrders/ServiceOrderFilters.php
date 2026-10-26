<?php

namespace App\Http\Controllers\ServiceOrders;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Support\Normalize;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtres de la liste des prestations (recherche avancée, CDC M9), partagés par l'écran et l'export CSV.
 * Tous se cumulent ; plusieurs types de prestation = au moins un de ces types.
 */
final class ServiceOrderFilters
{
    /**
     * @param  list<int>  $types
     */
    private function __construct(
        public readonly string $search,
        public readonly ?ServiceOrderStatus $status,
        public readonly ?int $employee,
        public readonly ?CarbonInterface $from,
        public readonly ?CarbonInterface $to,
        public readonly string $client,
        public readonly ?int $wilaya,
        public readonly string $plate,
        public readonly ?int $brand,
        public readonly ?int $model,
        public readonly ?int $yearFrom,
        public readonly ?int $yearTo,
        public readonly array $types,
    ) {}

    public static function fromRequest(Request $request): self
    {
        // « types=1,3 » dans l'adresse (ou un tableau) : au moins un de ces types.
        $raw = $request->input('types', []);
        $types = array_values(array_filter(array_map('intval', is_array($raw) ? $raw : explode(',', (string) $raw))));

        return new self(
            search: trim($request->string('search')->toString()),
            status: ServiceOrderStatus::tryFrom($request->string('status')->toString()),
            employee: $request->integer('employee') ?: null,
            from: $request->date('from'),
            to: $request->date('to'),
            client: trim($request->string('client')->toString()),
            wilaya: $request->integer('wilaya') ?: null,
            plate: trim($request->string('plate')->toString()),
            brand: $request->integer('brand') ?: null,
            model: $request->integer('brand') ? ($request->integer('model') ?: null) : null,
            yearFrom: $request->integer('year_from') ?: null,
            yearTo: $request->integer('year_to') ?: null,
            types: $types,
        );
    }

    /**
     * @return Builder<ServiceOrder>
     */
    public function query(): Builder
    {
        $plate = Normalize::plate($this->plate);

        return ServiceOrder::query()
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$this->search}%")
                ->orWhereHas('client', fn (Builder $query) => $query->search($this->search))
                ->orWhereHas('vehicle', fn (Builder $query) => $query->search($this->search))))
            ->when($this->status, fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->employee, fn (Builder $query) => $query->whereHas('lines', fn (Builder $query) => $query->where('employee_id', $this->employee)))
            ->when($this->from, fn (Builder $query) => $query->where('date', '>=', $this->from->toDateString()))
            ->when($this->to, fn (Builder $query) => $query->where('date', '<=', $this->to->toDateString()))
            ->when($this->client !== '', fn (Builder $query) => $query->whereHas('client', fn (Builder $query) => $query->identity($this->client)))
            ->when($this->wilaya, fn (Builder $query) => $query->whereHas('client', fn (Builder $query) => $query->where('wilaya_id', $this->wilaya)))
            ->when($plate !== '', fn (Builder $query) => $query->whereHas('vehicle', fn (Builder $query) => $query
                ->where('plate_normalized', 'like', "%{$plate}%")
                ->orWhere('vin', 'like', "%{$plate}%")))
            ->when($this->brand, fn (Builder $query) => $query->whereHas('vehicle', fn (Builder $query) => $query
                ->where('brand_id', $this->brand)
                ->when($this->model, fn (Builder $query) => $query->where('vehicle_model_id', $this->model))))
            ->when($this->yearFrom || $this->yearTo, fn (Builder $query) => $query->whereHas('vehicle', fn (Builder $query) => $query
                ->when($this->yearFrom, fn (Builder $query) => $query->where('year', '>=', $this->yearFrom))
                ->when($this->yearTo, fn (Builder $query) => $query->where('year', '<=', $this->yearTo))))
            ->when($this->types !== [], fn (Builder $query) => $query->whereHas('lines', fn (Builder $query) => $query->whereIn('service_type_id', $this->types)));
    }

    /**
     * Filtres renvoyés à la page (et remis dans l'adresse de l'export).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status?->value,
            'employee' => $this->employee,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'client' => $this->client,
            'wilaya' => $this->wilaya,
            'plate' => $this->plate,
            'brand' => $this->brand,
            'model' => $this->model,
            'year_from' => $this->yearFrom,
            'year_to' => $this->yearTo,
            'types' => $this->types,
        ];
    }
}
