<?php

namespace App\Http\Requests\Stock;

use App\Enums\StockUnit;
use App\Models\Article;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pièce : tous les champs. Format d'huile : seulement prix, seuil et catégorie
 * (nom, unité et contenance viennent de la fiche huile).
 */
class ArticleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Article|null $article */
        $article = $this->route('article');

        $rules = [
            'article_category_id' => ['required', 'integer', Rule::exists('article_categories', 'id')],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'alert_threshold' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];

        if ($article?->oil_product_id) {
            return $rules;
        }

        return [
            ...$rules,
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'stock_unit' => ['required', Rule::enum(StockUnit::class)],
            'initial_stock' => [$article ? 'prohibited' : 'nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'article_category_id' => 'catégorie',
            'name' => 'désignation',
            'brand' => 'marque',
            'reference' => 'référence',
            'stock_unit' => 'unité de stock',
            'purchase_price' => "prix d'achat",
            'sale_price' => 'prix de vente',
            'alert_threshold' => "seuil d'alerte",
            'initial_stock' => 'stock initial',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function articleData(): array
    {
        return $this->safe()->except('initial_stock');
    }
}
