<?php

namespace App\Models;

use Database\Factories\VehicleOwnershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une période de propriété ; ended_at null = propriétaire actuel.
 *
 * @property int $vehicle_id
 * @property int $client_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 */
#[Fillable(['vehicle_id', 'client_id', 'started_at', 'ended_at'])]
class VehicleOwnership extends Model
{
    /** @use HasFactory<VehicleOwnershipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
