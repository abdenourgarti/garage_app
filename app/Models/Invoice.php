<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Facture d'une prestation ; écrite uniquement par les actions (GenerateInvoice, RecordPayment, CancelInvoice…).
 *
 * @property int $id
 * @property string $number
 * @property int $service_order_id
 * @property int $client_id
 * @property Carbon $issued_at
 * @property string $labor_total
 * @property string $parts_total
 * @property string $discount
 * @property string $total
 * @property string $amount_paid
 * @property InvoiceStatus $status
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int|null $cancelled_by
 * @property int $user_id
 */
class Invoice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'labor_total' => 'decimal:2',
            'parts_total' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->status === InvoiceStatus::Cancelled;
    }

    public function subtotal(): string
    {
        return Decimal::add($this->labor_total, $this->parts_total);
    }

    /**
     * RG-13 : reste à payer (0 pour une facture annulée).
     */
    public function remaining(): string
    {
        return $this->isCancelled() ? '0.00' : Decimal::sub($this->total, $this->amount_paid);
    }

    /**
     * Statut déduit des montants : Payée dès que le total est atteint (y compris un total à 0).
     */
    public function syncStatus(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->status = match (true) {
            Decimal::cmp($this->amount_paid, $this->total) >= 0 => InvoiceStatus::Paid,
            Decimal::cmp($this->amount_paid, 0) > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Unpaid,
        };
    }

    /**
     * Factures qui comptent (crédit client, caisse) : toutes sauf les annulées.
     *
     * @param  Builder<static>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->where('status', '!=', InvoiceStatus::Cancelled);
    }

    /**
     * Factures avec un reste à payer : le crédit client.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]);
    }

    /**
     * @return BelongsTo<ServiceOrder, $this>
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
