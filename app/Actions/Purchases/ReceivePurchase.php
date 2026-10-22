<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Purchase;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Réception d'un achat : le stock entre ligne par ligne et le prix d'achat de chaque article
 * devient le dernier prix payé (coût figé plus tard à la sortie en prestation).
 */
class ReceivePurchase
{
    public function __construct(private StockService $stock) {}

    public function handle(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if (! $purchase->status->isEditable()) {
                throw ValidationException::withMessages(['status' => 'Cet achat est déjà '.mb_strtolower($purchase->status->label()).'.']);
            }

            if ($purchase->items()->doesntExist()) {
                throw ValidationException::withMessages(['lines' => 'Ajoutez au moins un article avant de réceptionner.']);
            }

            foreach ($purchase->items()->with('article')->get() as $item) {
                $this->stock->in($item->article, $item->quantity, StockMovementType::PurchaseIn, $item->unit_price, $purchase);
                $item->article->update(['purchase_price' => $item->unit_price]);
            }

            $purchase->status = PurchaseStatus::Received;
            $purchase->received_at = today();
            $purchase->save();

            return $purchase;
        });
    }
}
