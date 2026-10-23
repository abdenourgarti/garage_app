<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Paiement d'un client sur une facture ; créé uniquement par RecordPayment, annulé (jamais supprimé) avec sa facture.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $client_id
 * @property string $amount
 * @property int $payment_method_id
 * @property Carbon $paid_at
 * @property string|null $proof_path chèque ou ordre de virement (image ou PDF, stockage privé)
 * @property Carbon|null $cancelled_at
 * @property string|null $notes
 * @property int $user_id
 */
#[Fillable(['client_id', 'amount', 'payment_method_id', 'paid_at', 'proof_path', 'notes', 'user_id'])]
class Payment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
}
