<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Compteur annuel des numéros PR-/FA- ; utilisé par NumberingService (phase 5) sous lockForUpdate().
 *
 * @property string $name
 * @property int $year
 * @property int $last_number
 */
#[Fillable(['name', 'year', 'last_number'])]
class Sequence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
