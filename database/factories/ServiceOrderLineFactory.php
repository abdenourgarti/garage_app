<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrderLine>
 */
class ServiceOrderLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'service_type_id' => ServiceType::factory(),
            'employee_id' => Employee::factory(),
            'labor_price' => '1500.00',
            'position' => 0,
        ];
    }
}
