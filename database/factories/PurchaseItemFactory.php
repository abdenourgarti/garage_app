<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'article_id' => Article::factory(),
            'quantity' => '2.00',
            'unit_price' => '800.00',
            'line_total' => '1600.00',
        ];
    }
}
