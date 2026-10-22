<?php

namespace App\Models;

use App\Enums\OilUsage;
use App\Models\Concerns\Referential;
use Database\Factories\OilProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fiche d'une huile (sans prix ni stock) ; ses formats de vente sont des articles.
 *
 * @property int $id
 * @property string $brand
 * @property string|null $name
 * @property string $viscosity
 * @property OilUsage $usage
 * @property string|null $approval
 * @property bool $is_active
 */
#[Fillable(['brand', 'name', 'viscosity', 'usage', 'approval', 'is_active'])]
class OilProduct extends Model
{
    /** @use HasFactory<OilProductFactory> */
    use HasFactory, Referential;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage' => OilUsage::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * « Total Quartz 9000 5W-30 ».
     */
    public function label(): string
    {
        return trim(preg_replace('/\s+/', ' ', "{$this->brand} {$this->name} {$this->viscosity}"));
    }
}
