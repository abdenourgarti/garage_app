<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Remise globale en DA, possible tant qu'aucun paiement n'est enregistré (RG-12).
 */
class ApplyDiscount
{
    public function handle(Invoice $invoice, string $discount): Invoice
    {
        return DB::transaction(function () use ($invoice, $discount) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->isCancelled() || $invoice->payments()->exists()) {
                throw ValidationException::withMessages(['discount' => 'La remise ne peut plus changer : la facture a déjà un paiement.']);
            }

            if (Decimal::cmp($discount, 0) < 0 || Decimal::cmp($discount, $invoice->subtotal()) > 0) {
                throw ValidationException::withMessages(['discount' => 'La remise doit être comprise entre 0 et le montant de la facture.']);
            }

            $invoice->discount = $discount;
            $invoice->total = Decimal::sub($invoice->subtotal(), $discount);
            $invoice->syncStatus();
            $invoice->save();

            return $invoice;
        });
    }
}
