<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Annulation d'une commande non reçue et non payée (le remboursement fournisseur n'est pas géré).
 */
class CancelPurchase
{
    public function handle(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if (! $purchase->status->isEditable()) {
                throw ValidationException::withMessages(['status' => 'Un achat '.mb_strtolower($purchase->status->label()).' ne peut pas être annulé.']);
            }

            if (! Decimal::isZero($purchase->amount_paid)) {
                throw ValidationException::withMessages(['status' => 'Cet achat a déjà été payé en partie : il ne peut pas être annulé.']);
            }

            $purchase->status = PurchaseStatus::Cancelled;
            $purchase->save();

            return $purchase;
        });
    }
}
