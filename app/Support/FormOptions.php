<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Energy;
use App\Models\PaymentMethod;
use App\Models\VehicleModel;
use App\Models\Wilaya;

/**
 * Listes proposées dans les formulaires : éléments actifs seulement (RG-01).
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

    /**
     * Modes de paiement actifs, espèces en premier et proposées par défaut.
     *
     * @return array{paymentMethods: mixed, defaultPaymentMethodId: int|null}
     */
    public static function payment(): array
    {
        $methods = PaymentMethod::active()->orderByDesc('is_cash')->orderBy('name')->get(['id', 'name', 'is_cash']);

        return [
            'paymentMethods' => $methods,
            'defaultPaymentMethodId' => $methods->firstWhere('is_cash', true)?->id ?? $methods->first()?->id,
        ];
    }
}
