<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Article;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pour l'affichage uniquement : en vrai, un mouvement est créé par StockService avec la mise à jour du stock.
 *
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'type' => StockMovementType::Adjustment,
            'quantity' => '1.00',
            'unit_cost' => null,
            'reason' => 'Test',
            'user_id' => null,
        ];
    }
}
