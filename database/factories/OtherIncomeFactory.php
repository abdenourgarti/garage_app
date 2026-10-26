<?php

namespace Database\Factories;

use App\Models\OtherIncome;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OtherIncome>
 */
class OtherIncomeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => 'Vente batterie d’occasion',
            'amount' => '3000.00',
            'received_at' => today(),
            'payment_method_id' => null,
            'notes' => null,
            'user_id' => User::factory(),
        ];
    }
}
