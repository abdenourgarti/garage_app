<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Energy;
use App\Models\VehicleModel;
use App\Models\Wilaya;

/**
 * Listes proposées dans les formulaires client / véhicule : éléments actifs seulement (RG-01).
 */
class FormOptions
{
    /**
     * @return array{wilayas: mixed, brands: mixed, models: mixed, energies: mixed}
     */
    public static function clientVehicle(): array
    {
        return [
            'wilayas' => Wilaya::active()->orderBy('code')->get(['id', 'code', 'name']),
            'brands' => Brand::active()->orderBy('name')->get(['id', 'name']),
            'models' => VehicleModel::active()->orderBy('name')->get(['id', 'brand_id', 'name']),
            'energies' => Energy::active()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
