<?php

namespace App\Http\Requests\Clients;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Création rapide : champs client + champs véhicule (à plat) si with_vehicle.
 */
class QuickClientRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            ...ClientRequest::clientRules(),
            'with_vehicle' => ['boolean'],
        ];

        if ($this->boolean('with_vehicle')) {
            // Les remarques du formulaire rapide concernent le client.
            $vehicleRules = VehicleRequest::vehicleRules(null, $this->integer('brand_id') ?: null);
            unset($vehicleRules['notes']);
            $rules = [...$rules, ...$vehicleRules];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...ClientRequest::clientAttributes(), ...VehicleRequest::vehicleAttributes()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['plate.regex' => 'Le matricule doit contenir des chiffres ou des lettres.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientData(): array
    {
        return $this->safe()->only(['last_name', 'first_name', 'phone', 'wilaya_id', 'notes']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function vehicleData(): ?array
    {
        return $this->boolean('with_vehicle')
            ? $this->safe()->only(['plate', 'vin', 'brand_id', 'vehicle_model_id', 'year', 'energy_id', 'last_mileage'])
            : null;
    }
}
