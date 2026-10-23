<?php

namespace App\Models;

use Database\Factories\ServiceOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ligne de travaux : un type, un mécanicien (RG-07), un prix de main-d'œuvre et ses pièces.
 *
 * @property int $id
 * @property int $service_order_id
 * @property int $service_type_id
 * @property int|null $employee_id
 * @property string|null $description
 * @property string $labor_price
 * @property int $position
 */
#[Fillable(['service_type_id', 'employee_id', 'description', 'labor_price', 'position'])]
class ServiceOrderLine extends Model
{
    /** @use HasFactory<ServiceOrderLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'labor_price' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ServiceOrder, $this>
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * @return BelongsTo<ServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<ServiceOrderPart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(ServiceOrderPart::class);
    }
}
