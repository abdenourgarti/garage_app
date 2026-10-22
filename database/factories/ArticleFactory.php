<?php

namespace Database\Factories;

use App\Actions\Stock\SaveOilProduct;
use App\Enums\OilFormat;
use App\Enums\StockUnit;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\OilProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Stock à 0 : un stock se crée par StockService (mouvement), jamais par la factory.
 *
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_category_id' => ArticleCategory::factory(),
            'oil_product_id' => null,
            'oil_format' => null,
            'volume_l' => null,
            'stock_unit' => StockUnit::Unit,
            'name' => 'Filtre '.fake()->unique()->bothify('??-####'),
            'brand' => fake()->randomElement(['Purflux', 'Mann', 'Bosch', null]),
            'reference' => fake()->bothify('REF-####'),
            'purchase_price' => '800.00',
            'sale_price' => '1200.00',
            'alert_threshold' => null,
            'is_active' => true,
        ];
    }

    /**
     * One sale format of an oil (can or bulk).
     */
    public function oil(OilFormat $format, ?OilProduct $oil = null): static
    {
        return $this->state(function (array $attributes) use ($format, $oil) {
            $oil ??= OilProduct::factory()->create();

            return [
                'oil_product_id' => $oil->id,
                'oil_format' => $format,
                'volume_l' => $format->volume(),
                'stock_unit' => $format->stockUnit(),
                'name' => SaveOilProduct::formatName($oil, $format),
            ];
        });
    }
}
