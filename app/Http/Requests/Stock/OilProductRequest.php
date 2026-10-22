<?php

namespace App\Http\Requests\Stock;

use App\Enums\OilFormat;
use App\Enums\OilUsage;
use App\Models\OilProduct;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fiche huile + formats cochés : formats = [{format, purchase_price, sale_price, alert_threshold, initial_stock}].
 */
class OilProductRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var OilProduct|null $oil */
        $oil = $this->route('oilProduct');

        return [
            'brand' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:100'],
            'viscosity' => ['required', 'string', 'max:20'],
            'usage' => ['required', Rule::enum(OilUsage::class)],
            'approval' => ['nullable', 'string', 'max:255'],
            'article_category_id' => ['required', 'integer', Rule::exists('article_categories', 'id')],
            'formats' => [$oil ? 'present' : 'required', 'array', $oil ? 'min:0' : 'min:1'],
            'formats.*.format' => ['required', 'distinct', Rule::enum(OilFormat::class)],
            'formats.*.purchase_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'formats.*.sale_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'formats.*.alert_threshold' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'formats.*.initial_stock' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'brand' => 'marque',
            'name' => 'gamme',
            'viscosity' => 'viscosité',
            'usage' => 'usage',
            'approval' => 'homologation',
            'article_category_id' => 'catégorie',
            'formats' => 'formats',
            'formats.*.purchase_price' => "prix d'achat",
            'formats.*.sale_price' => 'prix de vente',
            'formats.*.alert_threshold' => "seuil d'alerte",
            'formats.*.initial_stock' => 'stock initial',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['formats.required' => 'Cochez au moins un format (bidon ou vrac).', 'formats.min' => 'Cochez au moins un format (bidon ou vrac).'];
    }

    /**
     * @return array<string, mixed>
     */
    public function oilData(): array
    {
        return $this->safe()->only(['brand', 'name', 'viscosity', 'usage', 'approval']);
    }

    /**
     * @return array<string, array<string, mixed>> keyed by OilFormat value
     */
    public function formats(): array
    {
        return collect($this->validated('formats'))->keyBy('format')->map(fn (array $format) => collect($format)->except('format')->all())->all();
    }
}
