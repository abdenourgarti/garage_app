<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PartOrigin;
use App\Enums\ServiceOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Invoice;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Annulation par le gérant (motif obligatoire, RG-12) : paiements annulés (ils sortent de la caisse),
 * pièces du stock réintégrées à leur coût, prestation repassée En cours pour être corrigée puis refacturée.
 * Les dépenses des pièces extérieures restent : l'argent a bien été dépensé.
 */
class CancelInvoice
{
    public function __construct(private StockService $stock) {}

    public function handle(Invoice $invoice, string $reason): Invoice
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Indiquez le motif de l’annulation.']);
        }

        return DB::transaction(function () use ($invoice, $reason) {
            $invoice = Invoice::with('serviceOrder.parts.article')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages(['reason' => 'Cette facture est déjà annulée.']);
            }

            $invoice->payments()->whereNull('cancelled_at')->update(['cancelled_at' => now()]);

            $order = $invoice->serviceOrder;

            foreach ($order->parts->where('origin', PartOrigin::Stock) as $part) {
                $this->stock->in($part->article, $part->quantity, StockMovementType::CancellationIn, $part->unit_cost, $order, "Annulation de la facture {$invoice->number}");
            }

            $order->status = ServiceOrderStatus::InProgress;
            $order->completed_at = null;
            $order->save();

            $invoice->status = InvoiceStatus::Cancelled;
            $invoice->cancelled_at = now();
            $invoice->cancel_reason = trim($reason);
            $invoice->cancelled_by = Auth::id();
            $invoice->save();

            return $invoice;
        });
    }
}
