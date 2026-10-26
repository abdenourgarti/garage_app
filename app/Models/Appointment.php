<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Rendez-vous (M2).
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $vehicle_id
 * @property Carbon $scheduled_at
 * @property string|null $notes
 * @property AppointmentStatus $status
 * @property int|null $service_order_id
 * @property int $user_id
 */
#[Fillable(['client_id', 'vehicle_id', 'scheduled_at', 'notes', 'user_id'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }

    public function isPlanned(): bool
    {
        return $this->status === AppointmentStatus::Planned;
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
     * @return BelongsTo<ServiceOrder, $this>
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * Prestations prévues.
     *
     * @return BelongsToMany<ServiceType, $this>
     */
    public function serviceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ServiceType::class);
    }
}
