<?php

namespace App\Http\Requests\Clients;

use App\Models\Vehicle;
use App\Support\Normalize;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Vehicle|null $vehicle */
        $vehicle = $this->route('vehicle');

        return self::vehicleRules($vehicle?->id, $this->integer('brand_id') ?: null);
    }

    /**
     * Shared with the quick creation form.
     *
     * @return array<string, array<mixed>>
     */
    public static function vehicleRules(?int $ignoreId, ?int $brandId): array
    {
        $unique = fn (string $column, Closure $normalize, string $message) => function (string $attribute, mixed $value, Closure $fail) use ($column, $normalize, $ignoreId, $message) {
            $normalized = $normalize((string) $value);

            if ($normalized === null || $normalized === '') {
                return;
            }

            $other = Vehicle::with(['client', 'brand'])
                ->where($column, $normalized)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->first();

            if ($other) {
                $fail(sprintf($message, $other->label(), $other->client->full_name));
            }
        };

        return [
            'plate' => ['required', 'string', 'max:30', 'regex:/[\p{L}\p{N}]/u',
                $unique('plate_normalized', Normalize::plate(...), 'Ce matricule est déjà enregistré (%s de %s).')],
            'vin' => ['nullable', 'string', 'max:30',
                $unique('vin', Normalize::vin(...), 'Ce numéro de châssis est déjà enregistré (%s de %s).')],
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')],
            'vehicle_model_id' => ['nullable', 'integer', Rule::exists('vehicle_models', 'id')->where('brand_id', $brandId)],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'energy_id' => ['nullable', 'integer', Rule::exists('energies', 'id')],
            'last_mileage' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::vehicleAttributes();
    }

    /**
     * @return array<string, string>
     */
    public static function vehicleAttributes(): array
    {
        return [
            'plate' => 'matricule',
            'vin' => 'numéro de châssis',
            'brand_id' => 'marque',
            'vehicle_model_id' => 'modèle',
            'year' => 'année',
            'energy_id' => 'énergie',
            'last_mileage' => 'kilométrage',
            'notes' => 'remarque',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['plate.regex' => 'Le matricule doit contenir des chiffres ou des lettres.'];
    }
}
