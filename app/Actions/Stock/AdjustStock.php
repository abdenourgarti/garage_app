<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\Article;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

/**
 * Correction de stock : le garagiste saisit ce qu'il a compté, l'écart devient un mouvement
 * « Correction » signé, avec un motif obligatoire.
 */
class AdjustStock
{
    public function __construct(private StockService $stock) {}

    public function handle(Article $article, string $countedQuantity, string $reason): StockMovement
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Indiquez le motif de la correction.']);
        }

        $difference = Decimal::sub($countedQuantity, $article->fresh()->stock_qty);

        if (Decimal::isZero($difference)) {
            throw ValidationException::withMessages(['counted_quantity' => 'Le stock est déjà à cette valeur : rien à corriger.']);
        }

        return $this->stock->move($article, StockMovementType::Adjustment, $difference, $article->purchase_price, reason: $reason);
    }
}
