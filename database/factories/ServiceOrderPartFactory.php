<?php

namespace Database\Factories;

use App\Enums\PartOrigin;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrderPart>
 */
class ServiceOrderPartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'origin' => PartOrigin::Client,
            'designation' => 'Filtre apporté',
            'quantity' => '1.00',
            'unit_price' => '0.00',
            'line_total' => '0.00',
        ];
    }
}
