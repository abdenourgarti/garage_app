<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Article;
use App\Models\StockMovement;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Seul point d'écriture de articles.stock_qty : chaque variation crée un mouvement,
 * dans la même transaction, article verrouillé. Invariant : stock = somme des mouvements.
 * Un stock négatif est accepté (RG-11 : alerte sans blocage).
 */
class StockService
{
    public function in(Article $article, string $quantity, StockMovementType $type, ?string $unitCost = null, ?Model $source = null, ?string $reason = null): StockMovement
    {
        return $this->move($article, $type, $quantity, $unitCost, $source, $reason);
    }

    public function out(Article $article, string $quantity, StockMovementType $type, ?string $unitCost = null, ?Model $source = null, ?string $reason = null): StockMovement
    {
        return $this->move($article, $type, Decimal::sub('0', $quantity), $unitCost, $source, $reason);
    }

    /**
     * @param  string  $quantity  signed: positive in, negative out
     */
    public function move(Article $article, StockMovementType $type, string $quantity, ?string $unitCost = null, ?Model $source = null, ?string $reason = null): StockMovement
    {
        return DB::transaction(function () use ($article, $type, $quantity, $unitCost, $source, $reason) {
            $locked = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();

            $movement = $locked->movements()->create([
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'reason' => $reason,
                'user_id' => Auth::id(),
            ]);

            $locked->stock_qty = Decimal::add($locked->stock_qty, $quantity);
            $locked->save();

            // L'instance de l'appelant reflète le nouveau stock.
            $article->stock_qty = $locked->stock_qty;
            $article->syncOriginalAttribute('stock_qty');

            return $movement;
        });
    }
}
