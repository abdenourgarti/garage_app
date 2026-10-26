<?php

namespace App\Models;

use App\Enums\SalaryType;
use App\Models\Concerns\Referential;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
#[Fillable(['name', 'position', 'phone', 'salary_type', 'salary_amount', 'is_active'])]
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

    /**
     * Avances et autres dépenses rattachées à l'employé.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<SalaryPayment, $this>
     */
    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class)->latest('period');
    }

    /**
     * @return HasMany<ServiceOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ServiceOrderLine::class);
    }
}
