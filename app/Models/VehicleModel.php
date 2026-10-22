<?php

namespace App\Models;

use App\Models\Concerns\Referential;
use Database\Factories\VehicleModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $brand_id
 * @property string $name
 * @property string|null $oil_capacity_l
 * @property bool $is_active
 */
#[Fillable(['brand_id', 'name', 'oil_capacity_l', 'is_active'])]
class VehicleModel extends Model
{
    /** @use HasFactory<VehicleModelFactory> */
    use HasFactory, Referential;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'oil_capacity_l' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
