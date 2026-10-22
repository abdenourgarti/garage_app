<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pour l'affichage uniquement : en vrai, un paiement passe par PaySupplier (avec sa dépense).
 *
 * @extends Factory<PurchasePayment>
 */
class PurchasePaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'amount' => '1000.00',
            'paid_at' => today(),
            'payment_method_id' => PaymentMethod::factory(),
            'proof_path' => null,
            'user_id' => User::factory(),
        ];
    }
}
