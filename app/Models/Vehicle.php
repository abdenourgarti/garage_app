<?php

namespace App\Models;

use App\Models\Concerns\Referential;
use App\Support\Normalize;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * client_id = propriétaire actuel ; il ne change que par TransferVehicleOwnership.
 *
 * @property int $id
 * @property int $client_id
 * @property string $plate
 * @property string $plate_normalized
 * @property string|null $vin
 * @property int $brand_id
 * @property int|null $vehicle_model_id
 * @property int|null $year
 * @property int|null $energy_id
 * @property int|null $last_mileage
 * @property string|null $notes
 * @property bool $is_active
 */
#[Fillable(['plate', 'vin', 'brand_id', 'vehicle_model_id', 'year', 'energy_id', 'last_mileage', 'notes', 'is_active'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, Referential;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_mileage' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Writing the plate also writes its normalized form (unique, searchable).
     */
    protected function plate(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => [
            'plate' => mb_strtoupper(trim((string) $value)),
            'plate_normalized' => Normalize::plate($value),
        ]);
    }

    protected function vin(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => Normalize::vin($value));
    }

    /**
     * Plate or VIN (start), brand or model (contains), owner name or phone.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);

        $query->where(function (Builder $query) use ($term) {
            if (preg_match('/\d/', $term)) {
                $plate = Normalize::plate($term);

                $query->where('plate_normalized', 'like', "{$plate}%")
                    ->orWhere('vin', 'like', "{$plate}%");
            }

            $query->orWhereHas('brand', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                ->orWhereHas('vehicleModel', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                ->orWhereHas('client', fn (Builder $query) => $query->search($term));
        });
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<VehicleModel, $this>
     */
    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    /**
     * @return BelongsTo<Energy, $this>
     */
    public function energy(): BelongsTo
    {
        return $this->belongsTo(Energy::class);
    }

    /**
     * Owners, most recent first.
     *
     * @return HasMany<VehicleOwnership, $this>
     */
    public function ownerships(): HasMany
    {
        return $this->hasMany(VehicleOwnership::class)->latest('started_at')->latest('id');
    }

    /**
     * « Peugeot 208 » / « Peugeot ».
     */
    public function label(): string
    {
        return trim($this->brand->name.' '.$this->vehicleModel?->name);
    }
}
