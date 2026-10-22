<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReferenceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReferenceRequest extends FormRequest
{
    /**
     * Rules come from the reference type in the URL (/admin/references/{type}/{id?}).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ReferenceType $type */
        $type = $this->route('type');
        $id = $this->route('id');

        return $type->rules($id === null ? null : (int) $id, $this->integer('brand_id') ?: null);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'code',
            'brand_id' => 'marque',
            'oil_capacity_l' => "capacité d'huile",
            'default_labor_price' => "prix de main-d'œuvre",
            'reminder_km' => 'rappel',
            'phone' => 'téléphone',
            'notes' => 'remarque',
        ];
    }
}
