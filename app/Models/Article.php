<?php

namespace App\Models;

use App\Enums\OilFormat;
use App\Enums\StockUnit;
use App\Models\Concerns\Referential;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une pièce, ou un format d'huile (oil_product_id + oil_format).
 * stock_qty n'est modifié que par App\Services\StockService (avec un mouvement) : il n'est pas fillable.
 *
 * @property int $id
 * @property int $article_category_id
 * @property int|null $oil_product_id
 * @property OilFormat|null $oil_format
 * @property string|null $volume_l
 * @property StockUnit $stock_unit
 * @property string $name
 * @property string|null $brand
 * @property string|null $reference
 * @property string $purchase_price
 * @property string $sale_price
 * @property string $stock_qty
 * @property string|null $alert_threshold
 * @property bool $is_active
 */
#[Fillable([
    'article_category_id', 'oil_product_id', 'oil_format', 'volume_l', 'stock_unit', 'name', 'brand', 'reference',
    'purchase_price', 'sale_price', 'alert_threshold', 'is_active',
])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, Referential;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'oil_format' => OilFormat::class,
            'stock_unit' => StockUnit::class,
            'volume_l' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock_qty' => 'decimal:2',
            'alert_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Stock at or below its alert threshold (out of stock included).
     *
     * @param  Builder<static>  $query
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('stock_qty', '<=', 0)
            ->orWhere(fn (Builder $query) => $query->whereNotNull('alert_threshold')->whereColumn('stock_qty', '<=', 'alert_threshold')));
    }

    /**
     * « ok », « bas » ou « rupture » (StatusBadge type stock).
     */
    public function stockStatus(): string
    {
        return match (true) {
            bccomp($this->stock_qty, '0', 2) <= 0 => 'rupture',
            $this->alert_threshold !== null && bccomp($this->stock_qty, $this->alert_threshold, 2) <= 0 => 'bas',
            default => 'ok',
        };
    }

    public function isCan(): bool
    {
        return $this->oil_format?->isCan() ?? false;
    }

    /**
     * @return BelongsTo<ArticleCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    /**
     * @return BelongsTo<OilProduct, $this>
     */
    public function oilProduct(): BelongsTo
    {
        return $this->belongsTo(OilProduct::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
