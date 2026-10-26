<?php

namespace App\Models;

use Database\Factories\OtherIncomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Entrée de caisse hors factures, saisie à la main dans la Caisse.
 *
 * @property int $id
 * @property string $label
 * @property string $amount
 * @property Carbon $received_at
 * @property int|null $payment_method_id
 * @property string|null $notes
 * @property int $user_id
 */
#[Fillable(['label', 'amount', 'received_at', 'payment_method_id', 'notes', 'user_id'])]
class OtherIncome extends Model
{
    /** @use HasFactory<OtherIncomeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
