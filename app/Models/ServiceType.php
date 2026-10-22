<?php

namespace App\Models;

use App\Models\Concerns\Referential;
use Database\Factories\ServiceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $default_labor_price
 * @property bool $requires_mileage
 * @property int|null $reminder_km
 * @property bool $is_active
 */
#[Fillable(['name', 'default_labor_price', 'requires_mileage', 'reminder_km', 'is_active'])]
class ServiceType extends Model
{
    /** @use HasFactory<ServiceTypeFactory> */
    use HasFactory, Referential;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_labor_price' => 'decimal:2',
            'requires_mileage' => 'boolean',
            'reminder_km' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
