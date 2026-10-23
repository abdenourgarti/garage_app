<?php

namespace App\Models;

use App\Enums\ImmobilizationUnit;
use App\Enums\ServiceOrderStatus;
use App\Support\Decimal;
use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Prestation : écrite par SaveServiceOrder, terminée par CompleteServiceOrder.
 * Les totaux ne sont pas stockés : la facture (phase 6) les figera.
 *
 * @property int $id
 * @property string $number
 * @property int $client_id
 * @property int $vehicle_id
 * @property Carbon $date
 * @property int|null $mileage
 * @property int|null $immobilization_value
 * @property ImmobilizationUnit|null $immobilization_unit
 * @property string|null $notes
 * @property string|null $advice
 * @property ServiceOrderStatus $status
 * @property Carbon|null $completed_at
 * @property int $user_id
 */
#[Fillable(['vehicle_id', 'date', 'mileage', 'immobilization_value', 'immobilization_unit', 'notes', 'advice'])]
class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'mileage' => 'integer',
            'immobilization_value' => 'integer',
            'immobilization_unit' => ImmobilizationUnit::class,
            'status' => ServiceOrderStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function isInProgress(): bool
    {
        return $this->status === ServiceOrderStatus::InProgress;
    }

    public function laborTotal(): string
    {
        return Decimal::add(...$this->lines->pluck('labor_price')->all());
    }

    public function partsTotal(): string
    {
        return Decimal::add(...$this->parts->pluck('line_total')->all());
    }

    public function total(): string
    {
        return Decimal::add($this->laborTotal(), $this->partsTotal());
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasMany<ServiceOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ServiceOrderLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<ServiceOrderPart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(ServiceOrderPart::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
