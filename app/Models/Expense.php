<?php

namespace App\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Sortie d'argent (RG-14) : saisie à la main (phase 11) ou générée par une Action
 * (paiement fournisseur, salaire, pièce achetée à l'extérieur) avec sa source.
 *
 * @property int $expense_category_id
 * @property string $label
 * @property string $amount
 * @property Carbon $spent_at
 * @property int|null $payment_method_id
 * @property string|null $notes
 * @property int $user_id
 */
#[Fillable(['expense_category_id', 'label', 'amount', 'spent_at', 'payment_method_id', 'employee_id', 'source_type', 'source_id', 'notes', 'user_id'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * Saisie à la main dans la Caisse (loyer, électricité…) : seule modifiable. Achats, salaires, avances et pièces
     * extérieures sont écrits par leurs actions.
     */
    public function isManual(): bool
    {
        return $this->source_type === null && $this->employee_id === null && $this->category->system_key === null;
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
