<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Support\Decimal;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Achat fournisseur ; total et amount_paid sont calculés par les Actions (RecordPurchase, PaySupplier).
 *
 * @property int $id
 * @property int $supplier_id
 * @property PurchaseStatus $status
 * @property Carbon $purchased_at
 * @property Carbon|null $received_at
 * @property string|null $supplier_invoice_ref
 * @property string $total
 * @property string $amount_paid
 * @property string|null $notes
 * @property string|null $document_path
 * @property int $user_id
 */
#[Fillable(['supplier_id', 'purchased_at', 'supplier_invoice_ref', 'notes'])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'purchased_at' => 'date',
            'received_at' => 'date',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function remaining(): string
    {
        return $this->status === PurchaseStatus::Cancelled ? '0.00' : Decimal::sub($this->total, $this->amount_paid);
    }

    /**
     * « payee », « partielle » ou « impayee » (mêmes statuts que les factures clients).
     */
    public function paymentStatus(): string
    {
        return match (true) {
            Decimal::cmp($this->amount_paid, $this->total) >= 0 && ! Decimal::isZero($this->total) => 'payee',
            Decimal::cmp($this->amount_paid, 0) > 0 => 'partielle',
            default => 'impayee',
        };
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return HasMany<PurchasePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class)->latest('paid_at')->latest('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
