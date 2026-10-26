<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * Paie d'un employé pour un mois ; écrite uniquement par PaySalary, avec sa dépense « Salaires ».
 *
 * @property int $id
 * @property int $employee_id
 * @property string $period « 2026-10 »
 * @property int|null $days_worked salaire journalier seulement
 * @property string $base_amount
 * @property string $advances_total avances du mois
 * @property string $carried_in avances du mois précédent qui dépassaient le salaire
 * @property string $net_paid
 * @property Carbon $paid_at
 * @property int|null $payment_method_id
 * @property int $user_id
 */
class SalaryPayment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_worked' => 'integer',
            'base_amount' => 'decimal:2',
            'advances_total' => 'decimal:2',
            'carried_in' => 'decimal:2',
            'net_paid' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphOne<Expense, $this>
     */
    public function expense(): MorphOne
    {
        return $this->morphOne(Expense::class, 'source');
    }
}
