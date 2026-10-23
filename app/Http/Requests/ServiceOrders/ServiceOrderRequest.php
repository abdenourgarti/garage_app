<?php

namespace App\Http\Requests\ServiceOrders;

use App\Enums\ImmobilizationUnit;
use App\Enums\PartOrigin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'une prestation (en-tête, lignes, pièces par ligne).
 * Kilométrage et mécanicien ne sont exigés qu'à « Terminer » (CompleteServiceOrder).
 */
class ServiceOrderRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'immobilization_value' => ['nullable', 'integer', 'min:1', 'max:999'],
            'immobilization_unit' => ['nullable', 'required_with:immobilization_value', Rule::enum(ImmobilizationUnit::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'advice' => ['nullable', 'string', 'max:2000'],
            'complete' => ['boolean'],
            'lines' => ['present', 'array'],
            'lines.*.service_type_id' => ['required', 'integer', Rule::exists('service_types', 'id')],
            'lines.*.employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.labor_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.parts' => ['nullable', 'array'],
            'lines.*.parts.*.origin' => ['required', Rule::enum(PartOrigin::class)],
            'lines.*.parts.*.article_id' => ['nullable', 'required_if:lines.*.parts.*.origin,stock', 'integer', Rule::exists('articles', 'id')],
            'lines.*.parts.*.designation' => ['nullable', 'required_unless:lines.*.parts.*.origin,stock', 'string', 'max:255'],
            'lines.*.parts.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.parts.*.unit_price' => ['nullable', 'required_unless:lines.*.parts.*.origin,client', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.parts.*.unit_cost' => ['nullable', 'required_if:lines.*.parts.*.origin,external', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehicle_id' => 'véhicule',
            'date' => 'date',
            'mileage' => 'kilométrage',
            'immobilization_value' => 'durée d’immobilisation',
            'immobilization_unit' => 'unité',
            'advice' => 'conseil',
            'lines.*.service_type_id' => 'travail',
            'lines.*.employee_id' => 'mécanicien',
            'lines.*.labor_price' => 'main-d’œuvre',
            'lines.*.parts.*.article_id' => 'article',
            'lines.*.parts.*.designation' => 'désignation',
            'lines.*.parts.*.quantity' => 'quantité',
            'lines.*.parts.*.unit_price' => 'prix facturé',
            'lines.*.parts.*.unit_cost' => 'prix d’achat',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.*.parts.*.article_id.required_if' => 'Choisissez l’article du stock.',
            'lines.*.parts.*.designation.required_unless' => 'Indiquez la pièce.',
            'lines.*.parts.*.unit_price.required_unless' => 'Indiquez le prix facturé.',
            'lines.*.parts.*.unit_cost.required_if' => 'Indiquez le prix d’achat.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderData(): array
    {
        return $this->safe()->only(['vehicle_id', 'date', 'mileage', 'immobilization_value', 'immobilization_unit', 'notes', 'advice']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return array_values($this->validated('lines'));
    }
}
