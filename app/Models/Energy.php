<?php

namespace App\Models;

use App\Models\Concerns\Referential;
use Database\Factories\EnergyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property bool $is_active
 */
#[Fillable(['name', 'is_active'])]
class Energy extends Model
{
    /** @use HasFactory<EnergyFactory> */
    use HasFactory, Referential;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
