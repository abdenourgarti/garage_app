<?php

namespace App\Actions\Stock;

use App\Enums\OilFormat;
use App\Enums\StockMovementType;
use App\Models\Article;
use App\Models\OilProduct;
use App\Services\StockService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fiche huile + ses formats de vente (un article par format, RG-09) en une seule opération.
 * Les formats non transmis sont laissés tels quels (on les désactive depuis leur fiche article).
 */
class SaveOilProduct
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array<string, mixed>  $oil  brand, name, viscosity, usage, approval
     * @param  array<string, array{purchase_price: string, sale_price: string, alert_threshold?: string|null, initial_stock?: string|null}>  $formats  keyed by OilFormat value
     */
    public function handle(?OilProduct $product, array $oil, int $categoryId, array $formats): OilProduct
    {
        if ($product === null && $formats === []) {
            throw ValidationException::withMessages(['formats' => 'Cochez au moins un format (bidon ou vrac).']);
        }

        return DB::transaction(function () use ($product, $oil, $categoryId, $formats) {
            $product ??= new OilProduct;
            $product->fill($oil)->save();

            foreach ($formats as $value => $prices) {
                $format = OilFormat::from($value);

                $article = Article::firstOrNew(['oil_product_id' => $product->id, 'oil_format' => $format]);
                $isNew = ! $article->exists;

                $article->fill([
                    'name' => self::formatName($product, $format),
                    'article_category_id' => $categoryId,
                    'volume_l' => $format->volume(),
                    'stock_unit' => $format->stockUnit(),
                    'purchase_price' => $prices['purchase_price'],
                    'sale_price' => $prices['sale_price'],
                    'alert_threshold' => $prices['alert_threshold'] ?? null,
                ])->save();

                $initial = $prices['initial_stock'] ?? null;

                if ($isNew && $initial !== null && Decimal::cmp($initial, 0) > 0) {
                    $this->stock->in($article, $initial, StockMovementType::Adjustment, $article->purchase_price, reason: CreateArticle::INITIAL_STOCK_REASON);
                }
            }

            // Les formats non transmis suivent aussi la fiche si elle est renommée.
            foreach ($product->articles()->get() as $article) {
                $article->update(['name' => self::formatName($product, $article->oil_format)]);
            }

            return $product;
        });
    }

    /**
     * « Total Quartz 9000 5W-30, bidon 5 L ».
     */
    public static function formatName(OilProduct $product, OilFormat $format): string
    {
        return $product->label().', '.$format->inlineLabel();
    }
}
