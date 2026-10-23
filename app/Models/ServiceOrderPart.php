<?php

namespace App\Models;

use App\Enums\PartOrigin;
use Database\Factories\ServiceOrderPartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce utilisée. unit_cost : figé à la sortie de stock (stock) ou prix d'achat (external).
 *
 * @property int $id
 * @property int $service_order_id
 * @property int|null $service_order_line_id
 * @property PartOrigin $origin
 * @property int|null $article_id
 * @property string $designation
 * @property string $quantity
 * @property string $unit_price
 * @property string|null $unit_cost
 * @property string $line_total
 */
#[Fillable(['service_order_line_id', 'origin', 'article_id', 'designation', 'quantity', 'unit_price', 'unit_cost', 'line_total'])]
class ServiceOrderPart extends Model
{
    /** @use HasFactory<ServiceOrderPartFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => PartOrigin::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ServiceOrder, $this>
     */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
