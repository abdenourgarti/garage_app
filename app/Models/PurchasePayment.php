<?php

namespace App\Models;

use Database\Factories\PurchasePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * Paiement d'un achat ; créé uniquement par PaySupplier, avec sa dépense.
 *
 * @property int $id
 * @property int $purchase_id
 * @property string $amount
 * @property Carbon $paid_at
 * @property int $payment_method_id
 * @property string|null $proof_path reçu, chèque ou ordre de virement (image ou PDF, stockage privé)
 * @property int $user_id
 */
#[Fillable(['amount', 'paid_at', 'payment_method_id', 'proof_path', 'user_id'])]
class PurchasePayment extends Model
{
    /** @use HasFactory<PurchasePaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
