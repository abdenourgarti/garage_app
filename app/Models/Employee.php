<?php

namespace App\Models;

use App\Enums\SalaryType;
use App\Models\Concerns\Referential;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mécanicien ou employé du garage (sans compte utilisateur), choisi sur chaque ligne de travaux.
 *
 * @property int $id
 * @property string $name
 * @property string|null $position
 * @property string|null $phone
 * @property SalaryType $salary_type
 * @property string $salary_amount
 * @property bool $is_active
 */
#[Fillable(['name', 'position', 'phone', 'is_active'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, Referential;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary_type' => SalaryType::class,
            'salary_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
