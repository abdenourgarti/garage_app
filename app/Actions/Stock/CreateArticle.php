<?php

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Models\Article;
use App\Services\StockService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Création d'une pièce ; un stock initial éventuel passe par un mouvement « Stock initial ».
 */
class CreateArticle
{
    public const INITIAL_STOCK_REASON = 'Stock initial';

    public function __construct(private StockService $stock) {}

    /**
     * @param  array<string, mixed>  $data  validated article fields
     */
    public function handle(array $data, ?string $initialStock = null): Article
    {
        return DB::transaction(function () use ($data, $initialStock) {
            $article = Article::create($data);

            if ($initialStock !== null && Decimal::cmp($initialStock, 0) > 0) {
                $this->stock->in($article, $initialStock, StockMovementType::Adjustment, $article->purchase_price, reason: self::INITIAL_STOCK_REASON);
            }

            return $article;
        });
    }
}
